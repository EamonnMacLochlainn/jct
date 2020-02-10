<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 13/09/2018
 * Time: 15:52
 */

namespace JCT;


use DateTime;
use DateTimeZone;
use Exception;

class SessionManager
{
    const SESSION_NAME = 'jct';
    const SESSION_TIMEZONE = 'Europe/Dublin';
    const COOKIE_LIFE = '1 day'; // how long the cookie will survive
    const COOKIE_EXPIRY = '1 hour'; // how long the cookie is valid

    private $now;

    private $_DB;
    private $session_id;

    public $cookie_life_obj;
    public $cookie_expiry_obj;

    public $session_args;


    function __construct(Database $db)
    {
        $this->_DB = $db;

        if(session_status() === PHP_SESSION_NONE)
        {
            $_SESSION[SessionManager::SESSION_NAME] = [];
            session_regenerate_id();
        }

        $this->now = new DateTime('now', new DateTimeZone($this::SESSION_TIMEZONE));
        $this->cookie_life_obj = clone $this->now;
        $this->cookie_expiry_obj = clone $this->now;

        $this->cookie_life_obj->modify('+ ' . $this::COOKIE_LIFE);
        $this->cookie_expiry_obj->modify('+ ' . $this::COOKIE_EXPIRY);
    }

    function set_session_id($session_id)
    {
        $this->session_id = $session_id;
    }

    function init_session_args()
    {
        $this->session_args = [
            'user' => [],
            'org' => []
        ];
    }




    function clear_user_session_record($user_id)
    {
        $db = $this->_DB;
        $db->query(" UPDATE user SET token = NULL, session_id = NULL, cookie_created = NULL, cookie_expiry = NULL WHERE ( id = {$user_id} ) ");
        $db->execute();
    }

    function clear_user_from_session_globals()
    {
        unset($_SESSION[self::SESSION_NAME]);

        $past = clone $this->now;
        $past->modify('-1 hour');
        setcookie(self::SESSION_NAME, '', $past->format('U'));
    }

    function recalc_cookie_expiry_times()
    {
        $this->cookie_life_obj->modify('+ ' . $this::COOKIE_LIFE);
        $this->cookie_expiry_obj->modify('+ ' . $this::COOKIE_EXPIRY);
    }

    function create_cookie_value($user_id, $guid, $role_id, $session_id, $token)
    {
        $str = $user_id . '|' . $guid . '|' . $role_id . '|' . $token . '|' . $session_id;
        return Cryptor::Encrypt($str);
    }

