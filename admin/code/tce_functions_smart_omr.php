<?php
//============================================================+
// File name   : tce_import_omr_answers_bulk_smart_processor.php
// Begin       : 2019-01-21
// Last Update : 2019-01-21
//
// Description : Import test answers using OMR (Optical Mark Recognition)
//               technique applied to images of scanned answer sheets, smartly
//
// Author: Damilola Olowookere
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2013 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

function startProcessing($job_id, $job_db_primarykey, $job_files)
{
    set_time_limit(0);

    require "./classes/ScannerTypes.php";

    // $scannertype = ScannerTypes::DEFAULT_SCANNER;
    // $scannertype = ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE;
    $scannertype = ScannerTypes::FAST_HP_SCANNER_300PPI;

    updateJobStatus($job_db_primarykey, 1, "percentage_progress");
    //for debug purposes, clear it first:
    array_map('unlink', glob(K_PATH_CACHE . "logs/resizes/*.*"));
    array_map('unlink', glob(K_PATH_CACHE . "logs/debug/*.*"));

    //start procseeing the files
    // *iterate through tmp_smart_omr/job_id/ and map the data into an array: [ 'unique_user_id' => all_omr_file_names_including_id_page ]
    // *don't forget to update the db entry with status text of how far. Client-side js always poll the db for status (for performance reasons, we may be updating the db after processing n-records, rather than at every single iteration)

    //default to today - alternatively, we can allow user also post the date to use
    $date               = (new DateTime())->format('c');
    $update_freq        = 3;
    $uploaded_data      = [];
    $universal_counter  = 1;
    $omr_insertion_data = [];

    $start_processing_all = microtime(true);

    foreach ($job_files as $filepath) {

        $start_processing_file = microtime(true);

        $name = basename($filepath);

        $encoded_data = F_extract_code_data_from_encoded_page($filepath, $job_id);

        //any file uploaded cannot just pass as valid OMR file. You
        //need to pass in encoded, well-scanned file that we can
        //pull question_paper_type_unique_sum from, either in the id page
        //OR in the OMR page
        if (
            (!array_key_exists('question_paper_type_unique_sum', $encoded_data) || empty($encoded_data['question_paper_type_unique_sum']))
            ||
            (!array_key_exists('dynamic_user_id', $encoded_data) || empty($encoded_data['dynamic_user_id']))
        ) {
            endMarkingSessionWithError(
                $job_id,
                "One of the files you uploaded is invalid (filename: " . $name . ")",
                "Data obtained when F_extract_code_data_from_encoded_page($filepath): " . json_encode($encoded_data)
            );
        }

        //ensure the id exists
        if (!array_key_exists($encoded_data['question_paper_type_unique_sum'], $uploaded_data)) {
            $uploaded_data[$encoded_data['question_paper_type_unique_sum']] = [];
        }

        //now save for this user in this paper type
        $this_user_dynamic_identifier = F_get_dynamic_identifier($job_id, $encoded_data['dynamic_user_id'], $name);
        if (!array_key_exists($this_user_dynamic_identifier, $uploaded_data[$encoded_data['question_paper_type_unique_sum']])) {
            $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier] = [];
        }

        $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['filename'] = $name;
        $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['omr_data'] = F_get_omrData_by_qrcodeId($encoded_data['qrcode_id']);

        //sort into answers- or id-page
        if ($encoded_data['doc_type'] == "ANSWERS") {

            if (!array_key_exists('answer_pages', $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier])) {
                $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['answer_pages'] = [];
            }

            $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['answer_pages'][] = [
                'file'         => $filepath,
                'start_number' => $encoded_data['start_number'],
            ];

        } else {
            if ($encoded_data['doc_type'] == "USERID") {
                try {
                    $userId = F_decodeIDentificationPage($filepath, $job_id, $scannertype);
                } catch (\Exception $th) {
                    endMarkingSessionWithError($job_id, "Error: $th - " . $name);
                }

                if ($userId !== false && is_array($userId)) {
                    $uid = intval(implode('', $userId));
                    if ($uid > 0) {
                        $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['user_id'] = $uid;
                    } else {
                        //it is possible that user id wrongly read due to some rows well shaded while others not. We check that later below
                        endMarkingSessionWithError($job_id, "Invalid User ID [$uid] from file: " . $name . "<br />\n" . json_encode($userId));
                    }
                } else {
                    endMarkingSessionWithError($job_id, "User ID cannot be decoded from file: " . $name);
                }
            }
        }

        //update db after every $update_freq files (to increase effiieny by reduing db roundtrips)
        if ((++$universal_counter % $update_freq) == 0) {

            //update percentage progress. This part is not more than 25% because we have for loops that we are d
            //ealng with. The remianing loops also take 25%
            //we can use universal_counter here beccause it direttly corresponds to this iteration
            //This 25% is where we are dividing by 4, so that 4 times of 25% makes
            //100% to make for cmpletion/determine completion.
            updateJobStatus($job_db_primarykey, round((($universal_counter * 100) / count($job_files)) / 4), "percentage_progress");

            if (array_key_exists("user_id", $uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier])) {
                $user = F_getUserByID($uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['user_id']);
                //sometimes, the system only reads a row, and no more than that, so possible to have wrong reading
                if (is_array($user)) {
                    updateJobStatus($job_db_primarykey, "Processing data for (user id: {$uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['user_id']}) {$user['user_firstname']} {$user['user_lastname']} [$name]");
                } else {
                    //it is possible that user id wrongly read due to some rows well shaded while others not
                    endMarkingSessionWithError($job_id, "Cannot process non-existing user (user id: {$uploaded_data[$encoded_data['question_paper_type_unique_sum']][$this_user_dynamic_identifier]['user_id']}) [$name]");
                }
            } else {
                updateJobStatus($job_db_primarykey, "Read {$universal_counter} of " . count($job_files) . " uploaded files");
            }
        }

        notifyElapseTime($name, $start_processing_file);

    }

    notifyElapseTime("All files processing", $start_processing_all);

    updateJobStatus($job_db_primarykey, 50, "percentage_progress");

    //extrapolate excpeted number of files
    //this will hep us to know if there are missing files, before marking
    $num_answer_sheets         = 0;
    $loop_counter              = 0;
    $start_check_missing_files = microtime(true);

    foreach ($uploaded_data as $question_paper_type_unique_sum => $this_dynamic_user_id) {

        foreach ($this_dynamic_user_id as $dynamic_user_id => $entries) {

            $num_questions      = (count($entries['omr_data']) - 1);
            $_num_answer_sheets = ceil($num_questions / 30);

            if (!array_key_exists('answer_pages', $entries)) {
                endMarkingSessionWithError($job_id, "No answer sheets uploaded for {$question_paper_type_unique_sum}");
            }

            if (count($entries['answer_pages']) != $_num_answer_sheets) {
                endMarkingSessionWithError($job_id, "Found " . count($entries['answer_pages']) . " out of {$_num_answer_sheets} answer sheets expected for user with answer sheet id {$question_paper_type_unique_sum} ({$entries['filename']})");
            }

            $num_answer_sheets += count($entries['answer_pages']);

            //update percentage progress. This part is not more than 25% because we have for loops that we are dealng with. The remianing loops also take 25%
            //We are using this handler function because we want to centralize  and eaasily manage this, reduccing cluster here too
            doCompositeFourPartPercentageUpdate($job_db_primarykey, $universal_counter, $update_freq, ++$loop_counter, count($uploaded_data));
        }
    }

    notifyElapseTime("Check missing files ", $start_check_missing_files);

    //now decide if client has enough marking creadits to mark this scritps
    updateJobStatus($job_db_primarykey, "There are {$num_answer_sheets} answer sheets to mark. Checking if available marking units is enough  [$name]");

    if (!there_is_enough_units_for_marking($num_answer_sheets)) {
        endMarkingSessionWithError($job_id, "units exhausted: there are {$num_answer_sheets} answer sheets to mark. But you only have " . get_marking_units_left() . " units left");
    }

    // run each mapping through marker
    updateJobStatus($job_db_primarykey, "Now marking scripts...  [$name]");
    updateJobStatus($job_db_primarykey, (new DateTime())->format('c'), "started_marking_at");
    $loop_counter         = 0;
    $start_mapping_marker = microtime(true);

    foreach ($uploaded_data as $question_paper_type_unique_sum => $this_dynamic_user_id) {

        foreach ($this_dynamic_user_id as $dynamic_user_id => $entries) {

            if ((++$universal_counter % $update_freq) == 0) {
                $user = F_getUserByID($entries['user_id']);
                updateJobStatus($job_db_primarykey, "Checking answers for {$user['user_firstname']} {$user['user_lastname']}  [$name]");
            }

            if ($insertion = mark_student_answer($job_id, $entries['user_id'], $entries['answer_pages'], $entries['filename'], $scannertype)) {

                $omr_insertion_data[] = [
                    'omr_data'  => $entries['omr_data'],
                    'insertion' => $insertion,
                ];
            } else {
                exit('Server marking error');
            }

            //update percentage progress. This part is not more than 25% because we have for loops that we are dealng with. The remianing loops also take 25%
            //We are using this handler function because we want to centralize  and eaasily manage this, reduccing cluster here too
            doCompositeFourPartPercentageUpdate($job_db_primarykey, $universal_counter, $update_freq, ++$loop_counter, count($uploaded_data));

        }
    }

    notifyElapseTime("Mapping of marks ", $start_mapping_marker);

    updateJobStatus($job_db_primarykey, "Updating students' score records...  [$name]");

    //at this point, we want to open another connection because we a connection for transaction
    if (!$transaction_connection = @F_db_connect(K_DATABASE_HOST, K_DATABASE_PORT, K_DATABASE_USER_NAME, K_DATABASE_USER_PASSWORD, K_DATABASE_NAME)) {
        endMarkingSessionWithError($job_id, "Could not open transactable connection");
    }

    //ensure we subtract the marking unit before inserting records into the db
    //do db transaction here so that removing units is atomic with insertion of records
    //transaction here also make db operation faster since we will be running lots of queries inside loop
    //make here atomic
    $transaction_connection->begin_transaction();
    $start_importing_marks = microtime(true);
    if (substract_marking_units($num_answer_sheets, $transaction_connection)) {
        $loop_counter = 0;
        foreach ($omr_insertion_data as $key => $extracted_data) {

            if ((++$universal_counter % $update_freq) == 0) {
                $user = F_getUserByID($entries['user_id']);
                updateJobStatus($job_db_primarykey, "Marking answers for {$user['user_firstname']} {$user['user_lastname']}  [$name]");
            }

            $data = $extracted_data['insertion'];

            if (!F_importOMRTestData($data['user_id'], $date, $extracted_data['omr_data'], $data['omr_answers'], true)) {
                //common failure from above is when user not found/user not in test group (usually error @F_importOMRTestData@F_count_rows#566)
                endMarkingSessionWithError($job_id, "Could not mark a processed answer sheet. Likely cause: wrong user/group match [user-id: {$data['user_id']}]");
            }

            //update percentage progress. This part is not more than 25% because we have for loops that we are dealng with. The remianing loops also take 25%
            //We are using this handler function because we want to centralize  and eaasily manage this, reduccing cluster here too
            doCompositeFourPartPercentageUpdate($job_db_primarykey, $universal_counter, $update_freq, ++$loop_counter, count($omr_insertion_data));
        }
    } else {
        endMarkingSessionWithError($job_id, "Error updating marking units");
    }
    // end atomic operation started above
    $transaction_connection->commit();
    notifyElapseTime("Importing of marks ", $start_importing_marks);

    // *Upon completion, update db entry as completed
    updateJobStatus($job_db_primarykey, "Completed marking of {$num_answer_sheets} scripts");
    updateJobStatus($job_db_primarykey, 1, "marking_completed_successfully");
    updateJobStatus($job_db_primarykey, (new DateTime())->format('c'), "completed_marking_at");
    updateJobStatus($job_db_primarykey, 100, "percentage_progress");

    // do not remove uploaded files...for reference sake
    exit("successful");
}

