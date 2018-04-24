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

class balance implements api_interface
{
    public $guid;

    private $operator;
    private $operator_username;
    private $operator_password;

    private $_DB;
    private $_ORG_DB;

    public $success = 0;
    public $error = 0;
    public $response;


    function __construct(Database $_DB, Database $_ORG_DB, $guid, $input = null)
    {
        try
        {
            $this->_DB = $_DB;
            $this->_ORG_DB = $_ORG_DB;
            $this->guid = $guid;
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
            $tmp = $this->get_org_operator();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $fn = 'get_' . $this->operator . '_balance';
            if(!method_exists($this, $fn))
                throw new Exception('No method found to retrieve Organisation\'s balance from their set Operator.');

            $this->$fn();
            try
            {
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

    function get_neon_balance()
    {
        try
        {
            $db = $this->_DB;

            $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = :operator AND param_key = 'balance_uri' ) ");
            $db->bind(':operator', $this->operator);
            $db->execute();
            $balance_uri = $db->fetchSingleColumn();

            if(empty($balance_uri))
                throw new Exception('No Balance URI was found for this Organisation\'s operator.');

            $query_string = $balance_uri . '?user=' . $this->operator_username . '&clipwd=' . $this->operator_password;
            $operator_response = file_get_contents($query_string);
            if(empty($operator_response))
                throw new Exception('No response was detected from the operator.');

            $balance_parts = explode(':', $operator_response);
            $balance_parts = array_map('trim', $balance_parts);

            if(strtoupper($balance_parts[0]) == 'ERR')
                throw new Exception('The Operator returned an error: ' . $balance_parts[1]);

            $arr = [
                'account' => $this->operator_username,
                'current_balance' => $balance_parts[1],
                'texts_sent_today' => $balance_parts[2],
                'credits_used_today' => $balance_parts[3]
            ];

            $this->success = 1;
            $this->response = $arr;
            return true;
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
            return false;
        }
    }

    private function update_statistics()
    {
        if($this->error)
            return false;

        try
        {
            $db = $this->_ORG_DB;
            $db->query(" SELECT setting_value FROM manager_setting WHERE ( setting_key = 'year_starting' ) ");
            $db->execute();
            $year_starting = $db->fetchSingleColumn();

            if(empty($year_starting))
                throw new Exception('Current year_starting not set in general settings.');

            $balance = (is_array($this->response)) ? $this->response['current_balance'] : null;
            if($balance === null)
                throw new Exception('current_balance not set in response.');

            $db = $this->_DB;
            $db->query(" INSERT INTO org_statistics 
                ( guid, statistic_key, statistic_value, statistic_year_starting, updated ) VALUES 
                ( :guid, 'last_balance', {$balance}, {$year_starting}, NOW() ) 
                ON DUPLICATE KEY UPDATE statistic_value = {$balance}, updated = NOW() ");
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