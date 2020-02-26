<?php /** @noinspection DuplicatedCode */

/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;



use Exception;

class Router extends User
{
    const DEFAULT_APP_SLUG = 'site';
    const DEFAULT_ORG_SECTION_SLUG = 'all';
    const DEFAULT_USER_MODULE_SLUG = 'all';
    const DEFAULT_DESTINATION_SLUG = 'home';

    protected $requested_uri;

    protected $app_slug;
    protected $org_section_slug;
    protected $user_module_slug;
    protected $destination_slug;
    protected $model_title;
    protected $model_class_name;
    protected $method_title;
    protected $method_arguments = [];

    protected $route_properties;


    protected $module_dir_path;
    protected $model;


    const ERR_CUSTOM = 0;
    const ERR_LOGIN_MISSING = 1;
    const ERR_ORG_TYPE_DISALLOWED = 2;
    const ERR_USER_ROLE_DISALLOWED = 3;
    const ERR_INVALID_MODEL_PATH = 4;
    const ERR_INVALID_APP_SLUG = 5;
    const ERR_INVALID_CLASS_NAME = 6;
    const ERR_INVALID_METHOD_NAME = 7;
    const ERR_INVALID_DESTINATION = 8;
    const ERR_INVALID_ORG_SECTION = 9;
    const ERR_INVALID_USER_MODULE = 9;



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

