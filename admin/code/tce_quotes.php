<?php
header('Access-Control-Allow-Origin: *');

require_once '../config/tce_config.php';

$thispage_title = $l['t_omr_answers_importer'];
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_omr.php';

//example lng quote: we should never pull anything this long: [243 chars]
//Hardboiled crime fiction came of age in 'Black Mask' magazine during the Twenties and Thirties. Writers like Dashiell Hammett and Raymond Chandler learnt their craft and developed a distinct literary style and attitude toward the modern world.
//examle of good for maximum: [96 chars]
//What I don't like is breakfast in the morning. I have a double-espresso cappuccino, but no food.

// $query = "SELECT quote , author , genre FROM quotes WHERE ( length(quote) < 20 ) ORDER BY RAND() LIMIT 100 ";
$query = "SELECT quote , author , genre FROM quotes WHERE ( length(quote) < 100 ) ORDER BY RAND() LIMIT 100 ";
// $query = "SELECT quote , author , genre FROM quotes WHERE ( length(quote) > 100 ) ORDER BY RAND() LIMIT 100 ";
// $query = "SELECT quote , author , genre FROM quotes WHERE length(quote) > 15 && ( length(quote) < 25 ) ORDER BY RAND() LIMIT 100 ";
if ( !$r = F_db_query( $query ,  $db ) ) {
    F_display_db_error();
    exit('Db error');
}

$quotes = array();
while($data = F_db_fetch_array($r)){
    $quotes[] = [
        "quote" => $data['quote'] , 
        "author" => $data['author'] ,
        "genre" => $data['genre'] ,
    ];
}

echo json_encode($quotes);
?>
