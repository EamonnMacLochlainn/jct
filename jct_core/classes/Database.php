<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 13:52
 */

namespace JCT;


use PDO;
use PDOException;
use Exception;

class Database
{
    private $db_handler;
    private $db_stmt;

    public $db_valid;
    public $db_error;

    public function __construct($db_user, $db_pass, $db_name = null, $db_host, $charset)
    {
        if(!is_null($db_name))
            $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=' . $charset;
        else
            $dsn = 'mysql:host=' . $db_host . ';charset=' . $charset;

        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$charset'"
        ];

        try
        {
            $this->db_handler = new PDO($dsn, $db_user, $db_pass, $options);
            $this->db_handler->exec('SET NAMES ' . $charset);
            $this->db_valid = true;
        }
        catch(PDOException $e)
        {
            $this->db_error = $e->getMessage();
            $this->db_valid = false;
        }
        
        return $this->db_valid;
    }

    public function query($query)
    {
        $this->db_stmt = $this->db_handler->prepare($query);
    }

    public function bind($param, $value, $type = null)
    {
        if (is_null($type))
        {
            switch(true)
            {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->db_stmt->bindValue($param, $value, $type);
    }

    public function execute()
    {
        return $this->db_stmt->execute();
    }

    public function rowCount()
    {
        return $this->db_stmt->rowCount();
    }

    public function lastInsertId()
    {
        return $this->db_handler->lastInsertId();
    }

    public function beginTransaction()
    {
        if($this->db_handler)
            return $this->db_handler->beginTransaction();
        else return null;
    }

    public function commit()
    {
        return $this->db_handler->commit();
    }

    public function rollBack()
    {
        return $this->db_handler->rollBack();
    }

    public function debugDumpParams()
    {
        return $this->db_stmt->debugDumpParams();
    }

    public function fetchAllAssoc($key_name = null)
    {
        if(is_null($key_name))
        {
            $this->execute();
            return $this->db_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->fetchAssocWithKey($key_name);
    }

    public function fetchAssocWithKey($key_name)
    {
        $this->execute();
        $tmp = $this->db_stmt->fetchAll(PDO::FETCH_ASSOC);

        if(empty($tmp))
            return $tmp;

        $test = $tmp[0];
        if(!array_key_exists($key_name, $test))
            return $tmp;

        $num_params = count($tmp[0]);
        $arr = [];
        if($num_params > 2)
        {
            foreach($tmp as $t)
            {
                $key = $t[$key_name];
                unset($t[$key_name]);

                $arr[$key] = $t;
            }
        }
        else
        {
            foreach($tmp as $t)
            {
                $key = $t[$key_name];
                unset($t[$key_name]);

                foreach($t as $k => $v)
                    $arr[$key] = $v;
            }
        }

        return $arr;
    }

    public function fetchAllObj()
    {
        $this->execute();
        return $this->db_stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function fetchSingleObj()
    {
        $this->execute();
        return $this->db_stmt->fetch(PDO::FETCH_OBJ);
    }

    public function fetchAllColumn()
    {
        $this->execute();
        return $this->db_stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function fetchSingleAssoc()
    {
        $this->execute();
        return $this->db_stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchSingleColumn()
    {
        $this->execute();
        return $this->db_stmt->fetch(PDO::FETCH_COLUMN);
    }
}