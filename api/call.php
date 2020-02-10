<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 01/03/2018
 * Time: 15:14
 */




/*if(empty($_POST))
    exit;*/

require_once '../ds_core/classes/Cryptor.php';
require_once 'AsciiEncrypt.php';
require_once 'api_interface.php';
require_once 'call_api.php';

$input = $_POST;
/*$input = [
    'username' => '17961E',
    'private_key' => '33212dfd1dsf',
    'app' => 'manager',
    'action' => 'org_synch',
    'data' => file_get_contents('tmp/17961e.json')
];*/


file_put_contents('call_input.txt', \JCT\Helper::show($input, true));





// as anything hitting this file is from a remote source,
// add token to input
$input['remote_api_call'] = 1;


$handler = new \JCT\call_api($input);
$response = $handler->response();

if(isset($input['verbose']))
{
    $d = new DateTime();
    $response['datetime'] = $d->format('Y-m-d H:i:s');
    $response['app'] = (isset($input['app'])) ? $input['app'] : '';
    $response['action'] = (isset($input['action'])) ? $input['action'] : '';
    $response['remote_api_call'] = (isset($input['remote_api_call'])) ? 1 : 0;
}

if(isset($input['custom']))
    $response['custom'] = $input['custom'];


$echo = true;
$response_type = (isset($input['response_type'])) ? $input['response_type'] : 'json';
switch($response_type)
{
    case('print'):
        $tmp = \JCT\Helper::show($response, true);
        break;
    case('json'):
        $tmp = json_encode($response, JSON_UNESCAPED_SLASHES);
        break;
    default:
        $tmp = $response;
        $echo = false;
        break;
}

if($echo)
    echo $tmp;
else
    return $tmp;

exit;