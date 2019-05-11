<?php
//============================================================+
// File name   : tce_functions_omr.php
// Begin       : 2011-05-17
// Last Update : 2014-06-11
//
// Description : Functions to import test data from scanned
//               OMR (Optical Mark Recognition) sheets.
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
//    Copyright (C) 2004-2014 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Functions to import test data from scanned OMR (Optical Mark Recognition) sheets.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2011-05-17
 */

/**
 * Encode OMR test data array as a string to be printed on QR-Code.
 * @param $data (array) array to be encoded
 * @return encoded string.
 */
function F_encodeOMRTestData($data)
{
    $str = serialize($data);
    $str = gzcompress($str, 9); // requires php-zlib extension
    $str = base64_encode($str);
    $str = urlencode($str);
    return $str;
}

/**
 * Decode OMR test data string (read from QR-Code) as array.
 * @param $str (string) string to be decoded.
 * @return array with test data (0 => test_id, n => array(0 => question_n_ID, 1 => array(answers_IDs)), or false in case of error.
 */
function F_decodeOMRTestData($str)
{
    if (empty($str)) {
        return false;
    }
    $data = $str;
    $data = urldecode($data);
    $data = base64_decode($data);
    $data = gzuncompress($data);
    $data = unserialize($data);
    return $data;
}

/**
 * Read QR-Code from OMR page and return Test data.
 * This function uses the external application zbarimg (http://zbar.sourceforge.net/).
 * @param $image (string) image file to be decoded (scanned OMR page).
 * @return array with test data or false in case o error
 */
function F_decodeOMRTestDataQRCode($image)
{
    require_once '../config/tce_config.php';
    if (empty($image)) {
        return false;
    }
    $command = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Sqrcode.enable -q ' . escapeshellarg($image);
    $str     = exec($command);
    return F_decodeOMRTestData($str);
}

/**
 * Decode a single OMR Page and return data array.
 * This function requires ImageMagick library and zbarimg (http://zbar.sourceforge.net/).
 * @param $image (string) image file to be decoded (scanned OMR page at 200 DPI with full color range).
 * @return array of answers data or false in case of error.
 */
function F_decodeOMRPage($image, int $scannertype)
{
    switch ($scanner) {

        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            echo "scanner: fast hp - generic mode <br />\n";
            break;

        case ScannerTypes::DEFAULT_SCANNER:
            echo "scanner: default <br />\n";
            break;
    }

    require_once '../config/tce_config.php';
    // decode barcode containing first question number
    $command = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Scode128.enable -q ' . escapeshellarg($image);
    $qstart  = exec($command);
    $qstart  = intval($qstart);
    if ($qstart == 0) {
        return false;
    }
    return F_realDecodeOMRPage($image, $qstart);
}

/**
 * Decode a single OMR Page and return data array.
 * @param $image (string) image file to be decoded (scanned OMR page at 200 DPI with full color range).
 * @param $qstart (int) the question start number of this answer sheet
 * @return array of answers data or false in case of error.
 */
