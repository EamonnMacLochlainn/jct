<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/06/2017
 * Time: 15:54
 */

namespace JCT;


use JCT\Connection;
use JCT\Database;
use JCT\SessionManager;
use Exception;
use DateTime;
use JCT\Helper;
use JCT\Cryptor;

class User extends Connection
{
    protected $user_is_logged_in = false;
    protected $user_id = 0;
    protected $user_role_id = 99;
    protected $user_locale = 'en_GB';
    protected $org_guid;

    public $user_error;

    function __construct()
    {
        parent::__construct();
    }


    protected function set_user_from_session()
    {
        if(!empty($_SESSION[SessionManager::SESSION_NAME]))
        {
            $session_manager = new SessionManager($this->default_db_connection);
            $session_manager->set_session_id(session_id());
            $check = $session_manager->check_current_session_is_valid();

            if(isset($check['error']))
            {
                $this->user_is_logged_in = false;
                return $check;
            }

            $session_manager->refresh_expiry();
            $this->user_is_logged_in = true;
            $this->user_id = intval($_SESSION[SessionManager::SESSION_NAME]['user']['id']);
            $this->user_role_id = intval($_SESSION[SessionManager::SESSION_NAME]['user']['role_id']);
            $this->user_locale = (!empty($_SESSION[SessionManager::SESSION_NAME]['user']['prefs'])) ? $_SESSION[SessionManager::SESSION_NAME]['user']['prefs']['locale'] : 'en_GB';
            $this->org_guid = trim(strtoupper($_SESSION[SessionManager::SESSION_NAME]['org']['guid']));
        }

        return ['success'=>1];
    }
}