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

// process uploaded files
if (isset($menu_mode) and ($menu_mode == 'upload') and !empty($_FILES)) {
    //TCExam now reduces paper wastage by saving qrcode into db and encoding same on
    //answer sheets
    //use one of the uploaded files to get the qrcode
    $omr_testdata  = F_get_omr_testdata($_FILES['omrfile']['tmp_name'][1]);
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
    // remove uploaded files
    for ($i = 0; $i < count($_FILES['omrfile']['tmp_name']); ++$i) {
        if ($_FILES['omrfile']['error'][$i] == 0) {
            @unlink($_FILES['omrfile']['tmp_name'][$i]);
        }
    }
}

// -----------------------------------------------------------------------------
echo '<link href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css" rel="stylesheet">';

echo '<div class="container">' . K_NEWLINE;
?>
<script src="../../shared/jscripts/socket.io-client/dist/socket.io.js"></script>
<script>
// var socket = io.connect("http://localhost:3000",{
//     secure: false,
//     rejectUnauthorized: false
// });

// socket.on('connect', function(msg) {
//     console.log('Connected to: ',console.dir( io));
//     socket.emit('setup_server_endpoint', {
//         auth_val: "api_token",
//     })
//     socket.on('server-connection-acknowledged', function (data) {
//       console.log('server-connection-acknowledged ' + data);
//       socket.emit('client-thanking-server',`connection connection-acknowledged: ${data}`)
//     })
// });
</script>
<?php
echo '<div class="tceformbox">' . K_NEWLINE;
echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="post" enctype="multipart/form-data" id="form_omrimport">' . K_NEWLINE;

// -----------------------------------------------------------------------------
// date
echo getFormRowTextInput('date', $l['w_date'], $l['w_date'] . ' ' . $l['w_datetime_format'], '', $date, '', 19, false, true, false);

// OMR ANSWER SHEET pages
for ($i = 1; $i < $max_omr_sheets; ++$i) {
    echo getFormUploadFile('omrfile[]', 'omrsheet' . $i, $l['w_omr_answer_sheet'] . ' ' . $i, '', 'document.getElementById(\'divomrsheet' . ($i + 1) . '\').style.display=\'block\';');
}
echo getFormUploadFile('omrfile[]', 'omrsheet' . $max_omr_sheets, $l['w_omr_answer_sheet'] . ' ' . $max_omr_sheets, '', '');

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
  <ul>
    <li v-for="file in files">{{file.name}} - Error: {{file.error}}, Success: {{file.success}}</li>
  </ul>
  <file-upload
    ref="upload"
    v-model="files"
    post-action="/post.method"
    put-action="/put.method"
    @input-file="inputFile"
    @input-filter="inputFilter"
  >
  Upload file
  </file-upload>
  <button v-show="!$refs.upload || !$refs.upload.active" @click.prevent="$refs.upload.active = true" type="button">Start upload</button>
  <button v-show="$refs.upload && $refs.upload.active" @click.prevent="$refs.upload.active = false" type="button">Stop upload</button>
</div>
<script>
    Vue.component('file-upload', VueUploadComponent);

new Vue({
  el: '#app',
  data: function () {
    return {
      files: []
    }
  },
  components: {
    FileUpload: VueUploadComponent
  },
  methods: {
    /**
     * Has changed
     * @param  Object|undefined   newFile   Read only
     * @param  Object|undefined   oldFile   Read only
     * @return undefined
     */
    inputFile: function (newFile, oldFile) {
      if (newFile && oldFile && !newFile.active && oldFile.active) {
        // Get response data
        console.log('response', newFile.response)
        if (newFile.xhr) {
          //  Get the response status code
          console.log('status', newFile.xhr.status)
        }
      }
    },
    /**
     * Pretreatment
     * @param  Object|undefined   newFile   Read and write
     * @param  Object|undefined   oldFile   Read only
     * @param  Function           prevent   Prevent changing
     * @return undefined
     */
    inputFilter: function (newFile, oldFile, prevent) {
      if (newFile && !oldFile) {
        // Filter non-image file
        if (!/\.(jpeg|jpe|jpg|gif|png|webp)$/i.test(newFile.name)) {
          return prevent()
        }
      }

      // Create a blob field
      newFile.blob = ''
      let URL = window.URL || window.webkitURL
      if (URL && URL.createObjectURL) {
        newFile.blob = URL.createObjectURL(newFile.file)
      }
    }
  }
});
</script>


<div class="border m-6 rounded-lg bg-white mx-auto shadow-lg max-w-xs overflow-hidden">
        <img class="h-24 min-w-full block" src="https://png.pngtree.com/thumb_back/fh260/back_pic/00/02/62/305619b17d2530d.jpg" />
        <div class="px-4 py-3 relative min-h-3">
            <div class="sm:flex sm:items-center">
                <img class="w-16 border-4 border-white border-white mr-3 rounded-full" src="https://avatars3.githubusercontent.com/u/13323281?s=460&v=4" />
                <div class="w-full">
                    <button class="float-right text-xs font-semibold rounded-full px-4 py-1 leading-normal bg-white border border-blue text-blue hover:bg-blue hover:text-white">Follow</button>
                </div>
            </div>
            <div class="mt-2 text-center sm:text-left sm:flex-grow">
                <div class="mb-4">
                    <p class="text-xl font-bold leading-tight">Ezeugwu Paschal</p>
                    <p class="text-sm leading-tight text-grey-dark">@paschaldev</p>
                </div>
                <div>
                    <p class="leading-tight text-grey-darkest text-sm">
                        This is a cool profile card showcasing the awesomeness of <a class="text-blue no-underline" href="https://tailwindcss.com">Tailwindcss</a> built by awesome people who want to make the web a better place.
                    </p>
                </div>
                <p class="mt-6 text-xs text-grey-dark">
                    Followed by <a class="text-blue no-underline" href="#">Google</a> and <a class="text-blue no-underline" href="5 others">5 others</a>
                </p>
            </div>
        </div>
    </div>

<?php

require_once '../code/tce_page_footer.php';

//============================================================+
// END OF FILE
//============================================================+