function F_realDecodeOMRPage($job_id, $image, $qstart, int $scannertype)
{

    switch ($scannertype) {

        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            echo "scanner: fast hp - generic mode <br />\n";
            break;

        case ScannerTypes::DEFAULT_SCANNER:
            echo "scanner: default <br />\n";
            break;
    }

    require_once '../config/tce_config.php';

    /*
    $img = new Imagick();
    $img->readImage($image);
    $imginfo = $img->identifyImage();
    if ($imginfo['type'] == 'TrueColor') {
    // remove red color
    $img->separateImageChannel(Imagick::CHANNEL_RED);
    } else {
    // desaturate image
    $img->modulateImage(100, 0, 100);
    }
    // get image width and height
    $w = $imginfo['geometry']['width'];
    $h = $imginfo['geometry']['height'];
    if ($h > $w) {
    // crop header and footer
    $y = round(($h - $w) / 2);
    $img->cropImage($w, $w, 0, $y);
    $img->setImagePage(0, 0, 0, 0);
    }
    $img->normalizeImage(Imagick::CHANNEL_ALL);
    $img->enhanceImage();
    $img->despeckleImage();
    $img->blackthresholdImage('#808080');
    $img->whitethresholdImage('#808080');
    $img->trimImage(85);
    $img->deskewImage(15);
    $img->trimImage(85);

    write_debug_file($img, $job_id, "-0-before-resize-for-decoding", basename($image));
    $img->resizeImage(1028, 1052, Imagick::FILTER_CUBIC, 1);

    $img->setImagePage(0, 0, 0, 0);
    // $img->writeImage(K_PATH_CACHE. mktime() . '_DEBUG_OMR_.PNG'); // DEBUG
     */

    $img = F_get_useable_image_base_on_scanner_type($image, $job_id, $scannertype);

    write_debug_file($img, $job_id, "-1-after-resize-for-decoding", basename($image));

    // scan block width
    $blkw = 16;
    // starting column in pixels
    $scol = 106;
    // starting row in pixels
    $srow = 49;
    // column distance in pixels between two answers
    $dcol = 75.364;
    // column distance in pixels between True/false circles
    $dtf = 25;
    // row distance in pixels between two questions
    $drow = 32.38;

    switch ($scannertype) {
        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            $x_offset = 111;
            break;

        default:
            $x_offset = 112;
            break;
    }

    // verify image pattern
    $imgtmp = clone $img;
    $imgtmp->cropImage(1028, 10, 0, 10);
    $imgtmp->setImagePage(0, 0, 0, 0);
    // create reference block pattern
    $impref = new Imagick();
    $impref->newImage(3, 10, new ImagickPixel('black'));
    $psum = 0;
    write_debug_file($imgtmp, $job_id, "-2-top-strip", basename($image));
    for ($c = 0; $c < 12; ++$c) {
        $x = round($x_offset + ($c * $dcol));
        // get square region inside the current grid position
        $imreg = $img->getImageRegion(3, 10, $x, 0);
        $imreg->setImagePage(0, 0, 0, 0);
        // get root-mean-square-error with reference image
        write_debug_file($imreg, $job_id, "-3-imgcompare-{$c}", basename($image));
        $rmse = $imreg->compareImages($impref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
        // count reference blocks
        $psum += round(1.25 - $rmse[1]);
    }

    $imreg->clear();
    $impref->clear();

    if ($psum != 12) {
        return false;
    }
    // create reference block
    $imref = new Imagick();
    $imref->newImage($blkw, $blkw, new ImagickPixel('black'));
    // array to be returned
    $omrdata = array();
    // for each row (question)
    for ($r = 0; $r < 30; ++$r) {
        $omrdata[($r + $qstart)] = array();
        $y                       = round($srow + ($r * $drow));
        // for each column (answer)
        for ($c = 0; $c < 12; ++$c) {
            // read true option
            $x = round($scol + ($c * $dcol));
            // get square region inside the current grid position
            $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // true option
            $opt_true = (2 * round(1.25 - $rmse[1]));

            // read false option
            $x += $dtf;
            // get square region inside the current grid position
            $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // false option
            $opt_false = round(1.25 - $rmse[1]);
            // set array to be returned (-1 = unset, 0 = false, 1 = true)
            //here, we may need to figure out if it is shaded region and thus breakout, so that student don't end up shading all options and
            //thus will always get a right answer column shaded
            $val = ($opt_true + $opt_false - 1);
            if ($val > 1) {
                $val = 1;
            }
            $omrdata[($r + $qstart)][($c + 1)] = $val;
        }
    }

    $img->clear();
    $imreg->clear();
    $imref->clear();
    $imgtmp->clear();
    return $omrdata;
}

/**
 * Decode a single OMR Page and return data array.
 * @param $image (string) image file to be decoded (scanned OMR page at 200 DPI with full color range).
 * @param $qstart (int) the question start number of this answer sheet
 * @return array of answers data or false in case of error.
 */
function F_decodeIDentificationPage($image, $job_id, int $scannertype)
{
    switch ($scanner) {

        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            echo "scanner: fast hp - generic mode <br />\n";
            break;

        case ScannerTypes::DEFAULT_SCANNER:
            echo "scanner: default <br />\n";
            break;
    }

    $img = F_get_useable_image_base_on_scanner_type($image, $job_id, $scannertype);

    write_debug_file($img, $job_id, "-3.2-after-ensure-useable-named-deb", basename($image), 1);
    // scan block width
    $blkw = 16;
    // starting column in pixels
    $scol = 106;
    // starting row in pixels
    $srow = 49;
    // column distance in pixels between two answers
    $dcol = 75.364;
    // column distance in pixels between True/false circles
    $dtf = 25;
    // row distance in pixels between two questions
    $drow = 32.38;
    // now verify image pattern

    switch ($scannertype) {
        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            $x_offset = 111;
            break;

        default:
            $x_offset = 112;
            break;
    }

    $imgtmp = clone $img;
    // $biggerCrop = clone $img;

    switch ($scannertype) {
        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            //Imagick::cropImage ($width ,$height , int $x , int $y )
            $imgtmp->cropImage(1028, 10, 30, 10);
            break;

        default:
            $imgtmp->cropImage(1028, 10, 0, 10);
            break;
    }

    write_debug_file($imgtmp, $job_id, "-4-crop-top-black-strip-from-deb", basename($image), 1);

    $imgtmp->setImagePage(0, 0, 0, 0);
    // create reference block pattern
    $impref = new Imagick();
    $impref->newImage(3, 10, new ImagickPixel('black'));
    $psum = 0;
    for ($c = 0; $c < 12; ++$c) {
        // $x = round(112 + ($c * $dcol));
        $x = round($x_offset + ($c * $dcol));
        // get square region inside the current grid position
        // Imagick::getImageRegion ($width ,$height , int $x , int $y )
        $imreg = $img->getImageRegion(3, 10, $x, 0);
        $imreg->setImagePage(0, 0, 0, 0);
        write_debug_file($imreg, $job_id, "-4-in-{$c}-crop-top-black-strip-from-deb", basename($image), 1);

        // get root-mean-square-error with reference image
        $rmse = $imreg->compareImages($impref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
        // count reference blocks
        $psum += round(1.25 - $rmse[1]);
    }

    $imreg->clear();
    $impref->clear();
    $imgtmp->clear();

    if ($psum != 12) {
        return false;
    }
    // create reference block
    $imref = new Imagick();
    $imref->newImage($blkw, $blkw, new ImagickPixel('black'));
    write_debug_file($imref, $job_id, "-zzzz-a-template-file", 1);
    // array to be returned
    $omrdata = array();
    // for each row (id)
    for ($r = 0; $r <= 6; ++$r) {
        $y = round($srow + ($r * $drow));
        // Imagick::getImageRegion ($width ,$height , int $x , int $y )
        write_debug_file($img->getImageRegion($img->getImageWidth(), $blkw, 0, $y), $job_id, "-zzzz-b-row-{$r}-afore", 1);
        // for each column (0-9)
        for ($c = 0; $c <= 10; ++$c) {
            // read true option
            $x = round($scol + ($c * $dcol));
            // get square region inside the current grid position
            $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            write_debug_file($imreg, $job_id, "-zzzz-b-row-{$r}-col-{$c}", 1);
            $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // true option
            // $opt_true = (2 * round(1.25 - $rmse[1]));

            // read false option
            $x += $dtf;
            // get square region inside the current grid position
            // $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            // $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            // $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // false option
            // $opt_false = round(1.25 - $rmse[1]);
            // set array to be returned (-1 = unset, 0 = false, 1 = true)
            // $val = ($opt_true + $opt_false - 1);
            // if ($val > 1) {
            //     $val = 1;
            // }
            // $omrdata[] = $val;
            //an RMSE[1] less than (highest so far is 0.42.) is okay...esp if we want to accomodate faint shadings, which is okay - from our tests
            if ((count($rmse) > 1) && ($rmse[1] < 0.45)) {
                $omrdata[] = $c;
            }
        }
    }

    $imreg->clear();
    $imref->clear();

    return $omrdata;
}

/**
 * Ensure the image is useable - solves differnces in scanner types
 *
 * @param [type] $img
 * @param [type] $job_id
 * @param [type] We need this because when we clone images etc., getFilename() on the Imagick instance brings extremely funny names - more of some sort of repitions in the names
 * @param string $scanner
 * @return void
 */
function F_ensureImageIsUseable($img, $job_id, $basename_filename, int $scanner)
{
    switch ($scanner) {

        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            echo "scanner: fast hp - generic mode <br />\n";
            break;

        case ScannerTypes::DEFAULT_SCANNER:
            echo "scanner: default <br />\n";
            break;
    }

    $imginfo = $img->identifyImage();
    // get image width and height
    $w = $imginfo['geometry']['width'];
    $h = $imginfo['geometry']['height'];

    switch ($scanner) {
        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:

            //for images scanned with our FastHpScanner, extra 850px was added to the
            //height of the scanned image (added as white spaces). So we need to crop-off
            //this 'contaminating' extra space by cropiingcropping from top to 3350px (the
            //whole image with all the white padding is 4200px. Check drive link for sample file -
            //files named ALIDADA 002.PNG..etc)

            write_debug_file($img, $job_id, "-1-afix-beforecropforscannertype", $basename_filename, 1);
            $img->cropImage($w, 1200, 0, 0);
            write_debug_file($img, $job_id, "-1-afix-immdtlyaftercropforscannertype", $basename_filename, 1);
            // $img->setImagePage(0, 0, 0, 0);
            break;
    }

    $maxHeight = 1200;
    //heavy images takes unecessarily long time to process. okay if height is just about  {$maxHeight}px
    if ($h > $maxHeight) {
        //resize it proportionately
        // $scaledownRatio = (($maxHeight * 100) / $h) / 100;
        // $newWidth       = ceil($w * ($scaledownRatio));
        // $newHeight      = ceil($h * $scaledownRatio);
        // $img->resizeImage($newWidth, $newHeight, Imagick::FILTER_CUBIC, 1, TRUE);
        write_debug_file($img, $job_id, "-1.1-afix-beforescaling", $basename_filename, 1);
        $img->scaleImage(0, $maxHeight);
        write_debug_file($img, $job_id, "-1.1-afix-immdtlyafterscaling", $basename_filename, 1);
    }

    if ($imginfo['type'] == 'TrueColor') {
        // remove red color
        $img->separateImageChannel(Imagick::CHANNEL_RED);
    } else {
        // desaturate image
        $img->modulateImage(100, 0, 100);
    }

    if ($h > $w) {

        // crop header and footer
        write_debug_file($img, $job_id, "-2-beforecropawayheaderandfooter", $basename_filename, 1);

        switch ($scanner) {

            case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
                echo "cropping as FAST_HP_SCANNER_GENERIC_MODE... <br />\n";
                //Imagick::cropImage($width, $height , int $x , int $y )
                $img->cropImage(826, 826, 10, 175);
                break;

            default:
                echo "cropping generically... <br />\n";
                $img->cropImage(826, 826, 10, 175);
                // $img->cropImage($w, $w, 0, round(($h - $w) / 2));
                break;
        }

        $img->setImagePage(0, 0, 0, 0);
        write_debug_file($img, $job_id, "-2-immdtlyaftercropawayheaderandfooter", $basename_filename, 1);

    }

    return $img;
}

/**
 * Import user's test data from OMR.
 * @param $user_id (int) user ID.
 * @param $date (string) date-time field.
 * @param $omr_testdata (array) Array containing test data.
 * @param $omr_answers (array) Array containing test answers (from OMR).
 * @param $overwrite (boolean) If true overwrites the previous answers on non-repeatable tests.
 * @return boolean TRUE in case of success, FALSE otherwise.
 */
function F_importOMRTestData($user_id, $date, $omr_testdata, $omr_answers, $overwrite = false, $connection = null)
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    global $db, $l;
    if (!is_null($connection)) {
        $db = $connection;
    }
    // check arrays
    if (count($omr_testdata) > (count($omr_answers) + 1)) {
        // arrays must contain the same amount of questions
        return false;
    }
    $test_id     = intval($omr_testdata[0]);
    $user_id     = intval($user_id);
    $time        = strtotime($date);
    $date        = date(K_TIMESTAMP_FORMAT, $time);
    $dateanswers = date(K_TIMESTAMP_FORMAT, ($time + 1));
    // check user's group
    if (F_count_rows(K_TABLE_USERGROUP . ', ' . K_TABLE_TEST_GROUPS . ' WHERE usrgrp_group_id=tstgrp_group_id AND tstgrp_test_id=' . $test_id . ' AND usrgrp_user_id=' . $user_id . ' LIMIT 1') == 0) {
        return false;
    }
    // get test data
    $testdata = F_getTestData($test_id);
    // 1. check if test is repeatable
    $sqls = 'SELECT test_id FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . ' AND test_repeatable=\'1\' LIMIT 1';
    if ($rs = F_db_query($sqls, $db)) {
        if ($ms = F_db_fetch_array($rs)) {
            // 1a. update previous test data if repeatable
            $sqld = 'UPDATE ' . K_TABLE_TEST_USER . ' SET testuser_status=testuser_status+1 WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id . ' AND testuser_status>3';
            if (!$rd = F_db_query($sqld, $db)) {
                F_display_db_error();
            }
        } else {
            if ($overwrite) {
                // 1b. delete previous test data if not repeatable
                $sqld = 'DELETE FROM ' . K_TABLE_TEST_USER . ' WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id . '';
                if (!$rd = F_db_query($sqld, $db)) {
                    F_display_db_error();
                }
            } else {
                // 1c. check if this data already exist
                if (F_count_rows(K_TABLE_TEST_USER, 'WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id . '') > 0) {
                    F_print_error('MESSAGE', "Error : you did not select to overwrite, and user {$user_id} already have answers uploaded for this test");
                    return false;
                }
            }
        }
    } else {
        F_display_db_error();
    }
    // 2. create new user's test entry
    // ------------------------------
    $sql = 'INSERT INTO ' . K_TABLE_TEST_USER . ' (
		testuser_test_id,
		testuser_user_id,
		testuser_status,
		testuser_creation_time,
		testuser_comment
		) VALUES (
		' . $test_id . ',
		' . $user_id . ',
		4,
		\'' . $date . '\',
		\'OMR\'
		)';
    if (!$r = F_db_query($sql, $db)) {
        F_display_db_error(false);
        return false;
    } else {
        // get inserted ID
        $testuser_id = F_db_insert_id($db, K_TABLE_TEST_USER, 'testuser_id');
        F_updateTestuserStat($date);
    }
    // 3. create test log entries
    $num_questions = count($omr_testdata) - 1;
    // for each question on array
    for ($q = 1; $q <= $num_questions; ++$q) {
        $question_id = intval($omr_testdata[$q][0]);
        $num_answers = count($omr_testdata[$q][1]);
        // get question data
        $sqlq = 'SELECT question_type, question_difficulty FROM ' . K_TABLE_QUESTIONS . ' WHERE question_id=' . $question_id . ' LIMIT 1';
        if ($rq = F_db_query($sqlq, $db)) {
            if ($mq = F_db_fetch_array($rq)) {
                // question scores
                $question_right_score      = ($testdata['test_score_right'] * $mq['question_difficulty']);
                $question_wrong_score      = ($testdata['test_score_wrong'] * $mq['question_difficulty']);
                $question_unanswered_score = ($testdata['test_score_unanswered'] * $mq['question_difficulty']);
                // add question
                $sqll = 'INSERT INTO ' . K_TABLE_TESTS_LOGS . ' (
					testlog_testuser_id,
					testlog_question_id,
					testlog_score,
					testlog_creation_time,
					testlog_display_time,
					testlog_reaction_time,
					testlog_order,
					testlog_num_answers
					) VALUES (
					' . $testuser_id . ',
					' . $question_id . ',
					' . $question_unanswered_score . ',
					\'' . $date . '\',
					\'' . $date . '\',
					1,
					' . $q . ',
					' . $num_answers . '
					)';
                if (!$rl = F_db_query($sqll, $db)) {
                    F_display_db_error(false);
                    return false;
                }
                $testlog_id = F_db_insert_id($db, K_TABLE_TESTS_LOGS, 'testlog_id');
                // set initial question score
                if ($mq['question_type'] == 1) { // MCSA
                    $qscore = $question_unanswered_score;
                } else { // MCMA
                    $qscore = 0;
                }
                $unanswered  = true;
                $numselected = 0; // count the number of MCSA selected answers
                // for each answer on array
                for ($a = 1; $a <= $num_answers; ++$a) {
                    $answer_id = intval($omr_testdata[$q][1][$a]);
                    if (isset($omr_answers[$q][$a])) {
                        $answer_selected = $omr_answers[$q][$a]; //-1, 0, 1
                    } else {
                        $answer_selected = -1;
                    }
                    // add answer
                    $sqli = 'INSERT INTO ' . K_TABLE_LOG_ANSWER . ' (
						logansw_testlog_id,
						logansw_answer_id,
						logansw_selected,
						logansw_order
						) VALUES (
						' . $testlog_id . ',
						' . $answer_id . ',
						' . $answer_selected . ',
						' . $a . '
						)';
                    if (!$ri = F_db_query($sqli, $db)) {
                        F_display_db_error(false);
                        return false;
                    }
                    // calculate question score
                    if ($mq['question_type'] < 3) { // MCSA or MCMA
                        // check if the answer is right
                        $answer_isright = false;
                        $sqla           = 'SELECT answer_isright FROM ' . K_TABLE_ANSWERS . ' WHERE answer_id=' . $answer_id . ' LIMIT 1';
                        if ($ra = F_db_query($sqla, $db)) {
                            if (($ma = F_db_fetch_array($ra))) {
                                $answer_isright = F_getBoolean($ma['answer_isright']);
                                switch ($mq['question_type']) {
                                    case 1:{ // MCSA - Multiple Choice Single Answer
                                            if ($answer_selected == 1) {
                                                ++$numselected;
                                                if ($numselected == 1) {
                                                    $unanswered = false;
                                                    if ($answer_isright) {
                                                        $qscore = $question_right_score;
                                                    } else {
                                                        $qscore = $question_wrong_score;
                                                    }
                                                } else {
                                                    // multiple answer selected
                                                    $unanswered = true;
                                                    $qscore     = $question_unanswered_score;
                                                }
                                            }
                                            break;
                                        }
                                    case 2:{ // MCMA - Multiple Choice Multiple Answer
                                            if ($answer_selected == -1) {
                                                $qscore += $question_unanswered_score;
                                            } elseif ($answer_selected == 0) {
                                                $unanswered = false;
                                                if ($answer_isright) {
                                                    $qscore += $question_wrong_score;
                                                } else {
                                                    $qscore += $question_right_score;
                                                }
                                            } elseif ($answer_selected == 1) {
                                                $unanswered = false;
                                                if ($answer_isright) {
                                                    $qscore += $question_right_score;
                                                } else {
                                                    $qscore += $question_wrong_score;
                                                }
                                            }
                                            break;
                                        }
                                }
                            }
                        } else {
                            F_display_db_error(false);
                            return false;
                        }
                    }
                } // end for each answer
                if ($mq['question_type'] == 2) { // MCMA
                    // normalize score
                    if (F_getBoolean($testdata['test_mcma_partial_score'])) {
                        // use partial scoring for MCMA and ORDER questions
                        $qscore = round(($qscore / $num_answers), 3);
                    } else {
                        // all-or-nothing points
                        if ($qscore >= ($question_right_score * $num_answers)) {
                            // right
                            $qscore = $question_right_score;
                        } elseif ($qscore == ($question_unanswered_score * $num_answers)) {
                            // unanswered
                            $qscore = $question_unanswered_score;
                        } else {
                            // wrong
                            $qscore = $question_wrong_score;
                        }
                    }
                }
                if ($unanswered) {
                    $change_time = '';
                } else {
                    $change_time = $dateanswers;
                }
                // update question score
                $sqll = 'UPDATE ' . K_TABLE_TESTS_LOGS . ' SET
					testlog_score=' . $qscore . ',
					testlog_change_time=' . F_empty_to_null($change_time) . ',
					testlog_reaction_time=1000
					WHERE testlog_id=' . $testlog_id . '';
                if (!$rl = F_db_query($sqll, $db)) {
                    F_display_db_error();
                    return false;
                }
            }
        } else {
            F_display_db_error(false);
            return false;
        }
    } // end for each question
    return true;
}

