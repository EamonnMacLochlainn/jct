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
            'person' => [],
            'org' => []
        ];
    }




    function clear_user_session_record($person_guid)
    {
        $db = $this->_DB;
        $db->query(" UPDATE person_cookie SET 
        token = NULL, session_id = NULL, created = NULL, expiry = NULL 
        WHERE ( person_guid = :person_guid ) ");
        $db->bind(':person_guid', $person_guid);
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

    function create_cookie_value($person_guid, $org_guid, $org_type_id, $org_sub_type_id, $role_id, $session_id, $token)
    {
        $str = $person_guid . '|' . $org_guid . '|' . $org_type_id . '|' . $org_sub_type_id . '|' . $role_id . '|' . $token . '|' . $session_id;
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

            if(count($split) !== 7)
                throw new Exception('Incomplete Cookie values.');

            return [
                'person_guid' => $split[0],
                'org_guid' => $split[1],
                'org_type_id' => $split[2],
                'org_sub_type_id' => $split[3],
                'role_id' => $split[4],
                'token' => $split[5],
                'session_id' => $split[6]
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

    function save_user_session_record($person_guid, $token)
    {
        $db = $this->_DB;

        $db->query(" SELECT tbl_id FROM person_cookie WHERE ( person_guid = :person_guid ) ");
        $db->bind(':person_guid', $person_guid);
        $db->execute();
        $x = intval($db->fetchSingleColumn());

        if($x === 0)
        {
            $db->query(" INSERT INTO person_cookie 
            ( person_guid, token, session_id, created, expiry ) VALUES 
            ( :person_guid, :token, :session_id, NOW(), :expiry )" );
        }
        else
        {
            $db->query(" UPDATE person_cookie SET 
            token = :token, session_id = :session_id, created = NOW(), expiry = :expiry 
            WHERE ( person_guid = :person_guid ) ");
        }
        $db->bind(':person_guid', $person_guid);
        $db->bind(':token', $token);
        $db->bind(':session_id', $this->session_id);
        $db->bind(':expiry', $this->cookie_expiry_obj->format('Y-m-d H:i:s'));
        $db->execute();
    }

    function check_current_session_is_valid()
    {
        $person_guid = 0;
        try
        {
            if(!isset($_COOKIE[self::SESSION_NAME]))
                throw new Exception('Cookie missing.');

            $c = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);

            if(empty($c['person_guid']))
                throw new Exception('User\'s GUID missing from COOKIE.');
            if(empty($c['org_guid']))
                throw new Exception('User\'s Organisation GUID missing from COOKIE.');
            if(empty($c['role_id']))
                throw new Exception('User\'s Role ID missing from COOKIE.');
            if(empty($c['token']))
                throw new Exception('Token missing from COOKIE.');
            if(empty($c['session_id']))
                throw new Exception('SESSION ID missing from COOKIE.');

            $db = $this->_DB;
            $db->query(" SELECT session_id, expiry FROM person_cookie WHERE ( person_guid = :person_guid ) ");
            $db->bind(':person_guid', $c['person_guid']);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp))
                throw new Exception('User login not recorded.');


            $person_guid = $c['person_guid'];
            $stored_session_id = $tmp['session_id'];
            $stored_expiry = DateTime::createFromFormat('Y-m-d H:i:s', $tmp['expiry'], new DateTimeZone(self::SESSION_TIMEZONE));

            if($c['session_id'] != $stored_session_id)
                throw new Exception('COOKIE ID does not match stored value.');

            if($this->now > $stored_expiry)
                throw new Exception('User has been timed out.');


            if(!isset($_SESSION[self::SESSION_NAME]))
                throw new Exception('Session missing.');

            $s = $_SESSION[self::SESSION_NAME];

            if(empty($s['person']['guid']))
                throw new Exception('User\'s ID missing from SESSION.');
            if(empty($s['person']['role_id']))
                throw new Exception('User\'s Role ID missing from SESSION.');
            if(empty($s['org']['guid']))
                throw new Exception('User\'s Organisation GUID missing from SESSION.');

            if($stored_session_id != $this->session_id)
                throw new Exception('SESSION ID does not match stored value.');

            if($c['person_guid'] != $s['person']['guid'])
                throw new Exception('COOKIE User ID does not match SESSION value.');
            if($c['role_id'] != $s['person']['role_id'])
                throw new Exception('COOKIE User Role ID does not match SESSION value.');
            if($c['org_guid'] != $s['org']['guid'])
                throw new Exception('COOKIE Organisation GUID does not match SESSION value.');

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage(), 'person_guid'=>$person_guid];
        }
    }

    function get_available_user_parameters()
    {
        $person_guid = null;
        $user_role_id = null;
        $org_guid = null;
        $org_type_id = null;
        $org_sub_type_id = null;

        if(!empty($_COOKIE[self::SESSION_NAME]))
        {
            $c = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);
            $person_guid = (!empty($c['person_guid'])) ? $c['person_guid'] : null;
            $user_role_id = (!empty($c['role_id'])) ? $c['role_id'] : null;
            $org_guid = (!empty($c['org_guid'])) ? $c['org_guid'] : null;
            $org_type_id = (!empty($c['org_type_id'])) ? $c['org_type_id'] : null;
            $org_sub_type_id = (!empty($c['org_sub_type_id'])) ? $c['org_sub_type_id'] : null;
        }

        if(!empty($_SESSION[self::SESSION_NAME]))
        {
            $s = $_SESSION[self::SESSION_NAME];
            $person_guid = (!empty($s['person']['guid'])) ? $s['person']['guid'] : $person_guid;
            $user_role_id = (!empty($s['user']['role_id'])) ? $s['user']['role_id'] : $user_role_id;
            $org_guid = (!empty($s['org']['guid'])) ? $s['org']['guid'] : $org_guid;
            $org_type_id = (!empty($s['org']['type_id'])) ? $s['org']['type_id'] : $org_type_id;
            $org_sub_type_id = (!empty($s['org']['sub_type_id'])) ? $s['org']['sub_type_id'] : $org_sub_type_id;
        }

        return [
            'person_guid' => $person_guid,
            'role_id' => $user_role_id,
            'org_guid' => $org_guid,
            'org_type_id' => $org_type_id,
            'org_sub_type_id' => $org_sub_type_id
        ];
    }

    function refresh_expiry()
    {
        $cookie_values = $this->get_cookie_values($_COOKIE[self::SESSION_NAME]);

        $db = $this->_DB;
        $db->query(" UPDATE person_cookie SET expiry = :expiry WHERE person_guid = :person_guid ");
        $db->bind(':person_guid', $cookie_values['person_guid']);
        $db->bind(':expiry', $this->cookie_expiry_obj->format('Y-m-d H:i:s'));
        $db->execute();
    }
}