<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;




class Router extends User
{
    const DEFAULT_APP_SLUG = 'site';
    const DEFAULT_NON_MODULAR_MODULE_SLUG = 'none';
    const DEFAULT_MODULAR_MODULE_SLUG = 'home';
    const DEFAULT_DESTINATION_SLUG = 'home';
    const DEFAULT_MODEL = 'Home';
    const DEFAULT_METHOD = 'index';

    const ERROR_DESTINATION_SLUG = 'error';

    const DEFAULT_USER_PERMISSION = null;
    const DEFAULT_PERMISSION = 'rw';

    protected $requested_uri;

    protected $app_slug;
    protected $module_slug;
    protected $destination_slug;

    protected $model_name;
    protected $method_name;
    protected $method_arguments = [];

    protected $user_permission_for_route;

    protected $app;
    protected $module_dir_name;
    protected $model;





    function __construct()
    {
        parent::__construct();

        if(!$this->default_connection_ok)
        {
            echo 'The default database connection has failed. Routing process aborted.<br/>';
            if(!is_null($this->connection_error))
                echo $this->connection_error;

            die();
        }

        $this->user_permission_for_route = self::DEFAULT_USER_PERMISSION;

        $this->set_for_render();
    }

    private function set_for_render()
    {
        // find out where the user wants to go

        $this->set_route();


        // set user from $_SESSION

        $tmp = $this->set_user_from_session();
        if( (isset($tmp['error'])) && ($this->app['requires_login'] === true) )
        {
            $error = base64_encode($tmp['error']);
            $user_id = (empty($tmp['user_id'])) ? 0 : base64_encode($tmp['user_id']);
            $redirect = base64_encode($this->requested_uri);

            header('location:' . JCT_URL_ROOT . 'login?redirect=' . $redirect . '&id=' . $user_id . '&error=' . $error);
            die();
        }


        // check if user is allowed to go to route

        /*$check_further = true;
        if($this->app['requires_login'] === false) // doesn't require a login, let through
        {
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;
            $check_further = false;
        }

        if( ($check_further) && ($this->user_is_logged_in === false) ) // does require a login, user is not, stop
        {
            $args = [
                'error_type' => 'login',
                'requested_uri' => $this->requested_uri
            ];
            $this->set_to_error($args);
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;
            $check_further = false;
        }

        if( // user can access by default under following conditions
            ($check_further) &&
            ($this->user_role_id === 1) ||
            ($this->app['is_required_for_all_users'] === true) ||
            ( (in_array($this->user_role_id, [7,8])) && ( ($this->destination_slug === 'public') || ($this->app_slug == 'family') )) ||
            ( ($this->user_role_id === 2) && ($this->app['requires_subscription'] === false) )
        )
        {
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;
            $check_further = false;
        }

        if($check_further)
        {
            $org_subscribed_apps = $this->get_org_subscribed_app_slugs($this->default_db_connection);

            if(!in_array($this->app_slug, $org_subscribed_apps)) // user's org is not subscribed to the app, stop
            {
                $args = [
                    'error_type' => 'permission',
                    'requested_uri' => $this->requested_uri
                ];
                $this->set_to_error($args);
                $this->user_permission_for_route = self::DEFAULT_PERMISSION;
                $check_further = false;
            }
            else
            {
                if($this->user_role_id === 2) // org is subscribed, and user is an admin, let through
                {
                    $this->user_permission_for_route = self::DEFAULT_PERMISSION;
                    $check_further = false;
                }
            }
        }

        if($check_further)
        {
            // org is subscribed, but user is not an admin, so get individual permission
            $user_permission_for_route = $this->get_user_permission_for_route($this->org_db_connection);
            if($user_permission_for_route === self::DEFAULT_USER_PERMISSION) // user has no permission
            {
                $args = [
                    'error_type' => 'permission',
                    'requested_uri' => $this->requested_uri
                ];
                $this->set_to_error($args);
                $this->user_permission_for_route = self::DEFAULT_PERMISSION;
            }
        }*/



        // check model is ok

        $module_dir_name = ($this->module_slug !== self::DEFAULT_NON_MODULAR_MODULE_SLUG) ? $this->module_slug . '\\' : '';
        $model_filename = __NAMESPACE__ . '\\' . $this->app_slug . '\\' . $module_dir_name . $this->model_name . 'Model';
        if(!class_exists($model_filename))
        {
            $path = JCT_PATH_APPS . $this->app_slug . JCT_DE . $module_dir_name . 'models' . JCT_DE . $this->model_name . 'Model.php';
            $exp = (file_exists($path)) ? ' (file found)' : ' (file not found)';

            $args = [
                'error_type' => 'routing',
                'error_message' => 'Model ' . $model_filename . ' not found' . $exp,
                'requested_uri' => $this->requested_uri
            ];
            $this->set_to_error($args);
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;

            $module_dir_name = '';
            $model_filename = $this->model_name . 'Model';
            $model_filename = __NAMESPACE__ . '\\' . $this->app_slug . '\\' . $module_dir_name . $model_filename;
        }






        // init model

        $model = new $model_filename($this->default_db_connection);

        if((is_array($model)) && (isset($model['error'])) )
        {
            $args = [
                'error_type' => 'model',
                'error_message' => $model['error'],
                'requested_uri' => $this->requested_uri
            ];
            $this->set_to_error($args);
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;

            $module_dir_name = '';
            $model_filename = $this->model_name . 'Model';
            $model_filename = __NAMESPACE__ . '\\' . $this->app_slug . '\\' . $module_dir_name . $model_filename;

            $model = new $model_filename($this->default_db_connection);
        }
        else
        {
            // check method is ok

            if(!method_exists($model, $this->method_name))
            {
                $args = [
                    'error_type' => 'model',
                    'error_message' => 'Method `' . $this->method_name . '` not found in Model ' . $this->model_name . '.',
                    'requested_uri' => $this->requested_uri
                ];
                $this->set_to_error($args);
                $this->user_permission_for_route = self::DEFAULT_PERMISSION;

                $module_dir_name = '';
                $model_filename = $this->model_name . 'Model';
                $model_filename = __NAMESPACE__ . '\\' . $this->app_slug . '\\' . $module_dir_name . $model_filename;
                $model = new $model_filename($this->default_db_connection);
            }
        }


        // set for Render

        $this->module_dir_name = $module_dir_name;
        $this->model = $model;
        $this->method_arguments = (empty($this->method_arguments)) ? null : $this->method_arguments;

        return true;
    }

