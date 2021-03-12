<?php
//============================================================+
// File name   : tce_import_users.php
// Begin       : 2006-03-17
// Last Update : 2018-11-29
//
// Description : Import users from an XML file or tab-delimited
//               TSV file.

//import std records
//convert existing question format to tcexam's
//
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
//    Copyright (C) 2004-2012  Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Import users from an XML file or TSV (Tab delimited text file).
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-17
 */

/**
 */

require_once '../config/tce_config.php';
ini_set('max_execution_time', 1800);

$pagelevel = K_AUTH_IMPORT_USERS;
require_once '../../shared/code/tce_authorization.php';

$thispage_title = $l['t_user_importer'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';

switch ($menu_mode) {
	case 'upload':{
			if ($_FILES['userfile']['name']) {
				require_once '../code/tce_functions_upload.php';
				// upload file
				$uploadedfile = F_upload_file('userfile', K_PATH_CACHE);
				if ($uploadedfile !== false) {
					switch ($file_type) {
						case 1:{
								$xmlimporter = new XMLUserImporter(K_PATH_CACHE . $uploadedfile);
								F_print_error('MESSAGE', $l['m_importing_complete']);
								break;
							}
						case 2:{
								if (F_import_tsv_users(K_PATH_CACHE . $uploadedfile)) {
									F_print_error('MESSAGE', $l['m_importing_complete']);
								}
								break;
							}
					}
				}
			}
			break;
		}

	default:{
			break;
		}
}; //end of switch
?>

<style>
form {
    margin-bottom: 150px;
}
.details-table td{
    vertical-align: top;
}

code, pre{
    font-size: 11px;
}
</style>

<div class="container">

<div class="tceformbox">
	<table class="details-table">
		<tr>
			<td>
				<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="post" enctype="multipart/form-data" id="form_importusers">
				<div class="row">
				<span class="label">
				<label for="userfile"><?php echo $l['w_upload_file']; ?></label>
				</span>
				<span class="formw">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo K_MAX_UPLOAD_SIZE; ?>" />
				<input type="file" name="userfile" id="userfile" size="20" title="<?php echo $l['h_upload_file']; ?>" />
				</span>
				&nbsp;
				</div>

				<div class="row">
				<div class="formw">
				<fieldset class="noborder">
				<legend title="<?php echo $l['h_file_type']; ?>"><?php echo $l['w_type']; ?></legend>

				<input type="radio" name="file_type" id="file_type_tsv" value="2" checked="checked" title="<?php echo $l['h_file_type_tsv']; ?>" />
				<label for="file_type_tsv">TSV</label>

				<br />

				<input type="radio" name="file_type" id="file_type_xml" value="1" title="<?php echo $l['h_file_type_xml']; ?>" />
				<label for="file_type_xml">XML</label>
				</fieldset>
				</div>
				</div>

				<div class="row">
                    <?php
// show buttons by case
F_submit_button("upload", $l['w_upload'], $l['h_submit_file']);
echo F_getCSRFTokenField() . K_NEWLINE;
?>
                    <span>Note that any existing user (matched by any of user_name, user_regnumber, or user_ssn) will be updated with the new data. Password field is updated only if it is specified (i.e. you cannot "remove" user password by simply specifying blank value in the uploaded file)</span>
				</div>
				</form>
			</td>
		</tr>
		<tr>
			<td>
				<code>
					Note: If you are uploading TSV, only plain, lean TSV file is allowed
				</code>
				<br>
				<pre style="text-align:left">
					<?php echo $import_instructions; ?>
				</pre>
			</td>
			<td>
				<pre style="text-align:left">Allowed year_levels ('as is')<?php print_r($year_level);?></pre></td>
		</tr>
		<tr>
			<td>
				<pre style="text-align:left">Allowed colleges ('as is')<?php print_r(get_colleges());?></pre>
			</td>
			<td>
				<pre style="text-align:left">Allowed departments ('as is')<?php print_r(get_departments());?></pre>
            </td>
		</tr>
	</table>
</div>
<?php

echo '<div class="pagehelp">' . $l['hp_import_xml_users'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

// ------------------------------------------------------------

/**
 * @class XMLUserImporter
 * This PHP Class imports users and groups data directly from a XML file.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni [www.tecnick.com]
 * @version 1.0.000
 */
class XMLUserImporter
{

	/**
	 * String Current data element.
	 * @private
	 */
	private $current_element = '';

	/**
	 * String Current data value.
	 * @private
	 */
	private $current_data = '';

	/**
	 * Array Array for storing user data.
	 * @private
	 */
	private $user_data = array();

	/**
	 * Array for storing user's group data.
	 * @private
	 */
	private $group_data = array();

	/**
	 * Int ID of last inserted user (counter)
	 * @private
	 */
	private $user_id = 0;

	/**
	 * String XML file
	 * @private
	 */
	private $xmlfile = '';

	/**
	 * Class constructor.
	 * @param $xmlfile (string) XML file name
	 */
	public function __construct($xmlfile)
	{
		// set xml file
		$this->xmlfile = $xmlfile;
		// creates a new XML parser to be used by the other XML functions
		$this->parser = xml_parser_create();
		// the following function allows to use parser inside object
		xml_set_object($this->parser, $this);
		// disable case-folding for this XML parser
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
		// sets the element handler functions for the XML parser
		xml_set_element_handler($this->parser, 'startElementHandler', 'endElementHandler');
		// sets the character data handler function for the XML parser
		xml_set_character_data_handler($this->parser, 'segContentHandler');
		// start parsing an XML document
		if (!xml_parse($this->parser, file_get_contents($xmlfile))) {
			die(sprintf(
				'ERROR xmlResourceBundle :: XML error: %s at line %d',
				xml_error_string(xml_get_error_code($this->parser)),
				xml_get_current_line_number($this->parser)
			));
		}
		// free this XML parser
		xml_parser_free($this->parser);
	}

	/**
	 * Class destructor;
	 */
	public function __destruct()
	{
		// delete uploaded file
		@unlink($this->xmlfile);
	}

	/**
	 * Sets the start element handler function for the XML parser parser.start_element_handler.
	 * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
	 * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
	 * @param $attribs (array) The third parameter, attribs, contains an associative array with the element's attributes (if any). The keys of this array are the attribute names, the values are the attribute values. Attribute names are case-folded on the same criteria as element names. Attribute values are not case-folded. The original order of the attributes can be retrieved by walking through attribs the normal way, using each(). The first key in the array was the first attribute, and so on.
	 * @private
	 */
	private function startElementHandler($parser, $name, $attribs)
	{
		$name = strtolower($name);
		switch ($name) {
			case 'user':{
					$this->user_data    = array();
					$this->group_data   = array();
					$this->current_data = '';
					break;
				}
			case 'name':
			case 'password':
			case 'email':
			case 'regdate':
			case 'ip':
			case 'firstname':
			case 'lastname':
			case 'birthdate':
			case 'birthplace':
			case 'regnumber':
			case 'ssn':
			case 'level':
			case 'verifycode':
			case 'otpkey':{
					$this->current_element = 'user_' . $name;
					$this->current_data    = '';
					break;
				}
			case 'group':{
					$this->current_element = 'group_name';
					$this->current_data    = '';
					break;
				}
			default:{
					break;
				}
		}
	}

	/**
	 * Sets the end element handler function for the XML parser parser.end_element_handler.
	 * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
	 * @param $name (string) The second parameter, name, contains the name of the element for which this handler is called. If case-folding is in effect for this parser, the element name will be in uppercase letters.
	 * @private
	 */
	private function endElementHandler($parser, $name)
	{
		global $l, $db;
		require_once '../config/tce_config.php';
		require_once 'tce_functions_user_select.php';

		switch (strtolower($name)) {
			case 'name':
			case 'password':
			case 'email':
			case 'regdate':
			case 'ip':
			case 'firstname':
			case 'lastname':
			case 'birthdate':
			case 'birthplace':
			case 'regnumber':
			case 'ssn':
			case 'level':
			case 'verifycode':
			case 'otpkey':{
					$this->current_data                      = F_escape_sql($db, F_xml_to_text($this->current_data));
					$this->user_data[$this->current_element] = $this->current_data;
					$this->current_element                   = '';
					$this->current_data                      = '';
					break;
				}
			case 'group':{
					$group_name = F_escape_sql($db, F_xml_to_text($this->current_data));
					// check if group already exist
					$sql = 'SELECT group_id
					FROM ' . K_TABLE_GROUPS . '
					WHERE group_name=\'' . $group_name . '\'
					LIMIT 1';
					if ($r = F_db_query($sql, $db)) {
						if ($m = F_db_fetch_array($r)) {
							// the group has been already added
							$this->group_data[] = $m['group_id'];
						} else {
							// add new group
							$sqli = 'INSERT INTO ' . K_TABLE_GROUPS . ' (
							group_name
							) VALUES (
							\'' . $group_name . '\'
							)';
							if (!$ri = F_db_query($sqli, $db)) {
								F_display_db_error(false);
							} else {
								$this->group_data[] = F_db_insert_id($db, K_TABLE_GROUPS, 'group_id');
							}
						}
					} else {
						F_display_db_error();
					}
					break;
				}
			case 'user':{
					// insert users
					if (!empty($this->user_data['user_name'])) {
						if (empty($this->user_data['user_regdate'])) {
							$this->user_data['user_regdate'] = date(K_TIMESTAMP_FORMAT);
						}
						if (empty($this->user_data['user_ip'])) {
							$this->user_data['user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
						}
						if (!isset($this->user_data['user_level']) or (strlen($this->user_data['user_level']) == 0)) {
							$this->user_data['user_level'] = 1;
						}
						if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
							// you cannot edit a user with a level equal or higher than yours
							$this->user_data['user_level'] = min(max(0, ($_SESSION['session_user_level'] - 1)), $this->user_data['user_level']);
							// non-administrator can access only to his/her groups
							if (empty($this->group_data)) {
								break;
							}
							$common_groups = array_intersect(F_get_user_groups($_SESSION['session_user_id']), $this->group_data);
							if (empty($common_groups)) {
								break;
							}
						}
						// check if user already exist
						$sql = 'SELECT user_id,user_level
						FROM ' . K_TABLE_USERS . '
						WHERE user_name=\'' . $this->user_data['user_name'] . '\'
							OR user_regnumber=\'' . $this->user_data['user_regnumber'] . '\'
							OR user_ssn=\'' . $this->user_data['user_ssn'] . '\'
						LIMIT 1';
						if ($r = F_db_query($sql, $db)) {
							if ($m = F_db_fetch_array($r)) {
								// the user has been already added
								$user_id = $m['user_id'];
								if (($_SESSION['session_user_level'] >= K_AUTH_ADMINISTRATOR) or ($_SESSION['session_user_level'] > $m['user_level'])) {
									//update user data
									$sqlu = 'UPDATE ' . K_TABLE_USERS . ' SET
									user_regdate=\'' . $this->user_data['user_regdate'] . '\',
									user_ip=\'' . $this->user_data['user_ip'] . '\',
									user_name=\'' . $this->user_data['user_name'] . '\',
									user_email=' . F_empty_to_null($this->user_data['user_email']) . ',';
									// update password only if it is specified
									if (!empty($this->user_data['user_password'])) {
										$sqlu .= ' user_password=\'' . F_escape_sql($db, getPasswordHash($this->user_data['user_password'])) . '\',';
									}
									$sqlu .= '
									user_regnumber=' . F_empty_to_null($this->user_data['user_regnumber']) . ',
									user_firstname=' . F_empty_to_null($this->user_data['user_firstname']) . ',
									user_lastname=' . F_empty_to_null($this->user_data['user_lastname']) . ',
									user_birthdate=' . F_empty_to_null($this->user_data['user_birthdate']) . ',
									user_birthplace=' . F_empty_to_null($this->user_data['user_birthplace']) . ',
									user_ssn=' . F_empty_to_null($this->user_data['user_ssn']) . ',
									user_level=\'' . $this->user_data['user_level'] . '\',
									user_verifycode=' . F_empty_to_null($this->user_data['user_verifycode']) . ',
									user_otpkey=' . F_empty_to_null($this->user_data['user_otpkey']) . '
									WHERE user_id=' . $user_id . '';
									if (!$ru = F_db_query($sqlu, $db)) {
										F_display_db_error(false);
										return false;
									}
								} else {
									// no user is updated, so empty groups
									$this->group_data = array();
								}
							} else {
								// add new user
								$sqlu = 'INSERT INTO ' . K_TABLE_USERS . ' (
								user_regdate,
								user_ip,
								user_name,
								user_email,
								user_password,
								user_regnumber,
								user_firstname,
								user_lastname,
								user_birthdate,
								user_birthplace,
								user_ssn,
								user_level,
								user_verifycode,
								user_otpkey
								) VALUES (
								' . F_empty_to_null($this->user_data['user_regdate']) . ',
								\'' . $this->user_data['user_ip'] . '\',
								\'' . $this->user_data['user_name'] . '\',
								' . F_empty_to_null($this->user_data['user_email']) . ',
								\'' . F_escape_sql($db, getPasswordHash($this->user_data['user_password'])) . '\',
								' . F_empty_to_null($this->user_data['user_regnumber']) . ',
								' . F_empty_to_null($this->user_data['user_firstname']) . ',
								' . F_empty_to_null($this->user_data['user_lastname']) . ',
								' . F_empty_to_null($this->user_data['user_birthdate']) . ',
								' . F_empty_to_null($this->user_data['user_birthplace']) . ',
								' . F_empty_to_null($this->user_data['user_ssn']) . ',
								\'' . $this->user_data['user_level'] . '\',
								' . F_empty_to_null($this->user_data['user_verifycode']) . ',
								' . F_empty_to_null($this->user_data['user_otpkey']) . '
								)';
								if (!$ru = F_db_query($sqlu, $db)) {
									F_display_db_error(false);
									return false;
								} else {
									$user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
								}
							}
						} else {
							F_display_db_error(false);
							return false;
						}

						// user's groups
						if (!empty($this->group_data)) {
							foreach ($this->group_data as $key => $group_id) {
								// check if user-group already exist
								$sqls = 'SELECT *
								FROM ' . K_TABLE_USERGROUP . '
								WHERE usrgrp_group_id=\'' . $group_id . '\'
									AND usrgrp_user_id=\'' . $user_id . '\'
								LIMIT 1';
								if ($rs = F_db_query($sqls, $db)) {
									if (!$ms = F_db_fetch_array($rs)) {
										// associate group to user
										$sqlg = 'INSERT INTO ' . K_TABLE_USERGROUP . ' (
										usrgrp_user_id,
										usrgrp_group_id
										) VALUES (
										' . $user_id . ',
										' . $group_id . '
										)';
										if (!$rg = F_db_query($sqlg, $db)) {
											F_display_db_error(false);
											return false;
										}
									}
								} else {
									F_display_db_error(false);
									return false;
								}
							}
						}
					}
					break;
				}
			default:{
					break;
				}
		}
	}

	/**
	 * Sets the character data handler function for the XML parser parser.handler.
	 * @param $parser (resource) The first parameter, parser, is a reference to the XML parser calling the handler.
	 * @param $data (string) The second parameter, data, contains the character data as a string.
	 * @private
	 */
	private function segContentHandler($parser, $data)
	{
		if (strlen($this->current_element) > 0) {
			// we are inside an element
			$this->current_data .= $data;
		}
	}
} // END OF CLASS

