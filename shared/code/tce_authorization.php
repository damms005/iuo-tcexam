<?php
//============================================================+
// File name   : tce_authorization.php
// Begin       : 2001-09-26
// Last Update : 2017-04-22
//
// Description : Check user authorization level.
//               Grants / deny access to pages.
//
// Author: Nicola Asuni
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2018 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * This script handles user's sessions.
 * Just the registered users granted with a username and a password are entitled to access the restricted areas (level > 0) of TCExam and the public area to perform the tests.
 * The user's level is a numeric value that indicates which resources (pages, modules, services) are accessible by the user.
 * To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
 * TCExam has 10 predefined user's levels:<ul>
 * <li>0 = anonymous user (unregistered).</li>
 * <li>1 = basic user (registered);</li>
 * <li>2-9 = configurable/custom levels;</li>
 * <li>10 = administrator with full access rights</li>
 * </ul>
 * @package com.tecnick.tcexam.shared
 * @brief TCExam Shared Area
 * @author Nicola Asuni
 * @since 2001-09-26
 */

/**
 */

register_shutdown_function('load_custom_ui');

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_functions_authorization.php';
require_once '../../shared/code/tce_functions_session.php';
require_once '../../shared/code/tce_functions_otp.php';

$logged = false; // the user is not yet logged in
// --- read existing user's session data from database
$PHPSESSIDSQL   = F_escape_sql($db, $PHPSESSID);
$fingerprintkey = getClientFingerprint();
$sqls           = 'SELECT * FROM ' . K_TABLE_SESSIONS . ' WHERE cpsession_id=\'' . $PHPSESSIDSQL . '\'';
if ($rs = F_db_query($sqls, $db)) {
    if ($ms = F_db_fetch_array($rs)) { // the user's session already exist
        // decode session data
        session_decode($ms['cpsession_data']);
        // check for possible session hijacking
        if (K_CHECK_SESSION_FINGERPRINT and ((!isset($_SESSION['session_hash'])) or ($fingerprintkey != $_SESSION['session_hash']))) {
            // display login form
            session_regenerate_id(true);
            F_login_form();
            exit();
        }
        // update session expiration time
        $expiry = date(K_TIMESTAMP_FORMAT, (time() + K_SESSION_LIFE));
        $sqlx   = 'UPDATE ' . K_TABLE_SESSIONS . ' SET cpsession_expiry=\'' . $expiry . '\' WHERE cpsession_id=\'' . $PHPSESSIDSQL . '\'';
        if (!$rx = F_db_query($sqlx, $db)) {
            F_display_db_error();
        }
    } else { // session do not exist so, create new anonymous session
        $_SESSION['session_hash']           = $fingerprintkey;
        $_SESSION['session_user_id']        = 1;
        $_SESSION['session_user_name']      = '- [' . substr($PHPSESSID, 12, 8) . ']';
        $_SESSION['session_user_ip']        = getNormalizedIP($_SERVER['REMOTE_ADDR']);
        $_SESSION['session_user_level']     = 0;
        $_SESSION['session_user_firstname'] = '';
        $_SESSION['session_user_lastname'] = '';
        $_SESSION['session_user_passport'] = '';
        $_SESSION['session_user_department'] = '';
        $_SESSION['session_user_year_level'] = '';
        $_SESSION['session_test_login'] = '';
        // read client cookie
        if (isset($_COOKIE['LastVisit'])) {
            $_SESSION['session_last_visit'] = intval($_COOKIE['LastVisit']);
        } else {
            $_SESSION['session_last_visit'] = 0;
        }
        // set client cookie
        $cookie_now_time    = time(); // note: while time() function returns a 32 bit integer, it works fine until year 2038.
        $cookie_expire_time = $cookie_now_time + K_COOKIE_EXPIRE; // set cookie expiration time
        setcookie('LastVisit', $cookie_now_time, $cookie_expire_time, K_COOKIE_PATH, K_COOKIE_DOMAIN, K_COOKIE_SECURE);
        setcookie('PHPSESSID', $PHPSESSID, $cookie_expire_time, K_COOKIE_PATH, K_COOKIE_DOMAIN, K_COOKIE_SECURE);
        // track when user request logout
        if (isset($_REQUEST['logout'])) {
            $_SESSION['logout'] = true;
            if (strlen(K_LOGOUT_URL) > 0) {
                $htmlredir = '<' . '?xml version="1.0" encoding="' . $l['a_meta_charset'] . '"?' . '>' . K_NEWLINE;
                $htmlredir .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">' . K_NEWLINE;
                $htmlredir .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $l['a_meta_language'] . '" lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
                $htmlredir .= '<head>' . K_NEWLINE;
                $htmlredir .= '<title>LOGOUT</title>' . K_NEWLINE;
                $htmlredir .= '<meta http-equiv="refresh" content="0;url=' . K_LOGOUT_URL . '" />' . K_NEWLINE;
                $htmlredir .= '</head>' . K_NEWLINE;
                $htmlredir .= '<body>' . K_NEWLINE;
                $htmlredir .= '<a href="' . K_LOGOUT_URL . '">LOGOUT...</a>' . K_NEWLINE;
                $htmlredir .= '</body>' . K_NEWLINE;
                $htmlredir .= '</html>' . K_NEWLINE;
                header('Location: ' . K_LOGOUT_URL);
                echo $htmlredir;
                exit;
            }
        }
    }
} else {
    F_display_db_error();
}

