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

$thispage_title = $l['t_omr_answers_importer'];
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
<?php
echo '<div class="tceformbox">' . K_NEWLINE;
echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="post" enctype="multipart/form-data" id="form_omrimport">' . K_NEWLINE;

// -----------------------------------------------------------------------------
// date
echo getFormRowTextInput('date', $l['w_date'], $l['w_date'] . ' ' . $l['w_datetime_format'], '', $date, '', 19, false, true, false);

// -----------------------------------------------------------------------------

echo '<div class="row">' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
// show upload button
F_submit_button('upload', $l['w_upload'], $l['h_submit_file']);
echo '</div>' . K_NEWLINE;

echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

// hide unused file upload fields
echo '<script type="text/javascript">' . K_NEWLINE;
echo '//<![CDATA[' . K_NEWLINE;
echo 'for (i=2; i<=' . $max_omr_sheets . '; i++) {document.getElementById(\'divomrsheet\'+i).style.display=\'none\';}' . K_NEWLINE;
echo '//]]>' . K_NEWLINE;
echo '</script>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_omr_answers_importer'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

?>


  <meta charset="utf-8">
  <title>Vue-upload-component Test</title>
  <script src="https://unpkg.com/vue"></script>
  <script src="https://unpkg.com/vue-upload-component"></script>

<body>

<div id="app">

</div>
<script src=""></script>

<?php

require_once '../code/tce_page_footer.php';

//============================================================+
// END OF FILE
//============================================================+
