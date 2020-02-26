<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\site;


use JCT\Connection;
use JCT\Database;
use Exception;
use JCT\Helper;
use JCT\SessionManager;

class HomeModel
{
    private $_DB;

    public $data;

    function __construct(Database $db)
    {
        $this->_DB = $db;
    }

    function index()
    {
    }








    // for login

    function get_connection()
    {
        return $this->_DB;
    }

    function check_user_password($username, $submitted_pass)
    {
        $db = $this->_DB;
        $db->query(" SELECT guid, password 
        FROM person 
        WHERE ( 
            username = :username AND 
            active_on_system = 1 
        ) ");
        $db->bind(':username', $username);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if( (empty($tmp)) || (!password_verify($submitted_pass, $tmp['password'])) )
            return ['error'=>'Invalid Login.'];

        return [ 'person_guid'=>$tmp['guid'] ];
    }

    function get_user_orgs($person_guid)
    {
        $db = $this->_DB;

        $db->query(" SELECT o.guid, o.title, o.type_id, ot.slug as org_type_slug, o.sub_type_id     
        FROM person_org po 
        LEFT JOIN org o on po.org_guid = o.guid 
        LEFT JOIN prm_org_type ot on o.type_id = ot.id 
        WHERE ( 
            po.person_guid = :person_guid AND 
            po.active_in_role = 1 
        )  
        GROUP BY o.guid ");
        $db->bind(':person_guid', $person_guid);
        $db->execute();
        return $db->fetchAllAssoc();
    }

    function get_user_roles_for_org($person_guid, $org_guid)
    {
        $db = $this->_DB;

        $db->query(" SELECT pr.id, pr.title, pr.slug as user_role_slug    
         FROM person_org po 
         LEFT JOIN prm_role pr on po.role_id = pr.id 
         WHERE ( 
             po.person_guid = :person_guid AND 
             org_guid = :org_guid 
         )  
         ORDER BY pr.title ");
        $db->bind(':person_guid', $person_guid);
        $db->bind(':org_guid', $org_guid);
        $db->execute();
        return $db->fetchAllAssoc();
    }

    function get_user_settings_in_org($person_guid, $org_guid)
    {
        $db = $this->_DB;

        $preferences = ['locale']; // prefs we're looking for
        $preferences_str = '\'' . implode('\',\'',$preferences) . '\'';

        $db->query(" SELECT setting_key, setting_value 
        FROM person_setting  
        WHERE ( 
            person_guid = :person_guid AND 
            org_guid = :org_guid AND 
            setting_key IN ({$preferences_str}) 
        ) ");
        $db->bind(':person_guid', $person_guid);
        $db->bind(':org_guid', $org_guid);
        $db->execute();
        return $db->fetchAllAssoc('setting_key', true);
    }


    // logout user

    function logout()
    {
        $s = new SessionManager($this->_DB);
        $c = $s->get_available_user_parameters();
        $person_guid = $c['person_guid'];

        if($person_guid === null)
        {
            header('location: ' . JCT_URL_ROOT);
            return false;
        }

        $s->clear_user_session_record($person_guid);
        unset($_SESSION[SessionManager::SESSION_NAME]);
        setcookie(SessionManager::SESSION_NAME, '', time()-3600, JCT_COOKIE_PATH, JCT_COOKIE_DOMAIN);
        header('location: ' . JCT_URL_ROOT);
        return false;
    }
}