// try other login systems
// (HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP)
require_once '../../shared/code/tce_altauth.php';
$altusr = F_altLogin();

// --- check if login information has been submitted
if (isset($_POST['logaction']) and ($_POST['logaction'] == 'login') and isset($_POST['xuser_name']) and isset($_POST['xuser_password'])) {
    $bruteforce = false;
    if (K_BRUTE_FORCE_DELAY_RATIO > 0) {
        // check login attempt from the current client device to avoid brute force attack
        $bruteforce = true;
        // we are using another entry in the session table to keep track of the login attempts
        $sqlt = 'SELECT * FROM ' . K_TABLE_SESSIONS . ' WHERE cpsession_id=\'' . $fingerprintkey . '\' LIMIT 1';
        if ($rt = F_db_query($sqlt, $db)) {
            if ($mt = F_db_fetch_array($rt)) {
                // check the expiration time
                if (strtotime($mt['cpsession_expiry']) < time()) {
                    $bruteforce = false;
                }

                // update wait time
                $wait = intval($mt['cpsession_data']);
                if ($wait < K_SECONDS_IN_HOUR) {
                    $wait *= K_BRUTE_FORCE_DELAY_RATIO;
                }
                $sqlup = 'UPDATE ' . K_TABLE_SESSIONS . ' SET
					cpsession_expiry=\'' . date(K_TIMESTAMP_FORMAT, (time() + $wait)) . '\',
					cpsession_data=\'' . $wait . '\'
					WHERE cpsession_id=\'' . $fingerprintkey . '\'';
                if (!F_db_query($sqlup, $db)) {
                    F_display_db_error();
                }
            } else {
                // add new record
                $wait = 1; // number of seconds to wait for the second attempt
                $sqls = 'INSERT INTO ' . K_TABLE_SESSIONS . ' (
					cpsession_id,
					cpsession_expiry,
					cpsession_data
					) VALUES (
					\'' . $fingerprintkey . '\',
					\'' . date(K_TIMESTAMP_FORMAT, (time() + $wait)) . '\',
					\'' . $wait . '\'
					)';
                if (!F_db_query($sqls, $db)) {
                    F_display_db_error();
                }
                $bruteforce = false;
            }
        }
    }
    if ($bruteforce) {
        F_print_error('WARNING', $l['m_login_brute_force'] . ' ' . $wait);
    } else {
        // encode password
        $xuser_password = getPasswordHash($_POST['xuser_password']);
        // check One-Time-Password if enabled
        $otp = false;
        if (K_OTP_LOGIN) {
            $mtime = microtime(true);
            if ((isset($_POST['xuser_otpcode'])) and !empty($_POST['xuser_otpcode'])
                and (($_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], $mtime))
                    or ($_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], ($mtime - 30)))
                    or ($_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], ($mtime + 30))))) {
                // check if this OTP token has been alredy used
                $sqlt = 'SELECT cpsession_id FROM ' . K_TABLE_SESSIONS . ' WHERE cpsession_id=\'' . $_POST['xuser_otpcode'] . '\' LIMIT 1';
                if ($rt = F_db_query($sqlt, $db)) {
                    if (!F_db_fetch_array($rt)) {
                        // Store this token on the session table to mark it as invalid for 5 minute (300 seconds)
                        $sqltu = 'INSERT INTO ' . K_TABLE_SESSIONS . ' (
							cpsession_id,
							cpsession_expiry,
							cpsession_data
							) VALUES (
							\'' . $_POST['xuser_otpcode'] . '\',
							\'' . date(K_TIMESTAMP_FORMAT, (time() + 300)) . '\',
							\'300\'
							)';
                        if (!F_db_query($sqltu, $db)) {
                            F_display_db_error();
                        }
                        $otp = true;
                    }
                }
            }
        }
        if (!K_OTP_LOGIN or $otp) {
            // check if submitted login information are correct
            $sql = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE user_name=\'' . F_escape_sql($db, $_POST['xuser_name']) . '\'';
            if ($r = F_db_query($sql, $db)) {
                if (($m = F_db_fetch_array($r)) and checkPassword($_POST['xuser_password'], $m['user_password'])) {
                    // sets some user's session data
                    $_SESSION['session_user_id']        = $m['user_id'];
                    $_SESSION['session_user_name']      = $m['user_name'];
                    $_SESSION['session_user_ip']        = getNormalizedIP($_SERVER['REMOTE_ADDR']);
                    $_SESSION['session_user_level']     = $m['user_level'];
                    $_SESSION['session_user_firstname'] = urlencode($m['user_firstname']);
                    $_SESSION['session_user_lastname'] = urlencode($m['user_lastname']);
                    $_SESSION['session_user_passport'] = $m['user_passport'];
                    $_SESSION['session_user_department'] = $departments[ $m['user_department']];
                    $_SESSION['session_user_year_level'] = $year_level[$m['user_year_level']];
                    $_SESSION['session_test_login'] = '';
                    // read client cookie
                    if (isset($_COOKIE['LastVisit'])) {
                        $_SESSION['session_last_visit'] = intval($_COOKIE['LastVisit']);
                    } else {
                        $_SESSION['session_last_visit'] = 0;
                    }
                    $logged = true;
                    if (K_USER_GROUP_RSYNC and ($altusr !== false)) {
                        // sync user groups
                        F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
                    }
                } elseif (!F_check_unique(K_TABLE_USERS, 'user_name=\'' . F_escape_sql($db, $_POST['xuser_name']) . '\'')) {
                    // the user name exist but the password is wrong
                    if ($altusr !== false) {
                        // resync the password
                        $sqlu = 'UPDATE ' . K_TABLE_USERS . ' SET
								user_password=\'' . F_escape_sql($db, $xuser_password) . '\'
								WHERE user_name=\'' . F_escape_sql($db, $_POST['xuser_name']) . '\'';
                        if (!$ru = F_db_query($sqlu, $db)) {
                            F_display_db_error();
                        }
                        // get user data
                        $sqld = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE user_name=\'' . F_escape_sql($db, $_POST['xuser_name']) . '\' AND user_password=\'' . F_escape_sql($db, $xuser_password) . '\'';
                        if ($rd = F_db_query($sqld, $db)) {
                            if ($md = F_db_fetch_array($rd)) {
                                // sets some user's session data
                                $_SESSION['session_user_id']        = $md['user_id'];
                                $_SESSION['session_user_name']      = $md['user_name'];
                                $_SESSION['session_user_ip']        = getNormalizedIP($_SERVER['REMOTE_ADDR']);
                                $_SESSION['session_user_level']     = $md['user_level'];
                                $_SESSION['session_user_firstname'] = urlencode($md['user_firstname']);
                                $_SESSION['session_user_lastname'] = urlencode($md['user_lastname']);
                                $_SESSION['session_user_passport'] = $md['user_passport'];
                                $_SESSION['session_user_department'] =$departments[ $md['user_department']];
                                $_SESSION['session_user_year_level'] =$year_level[ $md['user_year_level']];
                                $_SESSION['session_last_visit'] = 0;
                                $_SESSION['session_test_login'] = '';
                                $logged = true;
                                if (K_USER_GROUP_RSYNC) {
                                    // sync user groups
                                    F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
                                }
                            }
                        } else {
                            F_display_db_error();
                        }
                    } else {
                        // the password is wrong
                        F_print_error('WARNING', $l['m_login_wrong']);
                    }
                } else {
                    // this user do not exist on TCExam database
                    if ($altusr !== false) {
                        // replicate external user account on TCExam local database
                        $sql = 'INSERT INTO ' . K_TABLE_USERS . ' (
							user_regdate,
							user_ip,
							user_name,
							user_email,
							user_password,
							user_regnumber,
							user_firstname,
							user_lastname,
							user_birthdate,
							user_birthplace,
							user_ssn,
							user_level
							) VALUES (
							\'' . F_escape_sql($db, date(K_TIMESTAMP_FORMAT)) . '\',
							\'' . F_escape_sql($db, getNormalizedIP($_SERVER['REMOTE_ADDR'])) . '\',
							\'' . F_escape_sql($db, $_POST['xuser_name']) . '\',
							' . F_empty_to_null($altusr['user_email']) . ',
							\'' . F_escape_sql($db, $xuser_password) . '\',
							' . F_empty_to_null($altusr['user_regnumber']) . ',
							' . F_empty_to_null($altusr['user_firstname']) . ',
							' . F_empty_to_null($altusr['user_lastname']) . ',
							' . F_empty_to_null($altusr['user_birthdate']) . ',
							' . F_empty_to_null($altusr['user_birthplace']) . ',
							' . F_empty_to_null($altusr['user_ssn']) . ',
							\'' . intval($altusr['user_level']) . '\'
							)';
                        if (!$r = F_db_query($sql, $db)) {
                            F_display_db_error();
                        } else {
                            $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                            // sets some user's session data
                            $_SESSION['session_user_id']        = $user_id;
                            $_SESSION['session_user_name']      = F_escape_sql($db, $_POST['xuser_name']);
                            $_SESSION['session_user_ip']        = getNormalizedIP($_SERVER['REMOTE_ADDR']);
                            $_SESSION['session_user_level']     = intval($altusr['user_level']);
                            $_SESSION['session_user_firstname'] = urlencode($altusr['user_firstname']);
                            $_SESSION['session_user_lastname'] = urlencode($altusr['user_lastname']);
                            $_SESSION['session_user_passport'] = $altusr['user_passport'];
                            $_SESSION['session_user_department'] =$departments[ $altusr['user_department']];
                            $_SESSION['session_user_year_level'] =$year_level[ $altusr['user_year_level']];
                            $_SESSION['session_last_visit'] = 0;
                            $_SESSION['session_test_login'] = '';
                            $logged = true;
                            // sync user groups
                            F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
                        }
                    } else {
                        $login_error = true;
                    }
                }
            } else {
                F_display_db_error();
            }
        } else {
            $login_error = true;
        }
    } // end of brute-force check
}

