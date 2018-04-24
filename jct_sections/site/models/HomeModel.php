<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\site;


use JCT\Database;
use Exception;
use JCT\Helper;

class HomeModel
{
    private $_DB;
    private $_Permissions_Registry;

    public $data;

    function __construct(Database $db, $permissions_registry)
    {
        $this->_DB = $db;
        $this->_Permissions_Registry = $permissions_registry;
    }

    function index()
    {
    }






    function get_connection()
    {
        return $this->_DB;
    }


    function check_user_password($username, $submitted_pass)
    {
        $this->_DB->query(" SELECT id, position, org, password FROM user WHERE username = :username ");
        $this->_DB->bind(':username', $username);
        $this->_DB->execute();
        $tmp = $this->_DB->fetchSingleAssoc();

        if( (empty($tmp)) || (!password_verify($submitted_pass, $tmp['password'])) )
            return ['error'=>'Invalid Login.'];

        unset($tmp['password']);
        return $tmp;
    }

    function save_user_session_id($user_id, $session_id)
    {
        try
        {
            $this->_DB->query(" UPDATE user SET session_id = :session_id WHERE id = :id ");
            $this->_DB->bind(':session_id', $session_id);
            $this->_DB->bind(':id', $user_id);
            $this->_DB->execute();

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function logout()
    {
        unset($_SESSION['jct']);
        header('location: ' . JCT_URL_ROOT);
    }

    function get_id_email_for_username($username)
    {
        $this->_DB->query(" SELECT u.id, p.email  
            FROM user u 
            LEFT JOIN person p on u.id = p.id 
            WHERE username = :username ");
        $this->_DB->bind(':username', $username);
        $this->_DB->execute();
        return $this->_DB->fetchSingleAssoc();
    }

    function reset_user_password($user_id, $hashed_password)
    {
        try
        {
            $this->_DB->query(" UPDATE user SET password = :password WHERE id = :id ");
            $this->_DB->bind(':password', $hashed_password);
            $this->_DB->bind(':id', $user_id);
            $this->_DB->execute();

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}