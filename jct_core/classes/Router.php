<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;



/**
 * [namespace]/[app_param]/[module_param]/[model_param]/[method_param]/[optional trailing_params]?[query_string_params]
 */
use JCT\Navigation;
use Exception;

class Router extends User
{
    private $_Section_Registry;
    private $_Render;
    private $_Navigation;

    private $registered_namespaces = [
        0 => __NAMESPACE__
    ];

    private $route_str;
    private $routes = [
        'site' => [
            'home' => [ 'index' => 'index@Home' ],
            'privacy' => [ 'index' => 'index@Privacy' ],
            'contact' => [ 'index' => 'index@ContactUs' ],
            'contact-us' => [ 'index' => 'index@ContactUs' ],
            'help' => [ 'index' => 'index@Help' ],
            'about-us' => [ 'index' => 'index@AboutUs' ],
            'logout' => [ 'index' => 'logout@Home' ],
            'error' => [ 'index' => 'index@Error' ],
        ],
        'feedback' => [
            'home' => [ 'index' => 'index@Home' ]
        ],
        'dashboard' => [
            'home' => [ 'index' => 'index@Home' ]
        ],
    ];

    private $requested_uri;

    private $default_namespace = __NAMESPACE__;
    private $default_app_param = 'site';
    private $default_model_param = 'home';
    private $default_method_param = 'index';

    private $namespace;
    private $section_slug;
    private $module_param;
    private $model_param;
    private $method_param;

    private $model_full_name;
    private $view_full_name;
    private $method_name;

    private $trailing_params = [];
    private $query_string_params = [];
    private $method_arguments;

    public static $instantiation_error;


    function __construct(SectionRegistry $a, Render $b )
    {
        self::$instantiation_error = null;

        parent::__construct();
        if(!$this->default_connection_ok)
        {
            echo 'The default database connection has failed. Routing process aborted.<br/>';
            if(!is_null($this->connection_error))
                echo $this->connection_error;

            die();
        }

        $this->_Section_Registry = $a;
        $this->_Render = $b;
        $this->_Navigation = new Navigation($this->user_is_logged_in(), $this->user_position());


        // find out where the user wants to go
        $this->parse_request();

        // check if they are allowed to go there
        $this->check_route_permissions();

        // get the names of the actual method to call, and its arguments
        $this->set_route_and_arguments();

        // get the html of the screen
        $this->get_screen_content();
    }

    private function parse_request()
    {
        $this->namespace = $this->default_namespace;
        $this->section_slug = $this->default_app_param;
        $this->module_param = null;
        $this->model_param = $this->default_model_param;
        $this->method_param = $this->default_method_param;

        if(empty($_GET))
            return true;

        // store requested uri as string
        $tmp = (empty($_GET['ds'])) ? '' : $_GET['ds'];
        $this->requested_uri = trim( strtolower( preg_replace("/[^\w-\p{L}\p{N}\p{Pd}\w?\w&\w=\w.\/]/", "", urldecode($tmp) ) ) );
        $this->requested_uri = rtrim($this->requested_uri, '/');
        unset($_GET['ds']);

        // store remaining $_GET parameters
        $this->query_string_params = array_change_key_case($_GET, CASE_LOWER);

        if(empty($this->requested_uri))
            return true;

        // break up requested uri into component parts

        $tmp = explode('/', $this->requested_uri);


        // determine namespace

        $this->namespace = array_shift($tmp);

        // if not a registered namespace, revert and set to default
        if(!in_array($this->namespace, $this->registered_namespaces))
        {
            array_unshift($tmp, $this->namespace);
            $this->namespace = $this->default_namespace;
        }


        // determine app

        $this->section_slug = array_shift($tmp);

        // if not recognised as an app name, then re-insert param and default to site
        if(!array_key_exists($this->section_slug, $this->routes))
        {
            array_unshift($tmp, $this->section_slug);
            $this->section_slug = $this->default_app_param;
        }


        // determine module

        $this_app = $this->_Section_Registry->get_section_registry($this->section_slug);

        if($this_app->is_modular)
        {
            if(empty($tmp))
                $this->module_param = 'home';
            else
            {
                $this->module_param = array_shift($tmp);

                if(empty($this->module_param))
                {
                    $this->set_to_error('routing_error', 'Empty module detected');
                    return true;
                }
            }

            // if not recognised as an app module, then default to site error and insert error arg
            if(!array_key_exists($this->module_param, $this->routes[ $this->section_slug ]))
            {
                $this->set_to_error('routing_error', 'Invalid module detected');
                return true;
            }
        }


        if(!empty($tmp))
            $this->model_param = array_shift($tmp);


        // if not recognised as an app model, then default to site error and insert error arg
        if(
            ( (!$this_app->is_modular) && (!array_key_exists($this->model_param, $this->routes[ $this->section_slug ])) ) ||
            ( ($this_app->is_modular) && (!array_key_exists($this->module_param, $this->routes[ $this->section_slug ])) )
        )
        {
            $this->set_to_error('routing_error', null, $this->requested_uri);
            return true;
        }

        if(!empty($tmp))
            $this->method_param = array_shift($tmp);

        // if not recognised as an app model method, then feed as trailing param to default method
        if(
            ( (!$this_app->is_modular) && (!array_key_exists($this->method_param, $this->routes[ $this->section_slug ][ $this->model_param ])) ) ||
            ( ($this_app->is_modular) && (!array_key_exists($this->method_param, $this->routes[ $this->section_slug ][ $this->module_param ][ $this->model_param ])) )
        )
        {
            array_unshift($tmp, $this->method_param);
            $this->method_param = $this->default_method_param;
            $this->trailing_params = $tmp;

            return true;
        }

        // all other parameters stored for method arguments
        if(!empty($tmp))
            $this->trailing_params = $tmp;

        return true;
    }

