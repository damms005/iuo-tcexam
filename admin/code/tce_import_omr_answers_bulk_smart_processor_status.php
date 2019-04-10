<?php
header('Access-Control-Allow-Origin: *');

//============================================================+
// File name   : tce_import_omr_answers_bulk_smart_processor.php
// Begin       : 2019-01-21
// Last Update : 2019-01-21
//
// Description : Check status of smart marking jobs using the job_id
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
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_smart_omr.php';

if( isset( $_REQUEST['getStatus'] ) ) {
    $status = getJobStatus( $_REQUEST['job_id']);
    //we follow this convention: starting with 'e:' means error. while startig with 's:' means it is a status meaasge
    if(is_array($status)){
        if(!empty($status['error_text']) || !empty($status['error']) ){
            exit("e:" . !empty($status['error_text']) ? $status['error_text'] : $status['status_text'] );
        }else{
            exit("s:" . json_encode( $status ) );
        }
    }else{
        //emptiness means non of the other file upload requests has had the chance to write to the db while this one is making his own request
        if(!empty( $status) ){
            exit('e:Server could not process update request - ' . $status );
        }
    }
}

//============================================================+
// END OF FILE
//============================================================+