/**
 * @param  [type] $user_id        [description]
 * @param  Array  $omr_file_paths [description]
 * @return [Array]                [an array of omr reords to insert into db, typically after substracting the marking units from available marking units]
 */
function mark_student_answer($job_id, $user_id, array $answersheets, $filename, int $scannertype): array
{
    //TCExam now reduces paper wastage by saving qrcode into db and encoding same on
    //answer sheets
    $omr_answers = array();

    for ($i = 0; $i < count($answersheets); ++$i) {
        $answers_page = F_realDecodeOMRPage($job_id, $answersheets[$i]['file'], $answersheets[$i]['start_number'], $scannertype);
        if (($answers_page !== false) and !empty($answers_page)) {
            $omr_answers += $answers_page;
        } else {
            $user = F_getUserByID($user_id);
            endMarkingSessionWithError($job_id, "Could not mark for {$user['user_firstname']} - $filename");
        }
    }

    // sort answers (it should have been already sorted though - we are simply indirectly sorting questions here (i.e. consequently, the anserws group attached to each question s soted together with that question as a single unit of sort \_(0)_/ ))
    ksort($omr_answers);

    // import answers
    return [
        "user_id"     => $user_id,
        "omr_answers" => $omr_answers,
    ];
}

function ensureJobExistsInDb($job_id)
{
    global $db;

    //instantiate this job
    if (!F_db_query("INSERT INTO tce_omr_smart_jobs (job_id , status_text) VALUES( '" . F_escape_sql($db, $job_id) . "' ,  'Preparing to mark with job id: " . F_escape_sql($db, $job_id) . " ' ) ON DUPLICATE KEY UPDATE id=id", $db)) {

        F_display_db_error(false);

        endMarkingSessionWithError($job_id, "Data insertion error: " . $_POST['filename']);

    }

    return true;

}

