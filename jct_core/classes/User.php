<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/06/2017
 * Time: 15:54
 */

namespace JCT;



class User extends Connection
{
    protected $user_is_logged_in = false;
    protected $person_guid;
    protected $user_role_id;
    protected $user_locale = 'en_GB';
    protected $org_guid;
    protected $org_type_id;
    protected $org_sub_type_id;

    function __construct()
    {
        parent::__construct();
    }


    protected function set_user_from_session()
    {
        if(empty($_SESSION[SessionManager::SESSION_NAME]))
            return ['person_guid'=>0, 'status'=>'Not logged in'];

        $session_manager = new SessionManager($this->default_db_connection);
        $session_manager->set_session_id(session_id());
        $check = $session_manager->check_current_session_is_valid();

        if(isset($check['error']))
            return ['person_guid'=>$check['person_guid'], 'status'=>$check['error']];

        $session_manager->refresh_expiry();
        $this->user_is_logged_in = true;
        $this->person_guid = intval($_SESSION[SessionManager::SESSION_NAME]['person']['guid']);
        $this->user_role_id = intval($_SESSION[SessionManager::SESSION_NAME]['person']['role_id']);
        $this->user_locale = (!empty($_SESSION[SessionManager::SESSION_NAME]['person']['prefs'])) ? $_SESSION[SessionManager::SESSION_NAME]['person']['prefs']['locale'] : 'en_GB';
        $this->org_guid = trim(strtoupper($_SESSION[SessionManager::SESSION_NAME]['org']['guid']));
        $this->org_type_id = intval($_SESSION[SessionManager::SESSION_NAME]['org']['type_id']);
        $this->org_sub_type_id = intval($_SESSION[SessionManager::SESSION_NAME]['org']['sub_type_id']);


        return ['person_guid'=>$this->person_guid, 'status'=>'ok'];
    }
}