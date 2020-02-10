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
$position = 'start';
try
{
    if( (!isset($_SERVER['HTTP_ORIGIN'])) && (!isset($_SERVER['HTTP_REFERER'])) )
        throw new Exception('No Origin or Referer values detected');

    $address = 'http://' . $_SERVER['SERVER_NAME'];
    $address_ssl = 'https://' . $_SERVER['SERVER_NAME'];

    // could do some origin checking here





    $post = array_change_key_case($_POST, CASE_LOWER);


    $position = 'namespace';
    // namespace
    $namespace = (!empty($post['namespace'])) ? $post['namespace'] : __NAMESPACE__;



    $position = 'app';

    // app
    if(empty($post['app_param']))
        throw new Exception('No Application token detected.');

    $app_slug = trim($post['app_param']);

    $app = RouteRegistry::get_app($app_slug);
    if(isset($app['error']))
        throw new Exception('Invalid app parameter detected.');

    $data = null;
    $default_instance_name = JCT_DB_SIUD_USER . '@' . JCT_DB_SIUD_HOST . ':' . JCT_DB_SIUD_NAME;
    $org_instance_name = $org_msg_instance_name = $org_host_name = $org_db_name = $org_msg_db_name = null;



    $position = 'default_connection';
    $connection = new Connection();
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

        $guid = $_SESSION[$session_manager::SESSION_NAME]['org']['guid'];
    }






    $namespace = (empty($app_slug)) ? $namespace . '\\' : $namespace . '\\' . $app_slug . '\\';

    if($app['is_modular'])
    {
        if(empty($post['module_param']))
            throw new Exception('No Module token detected.');

        $namespace.= $post['module_param']. '\\';
    }


    $position = 'model';
    // model & controller
    if(empty($post['model_param']))
        throw new Exception('No Model token detected.');

    $controller_param = $post['model_param'] . 'Controller';
    $model_param = $post['model_param'] . 'Model';

    $controller_full_name = $namespace . $controller_param;
    $model_full_name = $namespace . $model_param;

    if(!class_exists($controller_full_name))
        throw new Exception('Invalid Controller token detected.');

    if(!class_exists($model_full_name))
        throw new Exception('Invalid Model token detected.');

    $activity_model = strtolower($post['model_param']);


    $position = 'method';
    // method
    $method_param = (!empty($post['method_param'])) ? $post['method_param'] : 'index';
    $activity_method = strtolower($method_param);





    $position = 'instantiation';

    $model = new $model_full_name($default_connection);
    $controller = new $controller_full_name($model);

    if(!method_exists($controller, $method_param))
        throw new Exception('Invalid Method token detected (' . $controller_full_name . '::' . $method_param . ')');

    unset($post['namespace']);
    unset($post['app_param']);
    unset($post['module_param']);
    unset($post['model_param']);
    unset($post['method_param']);
    unset($post['csrf']);

    // append file data to $post, if present
    if(!empty($_FILES))
        $post['files'] = $_FILES;

    try
    {
        $tmp = $controller->$method_param($post);
        $resp = json_encode($tmp, JSON_UNESCAPED_SLASHES);
        echo $resp;
    }
    catch(Exception $e)
    {
        throw new Exception($e->getMessage());
    }

    if(!isset($tmp['error']))
    {
        $at = new ActivityTracker($default_connection);
        $at->record_activity($activity_model, $activity_method);
    }

}
catch(Exception $e)
{
    echo json_encode(['error'=>'AJAX handler refused the request: ' . $e->getMessage(), 'position'=>$position], JSON_UNESCAPED_SLASHES);
}