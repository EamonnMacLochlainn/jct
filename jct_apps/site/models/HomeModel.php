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

        $db->query(" SELECT o.guid, o.title, o.type_id, o.sub_type_id     
        FROM person_org po 
        LEFT JOIN org o on po.org_guid = o.guid
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

        $db->query(" SELECT pr.id, pr.title  
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








    /*function get_roles_of_user($user_id, $org_id)
    {
        $this->_DB->query(" SELECT role_id 
        FROM person_org  
        WHERE ( 
            id = {$user_id} AND 
            org_id = {$org_id} AND 
            active = 1 
        ) ");
        $this->_DB->execute();
        return $this->_DB->fetchAllColumn();
    }


    function set_org_connection($org_id)
    {
        $this->_DB->query(" SELECT host_name, db_name FROM org_details WHERE id = :org_id ");
        $this->_DB->bind(':org_id', $org_id);
        $this->_DB->execute();
        $org_db_settings = $this->_DB->fetchSingleAssoc();

        $connection = new Connection();
        $tmp = $connection->set_org_connection($org_db_settings['host_name'], $org_db_settings['db_name']);
        if($tmp === false)
            return ['error' => 'No database connection could be established for this User\'s assigned Organisation: ' . $connection->connection_error];

        $instance_name = JCT_DB_SIUD_USER . '@' . $org_db_settings['host_name'] . ':' . $org_db_settings['db_name'];
        $tmp = $connection->get_connection($instance_name);
        if($tmp === false)
            return ['error'=>$connection->connection_error];

        return $tmp;
    }

    function get_email_account_by_username($username, Database $databiz_db)
    {
        $databiz_db->query(" SELECT * FROM email_account WHERE (username = :username) ");
        $databiz_db->bind(':username', $username);
        $databiz_db->execute();
        return $databiz_db->fetchSingleAssoc();
    }

    function get_user_app_screen_permissions(Database $org_db, $user_id)
    {
        try
        {
            $org_db->query(" SELECT app_slug, module, model, method FROM app_screen_user WHERE id = :id ");
            $org_db->bind(':id', $user_id);
            $org_db->execute();
            $tmp = $org_db->fetchAllAssoc();

            if(empty($tmp))
                throw new Exception('No Permissions found for this User ID in this Organisation database.');

            $arr = [];
            foreach($tmp as $t)
            {
                $app_slug = $t['app_slug'];
                $module = $t['module'];
                $model = $t['model'];
                $method = $t['method'];

                if( (empty($app_slug)) || (empty($model)) || (empty($method)) )
                    continue;

                if(!isset($arr[$app_slug]))
                    $arr[$app_slug] = [];

                if(!empty($module))
                {
                    if(!isset($arr[$app_slug][$module]))
                        $arr[$app_slug][$module] = [];

                    if(!isset($arr[$app_slug][$module][$model]))
                        $arr[$app_slug][$module][$model] = [];

                    $arr[$app_slug][$module][$model] = json_decode($method);
                }
                else
                {
                    if(!isset($arr[$app_slug][$model]))
                        $arr[$app_slug][$model] = [];

                    $arr[$app_slug][$model] = json_decode($method);
                }
            }

            return $arr;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_user_name_values(Database $org_db, $user_id)
    {
        try
        {
            $org_db->query(" SELECT title as salutation, fname, lname, salute_name 
                FROM person u 
                LEFT JOIN prm_salutation s ON ( u.salt_id = s.id ) 
                WHERE u.id = :id ");
            $org_db->bind(':id', $user_id);
            $org_db->execute();
            $tmp = $org_db->fetchSingleAssoc();

            $arr = [];
            if(empty($tmp))
            {
                $arr['salutation'] = '';
                $arr['fname'] = '';
                $arr['lname'] = '';
                $arr['salute_name'] = '';
            }
            else
                $arr = $tmp;

            return $arr;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_user_session_params()
    {
        return false;
    }




    // for new password

    function get_user_for_contact_params($mobile, $email)
    {
        $db = $this->_DB;

        $db->query(" SELECT id, mobile, email FROM user WHERE ( mobile = :mobile OR email = :email ) ");
        $db->bind(':mobile', $mobile);
        $db->bind(':email', $email);
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        if(empty($tmp))
            return ['error'=>'no_user'];

        return ['success'=>1, 'user'=>$tmp];
    }

    function update_user_password($id, $pass, $email, $mobile)
    {
        $db = $this->_DB;
        $db->beginTransaction();
        try
        {
            $db->query(" UPDATE user SET 
                pass = :pass, email = :email, mobile = :mobile, updated = NOW(), updated_by = :id 
                WHERE id = :id ");
            $db->bind(':id', $id);
            $db->bind(':pass', $pass);
            $db->bind(':email', $email);
            $db->bind(':mobile', $mobile);
            $db->execute();

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }





    // relogging


    function get_org_by_guid($org_guid)
    {
        $db = $this->_DB;

        $db->query(" SELECT uo.guid, od.org_name, od.id, od.public_contact   
            FROM user_org uo 
            LEFT JOIN org_details od ON ( uo.guid = od.guid )
            WHERE uo.guid = :guid  
            GROUP BY uo.guid 
            ORDER BY org_name ASC ");
        $db->bind(':guid', $org_guid);
        $db->execute();
        return $db->fetchSingleAssoc();
    }*/
}