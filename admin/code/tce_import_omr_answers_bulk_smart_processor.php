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

require_once '../config/tce_config.php';

$pagelevel       = K_AUTH_ADMIN_OMR_IMPORT;
require_once '../../shared/code/tce_authorization.php';

$thispage_title = $l['t_omr_answers_importer'];
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_omr.php';

// upload is done one-by-one so we do not clock the file upload limit

if (isset($_FILES)) {

    // STAGE 1:
    // 
    // *each file will carry a job_id and total_number_files
    // *with the job_id, we will use the total_number_files value to know the total files expected for the marking session
    // *upon receipt of a file, create a temporary tmp_smart_omr folder in the OMR directory
    // *inside tmp_smart_omr dir, mkdir for this job_id. Also create an entry in the db for this marking session, using the job_id as unique id
    // *inside tmp_smart_omr/job_id/; save all the files that belongs to this marking session until total_number_files reached 
    // *when total_number_files reached, update the status of the db entry as "files upload completed, commenced marking"
    // 
    if($_FILES['omrfile']['error'] == 0){
        $data = getOrCreateJob($_POST['job_id']);
        $dir = getJobFolder($_POST['job_id']);
        $name = basename($_FILES["pictures"]["name"][$key]);
        if(!move_uploaded_file( $_FILES['omrfile']['tmp_name'], "$dir/$name" )){
            exit("Could not process uploaded file location");
        }

        // -inside tmp_smart_omr/job_id/; save all the files that belongs to this marking session until total_number_files reached (dont foget to do `if ($_FILES['omrfile']['error'][$i] == 0)`). Also helps in processing is: `($answer_page_data['doc_type'] == "ANSWERS") || ($answer_page_data['doc_type'] == "USERID")`
        // -when total_number_files reached, update the status of the db entry as "files upload completed, commenced marking"
        if( $_POST['total_number_files'] == count( $job_files ) ){

            //strat procseeing the foles
            
            updateJobStatus($_POST['job_id'] , "Files upload successfully completed");

            // STAGE 2:
            // 
            // -iterate through tmp_smart_omr/job_id/ and map the data into an array: [ 'unique_user_id' => all_omr_file_names_including_id_page ]
            // -don't forget to update the db entry with status text of how far. Client-side js always poll the db for status (for performance reasons, we may be updating the db after processing n-records, rather than at every single iteration)
            // -When mapping complete, run each mapping through mark_student_answer($user_id, Array $omr_file_paths)
            // -Upon completion, update db entry as completed
            // -If any error occur at any point in the stps above, update db with the status, set db's error_col to true, and terminate the marking session

            $uploaded_data = [];
            $job_files = getJobFilepaths($_POST['job_id']);
            //extrapolate excpeted number of files
            $omr_testdata  = F_get_omr_testdata($_FILES['omrfile']['tmp_name'][1]);
            foreach ($job_files as $filepath) {
                $omr_testdata  = F_get_omr_testdata($_FILES['omrfile']['tmp_name'][1]);
            }

            //TCExam now reduces paper wastage by saving qrcode into db and encoding same on
            //answer sheets
            //use one of the uploaded files to get the qrcode
            $num_questions = (count($omr_testdata) - 1);
            $num_pages     = ceil($num_questions / 30);
            $omr_answers   = array();
            for ($i = 0; $i < count($_FILES['omrfile']['tmp_name']); ++$i) {
                // read OMR DATA page
                $data_file = $_FILES['omrfile']['tmp_name'][$i];
                $name      = $_FILES['omrfile']['name'][$i];
                if (!empty($name)) {
                    if ($_FILES['omrfile']['error'][$i] == 0) {
                        $answer_page_data = F_extract_code_data_from_answer_page($data_file);
                        if ($answer_page_data['doc_type'] == "ANSWERS") {
                            $answers_page = F_realDecodeOMRPage($data_file, $answer_page_data['start_number']);
                        } else {
                            if ($answer_page_data['doc_type'] == "USERID") {
                                $user_id = F_decodeIDentificationPage($data_file);
                                $user_id = intval(implode('', $user_id));
                                continue;
                            } else {
                                exit("Unidentified document: {$_FILES['omrfile']['name'][$i]}");
                            }
                        }
                        if (($answers_page !== false) and !empty($answers_page)) {
                            $omr_answers += $answers_page;
                        } else {
                            F_print_error('ERROR', '[OMR ANSWER SHEET ' . $i . '] ' . $l['m_omr_wrong_answer_sheet']);
                        }
                    } else {
                        F_print_error('ERROR', '[OMR ANSWER SHEET ' . $i . '] ' . $l['m_omr_wrong_answer_sheet']);
                    }
                }
            }
            // sort answers (it should have been already sorted though - we are simply indirectly sorting questions here (i.e. consequently, the anserws group attached to each question s soted together with that question as a single unit of sort \_(0)_/ ))
            ksort($omr_answers);

            // import answers
            if (F_importOMRTestData($user_id, $date, $omr_testdata, $omr_answers, true)) {
                $testuser_id = F_get_testuser_id($omr_testdata[0], $user_id);
                F_print_error('MESSAGE', $l['m_import_ok']
                    . ': <a href="tce_show_result_user.php?' .
                    ' testuser_id=' . $testuser_id
                    . '&test_id=' . $omr_testdata[0]
                    . '&user_id=' . $user_id
                    . '" title="' . $l['t_result_user']
                    . '" style="text-decoration:underline;color:#0000ff;">'
                    . $l['w_results'] . '</a>');
            } else {
                F_print_error('ERROR', $l['m_import_error']);
            }

            // do not remove uploaded files...for reference sake
        }else{
            //continue receviing file uploads
        }

    }else{
        exit('File error');
    }
}else{
    if (isset($_POST['getStatus'])) {
        exit(getStatusOfMarkingSession( $_POST['jobId'] ));
    }
}