function write_debug_file($img, $job_id, $append, $original_filename = "", $debug_level = 2)
{
    //we can control when to countenace this debug and when not to
    if ($debug_level > 0) {
        $fullname = K_PATH_CACHE . "logs/debug/" . time() . '-JOB' . $job_id . "-{$append}-{$original_filename}-DEBUG_OMR.png";
        // echo "\n <br /> writing: [$fullname] \n <br />";
        try {
            $img->writeImage($fullname);
            // echo "\n <br /> done \n <br />";
        } catch (\Exception $e) {
            // echo "\n <br /> failed write work: " . $e->getMessage() . " \n <br />\n <br />";
            endMarkingSessionWithError($job_id, $e->getMessage());
        }
    }
}

function F_get_omr_testdata_by_id(int $qrcode_db_id)
{
    global $db, $l;
    $omr_testdata = "";
    if ($r = F_db_query("SELECT qrcode FROM tce_qrcodes WHERE id={$qrcode_db_id}", $db)) {
        if ($m = F_db_fetch_array($r)) {
            $omr_testdata = F_decodeOMRTestData($m['qrcode']);
            // read OMR ANSWER SHEET pages
        }
    }
    return $omr_testdata;
}

function F_get_omr_testdata($uploaded_file, $job_id)
{
    global $db, $l;
    $omr_testdata  = "";
    $answer_codecs = F_extract_code_data_from_encoded_page($uploaded_file, $job_id);
    if ($r = F_db_query("SELECT qrcode FROM tce_qrcodes WHERE id={$answer_codecs['qrcode_id']}", $db)) {
        if ($m = F_db_fetch_array($r)) {
            $omr_testdata = F_decodeOMRTestData($m['qrcode']);
        }
    }

    return $omr_testdata;
}

