<?php
//============================================================+
// File name   : tce_import_omr_answers.php
// Begin       : 2011-05-20
// Last Update : 2014-05-14
//
// Description : Import test answers using OMR (Optical Mark Recognition)
//               technique applied to images of scanned answer sheets.
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
//    Copyright (C) 2004-2013 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Import test answers using OMR (Optical Mark Recognition) technique applied to images of scanned answer sheets.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2011-05-20
 */

/**
 */

require_once '../config/tce_config.php';

$pagelevel       = K_AUTH_ADMIN_OMR_IMPORT;
$enable_calendar = true;
$max_omr_sheets  = 10;
require_once '../../shared/code/tce_authorization.php';

$thispage_title = '';
require_once 'tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_omr.php';
require_once 'tce_functions_user_select.php';

if (isset($_REQUEST['user_id'])) {
    $user_id = intval($_REQUEST['user_id']);
    if (!F_isAuthorizedEditorForUser($user_id)) {
        F_print_error('ERROR', $l['m_authorization_denied']);
        exit;
    }
} else {
    $user_id = 0;
}
if (isset($_REQUEST['date'])) {
    $date      = $_REQUEST['date'];
    $date_time = strtotime($date);
    $date      = date(K_TIMESTAMP_FORMAT, $date_time);
} else {
    $date = date(K_TIMESTAMP_FORMAT);
}

?>

<!-- now vue part -->

<link rel="stylesheet" href="./smart-omr/dist/css/app.4387946c.css">
<link rel="stylesheet" href="./smart-omr/dist/css/chunk-vendors.427e74fb.css">

<style>
.main-vue-app{
  /* border:2px solid red; */
}
</style>

<div id="content-holder" style="position:absolute;  width:100%; height: 100%">
  <div id="app">
  </div>
</div>

<script src="./smart-omr/dist/js/chunk-vendors.31203818.js"></script>
<script src="./smart-omr/dist/js/app.827ab7a4.js"></script>

<script>
    document.getElementsByClassName("header")[0].style.display="none";
</script>

<?php

require_once '../code/tce_page_footer.php';

//============================================================+
// END OF FILE
//============================================================+