    private function set_route()
    {
        if(empty($_GET))
        {
            $this->app_slug = self::DEFAULT_APP_SLUG;
            $this->module_slug = self::DEFAULT_NON_MODULAR_MODULE_SLUG;
            $this->destination_slug = self::DEFAULT_DESTINATION_SLUG;
            $this->model_name = self::DEFAULT_MODEL;
            $this->method_name = self::DEFAULT_METHOD;

            $this->app = RouteRegistry::get_app($this->app_slug);
            return true;
        }

        // store requested uri as string

        $tmp = (empty($_GET['ds'])) ? '' : $_GET['ds'];
        $this->requested_uri = trim( strtolower( preg_replace("/[^\w-\p{L}\p{N}\p{Pd}\w?\w&\w=\w.\/]/", "", urldecode($tmp) ) ) );
        $this->requested_uri = rtrim($this->requested_uri, '/');
        unset($_GET['ds']);

        if(empty($this->requested_uri))
        {
            $this->app_slug = self::DEFAULT_APP_SLUG;
            $this->module_slug = self::DEFAULT_NON_MODULAR_MODULE_SLUG;
            $this->destination_slug = self::DEFAULT_DESTINATION_SLUG;
            $this->model_name = self::DEFAULT_MODEL;
            $this->method_name = self::DEFAULT_METHOD;

            $this->app = RouteRegistry::get_app($this->app_slug);
            return true;
        }

        // break up requested uri into component parts

        $uri_parts = explode('/', $this->requested_uri);



        // determine app

        $app_slug = array_shift($uri_parts);
        if(!in_array($app_slug, RouteRegistry::get_app_slugs()))
        {
            array_unshift($uri_parts, $app_slug);
            $app_slug = self::DEFAULT_APP_SLUG;
        }
        $this->app_slug = $app_slug;


        $this->app = RouteRegistry::get_app($this->app_slug);
        if(isset($this->app['error']))
        {
            $args = [
                'error_type' => 'routing',
                'error_message' => 'app',
                'requested_uri' => $this->requested_uri
            ];
            $this->set_to_error($args);
            $this->user_permission_for_route = self::DEFAULT_PERMISSION;
            return true;
        }


        // determine module

        if($this->app['is_modular'] === false)
            $module_slug = self::DEFAULT_NON_MODULAR_MODULE_SLUG;
        else
        {
            if(empty($uri_parts))
                $module_slug = self::DEFAULT_MODULAR_MODULE_SLUG;
            else
                $module_slug = array_shift($uri_parts);

            if(!array_key_exists($module_slug, $this->app['modules']))
            {
                $args = [
                    'error_type' => 'routing',
                    'error_message' => 'module',
                    'requested_uri' => $this->requested_uri
                ];
                $this->set_to_error($args);
                return true;
            }
        }
        $this->module_slug = $module_slug;




        // determine model & method

        if(empty($uri_parts))
            $destination_slug = self::DEFAULT_DESTINATION_SLUG;
        else
        {
            $destination_slug = array_shift($uri_parts);

            if(!array_key_exists($destination_slug, $this->app['modules'][$this->module_slug]['destinations']))
            {
                if(array_key_exists($destination_slug, $this->app['modules'][$this->module_slug]['destination_aliases']))
                    $destination_slug = $this->app['modules'][$this->module_slug]['destination_aliases'][$destination_slug];
                else
                {
                    $args = [
                        'error_type' => 'routing',
                        'error_message' => 'model:' . $destination_slug,
                        'requested_uri' => $this->requested_uri
                    ];
                    $this->set_to_error($args);
                    return true;
                }
            }
        }
        $this->destination_slug = $destination_slug;
        $this->model_name = $this->app['modules'][$this->module_slug]['destinations'][$this->destination_slug]['model'];
        $this->method_name = $this->app['modules'][$this->module_slug]['destinations'][$this->destination_slug]['method'];




        // store remaining URI parameters as method arguments

        $args = [];
        if(!empty($uri_parts))
        {
            foreach($uri_parts as $part)
                $args[$part] = 1;
        }

        // store remaining $_GET parameters

        if(!empty($_GET))
        {
            foreach($_GET as $k => $v)
                $args[$k] = $v;
        }
        $this->method_arguments = $args;

        return true;
    }