if (!isset($pagelevel)) {
    // set default page level
    $pagelevel = 0;
}

// check client SSL certificate if required
if ((K_AUTH_SSL_LEVEL !== false) and (K_AUTH_SSL_LEVEL <= $pagelevel)) {
    $sslids = preg_replace('/[^0-9,]*/', '', K_AUTH_SSLIDS);
    if (!empty($sslids)) {
        $client_hash = F_getSSLClientHash();
        $valid_ssl   = F_count_rows(K_TABLE_SSLCERTS, 'WHERE ssl_hash=\'' . $client_hash . '\' AND ssl_id IN (' . $sslids . ')');
        if ($valid_ssl == 0) {
            $thispage_title = $l['t_login_form']; //set page title
            require_once '../code/tce_page_header.php';
            F_print_error('ERROR', $l['m_ssl_certificate_required']);
            require_once '../code/tce_page_footer.php';
            exit(); //break page here
        }
    }
}

// check user's level
if ($pagelevel) { // pagelevel=0 means access to anonymous user
    // pagelevel >= 1
    if ($_SESSION['session_user_level'] < $pagelevel) { //check user level
        // To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
        F_login_form(); //display login form
    }
}

if ($logged) { //if user is just logged in: reloads page
    // html redirect
    $htmlredir = '<' . '?xml version="1.0" encoding="' . $l['a_meta_charset'] . '"?' . '>' . K_NEWLINE;
    $htmlredir .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . K_NEWLINE;
    $htmlredir .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $l['a_meta_language'] . '" lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
    $htmlredir .= '<head>' . K_NEWLINE;
    $htmlredir .= '<title>ENTER</title>' . K_NEWLINE;
    $htmlredir .= '<meta http-equiv="refresh" content="0" />' . K_NEWLINE; //reload page
    $htmlredir .= '</head>' . K_NEWLINE;
    $htmlredir .= '<body>' . K_NEWLINE;
    $htmlredir .= '<a href="' . $_SERVER['SCRIPT_NAME'] . '">ENTER</a>' . K_NEWLINE;
    $htmlredir .= '</body>' . K_NEWLINE;
    $htmlredir .= '</html>' . K_NEWLINE;
    switch (K_REDIRECT_LOGIN_MODE) {
        case 1:{
                // relative redirect
                header('Location: ' . $_SERVER['SCRIPT_NAME']);
                break;
            }
        case 2:{
                // absolute redirect
                header('Location: ' . K_PATH_HOST . $_SERVER['SCRIPT_NAME']);
                break;
            }
        case 3:{
                // html redirect
                echo $htmlredir;
                break;
            }
        case 4:
        default:{
                // full redirect
                header('Location: ' . K_PATH_HOST . $_SERVER['SCRIPT_NAME']);
                echo $htmlredir;
                break;
            }
    }
    exit;
}