function F_get_omrData_by_qrcodeId($qrcode_id)
{
    global $db, $l;
    if ($r = F_db_query("SELECT qrcode FROM tce_qrcodes WHERE id={$qrcode_id}", $db)) {
        if ($m = F_db_fetch_array($r)) {
            return F_decodeOMRTestData($m['qrcode']);
        }
    }
}

function F_ensure_optimum_size_shell($job_id, $filepath)
{
    $response = exec("mogrify -resize 1000x1500 " . escapeshellarg($filepath));
    if (!empty($response)) {
        endMarkingSessionWithError($job_id, "Error initializing file for marking: $filepath");
    }
}

function F_ensure_optimum_size($job_id, $filepath, $use_shell = true)
{
    //this is important for normalizing all the differnt scanner types, since we are resloving difernt scanner sizes based on this same resized-resolution
    if ($use_shell) {
        F_ensure_optimum_size_shell($job_id, $filepath);
    } else {
        $thumb = new Imagick();
        $thumb->readImage($filepath);

        //now check the width
        $width = $thumb->getImageWidth();

        //now check height
        $height = $thumb->getImageHeight();

        if ($height > $width) {

            $new_width  = 826;
            $new_height = (int) ($height / $width * 826);

            $thumb->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
        }

        $thumb->writeImage($filepath);
        $thumb->clear();}
}

