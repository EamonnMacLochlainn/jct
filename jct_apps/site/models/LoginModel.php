<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/05/2016
 * Time: 13:41
 */

namespace JCT\site;


use JCT\Connection;
use JCT\Database;
use JCT\AppRegistry;
use JCT\Router;
use JCT\SessionManager;
use Exception;
use DateTime;
use JCT\Helper;

class LoginModel
{
    public $_DB;

    public $data;

    function __construct(Database $db)
    {
        $this->_DB = $db;
    }

    function index()
    {
    }



    // for login

    function check_user_password($email, $submitted_pass)
    {
        $this->_DB->query(" SELECT id, pass FROM person WHERE email = :email ");
        $this->_DB->bind(':email', $email);
        $this->_DB->execute();
        $tmp = $this->_DB->fetchSingleAssoc();

        if( (empty($tmp)) || (!password_verify($submitted_pass, $tmp['pass'])) )
            return ['error'=>'Invalid Login.'];

        return [ 'user_id'=>$tmp['id']];
    }

    function check_user_orgs($user_id)
    {
        $db = $this->_DB;

        $db->query(" SELECT uo.guid, od.org_name, od.id, od.public_contact   
            FROM user_org uo 
            LEFT JOIN org_details od ON ( uo.guid = od.guid )
            WHERE uo.id = :id  
            GROUP BY uo.guid 
            ORDER BY org_name ASC ");
        $db->bind(':id', $user_id);
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        if(empty($tmp))
            return $tmp;

        $orgs = [];
        foreach($tmp as $t)
            $orgs[] = [
                'guid' => $t['guid'],
                'org_name' => $t['org_name'],
                'id' => $t['id'],
                'public_contact' => (empty($t['public_contact'])) ? '' : json_decode($t['public_contact'], true)
            ];

        return $orgs;
    }

    function get_email_account_by_username($username, Database $databiz_db)
    {
        $databiz_db->query(" SELECT * FROM email_account WHERE (username = :username) ");
        $databiz_db->bind(':username', $username);
        $databiz_db->execute();
        return $databiz_db->fetchSingleAssoc();
    }

    function get_org_roles(Database $org_db)
    {
        $org_db->query(" SELECT id, title, attribute AS rank 
          FROM prm_staff_role WHERE 1 ORDER BY attribute ASC ");
        $org_db->execute();
        return $org_db->fetchAllAssoc('id');
    }

    function get_user_org_roles($user_id, $org_guid)
    {
        $this->_DB->query(" SELECT role_id FROM user_org WHERE ( id = :id AND guid = :guid AND active = 1 ) ");
        $this->_DB->bind(':id', $user_id);
        $this->_DB->bind(':guid', $org_guid);
        $this->_DB->execute();
        return $this->_DB->fetchAllColumn();
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



    // logout user

    function logout()
    {
        unset($_SESSION[SessionManager::SESSION_NAME]);
        setcookie(SessionManager::SESSION_NAME, '', time()-3600, JCT_COOKIE_PATH, JCT_COOKIE_DOMAIN);

        header('location: ' . JCT_URL_ROOT);
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
    }
}