    private function check_route_permissions()
    {
        $this_section = $this->_Section_Registry->get_section_registry($this->section_slug);

        // target does not require login, let pass
        if(!$this_section->requires_login)
            return true;

        // target does require login, but user is not, so stop
        if(!$this->user_is_logged_in())
        {
            $this->set_to_error('login_error', 'You must be logged in to view this screen.');
            return true;
        }

        // user is admin, let pass
        if($this->user_position() === 'admin')
            return true;


        // check user's position has permission to access section
        if(!in_array($_SESSION['jct']['position'], $this_section->positions))
        {
            $this->set_to_error('login_error', 'Your registered position cannot view this screen.');
            return true;
        }

        return true;
    }

    private function set_route_and_arguments()
    {
        $route_params = (!is_null($this->module_param)) ?
            $this->routes[$this->section_slug][$this->module_param][$this->model_param][$this->method_param] :
            $this->routes[$this->section_slug][$this->model_param][$this->method_param];

        $route_parts = explode('@', $route_params);
        $this->route_str = $this->section_slug . ':' . $this->model_param . ':' . $this->method_param;

        // determine method to be called
        $this->method_name = $route_parts[0];

        // get class name
        $tmp = explode(':', $route_parts[1]);
        $class_name = array_shift($tmp);

        // assign any set keys to any trailing parameters
        if(!empty($tmp))
        {
            foreach($tmp as $t)
            {
                $value = null;
                $key = $t;

                $value = (!empty($this->trailing_params)) ? array_shift($this->trailing_params) : null;
                $this->method_arguments[ $key ] = $value;
            }
        }

        // discard unused trailing params as junk
        $this->trailing_params = [];

        // store any query string parameters as remaining method arguments
        if(!empty($this->query_string_params))
        {
            foreach($this->query_string_params as $k => $v)
                $this->method_arguments[ $k ] = $v;
        }

        // set class and view full names
        $this->model_full_name = (!is_null($this->module_param)) ?
            $this->namespace . '\\' . $this->section_slug . '\\' . $this->module_param . '\\' . $class_name . 'Model' :
            $this->namespace . '\\' . $this->section_slug . '\\' . $class_name . 'Model';

        $this->view_full_name = (!is_null($this->module_param)) ?
            $this->namespace . '\\' . $this->section_slug . '\\' . $this->module_param . '\\' . $class_name . 'View' :
            $this->namespace . '\\' . $this->section_slug . '\\' . $class_name . 'View';


        return true;
    }

    private function get_screen_content()
    {
        try
        {
            $method_name = $this->method_name;
            $args = (!empty($this->method_arguments)) ? $this->method_arguments : null;

            $model = new $this->model_full_name($this->default_db_connection, $this->_Section_Registry);

            if(property_exists($model, 'has_child_class'))
            {
                $child_name = $model->get_child_class();
                $child_model_name = $child_name . 'Model';
                $model = new $child_model_name($this->default_db_connection, $this->_Section_Registry);

                $this->view_full_name = $child_name . 'View';
            }
            $model->$method_name($args);
            $view = new $this->view_full_name($model);


            // set screen content
            $view->$method_name();

            // set navigation
            $this->_Navigation->set_focused_app_registry($this->_Section_Registry->get_section_registry($this->section_slug));
            $this->_Navigation->get_navigation($this->section_slug, $this->module_param, $this->model_param, $this->method_param);

            // render screen
            $this->_Render->set_global_navigation( $this->_Navigation->global_nav_html );
            $this->_Render->set_section_navigation( $this->_Navigation->app_nav_html );

            ob_start();
            echo $this->_Render->build_view( $view );
            $tmp = ob_get_clean();
            echo $tmp;
        }
        catch(Exception $e)
        {
            $this->section_slug = $this->default_app_param;
            $this->module_param = null;
            $this->model_param = 'error';
            $this->method_param = $this->default_method_param;
            $this->method_arguments['error'] = ['type'=>'default_error', 'thrown_error' => $e->getMessage()];

            $this->set_route_and_arguments();
            $this->get_screen_content();
        }
    }

    private function set_to_error($error_type = null, $thrown_error = null, $error_param = null)
    {
        $this->section_slug = $this->default_app_param;
        $this->module_param = null;
        $this->model_param = 'error';
        $this->method_param = $this->default_method_param;
        $this->method_arguments['error'] = ['type'=>'permissions_error'];

        if(!is_null($error_type))
            $this->method_arguments['error']['type'] = $error_type;
        if(!is_null($thrown_error))
            $this->method_arguments['error']['thrown_error'] = $thrown_error;
        if(!is_null($error_param))
            $this->method_arguments['error']['param'] = $error_param;
    }
}