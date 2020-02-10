<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 01/03/2018
 * Time: 13:36
 */

namespace JCT;


use Exception;
use JCT\Database;

class cadoo_installed implements api_interface
{
    public $guid;
    public $remote_api_call;

    public $success = 0;
    public $error = 0;
    public $response;

    private $_DB;
    private $_ORG_DB;
    private $input;
    private $user_id;

    private $data;



    private $operator;
    private $operator_username;
    private $operator_password;

    private $set_numbers;
    private $rejected_numbers;
    private $numbers_string;


    function __construct(Database $_DB, Database $_ORG_DB, $guid, $input)
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
            array_change_key_case($this->input, CASE_LOWER);
            if(empty($this->input['data']))
                throw new Exception('Data field missing.');

            $data = $this->input['data'];
            if(!is_array($data))
            {
                $tmp = json_decode($this->input['data'], true);
                if(json_last_error() !== JSON_ERROR_NONE)
                    throw new Exception('Invalid JSON detected in data field.');

                $data = $tmp;
            }

            array_change_key_case($data, CASE_LOWER);
            $this->data = $data;

            if(empty($data['numbers']))
                throw new Exception('Numbers field missing in data.');

            $tmp = $this->get_org_operator();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->check_cadoo_enabled();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->set_numbers($data['numbers']);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);




            if($this->remote_api_call == null)
            {
                if(!isset($_SESSION['databiz']))
                    throw new Exception('No User ID could be determined.');

                if(!isset($_SESSION['databiz']['id']))
                    throw new Exception('No User ID could be determined.');

                $this->user_id = intval($_SESSION['databiz']['user']['id']);
            }

            $this->get_cadoo_subscribers();
            try
            {
                $tmp = $this->update_settings();
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);
            }
            catch(Exception $e)
            {
                $error = $e->getMessage();
                if(is_array($this->response))
                    $this->response['recording_error'] = $error . ' The message was still sent however.';
            }
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
        }
    }





    private function get_org_operator()
    {
        $db = $this->_DB;

        $db->query(" SELECT operator, username, password FROM messaging_org_operator WHERE ( guid = :guid AND operator_end IS NULL AND operator = 'neon' ) ");
        $db->bind(':guid', $this->guid);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if( (empty($tmp)) || (empty($tmp['operator'])) )
            return ['error' => 'No Operator was found for this Organisation.'];

        if(empty($tmp['password']))
            return ['error' => 'No Operator password was found for this Organisation.'];

        $this->operator = $tmp['operator'];
        $this->operator_username = $tmp['username'];
        $this->operator_password = Cryptor::Decrypt($tmp['password']);

        return true;
    }

    private function check_cadoo_enabled()
    {

        $db = $this->_ORG_DB;
        $db->query(" SELECT setting_value FROM manager_setting WHERE ( setting_key = 'year_starting' ) ");
        $db->execute();
        $year_starting = $db->fetchSingleColumn();

        if(empty($year_starting))
            return ['error' => 'This Organisation has no set year_starting.'];

        $db = $this->_DB;
        $db->query(" SELECT setting_value FROM org_setting WHERE 
            ( guid = :guid AND setting_key = 'cadoo_enabled' AND setting_year_starting = {$year_starting} ) ");
        $db->bind(':guid', $this->guid);
        $db->execute();
        $cadoo_enabled = intval($db->fetchSingleColumn());

        if(empty($cadoo_enabled))
        {
            var_dump( $this->guid);
            return ['error' => 'This Organisation is not Cadoo enabled.'];
        }

        return true;
    }

    private function set_numbers($raw_numbers)
    {
        try
        {
            $numbers_arr = (is_array($raw_numbers)) ? $raw_numbers : explode('_', $raw_numbers);
            if(empty($numbers_arr))
                throw new Exception('No mobile numbers detected.');

            $numbers_arr = array_filter($numbers_arr, 'trim');

            $set_numbers = [];
            $rejected_numbers = [];
            $numbers_string = '';

            $n = 0;
            foreach($numbers_arr as $i => $num)
            {
                $normalised_num = Helper::normalise_contact_number($num);
                if(!is_null($normalised_num))
                {
                    if(in_array($normalised_num, $set_numbers))
                        continue;

                    $set_numbers[$n] = $normalised_num;
                    $numbers_string.= '&mobile[' . $n . ']=' . preg_replace("/[^0-9+]/", "", $normalised_num);

                    $n++;
                }
                else
                    $rejected_numbers[$num] = Helper::$libphonenumber_error;
            }

            if(empty($set_numbers))
                throw new Exception('No valid numbers were left after normalisation.');


            $this->set_numbers = $set_numbers;
            $this->rejected_numbers = $rejected_numbers;
            $this->numbers_string = $numbers_string;

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function get_cadoo_subscribers()
    {
        try
        {
            $db = $this->_DB;

            $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = :operator AND param_key = 'installed_uri' ) ");
            $db->bind(':operator', $this->operator);
            $db->execute();
            $subscriber_uri = $db->fetchSingleColumn();

            if(empty($subscriber_uri))
                return ['error' => 'No Send URI was found for this Organisation\'s operator.'];

            $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = :operator AND param_key = 'installed_post_script' ) ");
            $db->bind(':operator', $this->operator);
            $db->execute();
            $installed_post_script = $db->fetchSingleColumn();

            if(empty($installed_post_script))
                throw new Exception('No Installed URI was found for this Organisation\'s operator.');

            $query_string = 'user=' . $this->operator_username . '&clipwd=' . $this->operator_password;
            $query_string.= $this->numbers_string;

            $query_string_len = strlen($query_string);




            $socket = @fsockopen("$subscriber_uri", 80, $errno, $errstr, 120);
            if(!$socket)
                throw new Exception("Unable to get Neon server status.");

            $out = sprintf("POST $installed_post_script");
            $out.= " HTTP/1.1\n";
            $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out.= "Host: $subscriber_uri\r\n";
            $out.= "Content-Length: $query_string_len\r\n";
            $out.= "Connection: Close\r\n";
            $out.= "Cache-Control: no-cache\r\n\r\n";
            $out.= $query_string;

            fwrite($socket, $out);
            stream_set_blocking($socket, false);
            stream_set_timeout($socket, 120);
            $info = stream_get_meta_data($socket);

            $results = '';
            while (!feof($socket) && !$info['timed_out'])
            {
                $results.= fgets($socket, 4096);
                $info = stream_get_meta_data($socket);
            }

            $tmp = preg_split('/$\R?^/m', $results);
            $results = [];
            foreach($tmp as $t)
                $results[] = preg_replace('~[[:cntrl:]]~', '', $t);

            $results = array_slice($results, 7);

            if(empty($results))
                throw new Exception('No response received from the Operator.');

            $results = array_values($results);



            $status = [
                'successful' => [
                    'installed' => [],
                    'not_installed' => []
                ],
                'failed' => [],
                'rejected_numbers' => $this->rejected_numbers
            ];

            foreach($results as $n => $res)
            {
                $split = explode(':', $res);
                $split = array_map('trim', $split);

                if($split[0] === 'ERR')
                    throw new Exception('Operator responded with ' . $split[1]);

                $number = $split[0];
                if(is_numeric($split[1]))
                {
                    $normalised_number = Helper::normalise_contact_number($split[0]);

                    $installed = (intval($split[1]) > 0);
                    $key = ($installed) ? 'installed' : 'not_installed';
                    $status['successful'][$key][] = $normalised_number;
                }
                else
                    $status['failed'][$number] = $split[1];
            }

            $this->success = 1;
            $this->response = $status;


            if($this->remote_api_call)
            {
                $rejected_numbers = '';
                if(!empty($this->rejected_numbers))
                {
                    $tmp = array_keys($this->rejected_numbers);
                    $rejected_numbers = implode(',', $tmp);
                }

                $access_status = [
                    'response' => [],
                    'rejected_numbers' => $rejected_numbers
                ];

                if(!empty($status['successful']['installed']))
                {
                    foreach($status['successful']['installed'] as $num)
                    {
                        $stripped_num = preg_replace("/[^0-9,.]/", "", $num);
                        $access_status['response'][$stripped_num] = "1";
                    }
                }
                if(!empty($status['successful']['not_installed']))
                {
                    foreach($status['successful']['not_installed'] as $num)
                    {
                        $stripped_num = preg_replace("/[^0-9,.]/", "", $num);
                        $access_status['response'][$stripped_num] = "0";
                    }
                }

                $this->response = $access_status;
            }

            return true;
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
            return false;
        }
    }

    private function update_settings()
    {
        try
        {
            if($this->error)
                return false;

            if(!is_array($this->response))
                return false;

            if(empty($this->response['successful']))
                return false;

            $sent_by = ($this->remote_api_call !== 1) ? $this->user_id : -1;

            $db = $this->_ORG_DB;
            $db->query(" SELECT setting_value FROM manager_setting WHERE ( setting_key = 'year_starting' ) ");
            $db->execute();
            $year_starting = $db->fetchSingleColumn();

            if(!empty($this->response['successful']['installed']))
            {
                $nums_str = '\'' . implode('\',\'',$this->response['successful']['installed']) . '\'';

                $db = $this->_DB;
                $db->query(" SELECT id FROM user WHERE mobile IN ({$nums_str}) ");
                $db->execute();
                $user_ids = $db->fetchAllColumn();

                if(!empty($user_ids))
                {
                    $db->query(" INSERT INTO user_setting 
                    ( user_id, setting_key, setting_value, setting_year_starting, updated, updated_by ) VALUES 
                    ( :user_id, 'cadoo_installed', 1, {$year_starting}, NOW(), {$sent_by} ) 
                    ON DUPLICATE KEY UPDATE setting_value = 1, updated = NOW(), updated_by = {$sent_by} ");
                    foreach($user_ids as $user_id)
                    {
                        $db->bind(':user_id', $user_id);
                        $db->execute();
                    }
                }
            }

            if(!empty($this->response['successful']['not_installed']))
            {
                $nums_str = '\'' . implode('\',\'',$this->response['successful']['not_installed']) . '\'';

                $db = $this->_DB;
                $db->query(" SELECT id FROM user WHERE mobile IN ({$nums_str}) ");
                $db->execute();
                $user_ids = $db->fetchAllColumn();

                if(!empty($user_ids))
                {
                    $db->query(" INSERT INTO user_setting 
                    ( user_id, setting_key, setting_value, setting_year_starting, updated, updated_by ) VALUES 
                    ( :user_id, 'cadoo_installed', 0, {$year_starting}, NOW(), {$sent_by} ) 
                    ON DUPLICATE KEY UPDATE setting_value = 0, updated = NOW(), updated_by = {$sent_by} ");
                    foreach($user_ids as $user_id)
                    {
                        $db->bind(':user_id', $user_id);
                        $db->execute();
                    }
                }
            }

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}