        $this->set_for_render();
    }

    private function set_to_home($args = [])
    {
        $this->app_slug = self::DEFAULT_APP_SLUG;
        $this->org_section_slug = self::DEFAULT_ORG_SECTION_SLUG;
        $this->user_module_slug = self::DEFAULT_USER_MODULE_SLUG;
        $this->destination_slug = self::DEFAULT_DESTINATION_SLUG;

        $this->route_properties = RouteRegistry::get_route_properties($this->app_slug, $this->org_section_slug, $this->user_module_slug);
        $module = $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug];
        $this->model_title = $module['destinations'][$this->destination_slug]['model'];
        $this->method_title = $module['destinations'][$this->destination_slug]['method'];
        $this->method_arguments = $args;
    }

    private function set_to_error($error_code, $args = [])
    {
        $this->app_slug = self::DEFAULT_APP_SLUG;
        $this->org_section_slug = self::DEFAULT_ORG_SECTION_SLUG;
        $this->user_module_slug = self::DEFAULT_USER_MODULE_SLUG;
        $this->destination_slug = 'error';

        $this->route_properties = RouteRegistry::get_route_properties($this->app_slug, $this->org_section_slug, $this->user_module_slug);
        $module = $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug];
        $this->model_title = $module['destinations'][$this->destination_slug]['model'];
        $this->method_title = $module['destinations'][$this->destination_slug]['method'];

        $err = [
            'person_guid' => (empty($this->person_guid)) ? null : base64_encode($this->person_guid),
            'error_type' => null,
            'error_msg' => '',
            'requested_uri' => base64_encode($this->requested_uri),
            'args' => $args
        ];

        switch(intval($error_code))
        {
            case(self::ERR_CUSTOM):
                $err['error_type'] = 'custom';
                $err['error_msg'] = 'An unrecognised error occurred';
                break;
            case(self::ERR_LOGIN_MISSING):
                $err['error_type'] = 'login';
                $err['error_msg'] = 'You must log in to view this screen';
                break;
            case(self::ERR_ORG_TYPE_DISALLOWED):
                $err['error_type'] = 'login';
                $err['error_msg'] = 'Your logged in Organisation type does not allow you to view this screen';
                break;
            case(self::ERR_USER_ROLE_DISALLOWED):
                $err['error_type'] = 'login';
                $err['error_msg'] = 'Your logged in User role does not allow you to view this screen';
                break;
            case(self::ERR_INVALID_MODEL_PATH):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid Model path';
                break;
            case(self::ERR_INVALID_APP_SLUG):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid App slug';
                break;
            case(self::ERR_INVALID_CLASS_NAME):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid Class name';
                break;
            case(self::ERR_INVALID_METHOD_NAME):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid Method name';
                break;
            case(self::ERR_INVALID_DESTINATION):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid Destination';
                break;
            case(self::ERR_INVALID_ORG_SECTION):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid Org Section';
                break;
            case(self::ERR_INVALID_USER_MODULE):
                $err['error_type'] = 'routing';
                $err['error_msg'] = 'Invalid User Module';
                break;
        }
        $err['error_msg'] = (!empty($args['error_msg'])) ? $args['error_msg'] : $err['error_msg'];
        $err['error_msg'] = base64_encode($err['error_msg']);
        $this->method_arguments = $err;

        $this->module_dir_path = JCT_PATH_APPS . $this->app_slug . JCT_DE;

        $model_class_name = __NAMESPACE__ . '\\' . $this->app_slug . '\\' . $this->model_title . 'Model';
        $this->model_class_name = $model_class_name;
        $model = new $model_class_name($this->default_db_connection);
        $this->model = $model;
    }

    private function get_org_type_slugs(Database $db)
    {
        $db->query(" SELECT slug FROM prm_org_type WHERE 1 ");
        $db->execute();
        return $db->fetchAllColumn();
    }

    private function set_route_from_requested_url()
    {
        if(empty($_GET))
        {
            $this->set_to_home();
            return true;
        }

        // store requested uri as string

        $tmp = (empty($_GET['ds'])) ? '' : $_GET['ds'];
        $this->requested_uri = trim( strtolower( preg_replace("/[^\w-\p{L}\p{N}\p{Pd}\w?\w&\w=\w.\/]/", "", urldecode($tmp) ) ) );
        $this->requested_uri = rtrim($this->requested_uri, '/');
        unset($_GET['ds']);

        if(empty($this->requested_uri))
        {
            $this->set_to_home();
            return true;
        }





        // break up requested uri into component parts

        $uri_parts = explode('/', $this->requested_uri);

        // determine app
        // The first segment could either be an actual app slug (site, feedback, etc.) or a user role slug (jct, school, etc.)
        // if the first segment is neither of these, put it back and set that property to default (site)
        $segment = array_shift($uri_parts);
        $app_slugs = RouteRegistry::get_app_slugs();
        $role_slugs = $this->get_org_type_slugs($this->default_db_connection);

        $app_slug = null;
        if( (!in_array($segment, $app_slugs)) && (!in_array($segment, $role_slugs)) )
        {
            array_unshift($uri_parts, $segment);
            $app_slug = self::DEFAULT_APP_SLUG;
        }

        if($app_slug === null)
        {
            if(in_array($segment, $app_slugs))
                $app_slug = $segment;
            else
            {
                if(in_array($segment, $role_slugs))
                {
                    // effectively: jct/dashboard/... becomes dashboard/jct/...
                    $app_slug = array_shift($uri_parts);
                    array_unshift($uri_parts, $segment);
                }
            }
        }
        $this->app_slug = $app_slug;

        $this->route_properties = RouteRegistry::get_route_properties($this->app_slug);
        if(isset($this->route_properties['error']))
        {
            $this->set_to_error(self::ERR_INVALID_APP_SLUG);
            return true;
        }


        // if only one uri part is submitted (e.g. /help, /contact, etc.), then we can
        // set the default properties for the 'site' app
        if(count($uri_parts) < 2)
        {
            $this->org_section_slug = self::DEFAULT_ORG_SECTION_SLUG;
            $this->user_module_slug = self::DEFAULT_USER_MODULE_SLUG;
        }
        else
        {
            // if the app is not accessed per org type, we can set the org_section_slug to 'all'
            if($this->route_properties['accessed_per_org_type'] === false)
                $this->org_section_slug = self::DEFAULT_ORG_SECTION_SLUG;
            else
            {
                $org_section_slug = strtolower(array_shift($uri_parts));
                if(!array_key_exists($org_section_slug, $this->route_properties['org_sections']))
                {
                    $this->set_to_error(self::ERR_INVALID_ORG_SECTION);
                    return true;
                }
                $this->org_section_slug = $org_section_slug;
            }

            // if the app is not accessed per user role, we can set the user_module_slug to 'all'
            if($this->route_properties['accessed_per_role'] === false)
                $this->user_module_slug = self::DEFAULT_USER_MODULE_SLUG;
            else
            {
                $user_module_slug = strtolower(array_shift($uri_parts));
                if(!array_key_exists($user_module_slug, $this->route_properties['org_sections'][$this->org_section_slug]['user_modules']))
                {
                    $this->set_to_error(self::ERR_INVALID_USER_MODULE);
                    return true;
                }
                $this->user_module_slug = $user_module_slug;
            }
        }

        // last checks before destination
        if(!isset($this->route_properties['org_sections'][$this->org_section_slug]))
        {
            $this->set_to_error(self::ERR_INVALID_ORG_SECTION);
            return true;
        }
        if(!isset($this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug]))
        {
            $this->set_to_error(self::ERR_INVALID_USER_MODULE);
            return true;
        }

        // determine destination
        // if destination segment is unrecognised, put it back and set that property to default (home)
        $destination_slug = array_shift($uri_parts);
        if(!array_key_exists($destination_slug, $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug]['destinations']))
        {
            $this->set_to_error(self::ERR_INVALID_DESTINATION);
            return true;
        }
        $this->destination_slug = $destination_slug;


        // determine model & method
        // these are not set from the URI, but are properties of the destination
        $this->model_title = $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug]['destinations'][$this->destination_slug]['model'];
        $this->method_title = $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug]['destinations'][$this->destination_slug]['method'];


        // store remaining URI segments as method arguments
        $args = [];
        if(!empty($uri_parts))
        {
            foreach($uri_parts as $part)
                $args[$part] = 1;
        }
        if(!empty($_GET))
        {
            foreach($_GET as $k => $v)
                $args[$k] = $v;
        }
        $this->method_arguments = $args;

        return true;
    }

    private function check_user_against_set_route()
    {
        try
        {
            $route_requires_login = $this->route_properties['requires_login'];

            // route doesn't require a login, let through
            if($route_requires_login === false)
                return ['success'=>1];

            // route does require a login, user is not logged in, stop
            if( ($route_requires_login === true) && ($this->user_is_logged_in === false) )
                throw new Exception('', self::ERR_LOGIN_MISSING);

            // check user is accessing a route allowed to their org type
            $route_org_type_ids = $this->route_properties['org_sections'][$this->org_section_slug]['type_ids'];
            $route_org_type_ids = (!empty($route_org_type_ids)) ? array_map('intval',$route_org_type_ids) : [];
            if( (!empty($route_org_type_ids)) && (!in_array($this->org_type_id, $route_org_type_ids)) )
                throw new Exception('', self::ERR_ORG_TYPE_DISALLOWED);

            // check user is accessing a module allowed to their role ID
            $route_role_ids = $this->route_properties['org_sections'][$this->org_section_slug]['user_modules'][$this->user_module_slug]['role_ids'];
            $route_role_ids = (!empty($route_role_ids)) ? array_map('intval',$route_role_ids) : [];
            if( (!empty($route_role_ids)) && (!in_array($this->user_role_id, $route_role_ids)) )
                throw new Exception('', self::ERR_USER_ROLE_DISALLOWED);

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['message'=>$e->getMessage(), 'code'=>$e->getCode()];
        }
    }

    private function set_for_render()
    {
        // set route
        $this->set_route_from_requested_url();

        // set user
        $this->set_user_from_session();

        // check if user is allowed to go to route
        $route_check = $this->check_user_against_set_route();
        if(!isset($route_check['success']))
        {
            $this->set_to_error($route_check['code']);
            return true;
        }


        // set path to assets & model
        $accessed_per_org_type = $this->route_properties['accessed_per_org_type'];
        $accessed_per_role = $this->route_properties['accessed_per_role'];
        // if the app is accessed_per_org_type, then the org_type (jct, school, etc) comes before the app_slug; otherwise the org type is not included (app is accessed directly)
        // if the app is accessed_per_role, then the user_module_slug is included; otherwise we go straight to the MVCs
        $module_dir_path = ($accessed_per_org_type) ? JCT_PATH_APPS . $this->org_section_slug . JCT_DE . $this->app_slug . JCT_DE : JCT_PATH_APPS . $this->app_slug . JCT_DE;
        $module_dir_path.= ($accessed_per_role) ? $this->user_module_slug . JCT_DE : '';
        $this->module_dir_path = $module_dir_path;

        $model_path = $module_dir_path . 'models' . JCT_DE . $this->model_title . 'Model.php';

        if(!file_exists($model_path))
        {
            $this->set_to_error(self::ERR_INVALID_MODEL_PATH);
            return true;
        }

        // namespace\app_slug\[org_section_slug\][user_module_slug\]model_name
        $model_class_name = __NAMESPACE__ . '\\';
        $model_class_name.= ($accessed_per_org_type) ? $this->org_section_slug . '\\' . $this->app_slug . '\\' : $this->app_slug . '\\';
        $model_class_name.= ($accessed_per_role) ? $this->user_module_slug . '\\' : '';
        $model_class_name.= $this->model_title . 'Model';
        $this->model_class_name = $model_class_name;

        if(!class_exists($model_class_name))
        {
            $this->set_to_error(self::ERR_INVALID_CLASS_NAME);
            return true;
        }

        // init model
        $model = new $model_class_name($this->default_db_connection);
        if((is_array($model)) && (isset($model['error'])) )
        {
            $args = ['error_msg'=>$model['error']];
            $this->set_to_error(self::ERR_CUSTOM, $args);
            return true;
        }


        // check method is ok
        if(!method_exists($model, $this->method_title))
        {
            $this->set_to_error(self::ERR_INVALID_METHOD_NAME);
            return true;
        }


        // set for Render
        $this->model = $model;
        return true;
    }
}