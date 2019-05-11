<?php
    header('Access-Control-Allow-Origin: *');

    require_once('../../config/tce_config.php');
    require_once('../../../shared/code/tce_functions_form.php');
    require_once('../../../shared/code/tce_functions_tcecode.php');
    require_once('../../../shared/code/tce_functions_test.php');
    require_once('../../../shared/code/tce_functions_auth_sql.php');

    //get the current marking session from the posted data
    echo json_encode($_POST);
    exit(json_encode($_FILES));
    // var_dump($_FILES['omrfiles']);
    

    function job_exists_in_database($job_id){
        global $db;
        job_id,
    }
?>