    function get_cookie_values($cookie_value)
    {
        try
        {
            $raw = Cryptor::Decrypt($cookie_value);
            if(empty($raw))
                throw new Exception('No Cookie value found.');

            $split = explode('|',$raw);

            if(count($split) !== 5)
                throw new Exception('Incomplete Cookie values.');

            return [
                'user_id' => $split[0],
                'org_guid' => $split[1],
                'role_id' => $split[2],
                'token' => $split[3],
                'session_id' => $split[4]
            ];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function insert_user_into_session_globals($cookie_value)
    {
        $tmp = setcookie(
            self::SESSION_NAME,
            $cookie_value,
            $this->cookie_life_obj->format('U'),
            JCT_COOKIE_PATH,
            JCT_COOKIE_DOMAIN,
            JCT_COOKIE_SECURE,
            JCT_COOKIE_HTTP_ONLY);

        $_SESSION[self::SESSION_NAME] = $this->session_args;

        return $tmp;
    }

    function save_user_session_record($user_id, $token)
    {
        $db = $this->_DB;

        $db->query(" UPDATE user SET 
        token = :token, session_id = :session_id, cookie_created = NOW(), cookie_expiry = :expiry WHERE ( id = {$user_id} ) ");
        $db->bind(':token', $token);
        $db->bind(':session_id', $this->session_id);
        $db->bind(':expiry', $this->cookie_expiry_obj->format('Y-m-d H:i:s'));
        $db->execute();
    }

    function check_current_session_is_valid()
    {
        $user_id = 0;
        try
        {
            if(!isset($_COOKIE[self::SESSION_NAME]))
                throw new Exception('Cookie missing.');

            $c = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);

            if(empty($c['user_id']))
                throw new Exception('User\'s ID missing from COOKIE.');
            if(empty($c['org_guid']))
                throw new Exception('User\'s Organisation GUID missing from COOKIE.');
            if(empty($c['role_id']))
                throw new Exception('User\'s Role ID missing from COOKIE.');
            if(empty($c['token']))
                throw new Exception('Token missing from COOKIE.');
            if(empty($c['session_id']))
                throw new Exception('SESSION ID missing from COOKIE.');

            $db = $this->_DB;
            $db->query(" SELECT session_id, cookie_expiry FROM user WHERE ( id = :user_id ) ");
            $db->bind(':user_id', $c['user_id']);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp))
                throw new Exception('User login not recorded.');


            $user_id = $c['user_id'];
            $stored_session_id = $tmp['session_id'];
            $stored_expiry = DateTime::createFromFormat('Y-m-d H:i:s', $tmp['expiry'], new DateTimeZone(self::SESSION_TIMEZONE));

            if($c['session_id'] != $stored_session_id)
                throw new Exception('COOKIE ID does not match stored value.');

            if($this->now > $stored_expiry)
                throw new Exception('User has been timed out.');


            if(!isset($_SESSION[self::SESSION_NAME]))
                throw new Exception('Session missing.');

            $s = $_SESSION[self::SESSION_NAME];

            if(empty($s['user']['id']))
                throw new Exception('User\'s ID missing from SESSION.');
            if(empty($s['org']['guid']))
                throw new Exception('User\'s Organisation GUID missing from SESSION.');
            if(empty($s['user']['role_id']))
                throw new Exception('User\'s Role ID missing from SESSION.');

            if($stored_session_id != $this->session_id)
                throw new Exception('SESSION ID does not match stored value.');

            if($c['user_id'] != $s['user']['id'])
                throw new Exception('COOKIE User ID does not match SESSION value.');
            if($c['org_guid'] != $s['org']['guid'])
                throw new Exception('COOKIE Organisation GUID does not match SESSION value.');
            if($c['role_id'] != $s['user']['role_id'])
                throw new Exception('COOKIE User Role ID does not match SESSION value.');

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage(), 'user_id'=>$user_id];
        }
    }

    function get_available_user_parameters()
    {
        $user_id = null;
        $user_role_id = null;
        $org_guid = null;

        if(!empty($_COOKIE[self::SESSION_NAME]))
        {
            $c = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);
            $user_id = (!empty($c['user_id'])) ? $c['user_id'] : null;
            $user_role_id = (!empty($c['role_id'])) ? $c['role_id'] : null;
            $org_guid = (!empty($c['org_guid'])) ? $c['org_guid'] : null;
        }

        if(!empty($_SESSION[self::SESSION_NAME]))
        {
            $s = $_SESSION[self::SESSION_NAME];
            $user_id = (!empty($s['user']['id'])) ? $s['user']['id'] : $user_id;
            $user_role_id = (!empty($s['user']['role_id'])) ? $s['user']['role_id'] : $user_role_id;
            $org_guid = (!empty($s['org']['guid'])) ? $s['org']['guid'] : $org_guid;
        }

        return [
            'id' => $user_id,
            'role_id' => $user_role_id,
            'org_guid' => $org_guid
        ];
    }

    function refresh_expiry()
    {
        $cookie_values = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);

        $db = $this->_DB;
        $db->query(" UPDATE user SET cookie_expiry = :expiry WHERE id = :user_id ");
        $db->bind(':user_id', $cookie_values['user_id']);
        $db->bind(':expiry', $this->cookie_expiry_obj->format('Y-m-d H:i:s'));
        $db->execute();
    }
}