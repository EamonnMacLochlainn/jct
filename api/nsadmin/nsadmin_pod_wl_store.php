<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 11/12/2017
 * Time: 11:43
 */


namespace JCT;


use Exception;
use JCT\Database;


class nsadmin_pod_wl_store implements api_interface
{
    private $_DB;
    private $_ORG_DB;
    private $input;

    public $guid;
    public $remote_api_call;

    private $success;
    private $error;
    private $error_code;

    public function __construct(Database $_DB, Database $_ORG_DB = null, $guid, $input)
    {
        try
        {
            $this->_DB = $_DB;
            $this->_ORG_DB = $_ORG_DB;
            $this->guid = $guid;
            $this->input = $input;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
        }
    }

    function execute()
    {
        try
        {
            if(empty($this->input['datetime']))
                throw new Exception('Datetime field missing.');

            $upload_datetime = trim($this->input['datetime']);
            $upload_datetime_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $upload_datetime);
            if(!$upload_datetime_obj)
                throw new Exception('Invalid Datetime submitted.', 26);

            if(empty($this->input['data']))
                throw new Exception('Data field missing.');



            // parse data JSON into array
            // and check for parsing error

            $data_json = $this->input['data'];
            $data = json_decode($data_json, true);

            $error_msg = '';
            $error_code = 0;
            switch (json_last_error())
            {
                case JSON_ERROR_NONE:
                    break;
                case JSON_ERROR_DEPTH:
                    $error_code = 11;
                    $error_msg = 'The maximum stack depth has been exceeded.';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error_code = 12;
                    $error_msg = 'Invalid or malformed JSON.';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error_code = 13;
                    $error_msg = 'Control character error, possibly incorrectly encoded.';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error_code = 14;
                    $error_msg = 'Syntax error, malformed JSON.';
                    break;
                case JSON_ERROR_UTF8:
                    $error_code = 15;
                    $error_msg = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                    break;
                case JSON_ERROR_RECURSION:
                    $error_code = 22;
                    $error_msg = 'One or more recursive references in the value to be encoded.';
                    break;
                case JSON_ERROR_INF_OR_NAN:
                    $error_code = 23;
                    $error_msg = 'One or more NAN or INF values in the value to be encoded.';
                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    $error_code = 24;
                    $error_msg = 'A value of a type that cannot be encoded was given.';
                    break;
                default:
                    $error_code = 16;
                    $error_msg = 'Unknown JSON error occurred.';
                    break;
            }

            if(!empty($error_msg))
                throw new Exception($error_msg, $error_code);



            $this->input = $data;
            $data = array_change_key_case($data, CASE_LOWER);



            // check data for required fields

            $required_record_fields = [
                'Forename',
                'Surname',
                'PPS',
                'Dob',
                'Gender',
                'Add1',
                'Add2',
                'Add3',
                'Add4',
                'County',
                'Eircode',
                'Nationality',
                'PupilSource',
                'MotherTongue',
                'Ethnicity',
                'Religion'
            ];

            foreach($data as $i => $rec)
            {
                foreach($required_record_fields as $rf)
                {
                    if(!isset($rec[$rf]))
                    {
                        $fname = (empty($rec['Forename'])) ? '' : $rec['Forename'] . ' ';
                        $lname = (empty($rec['Surname'])) ? '' : $rec['Surname'];
                        $name = $fname . $lname;

                        throw new Exception('The data for the \'' . $rf . '\' field is missing or empty (Record no. ' . $i . ': ' . $name . ').', 27);
                    }
                }
            }



            // establish upload number

            $db = $this->_DB;

            $db->query(" SELECT MAX(upload_num) FROM api_nsadmin_pod_uploads WHERE guid = ':guid' ");
            $db->bind(':guid', $this->guid);
            $db->execute();
            $upload_num = intval($db->fetchSingleColumn());
            $upload_num++;



            // transaction

            $db->beginTransaction();
            try
            {
                // clear existing data

                $db->query(" DELETE FROM api_nsadmin_pod_data WHERE guid = ':guid' ");
                $db->bind(':guid', $this->guid);
                $db->execute();



                // do upload

                $db->query(" INSERT INTO api_nsadmin_pod_data 
                    ( guid, Forename, Surname, PPS, Dob, Gender, 
                    Add1, Add2, Add3, Add4, County, Eircode, Nationality, 
                    PupilSource, MotherTongue, Ethnicity, Religion, 
                    upload_num ) VALUES  
                    ( :guid, :Forename, :Surname, :PPS, :Dob, :Gender, 
                    :Add1, :Add2, :Add3, :Add4, :County, :Eircode, :Nationality, 
                    :PupilSource, :MotherTongue, :Ethnicity, :Religion, 
                    {$upload_num} ) ");
                $db->bind(':guid', $this->guid);
                foreach($data as $rec)
                {
                    foreach($rec as $k => $v)
                        $db->bind(':' . $k, $v);

                    $db->execute();
                }



                // record upload

                $upload_datetime = $upload_datetime_obj->format('Y-m-d H:i:s');
                $db->query(" INSERT INTO api_nsadmin_pod_uploads 
                    ( guid, upload_datetime, upload_num ) VALUES 
                    ( :guid, '{$upload_datetime}', {$upload_num} ) ");
                $db->bind(':guid', $this->guid);
                $db->execute();



                $db->commit();
            }
            catch(Exception $e)
            {
                $db->rollBack();
                throw new Exception('SQL EXCEPTION ' . $e->getMessage() . ' (' . $e->getLine() . ')', 21);
            }

        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            $this->error_code = $e->getCode();

            /**
             * 08 - GMP not loaded
             * 09 - invalid encoding
             * 10 - no data
             * 11 - JSON depth
             * 12 - JSON mismatch
             * 13 - JSON CTRL char
             * 14 - JSON syntax
             * 15 - UTF8
             * 16 - JSON default error
             * 17 - required root field missing or empty
             * 18 - no account
             * 19 - authentication failed
             * 20 - not subscribed
             * 21 - transaction failed
             * 22 - JSON recursion
             * 23 - JSON inf or NaN
             * 24 - JSON unsupported type
             * 25 - database connection error
             * 26 - invalid datetime
             * 27 - required data field missing or empty
            */
        }
    }

    function get_response()
    {
        if(empty($this->error))
        {
            if($this->remote_api_call)
            {
                $this->success = [
                    'success' => 'success'
                ];
            }
        }
        return (!empty($this->error)) ? ['error'=>$this->error] : $this->success;
    }
}

