<?php
header('Access-Control-Allow-Origin: *');
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

use Symfony\Component\Filesystem\Filesystem;

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_omr.php';
require_once 'tce_functions_user_select.php';

// upload is done one-by-one so we do not clock the file upload limit
// Report all errors except E_NOTICE
error_reporting(E_ALL & ~E_NOTICE);

$global_debug_level_counter = 0;

if (!empty($_FILES)) {

    // *each file will carry a job_id and total_number_files
    // *with the job_id, we will use the total_number_files value to know the total files expected for the marking session
    // *upon receipt of a file, create a temporary tmp_smart_omr folder in the OMR directory
    // *inside tmp_smart_omr dir, mkdir for this job_id. Also create an entry in the db for this marking session, using the job_id as unique id
    // *inside tmp_smart_omr/job_id/; save all the files that belongs to this marking session until total_number_files reached
    // *when total_number_files reached, update the status of the db entry as "files upload completed, commenced marking"

    if (isset($_FILES['omrfile']) && $_FILES['omrfile']['error'] == 0) {

        if (empty(trim($_POST['job_id']))) {
            exit("Job id not set");
        }

        // sleep(rand(892,997));
        //get symfony Filesystem
        require_once __DIR__ . '/smart-omr/vendor/autoload.php';
        $fileSystem                        = new Filesystem();
        $job_id                            = $_POST['job_id'];
        $job_dir                           = getJobFolder($job_id);
        $smart_omr_marker_control_filepath = realpath(__DIR__ . "/../../") . "/cache/smart-omr-marker-control-file.lock";

        if (!ensureJobExistsInDb($job_id)) {
            endMarkingSessionWithError($job_id, "Could not initialize job: {$job_id}");
        }

        $name              = basename($_FILES["omrfile"]["name"]);
        $job_db_primarykey = intval(getJobDbPrimaryKey($job_id));

        if (!is_uploaded_file($_FILES['omrfile']['tmp_name'])) {
            endMarkingSessionWithError($job_id, "Uploaded file error: {$_FILES['omrfile']['tmp_name']}");
        }

        //reduce time wasted on uneccesarily large files. Theretically, the higher the resolution, the easier it is to comute accurately. But in the real wolrld, at least as it applies to us, it doesn't make any differnce other than the fact that it takes longer to achieve same resul
        //copy appropriately, creating needed directories as requiredt
        $newpath = "$job_dir/$name";
        $fileSystem->copy($_FILES['omrfile']['tmp_name'], $newpath, true);
        F_ensure_optimum_size($job_id, $newpath);

        //now that we have saved this file, get it, alongside those that were uploaded prior
        $job_files = getJobFilepaths($job_id);

        //we need to atomize here because we want to make it impossible for user to make
        //triggering concurrent running at this stage.
        session_start();
        $_SESSION['job_id'] = $job_id;

        if ($_POST['total_number_files'] == count($job_files)) {

            if (!all_required_files_uploaded($_POST["job_id"])) {
                updateJobStatus($job_db_primarykey, 1, "all_required_files_uploaded");
                updateJobStatus($job_db_primarykey, "Files upload completed successfully [$name]");
            } else {
                exit("Exited: concurrent marking session prevented");
            }

            session_write_close();

            //we are not marking immediately now because we want client to get response of file uploads immediately
            //If not, client will wait long for this processing to be copleted - client needs to make UI adjustments
            //based on if all files are uploaded, etc
            exit("completed...will start marking on client's prompt");

        } else {
            //continue receiving file uploads
            exit("expecting more data (" . count($job_files) . " != " . $_POST['total_number_files'] . ") -> [$name]");
        }
    } else {
        //do not show this because browser does a pre-flight request to this script before making our
        //normal request, thus creating unecesaary xhr entries in Network tab of debug
        //window (we just want to avoid clusters/noise in debug window coz of preflifhgt requests)
        // exit('File error');
    }
}

if (isset($_POST['all_available_files_uploaded'])) {

    //Client sends us this so that server can set some specific error meesaages if need be

    //if it is client telling us that he has finished uploading all the supplied files
    //and server still wants more, then let client know that there is an error, as you
    //cannot just say you done when we still expect more!

    // $status = getJobStatus($_POST['job_id']);
    // endMarkingSessionWithError( $_POST['job_id'], "You have some files not yet uploaded. Please fix those and re-upload" );

}

if (isset($_POST['startProessingUploadedScripts'])) {
    $job_files         = getJobFilepaths($_POST['job_id']);
    $job_db_primarykey = intval(getJobDbPrimaryKey($_POST['job_id']));
    if ($job_files) {
        exit(startProcessing($_POST["job_id"], $job_db_primarykey, $job_files));
    } else {
        exit("No job files found");
    }
}

//============================================================+
// END OF FILE
//============================================================+