/**
 * Import users from TSV file (tab delimited text).
 * The format of TSV is the same obtained by exporting data from Users Selection Form.
 * @param $tsvfile (string) TSV (tab delimited text) file name
 * @return boolean TRUE in case of success, FALSE otherwise
 */
function F_import_tsv_users($tsvfile)
{
	global $db, $year_level;
	require_once '../config/tce_config.php';

	// get file content as array
	$tsvrows = file($tsvfile); // array of TSV lines
	if ($tsvrows === false) {
		return false;
	}

	//first row us description. Pop it off
	array_shift($tsvrows);

	$resource = F_db_query("SELECT `user_id`, `user_level`, `user_name`, `user_regnumber`, `user_ssn` FROM " . K_TABLE_USERS, $db);

	if ($resource === false) {
		F_display_db_error(false);
		return false;
	}

	$existing_users_keyed_by_ssn       = [];
	$existing_users_keyed_by_user_id   = [];
	$existing_users_keyed_by_usernames = [];
	$existing_users_keyed_by_regnumber = [];

	populate_existing_users($resource, $existing_users_keyed_by_ssn, $existing_users_keyed_by_user_id, $existing_users_keyed_by_usernames, $existing_users_keyed_by_regnumber);

	$update_statement_with_password    = get_record_update_query_with_password();
	$update_statement_without_password = get_record_update_query_without_password();

	$data_insertion_prepared_statement = 'INSERT INTO ' . K_TABLE_USERS . ' (
		user_name,
		user_password,
		user_email,
		user_regdate,
		user_ip,
		user_firstname,
		user_lastname,
		user_college,
		user_department,
		user_year_level,
		user_passport,
		user_birthdate,
		user_birthplace,
		user_regnumber,
		user_ssn,
		user_level,
		user_verifycode,
		user_otpkey
		)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

	if (!$connection_for_data_insertion = @F_db_connect(K_DATABASE_HOST, K_DATABASE_PORT, K_DATABASE_USER_NAME, K_DATABASE_USER_PASSWORD, K_DATABASE_NAME)) {
		die('<h2>Unable to connect to the database!</h2>');
	}
	if (!$connection_for_data_update_with_password = @F_db_connect(K_DATABASE_HOST, K_DATABASE_PORT, K_DATABASE_USER_NAME, K_DATABASE_USER_PASSWORD, K_DATABASE_NAME)) {
		die('<h2>Unable to connect to the database!</h2>');
	}
	if (!$connection_for_data_update_without_password = @F_db_connect(K_DATABASE_HOST, K_DATABASE_PORT, K_DATABASE_USER_NAME, K_DATABASE_USER_PASSWORD, K_DATABASE_NAME)) {
		die('<h2>Unable to connect to the database!</h2>');
	}

	if (($stmt = $connection_for_data_insertion->prepare($data_insertion_prepared_statement)) === false) {
		F_print_error('error', "Cannot prepare statement: {$connection_for_data_insertion->error}");
		return false;
	}

	if (($stmt_update_with_password = $connection_for_data_update_with_password->prepare($update_statement_with_password)) === false) {
		F_print_error('error', "Cannot prepare statement: {$connection_for_data_update_with_password->error}");
		return false;
	}

	if (($stmt_update_without_password = $connection_for_data_update_without_password->prepare($update_statement_without_password)) === false) {
		F_print_error('error', "Cannot prepare statement: {$connection_for_data_update_without_password->error}");
		return false;
	}

	$connection_for_data_insertion->begin_transaction();
	$connection_for_data_update_with_password->begin_transaction();
	$connection_for_data_update_without_password->begin_transaction();

	$password = null;

	$new_addition = 0;
	$updates      = 0;
	$row_count    = 0;
	foreach ($tsvrows as $item => $rowdata) {
		$row_count++;

		$userdata = explode("\t", $rowdata);

		$userdata = array_map(function ($value) {
			return $value ? trim($value) : null;
		}, $userdata);

		//hashing takes significant time when run multiple times. Password is same for all users @IUO
		if (is_null($password)) {
			$password = getPasswordHash($userdata[2]);
		}

		// set some default values
		if (empty($userdata[4])) {
			$userdata[4] = date(K_TIMESTAMP_FORMAT);
		}

		$userdata[5] = 'n/a';

		//group
		$userdata[19] = 'students';

		//transformations
		$userdata[9]  = $userdata[9];
		$userdata[10] = get_item_index($userdata[10], $year_level);

		if (!empty($userdata[9])) {
			if (!is_valid_department($userdata[9])) {
				F_print_error('error', "Invalid department: '{$userdata[9]}'");
				return false;
			}
		}

		if (!isset($userdata[16]) or (strlen($userdata[16]) == 0)) {
			$userdata[16] = 1;
		}

		if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
			// you cannot edit a user with a level equal or higher than yours
			$userdata[12] = min(max(0, ($_SESSION['session_user_level'] - 1)), $userdata[12]);
			// non-administrator can access only to his/her groups
			if (empty($userdata[19])) {
				break;
			}
			$usrgroups     = explode(',', addslashes($userdata[19]));
			$common_groups = array_intersect(F_get_user_groups($_SESSION['session_user_id']), $usrgroups);
			if (empty($common_groups)) {
				break;
			}
		}

		if (user_already_exists($userdata, $existing_users_keyed_by_usernames, $existing_users_keyed_by_regnumber, $existing_users_keyed_by_ssn, $userdata)) {
			$updates++;
			$existing_user_data = get_existing_user($userdata, $existing_users_keyed_by_usernames, $existing_users_keyed_by_regnumber, $existing_users_keyed_by_ssn);
			$user_id            = $existing_user_data['user_id'];
			if (can_add_this_record($existing_user_data)) {
				if (!empty($userdata[2])) {
					$stmt_update_with_password->bind_param(str_repeat('s', 19),
						$password,
						$userdata[1],
						$userdata[3],
						$userdata[4],
						$userdata[5],
						$userdata[6],
						$userdata[7],
						$userdata[8],
						$userdata[9],
						$userdata[10],
						$userdata[11],
						$userdata[12],
						$userdata[13],
						$userdata[14],
						$userdata[15],
						$userdata[16],
						$userdata[17],
						$userdata[18],
						$user_id);

					if ($stmt_update_with_password->execute() === false) {
						F_print_error('error', "Error running query: {$stmt_update_with_password->error} (row {$row_count})");
						return false;
					}
				} else {
					$stmt_update_without_password->bind_param(str_repeat('s', 18),
						$userdata[1],
						$userdata[3],
						$userdata[4],
						$userdata[5],
						$userdata[6],
						$userdata[7],
						$userdata[8],
						$userdata[9],
						$userdata[10],
						$userdata[11],
						$userdata[12],
						$userdata[13],
						$userdata[14],
						$userdata[15],
						$userdata[16],
						$userdata[17],
						$userdata[18],
						$user_id);

					if ($stmt_update_without_password->execute() === false) {
						F_print_error('error', "Error running query: {$stmt_update_without_password->error} (row {$row_count})");
						return false;
					}
				}
			} else {
				// no user is updated, so empty groups
				$userdata[19] = '';
			}
		} else {
			// add new user
			$new_addition++;

			$stmt->bind_param(str_repeat('s', 18),
				$userdata[1],
				$password,
				$userdata[3],
				$userdata[4],
				$userdata[5],
				$userdata[6],
				$userdata[7],
				$userdata[8],
				$userdata[9],
				$userdata[10],
				$userdata[11],
				$userdata[12],
				$userdata[13],
				$userdata[14],
				$userdata[15],
				$userdata[16],
				$userdata[17],
				$userdata[18]
			);

			if ($stmt->execute() === false) {
				F_print_error('error', "Error running query: {$stmt->error} (row {$row_count})");
				return false;
			}
		}
	}

	$connection_for_data_insertion->commit();
	$connection_for_data_update_with_password->commit();
	$connection_for_data_update_without_password->commit();

	echo "
	<pre>New additions: $new_addition</pre>
	<pre>Updates: $updates</pre>
	";

	return true;
}