    private function set_to_error($args)
    {
        $this->app_slug = self::DEFAULT_APP_SLUG;
        $this->module_slug = self::DEFAULT_NON_MODULAR_MODULE_SLUG;
        $this->destination_slug = self::ERROR_DESTINATION_SLUG;
        $this->app = RouteRegistry::get_app($this->app_slug);

        $this->model_name = $this->app['modules'][$this->module_slug]['destinations'][$this->destination_slug]['model'];
        $this->method_name = $this->app['modules'][$this->module_slug]['destinations'][$this->destination_slug]['method'];

        $this->method_arguments = $args;
    }

    protected function get_user_permission_for_route(Database $db)
    {
        $module_slug = ($this->module_slug === self::DEFAULT_NON_MODULAR_MODULE_SLUG) ? null : $this->module_slug;

        $db->query(" SELECT method 
        FROM app_screen_user 
        WHERE ( 
            id = :id AND 
            role_id = :role_id AND 
            app_slug = :app_slug AND 
            module = :module_slug AND 
            model = :destination_slug
        ) ");
        $db->bind(':id', $this->user_id);
        $db->bind(':role_id', $this->user_role_id);
        $db->bind(':app_slug', $this->app_slug);
        $db->bind(':module_slug', $module_slug);
        $db->bind(':destination_slug', $this->destination_slug);
        $db->execute();
        $tmp = $db->fetchAllColumn();

        $permission = self::DEFAULT_USER_PERMISSION;
        foreach($tmp as $t)
        {
            if(empty($t))
                continue;

            $split = explode(':', $t);
            if(count($split) !== 2)
                continue;

            $method_slug = $split[0];

            if($method_slug !== $this->method_name)
                continue;

            $permission = $split[1];
        }

        return $permission;
    }
}