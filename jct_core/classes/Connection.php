<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/06/2017
 * Time: 12:31
 */

namespace JCT;


use Exception;

class Connection
{
    public $connection_error = '';
    public $instances = [];

    private $default_db_instance_name = JCT_DB_SIUD_USER . '@' . JCT_DB_SIUD_HOST . ':' . JCT_DB_SIUD_NAME;

    protected $default_db_connection;
    protected $default_connection_ok = false;

    function __construct()
    {
        $this->default_connection_ok = $this->set_default_connection();
    }

    private function set_default_connection()
    {
        $tmp = $this->get_connection($this->default_db_instance_name);
        if(!$tmp)
            $tmp = $this->initialise_database_connection(JCT_DB_SIUD_HOST, JCT_DB_SIUD_NAME, JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS);

        if(!$tmp)
            return false;

        $this->default_db_connection = $this->get_connection($this->default_db_instance_name);
        return true;
    }

    function get_connection($instance_name)
    {
        if(array_key_exists($instance_name, $this->instances))
            return $this->instances[$instance_name];
        else
            return false;
    }

    private function initialise_database_connection($db_host, $db_name, $db_user, $db_pass, $charset = 'utf8')
    {
        try
        {
            $instance_name = $db_user . '@' . $db_host . ':' . $db_name;
            if(array_key_exists($instance_name, $this->instances))
                return true;

            $db = new Database($db_user, $db_pass, $db_name, $db_host, $charset);
            if(!$db->db_valid)
            {
                if(!empty($db->db_error))
                    throw new Exception('A valid database connection could not be established: ' . $db->db_error);

                throw new Exception('A valid database connection could not be established.');
            }

            $this->instances[$instance_name] = $db;
            return true;
        }
        catch(Exception $e)
        {
            $this->connection_error = $e->getMessage();
            return false;
        }
    }
}