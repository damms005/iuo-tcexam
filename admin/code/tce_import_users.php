<?php
//============================================================+
// File name   : tce_import_users.php
// Begin       : 2006-03-17
// Last Update : 2012-12-31
//
// Description : Import users from an XML file or tab-delimited
//               TSV file.

//import std records
//convert existing question format to tcexam's
//
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
//    Copyright (C) 2004-2012  Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Import users from an XML file or TSV (Tab delimited text file).
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-17
 */

/**
 */

require_once('../config/tce_config.php');
ini_set('max_execution_time', 1800);

$pagelevel = K_AUTH_IMPORT_USERS;
require_once('../../shared/code/tce_authorization.php');

$thispage_title = $l['t_user_importer'];
require_once('../code/tce_page_header.php');
require_once('../../shared/code/tce_functions_form.php');

switch ($menu_mode) {
    case 'upload': {
        if ($_FILES['userfile']['name']) {
            require_once('../code/tce_functions_upload.php');
            // upload file
            $uploadedfile = F_upload_file('userfile', K_PATH_CACHE);
            if ($uploadedfile !== false) {
                switch ($file_type) {
                    case 1: {
                        $xmlimporter = new XMLUserImporter(K_PATH_CACHE.$uploadedfile);
                        F_print_error('MESSAGE', $l['m_importing_complete']);
                        break;
                    }
                    case 2: {
                        if (F_import_tsv_users(K_PATH_CACHE.$uploadedfile)) {
                            F_print_error('MESSAGE', $l['m_importing_complete']);
                        }
                        break;
                    }
                }
            }
        }
        break;
    }

    default: {
        break;
    }
} //end of switch
?>

<div class="container">

<div class="tceformbox">
	<table>
		<tr>
			<td>
				<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="post" enctype="multipart/form-data" id="form_importusers">

				<div class="row">
				<span class="label">
				<label for="userfile"><?php echo $l['w_upload_file']; ?></label>
				</span>
				<span class="formw">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo K_MAX_UPLOAD_SIZE ?>" />
				<input type="file" name="userfile" id="userfile" size="20" title="<?php echo $l['h_upload_file']; ?>" />
				</span>
				&nbsp;
				</div>

				<div class="row">
				<div class="formw">
				<fieldset class="noborder">
				<legend title="<?php echo $l['h_file_type']; ?>"><?php echo $l['w_type']; ?></legend>

				<input type="radio" name="file_type" id="file_type_tsv" value="2" checked="checked" title="<?php echo $l['h_file_type_tsv']; ?>" />
				<label for="file_type_tsv">TSV</label>

				<br />

				<!-- <input type="radio" name="file_type" id="file_type_xml" value="1" title="<?php echo $l['h_file_type_xml']; ?>" />
				<label for="file_type_xml">XML</label> -->
				</fieldset>
				</div>
				</div>

				<div class="row">
				<?php
                // show buttons by case
                F_submit_button("upload", $l['w_upload'], $l['h_submit_file']);
                ?>
				</div>

				</form>
			</td>
		</tr>
		<tr>
			<td>
				<code>
					Note: lean, plain TSV only
				</code>
				<br>
				<pre style="text-align:left">
					<?php echo $import_instructions ?>
				</pre>
			</td>
			<td>
				<pre style="text-align:left">Recognized year_levels ('as is')
				<?php print_r($year_level) ?>
			</pre></td>
		</tr>
		<tr>
			<td>
				<pre style="text-align:left">Recognized colleges ('as is')
					<?php print_r($colleges) ?>
				</pre>
			</td>
			<td>
				<pre style="text-align:left">Recognized departments ('as is')
				<?php print_r($departments) ?>
			</pre></td>
		</tr>
	</table>
</div>
<?php

echo '<div class="pagehelp">'.$l['hp_import_xml_users'].'</div>'.K_NEWLINE;
echo '</div>'.K_NEWLINE;

require_once('../code/tce_page_footer.php');

// ------------------------------------------------------------

require('tce_import_users_xmlclass.php');
/**
 * Import users from TSV file (tab delimited text).
 * The format of TSV is the same obtained by exporting data from Users Selection Form.
 * @param $tsvfile (string) TSV (tab delimited text) file name
 * @return boolean TRUE in case of success, FALSE otherwise
 */
