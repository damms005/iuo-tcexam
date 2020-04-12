<?php

require_once '../../shared/code/tce_authorization.php';

$thispage_title = $l['t_question_importer'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

if (true) {
    ?>

<script src="../../public/js/jquery.js"></script>
<script src="../../shared/jscripts/tinymce/js/tinymce/tinymce.min.js"></script>
<script>

tinymce.init({
  selector: 'pre.imported',
  inline: true,
  menubar: false,

  height: 300,
  max_height: 350,

  plugins: "legacyoutput charmap code ",

//   visualaid,
//   removeformat, formats
//   cut, copy, paste, selectall, ,

  toolbar: 'undo redo | bold italic underline | subscript superscript strikethrough | link image | charmap code'
});


function doSubmission() {
    document.getElementById( 'transfer_box' ).innerHTML = document.getElementById( 'inHouse' ).innerHTML;
    document.getElementById( 'finalForm' ).submit();
}
</script>

<style>
    #final_transfer_form{
        width:  0px;
        height: 0px;
    }
</style>

<form enctype="multipart/form-data" action="converter.php" method="POST">
    <!-- MAX_FILE_SIZE must precede the file input field -->
    <input type="hidden" name="MAX_FILE_SIZE" value="300000" />

    <input type="text" value="<?php echo isset($_POST['course_code']) ? $_POST['course_code'] : ''; ?>" name="course_code" placeholder="course code" style="text_transform: uppercase" />
    <input type="text" value="<?php echo isset($_POST['course_title_description']) ? $_POST['course_title_description'] : ''; ?>" name="course_title_description" value="" placeholder="course title and/or description"  style="text_transform: uppercase"/>
    <!-- Name of input element determines name in $_FILES array -->
    Send this file: <input name="questions_file" type="file" />
    <input type="submit" value="Upload" />
</form>

<?php
}

//now handle uploaded file if any