function can_add_this_record($user_data)
{
	return (($_SESSION['session_user_level'] >= K_AUTH_ADMINISTRATOR) or ($_SESSION['session_user_level'] > $user_data['user_level']));
}

/**
 * Returns the index of $item in $stack
 *
 * @param mixed $item
 * @param array $stack
 *
 * @return void
 */
function get_item_index($item, $stack)
{
	$index = array_search($item, $stack);
	if (!is_numeric($index)) {
		$index = 0; //default
	}
	return $index;
}

function is_valid_department($department)
{
	return in_array($department, get_departments());
}

function user_already_exists(array $userdata, array $existing_users_keyed_by_usernames, array $existing_users_keyed_by_regnumber, array $existing_users_keyed_by_ssn)
{
	if (array_key_exists($userdata[1], $existing_users_keyed_by_usernames)) {
		return true;
	}

	if (!empty($userdata[14]) && !empty($existing_users_keyed_by_regnumber)) {
		if (array_key_exists($userdata[14], $existing_users_keyed_by_regnumber)) {
			return true;
		}
	}

	if (!empty($userdata[15]) && !empty($existing_users_keyed_by_ssn)) {
		if (array_key_exists($userdata[15], $existing_users_keyed_by_ssn)) {
			return true;
		}
	}

	return false;
}

