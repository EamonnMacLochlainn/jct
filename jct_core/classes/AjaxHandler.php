<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/07/2015
 * Time: 05:55
 */

namespace JCT;

use Exception;

require_once '../Config.php';
require_once 'Autoloader.php';
require_once '../vendors/hash_compat/hash_equals.php';


session_start();
date_default_timezone_set(JCT_DEFAULT_TIMEZONE);
try
{
    if( (!isset($_SERVER['HTTP_ORIGIN'])) && (!isset($_SERVER['HTTP_REFERER'])) )
        throw new Exception('No Origin or Referer values detected');

    $post = array_change_key_case($_POST, CASE_LOWER);


    // namespace
    $namespace = (!empty($post['namespace'])) ? $post['namespace'] : __NAMESPACE__;

    // app
    if(empty($post['app_slug']))
        throw new Exception('No Application token detected.');
    $app_slug = trim($post['app_slug']);

    $app = RouteRegistry::get_route_properties($app_slug);
    if(isset($app['error']))
        throw new Exception('Invalid app parameter detected.');

    // init data
    $data = null;

    // set DB connection
    $connection = new Connection();
    $default_instance_name = JCT_DB_SIUD_USER . '@' . JCT_DB_SIUD_HOST . ':' . JCT_DB_SIUD_NAME;
    $default_connection = $connection->get_connection($default_instance_name);
    if($default_connection == false)
        throw new Exception('An error was encountered while establishing the default database connection: ' . $connection->connection_error);

    $db = $default_connection;


    if($app['requires_login'])
    {
        $session_manager = new SessionManager($db);
        $session_manager->set_session_id(session_id());
        $session = $session_manager->check_current_session_is_valid();
        if(isset($session['error']))
        {
            $user_values = $session_manager->get_available_user_parameters();
            echo json_encode(['error'=>$session['error'], 'revalidate'=>1, 'posted'=>$post,'user'=>$user_values], JSON_UNESCAPED_SLASHES);
            return true;
        }
        else
            $session_manager->refresh_expiry();
    }

    // qualify namespace
    $namespace = (empty($app_slug)) ? $namespace . '\\' : $namespace . '\\' . $app_slug . '\\';

    if($app['accessed_per_org_type'])
    {
        if(empty($post['org_section']))
            throw new Exception('No Org section detected.');
        $namespace.= strtolower(trim($post['org_section'])) . '\\';
    }

    if($app['accessed_per_role'])
    {
        if(empty($post['user_module']))
            throw new Exception('No Module token detected.');

        $namespace.= $post['user_module']. '\\';
    }


    // model & controller
    if(empty($post['model_title']))
        throw new Exception('No Model token detected.');

    $model_title = $post['model_title'] . 'Model';
    $controller_title = $post['model_title'] . 'Controller';

    $controller_class_name = $namespace . $controller_title;
    $model_class_name = $namespace . $model_title;

    if(!class_exists($controller_class_name))
        throw new Exception('Invalid Controller token detected.');

    if(!class_exists($model_class_name))
        throw new Exception('Invalid Model token detected.');

    // method
    $method_title = (!empty($post['method_title'])) ? $post['method_title'] : 'index';
    $method_title = strtolower($method_title);

    $model = new $model_class_name($default_connection);
    $controller = new $controller_class_name($model);

    if(!method_exists($controller, $method_title))
        throw new Exception('Invalid Method token detected (' . $controller_class_name . '::' . $method_title . ')');

    unset($post['namespace']);
    unset($post['app_slug']);
    unset($post['org_section']);
    unset($post['user_module']);
    unset($post['model_title']);
    unset($post['method_title']);

    // append file data to $post, if present
    if(!empty($_FILES))
        $post['files'] = $_FILES;

    try
    {
        $tmp = $controller->$method_title($post);
        $resp = json_encode($tmp, JSON_UNESCAPED_SLASHES);
        echo $resp;
    }
    catch(Exception $e)
    {
        throw new Exception($e->getMessage());
    }

    /*if(!isset($tmp['error']))
    {
        $at = new ActivityTracker($default_connection);
        $at->record_activity($model_title, $method_title);
    }*/

}
catch(Exception $e)
{
    echo json_encode(['error'=>'AJAX handler refused the request: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
}