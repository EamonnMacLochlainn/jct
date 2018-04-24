<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 26/03/2018
 * Time: 18:02
 */

namespace JCT;


use Exception;

class Neon
{
    private $_DB;
    private $org_guid;

    private $username;
    private $password;

    private $settings;

    private $balance_uri;

    public $current_balance;
    public $texts_sent_today;
    public $credits_used_today;


    public $error;

    function __construct($org_guid, Database $_DB = null)
    {
        try
        {
            if(is_readable('../../ds_core/Config.php'))
                require_once '../../ds_core/Config.php';
            else
                throw new Exception('Config file not found.');

            if(is_readable('../../ds_core/classes/Cryptor.php'))
                require_once '../../ds_core/classes/Cryptor.php';
            else
                throw new Exception('Encryption file not found.');


            $this->org_guid = trim($org_guid);

            if(!is_null($_DB))
                $this->_DB = $_DB;
            else
            {
                $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_HOST, JCT_DB_SIUD_HOST);
                if(!$db->db_valid)
                    throw new Exception('Dynamic database connection not valid.');
            }

            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    function set_credentials($username = null, $password = null)
    {
        if( (!is_null($username)) && (!is_null($password)) )
        {
            $this->username = $username;
            $this->password = $password;
            return true;
        }

        $db = $this->_DB;

        $db->query(" SELECT username, password FROM messaging_org_operator WHERE ( guid = :guid AND operator_end IS NULL ) ");
        $db->bind(':guid', $this->org_guid);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if( (empty($tmp['username'])) || (empty($tmp['password'])) )
            return ['error'=>'Credentials could not be set.'];

        $this->username = Cryptor::Decrypt($tmp['username']);
        $this->password = Cryptor::Decrypt($tmp['password']);

        return true;
    }

    private function get_operator_param($param_key)
    {
        $db = $this->_DB;

        $db->query(" SELECT param_value FROM messaging_operator_params WHERE ( operator = 'neon' AND param_key = :param_key ) ");
        $db->bind(':param_key', $param_key);
        $db->execute();
        return $db->fetchSingleColumn();
    }

    function set_settings()
    {
        $db = $this->_DB;

        $db->query(" SELECT setting_key, setting_value FROM messaging_operator_settings WHERE operator = 'neon' ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        if(empty($tmp))
            return ['error'=>'No settings found for this Operator.'];

        $this->settings = [];
        foreach($tmp as $i => $s)
            $this->settings[ $s['setting_key'] ] = $s['setting_value'];

        return true;
    }

    function get_settings()
    {
        return $this->settings;
    }

    function set_balance_uri($balance_uri = null)
    {
        if(!is_null($balance_uri))
        {
            $this->balance_uri = $balance_uri;
            return true;
        }

        $balance_uri = $this->get_operator_param('balance_uri');
        if(empty($balance_uri))
            return ['error'=>'Balance URI could not be set.'];

        $this->balance_uri = $balance_uri;

        return true;
    }

    function set_balance()
    {
        try
        {
            if(empty($this->balance_uri))
            {
                $tmp = $this->set_balance_uri();
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);
            }

            if( (empty($this->username)) || (empty($this->password)) )
            {
                $tmp = $this->set_credentials();
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);
            }



            $query_string = $this->balance_uri . '?user=' . $this->username . '&clipwd=' . $this->password;
            $operator_response = file_get_contents($query_string);
            if(empty($operator_response))
                throw new Exception('No response was detected from Balance URI.');

            $balance_parts = explode(':', $operator_response);
            $balance_parts = array_map('trim', $balance_parts);

            if(strtoupper($balance_parts[0]) == 'ERR')
                throw new Exception('The Operator returned an error: ' . $balance_parts[1]);

            $this->current_balance = $balance_parts[1];
            $this->texts_sent_today = $balance_parts[2];
            $this->credits_used_today = $balance_parts[3];

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>'An error occurred when retrieving the Account balance from the Operator: ' . $e->getMessage()];
        }
    }
}