function getJobFolder($job_id)
{
    //cache definition has trailing slash
    return K_PATH_CACHE . '/logs/jobs/' . $job_id;
}

/**
 * gets the files already uploaded into the custom folder specially created for
 * this job
 *
 * @param mixed $job_id
 * @return Array
 */
function getJobFilepaths($job_id)
{
    $files = [];
    $dir   = getJobFolder($job_id);
    $cdir  = scandir($dir);
    foreach ($cdir as $key => $value) {
        if (!in_array($value, array(".", ".."))) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $value;
            if (!is_dir($filePath)) {
                $files[] = $filePath;
            }
        }
    }
    return $files;
}

function all_required_files_uploaded($job_id)
{
    global $db;
    if (!$r = F_db_query(" SELECT all_required_files_uploaded FROM tce_omr_smart_jobs WHERE job_id = '" . F_escape_sql($db, $job_id) . "'", $db)) {
        F_display_db_error();
        exit('Db error');
    }

    return F_db_fetch_array($r)['all_required_files_uploaded'] == '1';
}

function getJobDbPrimaryKey($job_id)
{
    global $db;
    if (!$r = F_db_query(" SELECT id FROM tce_omr_smart_jobs WHERE job_id = '" . F_escape_sql($db, $job_id) . "'", $db)) {
        F_display_db_error();
        exit('Db error');
    }

    return intval(F_db_fetch_array($r)['id']);
}