// check for test password
if (isset($_POST['testpswaction']) and ($_POST['testpswaction'] == 'login') and isset($_POST['xtest_password']) and isset($_POST['testid'])) {
    require_once '../../shared/code/tce_functions_test.php';
    $tph = F_getTestPassword($_POST['testid']);
    if (checkPassword($_POST['xtest_password'], $tph)) {
        // test password is correct, save status on a session variable
        $_SESSION['session_test_login'] = getPasswordHash($tph . $_POST['testid'] . $_SESSION['session_user_id'] . $_SESSION['session_user_ip']);
    } else {
        F_print_error('WARNING', $l['m_wrong_test_password']);
    }
}

function load_custom_ui()
{

    if (!isset($custom_style) or empty($custom_style)) {
        $custom_style = K_TCEXAM_STYLE_CUSTOM;
    }

    ?>

    <link rel="stylesheet" href="<?php echo K_PATH_URL; ?>/shared/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo K_PATH_URL; ?>/shared/css/pretty-checkbox.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo K_PATH_URL; ?>shared/css/materialdesignicons.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo K_PATH_URL; ?>shared/css/animate.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo $custom_style; ?>" type="text/css" />
    <script src="<?php echo K_PATH_URL; ?>shared/jscripts/jquery.js"></script>
    <script src="<?php echo K_PATH_URL; ?>shared/jscripts/popper.js"></script>
    <script src="<?php echo K_PATH_URL; ?>shared/jscripts/bootstrap.min.js"></script>
    <script src="<?php echo K_PATH_URL; ?>shared/jscripts/tinymce/js/tinymce/tinymce.min.js"></script>

    <script>

        //we are doing this in this auth file because this
        //one file that is sure to be added to all pages we desire to restyle

        let default_boorder = 'border-2';

        $(function(){

            //space content
            $(' .content h1 , div.body h1 ').addClass('mt-5 mb-8')

            //change all radioboxes to pretty
            $("input[type='radio']").each(function( index , elmnt ){
                let closest_label = $(elmnt).siblings(`label[for='${$(elmnt).attr('id')}']`);
                if( closest_label ) {
                    $(elmnt).wrap('<div class="mb-5 pretty p-default p-round p-pulse" />');
                    $( elmnt ).closest('div').append( ` <div class="state p-primary-o"> <label> ${$( closest_label ).text()} </label> </div> `);
                    $( closest_label ).remove();
                }else{
                    console.log(` ${$(closest_label).attr('for')} != ${$(elmnt).attr('id')} `);
                }
            })

            //change all cheackboxes
            $("span.formw input[type='checkbox']").each(function( index , elmnt ){
                let closest_label = $(elmnt).closest('div').children(`label[for='${$(elmnt).attr('id')}']`);
                if( closest_label ) {
                    $(elmnt).wrap('<div class="pretty p-icon p-smooth" />');
                    $( elmnt ).closest('div').append( ` <div class="state p-danger-o"> <i class="icon mdi mdi-check"></i> <label> ${$( closest_label ).text()} </label> </div> `);
                    $( closest_label ).remove();
                }else{
                    console.log(` ${$(closest_label).attr('for')} != ${$(elmnt).attr('id')} `);
                }
            })
            $("input[type=checkbox]").each(function(ind , elmnt){
                let filterOut = $(elmnt).parents("span.formw").length;
                if( filterOut == 0 && ((!!$(elmnt).attr('class')) == false  || $(elmnt).attr('class') == '') ){
                    $(elmnt).wrap('<div class="pretty p-icon p-smooth" />');
                    $( elmnt ).closest('div').append( ` <div class="state p-danger-o"> <i class="icon mdi mdi-check"></i> <label>&nbsp;</label> </div> `);
                    let sp = $(elmnt).closest('span');
                    sp.css({backgroundColor: ''});
                    sp.addClass('rounded border border-green-darkest p-1');
                }
            })

            //unstyled buttons, and button-like elements
            let stylable = [ 'buttongreen' , 'xmlbutton' , 'styledbutton' ];
            $( "input[type='submit'] , .xmlbutton , .testlist a.buttongreen , .styledbutton" ).each(function(index , el){
                if( (!!$(el).attr('class')) == false  || $(el).attr('class') == '' || stylable.indexOf( ($(el).attr('class') ) >= 0) ){
                    $(el).removeClass('xmlbutton').addClass('border-blue ' + default_boorder + ' bg-white hover:bg-blue text-blue-dark hover:text-white hover:no-underline hover:rounded rounded no-underline p-2 mt-1 mb-1 mr-1 cursor-pointer')
                }
            })

            //input boxes unstyled
            $(" span.formw select , span.formw textarea , span.formw input[type='text'] , span.formw input[type='password'] , span.formw input[type='number']")
            .each(function( index , elmnt ){
                $(elmnt).addClass('form-control rounded')
            })

            //restylings for the questions page
            $('label[for="answertext"] , textarea#answertext') .each(function( index , elmnt ){
                $(elmnt).addClass('m-4')
            });
            $('textarea#answertext').addClass('form-control rounded p-2').attr('placeholder','type your answer here');
            $('form#testform div.rowl') .each(function( index , elmnt ){
                $(elmnt).addClass('p-4')
            });

            //style information boxes
            $("div.warning , div.error").removeClass('warning').removeClass('error').addClass("mt-5 mb-5 p-4 bg-red text-white rounded animated shake")
            $("div.message").removeClass('message').addClass("mt-5 mb-5 p-4 bg-pink-darkest text-white rounded animated shake")
            $("div.preview").removeClass('preview').addClass('rounded border bg-pink-lightest p-3')
            $("div.tceformbox").removeClass('tceformbox').addClass('rounded border bg-transparent p-3')
            //this is also the class for test form. So style it differntly
            $("div.tcecontentbox").each(function(ind,el){
                let examTakingForm = $(el).closest('form#testform').length;
                if(examTakingForm > 0){
                    $("div.tcecontentbox").removeClass('tcecontentbox').addClass('rounded border bg-white p-4 animated fadeIn')
                }else{
                    $("div.tcecontentbox").removeClass('tcecontentbox').addClass('rounded border bg-blue-lighter p-4 text-leading')
                }
            })


            //div.tceformbox
            $("div.tceformbox").addClass("m-5 p-5 rounded bg-blue animated fadeIn")

            //div.pagehelp
            $("div.pagehelp").removeClass('pagehelp').addClass("mt-4 p-3 rounded bg-blue-lighter")

            //hide unecesary elements
            $(".langselector").hide();
            $(".minibutton").hide();

            //do fine tooltips
            $("a, input, select, acronym, span.formw textarea").each(function(ind,el){
                let title = $(el).attr('title');
                if(!!title){
                    $(el).attr("data-title" , title);
                    $(el).attr("data-toggle" , "tooltip");
                    $(el).attr("title" , '');
                }
            })
            // $('[data-toggle="tooltip"]').tooltip({
            //     delay: { "show": 850, "hide": 50 },
            //     placement: 'auto'
            // });
            var HasTooltip = $('[data-toggle="tooltip"]');
            HasTooltip.on('enter focus mouseover', function(e) {
                e.preventDefault();
                var isShowing = $(this).data('isShowing');
                HasTooltip.removeData('isShowing');
                if (isShowing !== 'true')
                {
                    HasTooltip.not(this).tooltip('hide');
                    $(this).data('isShowing', "true");
                    $(this).tooltip('show');
                }
                else
                {
                    $(this).tooltip('hide');
                }
            })
            .on('blur mouseout leave mouseleave', function(){
                $(this).tooltip('hide');
            })
            .tooltip({
                animation: true,
                trigger: 'manual',
                delay: {
                    "show": 850, "hide": 50
                }
            });

            //make tables more presentable
            $(".userselect").addClass('table table-bordered table-hover table-striped');//results table
            $(".testlist").addClass('table table-bordered table-hover table-striped');
            $(".testlist").css({ width: '70%' });//because when list of test too long, we need to make the 'execute' button clickable: not covered by the user details card
            $(".testlist").closest('div.bg-blue-lighter').removeClass('bg-blue-lighter');//we have already added this bg to all 'tcecontentbox', which .testlist falls into the category. However, it feels awful on this design, so we are exempting it

            //give user clue that there is tooltip on acronymns
            $('acronym').addClass('cursor-pointer');

            //fix layout login button
            $("#login").css({marginLeft: '-500px'})

            //testform
            $("#testform").addClass('border rounded bg-red-lightest pb-4')
            $(".navlink").addClass('pt-4 pl-4')
            $("#terminatetest").addClass('mb-4 ml-4')

            //all bootstrap table that they put td text as centered is bad layout
            $(".table td").css({textAlign: 'left'});

            //effizy
            $("#userLcd").addClass("animate shake");

            //make test description editable
            $("textarea.test_description").addClass('keyboardInput')
            //question list
            $(" ul.question > li").addClass("m-4 p-10 border rounded bg-grey-lightest mb-5")

            //our styling gave "life" to disbaled elements. Fix that:
            //1. remove hover classes
            //2. make bg-grey
            //3. make border darker grey
            $("input[disabled='disabled']").each(function( index,element ){
                let classes = $(element).attr("class");
                classes = classes.replace("'","");
                classes = classes.replace('"',"");
                classes = classes.split(" ");
                $.each( classes , function( index , value ){
                    //remove hovers
                    if( value.indexOf('hover') >= 0 ){
                        $(element).removeClass( value);
                    }
                    //remove bg's
                    if( value.indexOf('bg-') >= 0 ){
                        $(element).removeClass( value);
                    }
                    //remove borders
                    if( value.indexOf('border-') >= 0 ){
                        $(element).removeClass( value);
                    }
                    //text color
                    if( value.indexOf('text-') >= 0 ){
                        $(element).removeClass( value);
                    }
                });
                $(element).addClass( 'bg-grey-lightest border-grey-light text-grey ' + default_boorder );
            });

        })
    </script>

    <?php
}

; //============================================================+; // END OF FILE; //============================================================+