if (isset($_FILES['questions_file'])) {

    require __DIR__ . "/../../vendor/autoload.php";

    /** Load $inputFileName to a Spreadsheet Object  **/
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['questions_file']['tmp_name']);
    if ($spreadsheet->getSheetCount() < 1) {
        exit("No sheets found in the Excel file");
    }

    // $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    $sheetData = $spreadsheet->getSheet(0)->toArray(null, true, false, false);

    // Get a file into an array.  In this example we'll go through HTTP to get
    // the HTML source of a URL.
    // $lines = file($_FILES['questions_file']['tmp_name']);
    $lines = convertPHPExcelToLegacy($sheetData);

    $conformed     = true;
    $allowed_types = ['s', 'o', 't'];
    $parsed_line   = [];

    for ($z_index_line_number = 0; $z_index_line_number < count($lines); $z_index_line_number++) {
        $this_line = trim($lines[$z_index_line_number]);
        $this_line = htmlspecialchars($this_line, ENT_SUBSTITUTE | ENT_DISALLOWED);

        $cols = explode("\t", $this_line);
        $cols = array_map("trim_it", $cols);

        if ($z_index_line_number == 0) {
            if (count($cols) < 7) {
                echo "<pre>";
                print_r($cols);
                echo "</pre>";
                exit('First row does not contain appropriate headers: e.g. questions and options headers [' . count($cols) . ' cols in "' . implode('-', $cols) . '"]');
            }

            continue;
        }

        //confirm col2 is either s, o, or t (true/false)
        if (count($cols) > 1) {
            $cols[1] = trim(strtolower($cols[1]));
            if (in_array($cols[1], $allowed_types) === false) {
                exit("Row " . ($z_index_line_number + 1) . " does not have correct question type. Supplied question type: '{$cols[1]}' Allowed question types: " . implode(',', $allowed_types));
            }
        } else {
            if (empty(implode('', $cols))) {
                continue;
            } else {
                exit("Row " . ($z_index_line_number + 1) . " does not have enough columns");
            }
        }

        //purify question: remove () as in subjective, and strlen must be more than 5 chars
        $cols[0] = str_replace('()', '_____________', $cols[0]);
        if (strlen($cols[0]) < 5) {
            exit("Row " . ($z_index_line_number + 1) . " does not have correct question structure (question was less than 5 chars). It is just " . strlen($cols[0]) . " chars [ $this_line ({$cols[0]}) ]");
        }

        if (count($cols) < 7) {
            clear_print( $cols );
            exit("Row " . ($z_index_line_number + 1) . " does not have up to 7 columns");
        }

        switch ($cols[1]) {
            case 'o':
                //confirm col3-6 filled with option
                if (strlen($cols[2]) < 1) {
                    exit("Row " . ($z_index_line_number + 1) . " is specified as objective question, but it does not have option1 defined ({$cols[2]}) " . " [ $this_line ({$cols[0]}) ]");
                }
                //confirm col3-6 filled with option
                if (strlen($cols[3]) < 1) {
                    report_line($cols, 3);
                    exit("Row " . ($z_index_line_number + 1) . " is specified as objective question, but it does not have option2 defined" . " [ $this_line ({$cols[3]}) ]");
                }
                //confirm col3-6 filled with option
                if (strlen($cols[4]) < 1) {
                    exit("Row " . ($z_index_line_number + 1) . " is specified as objective question, but it does not have option3 defined" . " [ $this_line ({$cols[0]}) ]");
                }
                //confirm col3-6 filled with option
                if (strlen($cols[5]) < 1) {
                    exit("Row " . ($z_index_line_number + 1) . " is specified as objective question, but it does not have option4 defined" . " [ $this_line ({$cols[0]}) ]");
                }

                //confirm col7 is either a-d
                $cols[6] = trim(strtolower($cols[6]));
                if (in_array($cols[6], ['a', 'b', 'c', 'd']) === false) {
                    exit("Row " . ($z_index_line_number + 1) . ", objective question type, can only have options set as a,b,c, or d. The supplied option: '{$cols[6]}' is invalid");
                }
                break;

            case 's':
                //col3-6 must be empty
                if (strlen($cols[2]) > 0 || strlen($cols[3]) > 0 || strlen($cols[4] > 0)) {
                    exit("Row " . ($z_index_line_number + 1) . " is specified as subjective question, but options are supplied for it. Only answer should be supplied to subjective questions, in column 7");
                }
                //col7 should contain correct answer, semi-colon or comma separated
                $cols[6] = strtolower($cols[6]);
                if (strlen($cols[6]) < 1) {
                    exit("Row " . ($z_index_line_number + 1) . ", subjective question type, should have correct option specified in column 7");
                }
                break;

            case 't':
                //col3-6 must be empty
                if (strlen($cols[2]) > 0 || strlen($cols[3]) > 0 || strlen($cols[4]) > 0) {
                    exit("Row " . ($z_index_line_number + 1) . " is specified as 'true or false' type, but options are supplied for it. Only specify 'TRUE' or 'FALSE' in column 7");
                }
                //col7 should contain correct 'true' or 'false'. tcexam marks case insensitively
                $cols[6] = trim(strtolower($cols[6]));
                if (!in_array($cols[6], ['true', 'false', '0', '1']) ) {
                    clear_print( $cols );
                    exit("Row " . ($z_index_line_number + 1) . ", true/false question type, should have correct option specified in column 7");
                }
                break;
        }

        $parsed_line[] = $cols;
    }

    //reached here means well parsed
    //format it for tcexam
    $tcexam_data = [
        array('M=MODULE', 'module_enabled', 'module_name'),
        array('S=SUBJECT', 'subject_enabled', 'subject_name', 'subject_description'),
        array('Q=QUESTION', 'question_enabled', 'question_description', 'question_explanation', 'question_type', 'question_difficulty', 'question_position', 'question_timer', 'question_fullscreen', 'question_inline_answers', 'question_auto_next'),
        array('A=ANSWER	answer_enabled', 'answer_description', 'answer_explanation', 'answer_isright', 'answer_position', 'answer_keyboard_key'),
        array(''),
        array('M', '1', 'default'),
        array('S', '1', $_POST['course_code'], $_POST['course_title_description']),
    ]; //add row 1

    foreach ($parsed_line as $each_question) {
        $tcexam_data[] = array('Q', '1', $each_question[0], '', get_answer_type($each_question[1]), '1', '', '0', '0', '0', '0');

        switch ($each_question[1]) {
            case 'o':
                for ($i = 2; $i <= 5; $i++) {
                    $tcexam_data[] = array('A', '1', $each_question[$i], '', decide_correct_obj($i, $each_question[6]), '', '', '', '', '', '');
                }
                break;

            case 's':
                $allowed_options = preg_split("/[,;]/", $each_question[6], null, PREG_SPLIT_NO_EMPTY);
                $allowed_options = array_map("trim_it", $allowed_options);
                for ($i = 0; $i < count($allowed_options); $i++) {
                    $tcexam_data[] = array('A', '1', $allowed_options[$i], '', 1, '', '', '', '', '', '');
                }
                break;

            case 't':
                $tcexam_data[] = array('A', '1', 'true', '', ( in_array( strtolower($each_question[6]) , ['true','1'] ) ? '1' : '0'), '', '', '', '', '', '');
                $tcexam_data[] = array('A', '1', 'false', '', ( in_array( strtolower($each_question[6] ) , ['false','0'] ) ? '1' : '0'), '', '', '', '', '', '');
                break;

            default:
                exit('No questio type defined');
        }
    }

    echo "
        <br />
        <br />
        <input value='Import' type='button' onclick='doSubmission()' />
        <br />
        <br />
        <form id='finalForm' method='post' action='converter.php'>
        <textarea width='1' height='1' style='width: 1px; height: 1px' id='transfer_box' name='transfer_box'></textarea>
        <pre class='imported' id='inHouse'>";

    foreach ($tcexam_data as $eacline) {
        echo implode("\t", $eacline) . "\n";
    }

    echo "
        </pre>
        <br />
        <br />
        <input value='Import' type='button' onclick='doSubmission()' />
        <br />
        <br />
        </form>
        ";

} else {
    if (isset($_POST['transfer_box'])) {
        ?>

        <form id="transferForm" enctype="multipart/form-data" action="tce_import_questions.php" method="POST" >
        <textarea id="final_transfer_form" name="uploadable_module" >
        <?php echo replace_html_tags_with_tinymce_tags($_POST['transfer_box']); ?>
        </textarea>
        </form>

        <br>
        <br>
        <div>
            Loading...
        </div>
        <br>
        <br>

        <script>
            document.getElementById("transferForm").submit();
        </script>

        <?php

        exit;
    }
}

