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
use JCT\SectionRegistry;
use JCT\Router;
use Exception;
use DateTime;
use JCT\Helper;

class LoginModel
{
    public $_DB;
    public $_ORG_DB;
    private $_App_Registry;

    private $user_id;
    private $user_app_screen_permissions;
    private $user_name_values;

    private $org_id;
    private $org_guid;
    private $org_host_name;
    private $org_db_name;
    private $org_name;
    private $org_public_contact;

    public $data;

    function __construct(Database $db, Database $org_db = null, SectionRegistry $app_registry)
    {
        $this->_DB = $db;
        $this->_ORG_DB = $org_db;
        $this->_App_Registry = $app_registry;
    }

    function index()
    {
        $this->data['crest_src'] = '';

        if(is_readable(JCT_PATH_MEDIA . 'DATABIZ' . JCT_DE . 'assets' . JCT_DE . 'crest.png'))
            $this->data['crest_src'] = JCT_URL_MEDIA . 'DATABIZ/assets/crest.png';
    }


    // for screen

    function get_countries()
    {
        $this->_DB->query(" SELECT title, UPPER(attribute) AS attribute 
            FROM prm_country WHERE 1 
            ORDER BY (title = 'Ireland') DESC, (title = 'United Kingdom') DESC, title ASC ");
        $this->_DB->execute();
        return $this->_DB->fetchAllAssoc();
    }


    // for login

    function check_user_password($email, $submitted_pass)
    {
        $this->_DB->query(" SELECT id, pass FROM user WHERE email = :email ");
        $this->_DB->bind(':email', $email);
        $this->_DB->execute();
        $tmp = $this->_DB->fetchSingleAssoc();

        if( (empty($tmp)) || (!password_verify($submitted_pass, $tmp['pass'])) )
            return ['error'=>'Invalid Login.'];

        $this->user_id = $tmp['id'];
        return [ 'success'=>1 ];
    }

    function check_user_orgs()
    {
        $this->_DB->query(" SELECT uo.guid, od.org_name, od.id, od.public_contact   
            FROM user_org uo 
            LEFT JOIN org_details od ON ( uo.guid = od.guid )
            WHERE uo.id = :id  
            GROUP BY uo.guid 
            ORDER BY org_name ASC ");
        $this->_DB->bind(':id', $this->user_id);
        $this->_DB->execute();
        $tmp = $this->_DB->fetchAllAssoc();

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

    function set_user_org($set_org)
    {
        $this->org_id = $set_org['id'];
        $this->org_guid = $set_org['guid'];
        $this->org_name = $set_org['org_name'];
        $this->org_public_contact = $set_org['public_contact'];
    }

    function set_org_connection()
    {
        $this->_DB->query(" SELECT host_name, db_name FROM org_details WHERE id = :org_id ");
        $this->_DB->bind(':org_id', $this->org_id);
        $this->_DB->execute();
        $org_db_settings = $this->_DB->fetchSingleAssoc();

        $connection = new Connection();
        $tmp = $connection->set_org_connection($org_db_settings['host_name'], $org_db_settings['db_name']);
        if($tmp === false)
            return ['error' => 'No database connection could be established for this User\'s assigned Organisation'];

        $instance_name = JCT_DB_SIUD_USER . '@' . $org_db_settings['host_name'] . ':' . $org_db_settings['db_name'];
        $tmp = $connection->get_connection($instance_name);
        if($tmp === false)
            return ['error'=>$connection->connection_error];

        $this->org_host_name = $org_db_settings['host_name'];
        $this->org_db_name = $org_db_settings['db_name'];
        $this->_ORG_DB = $tmp;

        return true;
    }

    function get_org_roles()
    {
        $this->_ORG_DB->query(" SELECT id, title, attribute AS rank 
          FROM prm_staff_role WHERE 1 ORDER BY attribute ASC ");
        $this->_ORG_DB->execute();
        return $this->_ORG_DB->fetchAllAssoc('id');
    }

    function get_user_org_roles()
    {
        $this->_DB->query(" SELECT role FROM user_org WHERE ( id = :id AND guid = :guid ) ");
        $this->_DB->bind(':id', $this->user_id);
        $this->_DB->bind(':guid', $this->org_guid);
        $this->_DB->execute();
        return $this->_DB->fetchAllColumn();
    }

    function get_user_app_screen_permissions()
    {
        try
        {
            $this->_ORG_DB->query(" SELECT app_slug, module, model, method FROM app_screen_user WHERE id = :id ");
            $this->_ORG_DB->bind(':id', $this->user_id);
            $this->_ORG_DB->execute();
            $tmp = $this->_ORG_DB->fetchAllAssoc();

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

            $this->user_app_screen_permissions = $arr;
            return $arr;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_user_name_values()
    {
        try
        {
            $this->_ORG_DB->query(" SELECT title as salutation, fname, lname, salute_name 
                FROM person u 
                LEFT JOIN prm_salutation s ON ( u.salt_id = s.id ) 
                WHERE u.id = :id ");
            $this->_ORG_DB->bind(':id', $this->user_id);
            $this->_ORG_DB->execute();
            $tmp = $this->_ORG_DB->fetchSingleAssoc();

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

            $this->user_name_values = $arr;

            return $arr;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_user_session_params()
    {
        return [
            'id' => $this->user_id,
            'org' => [
                'guid' => $this->org_guid,
                'name' => $this->org_name,
                'host_name' => $this->org_host_name,
                'db_name' => $this->org_db_name,
                'public_contact' => $this->org_public_contact
            ],
            'permissions' => $this->user_app_screen_permissions,
            'name_values' => $this->user_name_values
        ];
    }

    function save_user_session_id($session_id)
    {
        try
        {
            // set session

            $this->_DB->query(" UPDATE user SET session_id = :session_id WHERE id = :id ");
            $this->_DB->bind(':session_id', $session_id);
            $this->_DB->bind(':id', $this->user_id);
            $this->_DB->execute();

            return true;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }




    // for new password

    function get_user_for_contact_params($mobile, $email)
    {
        $this->_DB->query(" SELECT id FROM user WHERE ( mobile = :mobile OR email = :email ) ");
        $this->_DB->bind(':mobile', $mobile);
        $this->_DB->bind(':email', $email);
        $this->_DB->execute();
        $tmp = $this->_DB->fetchAllColumn();

        if(empty($tmp))
            return ['error'=>'No User was found matching the supplied contact details.'];

        if(count($tmp) > 1)
            return ['error'=>'No User was found matching the supplied contact number and email pairing.'];

        return ['success'=>1, 'id'=>$tmp[0]];
    }

    function update_user_password($id, $pass)
    {
        $this->_DB->beginTransaction();
        try
        {
            $this->_DB->query(" UPDATE user SET 
                pass = :pass, updated = NOW(), updated_by = :id 
                WHERE id = :id ");
            $this->_DB->bind(':id', $id);
            $this->_DB->bind(':pass', $pass);
            $this->_DB->execute();

            $this->_DB->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $this->_DB->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }












    /*function get_user_permissions($ind_id, $org)
    {
        $permissions = []; // apps and rank/roles accessible by user
        $org_apps = []; // app slugs subscribed to by org, irrespective of user

        try
        {
            // get apps to which org is subscribed
            $this->_DB->query(" SELECT app_slug FROM org_apps WHERE org_guid = :org_guid AND active = 1 ");
            $this->_DB->bind(':org_guid', $org['guid']);
            $this->_DB->execute();
            $org_apps = $this->_DB->fetchAllColumn();

            if(empty($org_apps))
                throw new Exception('Your login is valid, but your Organisation has not yet subscribed to any Applications.');


            // get apps which user has permission to access
            $this->_DB->query(" SELECT ia.app_slug 
            FROM ind_app_permissions ia 
            LEFT JOIN org_apps oa ON ( ia.guid = oa.org_guid ) 
            WHERE ( id = :ind_id AND ia.guid = :org_guid AND oa.active = 1 ) ");
            $this->_DB->bind(':ind_id', $ind_id);
            $this->_DB->bind(':org_guid', $org['guid']);
            $this->_DB->execute();
            $ind_apps = $this->_DB->fetchAllColumn();

            if(empty($ind_apps))
                throw new Exception('Your login is valid, but you have not been granted general access to any Applications by your Organisation.');

            foreach($ind_apps as $t)
                $permissions[ $t ] = [];

            $this->_ORG_DB->query(" SELECT model, method FROM app_screen_user WHERE ( id = :ind_id AND app_slug = :app_slug ) ");
            foreach($permissions as $app_slug => $a)
            {
                $this->_ORG_DB->bind(':app_slug', $app_slug);
                $this->_ORG_DB->bind(':ind_id', $ind_id);
                $this->_ORG_DB->execute();
                $tmp = $this->_ORG_DB->fetchAllAssoc();

                if(empty($tmp))
                {
                    unset($permissions[ $app_slug ]);
                    continue;
                }

                foreach($tmp as $t)
                {
                    if(!isset($permissions[ $app_slug ][ $t['model'] ]))
                        $permissions[ $app_slug ][ $t['model'] ] = [];

                    $permissions[ $app_slug ][ $t['model'] ][] = $t['method'];
                }
            }

            if(empty($permissions))
                throw new Exception('Your login is valid, but you have not been granted access to any Application screens by your Organisation yet.');

            return ['org_apps'=>$org_apps, 'permissions'=>$permissions];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }*/

    function logout()
    {
        unset($_SESSION['databiz']);
        header('location: ' . JCT_URL_ROOT);
    }
}