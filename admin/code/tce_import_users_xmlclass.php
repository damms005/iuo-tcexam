<?php
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
            case 'user': {
                $this->user_data = array();
                $this->group_data = array();
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
            case 'otpkey': {
                $this->current_element = 'user_'.$name;
                $this->current_data = '';
                break;
            }
            case 'group': {
                $this->current_element = 'group_name';
                $this->current_data = '';
                break;
            }
            default: {
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
        require_once('../config/tce_config.php');
        require_once('tce_functions_user_select.php');

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
            case 'otpkey': {
                $this->current_data = F_escape_sql($db, F_xml_to_text($this->current_data));
                $this->user_data[$this->current_element] = $this->current_data;
                $this->current_element = '';
                $this->current_data = '';
                break;
            }
            case 'group': {
                $group_name = F_escape_sql($db, F_xml_to_text($this->current_data));
                // check if group already exist
                $sql = 'SELECT group_id
					FROM '.K_TABLE_GROUPS.'
					WHERE group_name=\''.$group_name.'\'
					LIMIT 1';
                if ($r = F_db_query($sql, $db)) {
                    if ($m = F_db_fetch_array($r)) {
                        // the group has been already added
                        $this->group_data[] = $m['group_id'];
                    } else {
                        // add new group
                        $sqli = 'INSERT INTO '.K_TABLE_GROUPS.' (
							group_name
							) VALUES (
							\''.$group_name.'\'
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
            case 'user': {
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
						FROM '.K_TABLE_USERS.'
						WHERE user_name=\''.$this->user_data['user_name'].'\'
							OR user_regnumber=\''.$this->user_data['user_regnumber'].'\'
							OR user_ssn=\''.$this->user_data['user_ssn'].'\'
						LIMIT 1';

                    var_dump($sql);

                    if ($r = F_db_query($sql, $db)) {
                        if ($m = F_db_fetch_array($r)) {
                            // the user has been already added
                            $user_id = $m['user_id'];
                            if (($_SESSION['session_user_level'] >= K_AUTH_ADMINISTRATOR) or ($_SESSION['session_user_level'] > $m['user_level'])) {
                                //update user data
                                $sqlu = 'UPDATE '.K_TABLE_USERS.' SET
									user_regdate=\''.$this->user_data['user_regdate'].'\',
									user_ip=\''.$this->user_data['user_ip'].'\',
									user_name=\''.$this->user_data['user_name'].'\',
									user_email='.F_empty_to_null($this->user_data['user_email']).',';
                                // update password only if it is specified
                                if (!empty($this->user_data['user_password'])) {
                                    $sqlu .= ' user_password=\''.F_escape_sql($db, getPasswordHash($this->user_data['user_password'])).'\',';
                                }
                                $sqlu .= '
									user_regnumber='.F_empty_to_null($this->user_data['user_regnumber']).',
									user_firstname='.F_empty_to_null($this->user_data['user_firstname']).',
									user_lastname='.F_empty_to_null($this->user_data['user_lastname']).',
									user_birthdate='.F_empty_to_null($this->user_data['user_birthdate']).',
									user_birthplace='.F_empty_to_null($this->user_data['user_birthplace']).',
									user_ssn='.F_empty_to_null($this->user_data['user_ssn']).',
									user_level=\''.$this->user_data['user_level'].'\',
									user_verifycode='.F_empty_to_null($this->user_data['user_verifycode']).',
									user_otpkey='.F_empty_to_null($this->user_data['user_otpkey']).'
									WHERE user_id='.$user_id.'';

                                    var_dump($sqlu);

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
                            $sqlu = 'INSERT INTO '.K_TABLE_USERS.' (
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
								'.F_empty_to_null($this->user_data['user_regdate']).',
								\''.$this->user_data['user_ip'].'\',
								\''.$this->user_data['user_name'].'\',
								'.F_empty_to_null($this->user_data['user_email']).',
								\''.F_escape_sql($db, getPasswordHash($this->user_data['user_password'])).'\',
								'.F_empty_to_null($this->user_data['user_regnumber']).',
								'.F_empty_to_null($this->user_data['user_firstname']).',
								'.F_empty_to_null($this->user_data['user_lastname']).',
								'.F_empty_to_null($this->user_data['user_birthdate']).',
								'.F_empty_to_null($this->user_data['user_birthplace']).',
								'.F_empty_to_null($this->user_data['user_ssn']).',
								\''.$this->user_data['user_level'].'\',
								'.F_empty_to_null($this->user_data['user_verifycode']).',
								'.F_empty_to_null($this->user_data['user_otpkey']).'
								)';

                                var_dump($sqlu);

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
                        while (list($key,$group_id) = each($this->group_data)) {
                            // check if user-group already exist
                            $sqls = 'SELECT *
								FROM '.K_TABLE_USERGROUP.'
								WHERE usrgrp_group_id=\''.$group_id.'\'
									AND usrgrp_user_id=\''.$user_id.'\'
								LIMIT 1';
                            if ($rs = F_db_query($sqls, $db)) {
                                if (!$ms = F_db_fetch_array($rs)) {
                                    // associate group to user
                                    $sqlg = 'INSERT INTO '.K_TABLE_USERGROUP.' (
										usrgrp_user_id,
										usrgrp_group_id
										) VALUES (
										'.$user_id.',
										'.$group_id.'
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
            default: {
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
}
// END OF CLASS
?>