function get_existing_user(array $userdata, array $existing_users_keyed_by_usernames, array $existing_users_keyed_by_regnumber, array $existing_users_keyed_by_ssn)
{
	if (array_key_exists($userdata[1], $existing_users_keyed_by_usernames)) {
		return $existing_users_keyed_by_usernames[$userdata[1]];
	}

	if (!empty($userdata[14]) && !empty($existing_users_keyed_by_regnumber)) {
		if (array_key_exists($userdata[14], $existing_users_keyed_by_regnumber)) {
			return $existing_users_keyed_by_regnumber[$userdata[14]];
		}
	}

	if (!empty($userdata[15]) && !empty($existing_users_keyed_by_ssn)) {
		if (array_key_exists($userdata[15], $existing_users_keyed_by_ssn)) {
			return $existing_users_keyed_by_ssn[$userdata[15]];
		}
	}

	F_print_error('error', 'User not found');
	throw new Exception("User not found");
}

function populate_existing_users($resource, &$existing_users_keyed_by_ssn, &$existing_users_keyed_by_user_id, &$existing_users_keyed_by_usernames, &$existing_users_keyed_by_regnumber)
{
	while ($existing_user = F_db_fetch_assoc($resource)) {
		if (!empty($existing_user['user_id'])) {
			$existing_users_keyed_by_user_id[$existing_user['user_id']] = $existing_user;
		}

		if (!empty($existing_user['user_ssn'])) {
			$existing_users_keyed_by_ssn[$existing_user['user_ssn']] = $existing_user;
		}

		if (!empty($existing_user['user_name'])) {
			$existing_users_keyed_by_usernames[$existing_user['user_name']] = $existing_user;
		}

		if (!empty($existing_user['user_regnumber'])) {
			$existing_users_keyed_by_regnumber[$existing_user['user_regnumber']] = $existing_user;
		}

	}
}

function get_record_update_query_with_password()
{
	return 'UPDATE ' . K_TABLE_USERS . ' SET ' . implode(',', get_users_table_structure()) . ' WHERE user_id=?';
}

function get_record_update_query_without_password()
{
	$meta_schema = get_users_table_structure();
	array_shift($meta_schema);
	return 'UPDATE ' . K_TABLE_USERS . ' SET ' . implode(',', $meta_schema) . ' WHERE user_id=?';
}

function get_users_table_structure()
{
	return [
		'user_password=?',
		'user_name=?',
		'user_email=?',
		'user_regdate=?',
		'user_ip=?',
		'user_firstname=?',
		'user_lastname=?',
		'user_college=?',
		'user_department=?',
		'user_year_level=?',
		'user_passport=?',
		'user_birthdate=?',
		'user_birthplace=?',
		'user_regnumber=?',
		'user_ssn=?',
		'user_level=?',
		'user_verifycode=?',
		'user_otpkey=?',
	];
}

//============================================================+
// END OF FILE
//============================================================+