function getStatusOfMarkingSession( $jobId ){
    global $db;
    if (!$r = F_db_query("SELECT status_text , percentage_progress FROM tce_smart_jobs WHERE job_id = '".F_escape_sql($db, $job_id)."'", $db)) {
        F_display_db_error();
        exit('Db error');
    }else{
        $data = F_db_fetch_array($r);
        exit([
            'status_text => '$data['status_text'],
            'percentage_progress => '$data['percentage_progress'],
        ]);
    }
}

function mark_student_answer($user_id, string $answersheet_id, Array $omr_file_paths, int $qrcode_db_id){
    //TCExam now reduces paper wastage by saving qrcode into db and encoding same on
    //answer sheets
    $omr_testdata  = F_get_omr_testdata_by_id($qrcode_db_id);
    $num_questions = (count($omr_testdata) - 1);
    $num_pages     = ceil($num_questions / 30);
    if(count($omr_file_paths['answer_sheets']) != $num_pages ){
        exit("Error: found " . count($omr_file_paths['answer_sheets']) . " out of {$num_pages} answer sheets expected for user with answer sheet id {$answersheet_id}");
    }

    $omr_answers   = array();
    for ($i = 0; $i < count($omr_file_paths['answer_sheets']); ++$i) {
        $answer_page_data = F_extract_code_data_from_answer_page($omr_file_paths['answer_sheets'][$i]);
        if ($answer_page_data['doc_type'] == "ANSWERS") {
            $answers_page = F_realDecodeOMRPage($data_file, $answer_page_data['start_number']);
        } 

        if (($answers_page !== false) and !empty($answers_page)) {
            $omr_answers += $answers_page;
        } else {
            F_print_error('ERROR', '[OMR ANSWER SHEET ' . $i . '] ' . $l['m_omr_wrong_answer_sheet']);
        }
    }

    // sort answers (it should have been already sorted though - we are simply indirectly sorting questions here (i.e. consequently, the anserws group attached to each question s soted together with that question as a single unit of sort \_(0)_/ ))
    ksort($omr_answers);

    // import answers
    if (F_importOMRTestData($user_id, $date, $omr_testdata, $omr_answers, true)) {
        return true;
    } else {
        F_print_error('ERROR', $l['m_import_error']);
    }
}

function getOrCreateJob($job_id)
{
    global $db;
    //see if the job_id in already in database
    if (!$r = F_db_query("SELECT job_id FROM tce_smart_jobs WHERE job_id = '".F_escape_sql($db, $job_id)."'", $db)) {
        F_display_db_error();
        exit('Db error');
    }

    $data = F_db_fetch_array($r);
    if( empty($data) ) {
      //instantiate this job
      if (!$r = F_db_query("INSERT INTO tce_smart_jobs (job_id , status_text) '".F_escape_sql($db, $job_id)."'", $db)) {
        F_display_db_error();
        exit('Db error');
      }

      //create the job's folder
      if(mkdir( getJobFolder($job_id) , 0777 , true )){
        return F_db_fetch_array($r);
      }else{
        exit("Could not initiate marking job");
      }
    }else{
        return $data;
    }
}

function getJobFolder($job_id){
    return K_PATH_CACHE . '/jobs/' . $job_id;
}

function getJobFilepaths($job_id){
    $files = [];
    $dir = getJobFolder($job_id);
    $cdir = scandir($dir); 
    foreach ($cdir as $key => $value) 
    { 
      if (!in_array($value,array(".",".."))) 
      { 
         $filePath = $dir . DIRECTORY_SEPARATOR . $value;
         if ( !is_dir( $filePath ) ) 
         { 
            $files[] = $filePath; 
         } 
      } 
    }
    return $files;
}

function updateJobStatus($job_id , $status)
{
    global $db;
    return F_db_query("UPDATE tce_smart_jobs status_text = '" . F_escape_sql($db, $status) . "' WHERE job_id = '" . F_escape_sql($db, $job_id) . "' ", $db)) {
}

function getJobFolder($job_id){

}

//============================================================+
// END OF FILE
//============================================================+