/**
 * return [
 *      'qrcode_id'          => $answer_codecs[0], //qrcode_id is id to the qr data; which hold the questions and answers to those questions - it is directly used in marking the script
 *      'start_number'       => $answer_codecs[1], (applies only to answer pages)
 *      'question_paper_type_unique_sum' => explode('(', $answer_codecs[2])[0],
 *      'doc_type'           => $answer_codecs[3], //if it is answer sheet or identification page
 *      ];
 * @param [type] $uploaded_file [description]
 */
function F_extract_code_data_from_encoded_page($uploaded_file, $job_id)
{
    $command       = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Scode128.enable -q ' . escapeshellarg($uploaded_file);
    $answer_codecs = exec($command);

    if (empty($answer_codecs)) {
        $trials = 0;
        $img    = new Imagick();
        while (empty($answer_codecs)) {

            updateJobStatus($job_id, $answer_codecs);

            //we noticed that reducing the "density"/compressing the image helps zbarimg to succeed
            $trials++;
            $img->clear();
            $img->readImage($uploaded_file);
            $img->resizeImage((1028 / $trials), null, Imagick::FILTER_CUBIC, 1);
            // $img->resizeImage((1028 / $trials), (1052 / $trials), Imagick::FILTER_CUBIC, 1, true);
            $newFilePath = K_PATH_CACHE . "logs/resizes/" . time() . '-' . ($trials) . '-' . basename($uploaded_file) . '-RESIZE_OMR.PNG';
            $img->writeImage($newFilePath);
            $command       = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Scode128.enable -q ' . escapeshellarg($newFilePath);
            $answer_codecs = exec($command);

            if ($trials == 2) {
                break;
            }
        }
    }

    $answer_codecs = explode(',', $answer_codecs);

    /*
     * sample answer_codecs return:

    129,0,5EBBE36(T1),USERID
    129,1,5EBBE36(T1-1),ANSWERS
    133,0,C5F84A7(K2),USERID
    133,1,C5F84A7(K2-1),ANSWERS
     */

    //if the image is not ok, we won't have much
    if (count($answer_codecs) > 3) {
        $dynamic_id = explode('(', $answer_codecs[2]);
        return [
            'qrcode_id'                      => $answer_codecs[0],
            'start_number'                   => $answer_codecs[1],
            'question_paper_type_unique_sum' => $dynamic_id[0],
            'dynamic_user_id'                => $dynamic_id[1],
            'doc_type'                       => $answer_codecs[3], //if it is answer sheet or identification page
        ];
    } else {
        return [];
    }
}

function F_get_dynamic_identifier($job_id, $question_paper_type_unique_sum, $filename)
{
    //usually in the form of any of the below:
    //T1-1)
    //K2).
    //We only need the T1/K2 part
    //Condition: original string must have at least the "(" char
    if (strpos($question_paper_type_unique_sum, ")") !== false) {
        return explode("-", explode(")", $question_paper_type_unique_sum)[0])[0];
    } else {
        endMarkingSessionWithError($job_id, "No recognizable user identifier: $question_paper_type_unique_sum ({$filename})");
    }
}

//============================================================+
// END OF FILE
//============================================================+
