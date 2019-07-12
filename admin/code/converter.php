<?php

require_once '../../shared/code/tce_authorization.php';

$thispage_title = $l['t_question_importer'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

if (true) {
    ?>

<script src="../../shared/jscripts/jquery.js"></script>
<script src="../../shared/jscripts/tinymce/js/tinymce/tinymce.min.js"></script>

<script>

$(function(){
    tinymce.init({
        selector: 'pre#importedExcel',
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
});

function validateTinymceEdit(tinymceData) {

    //ensure that user does not mess-up our conversion due to tinymce editing
    //We will be guided by the following rules:
    //1. Line index[0] should start with "M=", and when we split by '\t', the resulting array should have a length of 3
    //2. Line index[1] should start with "S=", and when we split by '\t', the resulting array should have a length of 4
    //3. Line index[2] should start with "Q=", and when we split by '\t', the resulting array should have a length of 11
    //4. Line index[3] should start with "A=", and when we split by '\t', the resulting array should have a length of 7
    //5. Line index[4] should be empty
    //6. Any line that starts with 'A', when we split by '\t', the resulting array should have a length of 11
    //7. Any line that starts with 'Q', when we split by '\t', the resulting array should have a length of 11
    //any other line that does not fit into any of the above is an error

    let lines = tinymceData.split(/\n/);

    for (let index = 0; index < lines.length; index++) {

        switch (index) {
            case 0:
            if( !assertStartsWith( lines[index] , 'M=', index) || !assertSplittedLengthIs( lines[index] , 3, index ) ) {
                return false;
            }
            break;

            case 1:
            if( !assertStartsWith( lines[index] , 'S=', index) || !assertSplittedLengthIs( lines[index] , 4, index ) ) {
                return false;
            }
            break;

            case 2:
            if( !assertStartsWith( lines[index] , 'Q=', index) || !assertSplittedLengthIs( lines[index] , 11, index ) ) {
                return false;
            }
            break;

            case 3:
            if( !assertStartsWith( lines[index] , 'A=', index) || !assertSplittedLengthIs( lines[index] , 7, index ) ) {
                return false;
            }
            break;

            case 4:
            if(lines[index].trim().length > 0){
                alert(`Line at zero-index ${index} with content (${lines[index]}) should be empty` );
            }
            break;

            default:
            //6. Any line that starts with 'A', when we split by '\t', the resulting array should have a length of 11
            //7. Any line that starts with 'Q', when we split by '\t', the resulting array should have a length of 11
            //any other line that does not fit into any of the above is an error
            break;
        }
    }

    return true;

}

function assertStartsWith(subject, startString, lineNumber){
    if(subject.startsWith(startString)){
        return true;
    }else{
        alert(`Line at zero-index ${lineNumber} with content (${subject}) should start with the string "${startString}"` );
        return false;
    }
}

function assertSplittedLengthIs( subject , length , lineNumber ){
    let splitLength = subject.split("\t").length;
    if( splitLength == length ){
        return true;
    }else{
        alert(`Line at zero-index ${lineNumber} with content (${subject}) should have a tab-splitted length of "${length}" instead of the current ${splitLength}` );
        return false;
    }
}

function doSubmission() {
    document.getElementById( 'transfer_box' ).innerHTML = document.getElementById( 'importedExcel' ).innerHTML;
    document.getElementById( 'finalForm' ).submit();
}
</script>

<form enctype="multipart/form-data" action="converter.php" method="POST">
    <!-- MAX_FILE_SIZE must precede the file input field -->
    <input type="hidden" name="MAX_FILE_SIZE" value="300000" />

    <span class="formw">
    <input type="text" value="<?php echo @$_POST['course_code']; ?>" name="course_code" placeholder="course code" style="text_transform: uppercase" />
    <input type="text" value="<?php echo @$_POST['course_title_description']; ?>" name="course_title_description" value="" placeholder="course title and/or description"  style="text_transform: uppercase"/>
    </span>
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
            echo "<pre>";
            print_r($cols);
            echo "</pre>";
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
                if (in_array($cols[6], ['true', 'false']) === false) {
                    exit("Row " . ($z_index_line_number + 1) . ", subjective question type, should have correct option specified in column 7");
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
                $tcexam_data[] = array('A', '1', 'true', '', (strtolower($each_question[6]) == 'true' ? '1' : '0'), '', '', '', '', '', '');
                $tcexam_data[] = array('A', '1', 'false', '', (strtolower($each_question[6]) == 'false' ? '1' : '0'), '', '', '', '', '', '');
                break;

            default:
                exit('No questio type defined');
        }
    }

    echo "
        <br />
        <br />
        <input value='Import' type='button' class='styledbutton' onclick='doSubmission()' />
        <br />
        <br />
        <form id='finalForm' method='post' action='converter.php'>
        <textarea width='1' height='1' style='width: 1px; height: 1px' id='transfer_box' name='transfer_box'></textarea>
        <pre id='importedExcel'>";

    foreach ($tcexam_data as $eacline) {
        echo implode("\t", $eacline) . "\n";
    }

    echo "
        </pre>
        <br />
        <br />
        <input value='Import' type='button'  class='styledbutton' onclick='doSubmission()' />
        <br />
        <br />
        </form>
        ";

} else {
    if (isset($_POST['transfer_box'])) {
        ?>

        <form id="transferForm" enctype="multipart/form-data" action="tce_import_questions.php" method="POST" >
        <textarea width='1' height='1' style="width: 1px; height: 1px" name="uploadable_module" >
        <?php echo replace_html_tags_with_tinymce_tages($_POST['transfer_box']); ?>
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

function replace_html_tags_with_tinymce_tages($stuff)
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
