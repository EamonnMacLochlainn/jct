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
use DateTime;

class send implements api_interface
{
    public $guid;
    public $remote_api_call;

    private $operator;
    private $operator_username;
    private $operator_password;

    private $_DB;
    private $_ORG_DB;
    private $input;
    private $data;
    private $user_id;

    private $message;
    private $set_numbers;
    private $rejected_numbers;
    private $numbers_string;

    public $success = 0;
    public $error = 0;
    public $response;


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
            $this->error = 1;
            $this->response = $e->getMessage();
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

            if(empty($data['text']))
                throw new Exception('Text field missing in data.');

            if(empty($data['numbers']))
                throw new Exception('Numbers field missing in data.');

            $tmp = $this->get_org_operator();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $fn = $this->operator . '_send';
            if(!method_exists($this, $fn))
                throw new Exception('No method found to retrieve Organisation\'s balance from their set Operator.');

            $tmp = $this->set_message($data['text']);
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

                $this->user_id = intval($_SESSION['databiz']['id']);
            }

            $this->$fn();
            try
            {
                $history_fn = 'save_' . strtolower($this->operator) . '_message_status';
                if(!method_exists($this, $history_fn))
                    throw new Exception('No method was found to write this Operator\'s message status to history.');

                $tmp = $this->$history_fn();
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);

                $tmp = $this->update_statistics();
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

        $db->query(" SELECT operator, username, password FROM messaging_org_operator WHERE ( guid = :guid AND operator_end IS NULL ) ");
        $db->bind(':guid', $this->guid);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if( (empty($tmp)) || (empty($tmp['operator'])) )
            return ['error' => 'No Operator was found for this Organisation.'];

        if(empty($tmp['username']))
            return ['error' => 'No Operator username was found for this Organisation.'];

        if(empty($tmp['password']))
            return ['error' => 'No Operator password was found for this Organisation.'];

        $this->operator = $tmp['operator'];
        $this->operator_username = $tmp['username'];
        $this->operator_password = $tmp['password'];

        return true;
    }

    private function set_message($raw_message)
    {
        try
        {
            if(empty($raw_message))
                throw new Exception('No message detected.');

            $message = mb_check_encoding($raw_message, 'UTF-8') ? utf8_decode($raw_message) : $raw_message;
            $message = trim($message);

            if(empty($message))
                throw new Exception('No message was left after sanitizing.');

            $this->message = $message;

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
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
                    $numbers_string.= '&to[' . $n . ']=' . preg_replace("/[^0-9]/", "", $normalised_num);

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

    private function neon_send()
    {
        try
        {
            $db = $this->_DB;

            $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = :operator AND param_key = 'send_uri' ) ");
            $db->bind(':operator', $this->operator);
            $db->execute();
            $send_uri = $db->fetchSingleColumn();

            if(empty($send_uri))
                throw new Exception('No Send URI was found for this Organisation\'s operator.');

            $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = :operator AND param_key = 'send_post_script' ) ");
            $db->bind(':operator', $this->operator);
            $db->execute();
            $send_post_script = $db->fetchSingleColumn();

            if(empty($send_post_script))
                throw new Exception('No Send target script was found for this Organisation\'s operator.');



            // optional parameters

            $source_number = null;
            $datetime_to_send = null;
            $mins_till_fallback_sms = null;

            $data = $this->data;
            array_change_key_case($data, CASE_LOWER);

            if(!empty($data['source_number']))
            {
                $normalised_num = Helper::normalise_contact_number($data['source_number']);
                if(!is_null($normalised_num))
                    $source_number = preg_replace("/[^0-9]/", "", $normalised_num);
                else
                    throw new Exception('The designated \'from\' number is not a valid number.');
            }

            if(!empty($data['datetime_to_send']))
            {
                $d = DateTime::createFromFormat('YmdHis', $data['datetime_to_send']);
                $d_errors = DateTime::getLastErrors();
                if( (!empty($d_errors['warning_count'])) || (strlen($data['datetime_to_send']) != 14) )
                    throw new Exception('The designated \'datetime_to_send\' value is not a valid date.');

                $n = new DateTime();
                if($d <= $n)
                    throw new Exception('The designated \'datetime_to_send\' value is before the current datetime.');

                $datetime_to_send = $data['datetime_to_send'];
            }

            if(!empty($data['fallback']))
            {
                $fallback = intval($data['fallback']);
                if( (!empty($fallback)) && ($fallback <= 2880) )
                    $mins_till_fallback_sms = $fallback;
            }



            // query string

            $query_string = 'user=' . $this->operator_username . '&clipwd=' . $this->operator_password;
            $query_string.= '&text=' . urldecode($this->message) . $this->numbers_string;

            var_dump($source_number);
            if($source_number !== null)
                $query_string.= '&from=' . $source_number;

            if($datetime_to_send !== null)
                $query_string.= '&date=' . $datetime_to_send;

            if($mins_till_fallback_sms !== null)
                $query_string.= '&fallback=' . $mins_till_fallback_sms;

            $query_string_len = strlen($query_string);



            // send

            $socket = @fsockopen("$send_uri", 80, $errno, $errstr, 120);
            if(!$socket)
                throw new Exception("Unable to get Neon server status.");

            $out = sprintf("POST $send_post_script");
            $out.= " HTTP/1.1\n";
            $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out.= "Host: $send_uri\r\n";
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
                'successful' => [],
                'failed' => [],
                'rejected_numbers' => $this->rejected_numbers
            ];

            foreach($results as $n => $res)
            {
                $split = explode(':', $res);
                $split = array_map('trim', $split);

                $number = $this->set_numbers[$n];
                $key = ($split[0] == 'OK') ? 'successful' : 'failed';

                $status[$key][$number] = $split[1];
            }

            $this->success = 1;
            $this->response = $status;
            return true;
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
            return false;
        }
    }

    private function save_neon_message_status()
    {
        try
        {
            if(!$this->success)
                return false;

            if(!is_array($this->response))
                return false;


            $content_len = strlen($this->message);
            $max_len = 160;
            $suffix = '[...]';
            $suffix_len = strlen($suffix);
            if($content_len <= $max_len)
                $excerpt = $this->message;
            else
            {
                $len = $max_len - $suffix_len;
                $excerpt = substr($this->message, 0, $len) . $suffix;
            }


            $sent_by = ($this->remote_api_call !== 1) ? $this->user_id : -1;
            $operator = $this->operator;

            $db = $this->_ORG_DB;
            $db->query(" INSERT INTO messaging_msg_content  
                (content, updated, updated_by) VALUES 
                (:content, NOW(), {$sent_by}) ");
            $db->bind(':content', $excerpt);
            $db->execute();
            $content_id = $db->lastInsertId();

            $db->query(" INSERT INTO messaging_msg_history  
                ( operator, operator_id, content_id, mobile, first_status, first_created, last_status, last_checked, sent_by ) VALUES 
                ( '{$operator}', :operator_id, {$content_id}, :mobile, :status, NOW(), :status, NOW(), {$sent_by} ) ");
            foreach($this->response['successful'] as $number => $operator_id)
            {
                $db->bind(':operator_id', $operator_id);
                $db->bind(':mobile', $number);
                $db->bind(':status', 'Sent');
                $db->execute();
            }
            foreach($this->response['failed'] as $number => $err_msg)
            {
                $db->bind(':operator_id', null);
                $db->bind(':mobile', $number);
                $db->bind(':status', $err_msg);
                $db->execute();
            }

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function update_statistics()
    {
        if($this->error)
            return false;

        try
        {
            $num_sent = (is_array($this->response)) ? count($this->response['successful']) : 0;

            $db = $this->_ORG_DB;
            $db->query(" SELECT setting_value FROM manager_setting WHERE ( setting_key = 'year_starting' ) ");
            $db->execute();
            $year_starting = $db->fetchSingleColumn();

            if(empty($year_starting))
                throw new Exception('Current year_starting not set in general settings.');

            $db = $this->_DB;
            $db->query(" SELECT statistic_value FROM org_statistics 
                WHERE ( guid = :guid AND statistic_key = 'messages_sent' AND statistic_year_starting = {$year_starting} ) ");
            $db->bind(':guid', $this->guid);
            $db->execute();
            $messages_sent = intval($db->fetchSingleColumn());

            $messages_sent = ($messages_sent + $num_sent);

            $db->query(" INSERT INTO org_statistics 
                ( guid, statistic_key, statistic_value, statistic_year_starting, updated ) VALUES 
                ( :guid, 'messages_sent', {$messages_sent}, {$year_starting}, NOW() ) 
                ON DUPLICATE KEY UPDATE statistic_value = {$messages_sent}, updated = NOW() ");
            $db->bind(':guid', $this->guid);
            $db->execute();

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}