function updateJobStatus($job_id, $value, $target_col = "status_text")
{

    global $db;
    //$job_id can either be posted job id or db primaery key value of the orignal posted job id
    $condition_col = is_int($job_id) ? "id" : "job_id";
    if ($job_id) {
        $q = "UPDATE tce_omr_smart_jobs SET $target_col = '" . F_escape_sql($db, $value) . "' WHERE $condition_col = '" . F_escape_sql($db, $job_id) . "' ";
        return F_db_query($q, $db);
    }

}

function getJobStatus($job_id)
{
    global $db;
    if ($job_id) {
        $q = "SELECT * FROM tce_omr_smart_jobs WHERE job_id = '" . F_escape_sql($db, $job_id) . "' ";
        if (!$r = F_db_query($q, $db)) {
            F_display_db_error();
            exit('Db error');
        }

        $sr = F_db_fetch_array($r, MYSQLI_ASSOC);
        return $sr;
    }
}

function endMarkingSessionWithError($job_id, $error_message, $debug_details = null)
{
    //report error and immediately terminate script
    global $db;
    //prevent data too long error: let error text not more than 250 chars
    $error_message = substr($error_message, 0, 1050);
    if (!F_db_query(" UPDATE tce_omr_smart_jobs SET
        error_text = '" . F_escape_sql($db, $error_message) . "' ,
        error = 1 ,
        debug_details = '" . F_escape_sql($db, $debug_details) . "'
        WHERE job_id = '" . F_escape_sql($db, $job_id) . "' ", $db)) {
        endMarkingSessionWithError($job_id, "Functional db error for $job_id - " . F_db_error($db));
        exit("Functional db error for $job_id - " . F_db_error($db));
    }

    exit("Marking ended with error: $error_message");
}

function get_marking_units_left()
{
    global $db;
    //we are using generic low_level imlementations f query here instead of the hihg-level functions
    //provided by tcexam, because we want to be sure that close() will not be called on the underlying
    //mysqli instance (although that's currently the case, but maintining this will prevent breakage from
    //possible changes in the future - this non-closing is needed because we use transactions)
    if (!$r = $db->query(" SELECT value FROM tce_settings WHERE name = 'marking_unitsavailable' ")) {
        F_display_db_error();
        exit('Db error');
    }

    return intval(F_db_fetch_array($r)['value']);
}

function substract_marking_units($number_of_units_to_subtract, $transaction_connection)
{

    //we are using generic low_level imlementations f query here instead of the hihg-level functions
    //provided by tcexam, because we want to be sure that close() will not be called on the underlying
    //mysqli instance (although that's currently the case, but maintining this will prevent breakage from
    //possible changes in the future - this non-closing is needed because we use transactions)
    if (!$transaction_connection->query("UPDATE tce_settings SET  value =  ( value - " . intval($number_of_units_to_subtract) . " ) WHERE name = 'marking_unitsavailable'")) {
        F_display_db_error();
        exit('Db error');
    }

    return $transaction_connection->affected_rows > 0;
}

function there_is_enough_units_for_marking($num_answer_sheets)
{
    //efficiency of this dpends n a lot of things
    //We may be okay with db repository if we can guarantee complete headless server lockdpwn with
    //minimal access (i.e. only http port open to communicate ith the server)
    //Thus if user have no access to edit code files, nor alter db records, then we may get away
    //with mangin this only on server db level
    //However, if we cannot guarantee that, we can use encrupted file on the server to manage this
    //That also still requires us to ensure that user cannot edit/alter tfiles (i.e. minimal access)

    if (get_marking_units_left() >= $num_answer_sheets) {
        return true;
    } else {
        return false;
    }
}

function doCompositeFourPartPercentageUpdate($job_id, &$universal_counter, $update_freq, $loop_counter, $relative_total_count)
{
    //this function handles those that are part of a composite db progress update function
    //usually, we simply update percentage progress. Each part of this updater is usually part not more than 25%
    //Because of oter loops that are part of this composite update (hence the word "composite" in this function name).
    //This 25% cap explains the "four" in this function name. It means each part takes 25%, four of which makes
    //100% to make for cmpletion/determine completion.
    //The remianing component parts also take the same percentage upfate quota
    if ((++$universal_counter % $update_freq) == 0) {
        updateJobStatus($job_id, round((($loop_counter * 100) / $relative_total_count) / 4, 0), "percentage_progress");
    }
}

function notifyElapseTime($tag, $relative_microtime)
{
    echo "$tag took " . (microtime(true) - $relative_microtime) . " secs... <br /> \n";
}

function F_get_useable_image_base_on_scanner_type($image, $job_id, int $scannertype)
{

    require_once '../config/tce_config.php';
    global $global_debug_level_counter;
    // $global_debug_level_counter; //function calls inside this function also do their increments, so preserve ours

    $img = new Imagick();
    $img->readImage($image);
    $fname = basename($image);

    //no need to write any file here. Simply depend on the writings by the function called below so that debug output filename naming continuum will be maintained
    // write_debug_file($img, $job_id, "-{$global_debug_level_counter}." . ++$local_debug_level_counter . "-before-ensure-useable-scannertype", $fname);
    $img = F_ensureImageIsUseable($img, $job_id, $fname, $scannertype);

    write_debug_file($img, $job_id, "-" . ++$global_debug_level_counter . "-start-get-useable-based-on-scannertype", basename($image));

    $img->normalizeImage(Imagick::CHANNEL_ALL);
    $img->enhanceImage();
    $img->despeckleImage();
    $img->blackthresholdImage('#808080');
    $img->whitethresholdImage('#808080');
    $img->trimImage(85);
    $img->deskewImage(15);
    $img->trimImage(85);

    switch ($scannertype) {

        case ScannerTypes::DEFAULT_SCANNER:
            $img->resizeImage(1028, 1052, Imagick::FILTER_CUBIC, 1);
            break;

        case ScannerTypes::FAST_HP_SCANNER_GENERIC_MODE:
            //Imagick::cropImage ($width ,$height , int $x , int $y )
            $img->cropImage($img->getImageWidth(), $img->getImageHeight(), 79, 69);
            $img->setImagePage(0, 0, 0, 0);

            write_debug_file($img, $job_id, "-" . ++$global_debug_level_counter . "-after-crop-useable-scanadjust", $fname);

            $img->resizeImage(1028, 1052, Imagick::FILTER_CUBIC, 1);
            $img->setImagePage(0, 0, 0, 0);

            write_debug_file($img, $job_id, "-" . ++$global_debug_level_counter . "-after-resize-useable-scanadjust", $fname);

            break;

        case ScannerTypes::FAST_HP_SCANNER_300PPI:
            //Imagick::cropImage ($width ,$height , int $x , int $y )
            $img->cropImage($img->getImageWidth() + 100, $img->getImageHeight() + 100, 0, 33);
            $img->resizeImage(1028, 1052, Imagick::FILTER_CUBIC, 1);
            break;
    }

    $img->setImagePage(0, 0, 0, 0);
    write_debug_file($img, $job_id, "-" . ++$global_debug_level_counter . "-end-get-useable-based-on-scannertype", basename($image));
    return $img;
}

//============================================================+
// END OF FILE
//============================================================+
