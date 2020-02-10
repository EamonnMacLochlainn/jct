<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 01/03/2018
 * Time: 12:40
 */


namespace JCT;

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Database.php';
require_once '../ds_core/classes/Helper.php';

use JCT\Helper;
use Exception;

class call_api
{
    private $required_fields = [
        'username', 'private_key', 'app', 'action'
    ];


    private $guid;
    private $username;
    private $private_key;
    private $app;
    private $action;

    private $_DB;
    private $_ORG_DB;

    private $input;
    public $remote_api_call;

    public $success = 0;
    public $error = 0;
    public $response;


    function __construct($input)
    {
        try
        {
            $tmp = $this->parse_input($input);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->validate_user();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->get_db_connections();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $this->execute_app_handler();
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
        }
    }

    private function parse_input($input)
    {
        try
        {
            if(empty($input))
                throw new Exception('No input detected.');


            $input = array_change_key_case($input, CASE_LOWER);

            foreach($this->required_fields as $required_field)
            {
                if (empty($input[$required_field]))
                    throw new Exception('Input missing required field');

                $this->$required_field = trim(htmlentities($input[$required_field]));

                if(empty($this->$required_field))
                    throw new Exception('Input missing required field');

                unset($input[$required_field]);
            }

            $this->remote_api_call = 0;
            if(isset($input['remote_api_call']))
            {
                $this->remote_api_call = intval($input['remote_api_call']);
                unset($input['remote_api_call']);
            }
            $this->input = $input;

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function validate_user()
    {
        return true;

        /* try
         {
             $roll_number = trim(strtoupper($this->username));
             if($roll_number === 'DATABIZ')
                 return ($this->private_key == '2154910792');

             $raw_pass = trim($this->private_key);

             $pass = AsciiEncrypt::decrypt($raw_pass);
             $roll_number_arr = str_split($roll_number, 1);
             // use 2nd, 4th, 5th, and 6th chars
             unset($roll_number_arr[0]);
             unset($roll_number_arr[2]);
             $tmp = implode('', $roll_number_arr);

             if( strtoupper($tmp) !== strtoupper($pass) )
                 throw new Exception('Ascii authentication failed.', 19);

             return true;
         }
         catch(Exception $e)
         {
             return ['error'=>$e->getMessage()];
         }*/
    }

    private function get_db_connections()
    {
        /**
         * manager:member_update calls will come in from school on either the pilot OP system or the new Apps system
         * Therefore we first determine which system is to be updated, and pass the execute call on to the
         * relevant script.
         *
         * We do this now as those schools on the pilot OP system will have a different DB connections to those on the
         * App system.
         */

        $core_db = $this->set_core_db_connection();
        if( (is_array($core_db)) && (isset($core_db['error'])) )
            return ['error'=>$core_db['error']];

        if($this->action == 'member_update')
        {
            $core_db->query(" SELECT setting_value FROM org_setting WHERE guid = '{$this->guid}' AND setting_key = 'on_pilot_payments'; ");
            $core_db->execute();
            $using_pilot_system = (intval($core_db->fetchSingleColumn()) > 0);

            if($using_pilot_system)
            {
                /**
                 * If using the pilot system, both the core and org DBs will be different to the Apps norm.
                 * We reset the core DB, rename the action to a script for that purpose, and set the org DB.
                 */

                $core_db = new Database('databizs_authU', 'authPass', 'databizs_authorisation', 'localhost', 'utf8');
                if(!$core_db->db_valid)
                    return ['error'=>'Authorisation DB connection is invalid.'];

                $guid_lwr = strtolower($this->guid);
                $org_db = new Database('databizs_orgU', 'orgPass', 'databizs_op_' . $guid_lwr, 'localhost', 'utf8');
                if(!$org_db->db_valid)
                    return ['error'=>'Org Pilot DB connection is invalid.'];

                $this->_DB = $core_db;
                $this->_ORG_DB = $org_db;
                $this->action = 'pilot_member_update';

                return true;
            }
            else
                return $this->get_apps_db_connection($core_db);
        }
        else
            return $this->get_apps_db_connection($core_db);
    }

    private function get_apps_db_connection(Database $core_db)
    {

        $core_db->query(" SELECT guid, host_name, db_name FROM org_details WHERE UPPER(guid) = UPPER(:username) ");
        $core_db->bind(':username', $this->username);
        $core_db->execute();
        $tmp = $core_db->fetchSingleAssoc();

        if( (empty($tmp)) || (empty($tmp['host_name'])) || (empty($tmp['db_name'])) )
            return ['error'=>'User DB connection could not be set.'];

        $this->guid = $tmp['guid'];
        $host_name = $tmp['host_name'];
        $db_name = $tmp['db_name'];

        $org_connection = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $db_name, $host_name, 'utf8');
        if(!$org_connection->db_valid)
            return ['error'=>'User DB connection is invalid.'];

        $this->_ORG_DB = $org_connection;

        return true;
    }

    private function set_core_db_connection()
    {
        $core_db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
        if(!$core_db->db_valid)
            return ['error'=>$core_db->db_error];

        $this->_DB = $core_db;
        return $core_db;
    }

    private function execute_app_handler()
    {
        try
        {
            $path = JCT_PATH_ROOT . 'api' . JCT_DE . $this->app;
            if(!is_dir($path))
                throw new Exception('App directory not recognised.');

            $class_name = strtolower($this->action);
            $class_path = $path . JCT_DE . $class_name . '.php';

            if(!is_readable($class_path))
                throw new Exception('App handler for the action `' . $class_name . '` not found.');

            require_once $class_path;

            $qualified_class_name = '\DS\\' . $class_name;
            $class = new $qualified_class_name($this->_DB, $this->_ORG_DB, $this->guid, $this->input);

            if(!method_exists($class, 'execute'))
                throw new Exception('No executable function found for this action\'s API class.');

            if(property_exists($class, 'remote_api_call'))
                $class->remote_api_call = $this->remote_api_call;

            $class->execute();
            $this->error = $class->error;
            $this->success = $class->success;
            $this->response = $class->response;

            return true;
        }
        catch(Exception $e)
        {
            $this->error = 1;
            $this->response = $e->getMessage();
            return false;
        }

    }

    function response()
    {
        $arr = [];
        if(!$this->remote_api_call)
        {
            if($this->success == 1)
                $arr['success'] = 1;
            else if($this->error == 1)
                $arr['error'] = 1;

            $arr['response'] = $this->response;
        }
        else
            $arr = $this->response;

        return $arr;
    }
}