function trim_it($v)
{
    return trim($v);
}

function convertPHPExcelToLegacy($sheetData)
{
    $lines = [];
    foreach ($sheetData as $columnInex => $rowContent) {
        $thisLine = [];
        foreach ($rowContent as $value) {
            //handle true/false
            if (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            }
            $thisLine[] = (string) $value;
        }
        $lines[] = implode("\t", $thisLine);
    }

    return $lines;
}

function get_answer_type($question_type)
{
    // echo "<pre>";
    // var_dump('checking return for '.$question_type);
    // echo "</pre>";
    switch ($question_type) {
        case 'o':
        case 't': //true or false
            return "S";
            break;

        case 's':
            return "T";
            break;

    }
}

function decide_correct_obj($col_index, $correct_option)
{
    $options_map = array(
        2 => 'a',
        3 => 'b',
        4 => 'c',
        5 => 'd',
    );

    if ($options_map[$col_index] == $correct_option) {
        return 1;
    } else {
        return 0;
    }
}

function report_line($cols, $index)
{
    echo "<pre>{";
    var_dump($cols);
    echo " [ observed data: {$cols[$index]} ] ";
    echo "}</pre>";
}

function replace_html_tags_with_tinymce_tags($stuff)
{
    $tcexam_html_tags_map = [
        'b'   => ['b', 'strong'],
        'i'   => ['i', 'em'],
        'u'   => ['u'],
        's'   => ['strike'],
        'sup' => ['sup'],
        'sub' => ['sub'],
    ];

    foreach ($tcexam_html_tags_map as $tnymce_tag => $html_tags) {
        foreach ($html_tags as $html_tag) {

            //replace the open and close tags
            $stuff = str_ireplace("<{$html_tag}>", "[{$tnymce_tag}]", $stuff);
            $stuff = str_ireplace("</{$html_tag}>", "[/{$tnymce_tag}]", $stuff);

        }
    }

    return $stuff;
}

function clear_print($data){
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
}