function F_import_tsv_users($tsvfile)
{
    global $l, $db, $colleges, $departments, $year_level;
    require_once('../config/tce_config.php');

    // get file content as array
    $tsvrows = file($tsvfile); // array of TSV lines
    if ($tsvrows === false) {
        return false;
    }

    // move pointer to second line (discard headers)
    next($tsvrows);

    // for each row
	$new_addition = 0;
	$updates = 0;
    while (list($item, $rowdata) = each($tsvrows)) {
        // get user data into array
        $userdata = explode("\t", $rowdata);

        // set some default values
        if (empty($userdata[4])) {
            $userdata[4] = date(K_TIMESTAMP_FORMAT);
        }

        if (empty($userdata[5])) {
            $userdata[5] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
        }

        //group
        $userdata[19] = 'students';

        //transformations
        $userdata[8] = get_item_index($userdata[8], $colleges);
        $userdata[9] = get_item_index($userdata[9], $departments);
        $userdata[10] = get_item_index($userdata[10], $year_level);

        // user level
        if (!isset($userdata[16]) or (strlen($userdata[16]) == 0)) {
            $userdata[16] = 1;
        }

        if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
            // you cannot edit a user with a level equal or higher than yours
            $userdata[12] = min(max(0, ($_SESSION['session_user_level'] - 1)), $userdata[12]);
            // non-administrator can access only to his/her groups
            if (empty($userdata[19])) {
                break;
            }
            $usrgroups = explode(',', addslashes($userdata[19]));
            $common_groups = array_intersect(F_get_user_groups($_SESSION['session_user_id']), $usrgroups);
            if (empty($common_groups)) {
                break;
            }
        }
        // check if user already exist
        $sql = 'SELECT user_id,user_level
			FROM '.K_TABLE_USERS.'
			WHERE user_name=\''.F_escape_sql($db, $userdata[1]).'\'';
        $sql .= (empty($userdata[14])) ? "" : ' OR user_regnumber='.F_empty_to_null($userdata[14]) ;
        $sql .= (empty($userdata[15])) ? "" : ' OR user_ssn='.F_empty_to_null($userdata[15]);
        $sql .= ' LIMIT 1';

        //var_dump($sql);

        if ($r = F_db_query($sql, $db)) {
            if ($m = F_db_fetch_array($r)) {
                // the user has been added already
			   $updates++;
                $user_id = $m['user_id'];
                if (($_SESSION['session_user_level'] >= K_AUTH_ADMINISTRATOR) or ($_SESSION['session_user_level'] > $m['user_level'])) {
                    //update user data
                    $sqlu = 'UPDATE '.K_TABLE_USERS.' SET
						user_name=\''.F_escape_sql($db, $userdata[1]).'\',';
                    // update password only if it is specified
                    if (!empty($userdata[2])) {
                        $sqlu .= ' user_password=\''.F_escape_sql($db, getPasswordHash($userdata[2])).'\',';
                    }
                    $sqlu .= '
						user_email='.F_empty_to_null($userdata[3]).',
						user_regdate=\''.F_escape_sql($db, $userdata[4]).'\',
						user_ip=\''.F_escape_sql($db, $userdata[5]).'\',
						user_firstname='.F_empty_to_null($userdata[6]).',
						user_lastname='.F_empty_to_null($userdata[7]).',

                        user_college='.F_empty_to_null($userdata[8]).',
                        user_department='.F_empty_to_null($userdata[9]).',
                        user_year_level='.F_empty_to_null($userdata[10]).',
                        user_passport='.F_empty_to_null($userdata[11]).',

						user_birthdate='.F_empty_to_null($userdata[12]).',
						user_birthplace='.F_empty_to_null($userdata[13]).',
						user_regnumber='.F_empty_to_null($userdata[14]).',
						user_ssn='.F_empty_to_null($userdata[15]).',
						user_level=\''.intval($userdata[16]).'\',
						user_verifycode='.F_empty_to_null($userdata[17]).',
						user_otpkey='.F_empty_to_null($userdata[18]).'
						WHERE user_id='.$user_id.'';
                    //var_dump($sqlu);
                    if (!$ru = F_db_query($sqlu, $db)) {
                        F_display_db_error(false);
                        return false;
                    }
                } else {
                    // no user is updated, so empty groups
                    $userdata[19] = '';
                }
            } else {
                // add new user
				$new_addition++;
                $sqlu = 'INSERT INTO '.K_TABLE_USERS.' (
					user_name,
					user_password,
					user_email,
					user_regdate,
					user_ip,
					user_firstname,
					user_lastname,
                    user_college,
                    user_department,
                    user_year_level,
                    user_passport,
					user_birthdate,
					user_birthplace,
					user_regnumber,
					user_ssn,
					user_level,
					user_verifycode,
					user_otpkey
					) VALUES (
					\''.F_escape_sql($db, $userdata[1]).'\',
					\''.F_escape_sql($db, getPasswordHash($userdata[2])).'\',
					'.F_empty_to_null($userdata[3]).',
					\''.F_escape_sql($db, $userdata[4]).'\',
					\''.F_escape_sql($db, $userdata[5]).'\',
					'.F_empty_to_null($userdata[6]).',
					'.F_empty_to_null($userdata[7]).',
					'.F_empty_to_null($userdata[8]).',
					'.F_empty_to_null($userdata[9]).',
					'.F_empty_to_null($userdata[10]).',
					'.F_empty_to_null($userdata[11]).',
					'.F_empty_to_null($userdata[12]).',
					'.F_empty_to_null($userdata[13]).',
					'.F_empty_to_null($userdata[14]).',
					'.F_empty_to_null($userdata[15]).',
					'.intval($userdata[16]).',
					'.F_empty_to_null($userdata[17]).',
					'.F_empty_to_null($userdata[18]).'
					)';
                //var_dump($sqlu);
                if (!$ru = F_db_query($sqlu, $db)) {
                    F_display_db_error(false);
                    return false;
                } else {
                    $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                }
            }
        } else {
            F_display_db_error(false);
            return false;
        }

        // user's groups
        if (!empty($userdata[19])) {
            $groups = preg_replace("/[\r\n]+/", '', $userdata[19]);
            $groups = explode(',', addslashes($groups));
            while (list($key, $group_name)=each($groups)) {
                $group_name = F_escape_sql($db, $group_name);
                // check if group already exist
                $sql = 'SELECT group_id
					FROM '.K_TABLE_GROUPS.'
					WHERE group_name=\''.$group_name.'\'
					LIMIT 1';
                if ($r = F_db_query($sql, $db)) {
                    if ($m = F_db_fetch_array($r)) {
                        // the group already exist
                        $group_id = $m['group_id'];
                    } else {
                        // creat a new group
                        $sqli = 'INSERT INTO '.K_TABLE_GROUPS.' (
							group_name
							) VALUES (
							\''.$group_name.'\'
							)';
                        if (!$ri = F_db_query($sqli, $db)) {
                            F_display_db_error(false);
                            return false;
                        } else {
                            $group_id = F_db_insert_id($db, K_TABLE_GROUPS, 'group_id');
                        }
                    }
                } else {
                    F_display_db_error(false);
                    return false;
                }
                // check if user-group already exist
                $sqls = 'SELECT *
					FROM '.K_TABLE_USERGROUP.'
					WHERE usrgrp_group_id=\''.$group_id.'\'
						AND usrgrp_user_id=\''.$user_id.'\'
					LIMIT 1';
                if ($rs = F_db_query($sqls, $db)) {
                    if (!$ms = F_db_fetch_array($rs)) {
                        // associate group to user
                        $sqlg = 'INSERT INTO '.K_TABLE_USERGROUP.' (
							usrgrp_user_id,
							usrgrp_group_id
							) VALUES (
							'.$user_id.',
							'.$group_id.'
							)';
                        if (!$rg = F_db_query($sqlg, $db)) {
                            F_display_db_error(false);
                            return false;
                        }
                    }
                } else {
                    F_display_db_error(false);
                    return false;
                }
            }
        }
    }

	echo "
	<pre>New additions: $new_addition</pre>
	<pre>Updates: $updates</pre>
	";

    return true;
}

function get_item_index($item, $stack)
{
    $index = array_search($item, $stack);
    if (!is_numeric($index)) {
        $index = 0;//default
    }
    return $index;
}

//============================================================+
// END OF FILE
//============================================================+
