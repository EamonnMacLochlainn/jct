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
require_once 'SectionRegistry.php';
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

    /*if(!isset($_SERVER['HTTP_ORIGIN']))
    {
        if(strpos($address, $_SERVER['HTTP_REFERER']) !== 0)
        {
            if(strpos($address_ssl, $_SERVER['HTTP_REFERER']) !== 0)
                throw new Exception('Invalid Referrer header: ' . $_SERVER['HTTP_REFERER'] . ' : ' . $_SERVER['HTTP_ORIGIN']);
        }
    }
    else if(strpos($address, $_SERVER['HTTP_ORIGIN']) !== 0)
    {
        if(strpos($address_ssl, $_SERVER['HTTP_ORIGIN']) !== 0)
            throw new Exception('Invalid Origin header: ' . $_SERVER['HTTP_ORIGIN'] . ' : ' . $address);
    }*/



    $post = array_change_key_case($_POST, CASE_LOWER);


    $position = 'namespace';
    // namespace
    $namespace = (!empty($post['namespace'])) ? $post['namespace'] : __NAMESPACE__;
    unset($post['namespace']);



    $position = 'app';

    // app
    if(empty($post['app_param']))
        throw new Exception('No Application token detected.');

    $app_param = trim($post['app_param']);
    unset($post['app_param']);

    $p = new SectionRegistry();
    $app_registry = $p->get_section_registry($app_param);
    if($app_registry === false)
        throw new Exception('Invalid app parameter detected.');

    $data = null;
    $default_instance_name = JCT_DB_SIUD_USER . '@' . JCT_DB_SIUD_HOST . ':' . JCT_DB_SIUD_NAME;
    $org_instance_name = $org_host_name = $org_db_name = null;
    if($app_registry->requires_login)
    {
        if(empty($_SESSION['databiz']))
            throw new Exception('User Session not detected.');

        if(empty($post['csrf']))
            throw new Exception('No post security token detected.');

        if(!hash_equals($_SESSION['databiz']['csrf'], $post['csrf']))
            throw new Exception('Invalid post security token detected.');

        $headers = apache_request_headers();
        if(!empty($headers['csrf']))
        {
            if(!hash_equals($_SESSION['databiz']['csrf'], $headers['csrf']))
                throw new Exception('Invalid header security token detected.');
        }

        if(empty($_SESSION['databiz']['data']))
            throw new Exception('User Session data not detected.');

        $data = json_decode(base64_decode($_SESSION['databiz']['data']), true);

        if(empty($data['org']))
            throw new Exception('User\'s Organisation not detected by Ajax Controller.');

        if(empty($data['org']['host_name']))
            throw new Exception('User\'s host name not detected by Ajax Controller.');

        if(empty($data['org']['db_name']))
            throw new Exception('User\'s database name not detected by Ajax Controller.');

        $org_host_name = $data['org']['host_name'];
        $org_db_name = $data['org']['db_name'];
        $org_instance_name = JCT_DB_SIUD_USER . '@' . $org_host_name . ':' . $org_db_name;
    }



    $namespace = (empty($app_param)) ? $namespace . '\\' : $namespace . '\\' . $app_param . '\\';

    if($app_registry->is_modular)
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
    unset($post['model_param']);


    $position = 'method';
    // method
    $method_param = (!empty($post['method_param'])) ? $post['method_param'] : 'index';
    $activity_method = strtolower($method_param);
    unset($post['method_param']);



    $position = 'connection';
    // Connections


    $position = 'default_connection';
    $connection = new Connection();
    $default_connection = $connection->get_connection($default_instance_name);
    if($default_connection == false)
        throw new Exception('An error was encountered while establishing the default database connection: ' . $connection->connection_error);





    $position = 'org_connection';
    $org_connection = null;
    if(!is_null($org_instance_name))
    {
        $tmp = $connection->set_org_connection($org_host_name, $org_db_name);
        if($tmp !== true)
            throw new Exception('An error was encountered while establishing the user\'s database connection: ' . $connection->connection_error);

        $org_connection = $connection->get_connection($org_instance_name);
    }




    $position = 'instantiation';
    $model = new $model_full_name($default_connection, $org_connection, new SectionRegistry());
    $controller = new $controller_full_name($model);

    if(!method_exists($controller, $method_param))
        throw new Exception('Invalid Method token detected.' . $method_param);

    $tmp = $controller->$method_param($post);

    if(!isset($tmp['error']))
    {
        $at = new ActivityTracker($default_connection);
        $at->record_activity($activity_model, $activity_method);
    }

    echo json_encode($tmp, JSON_UNESCAPED_SLASHES);
}
catch(Exception $e)
{
    echo json_encode(['error'=>'AJAX handler refused the request: ' . $e->getMessage(), 'position'=>$position], JSON_UNESCAPED_SLASHES);
}