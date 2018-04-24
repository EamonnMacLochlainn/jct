<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 01/03/2018
 * Time: 15:14
 */




/*if(empty($_POST))
    exit;*/

require_once 'AsciiEncrypt.php';
require_once 'api_interface.php';
require_once 'call_api.php';

$input = $_POST;


// {"roll_number":"19702N","pass":"64912223","datetime":"2018-04-06 10:57:34","data":[{"Forename":"Stacey","Surname":"Harrison","PPS":"8144050O","Dob":"08/11/2012","Gender":"2","Add1":"40 Oak Avenue","Add2":"Cnocan Rua","Add3":"Salthill","Add4":"","County":"27","Eircode":"","Nationality":"678","PupilSource":"1","MotherTongue":"1","Ethnicity":"10","Religion":"1"}]}
/*$input = [
    'username' => '19702N',
    'private_key' => '64912223',
    'app' => 'nsadmin',
    'action' => 'pod_wl_store',
    'data' => '[{"Forename":"Stacey","Surname":"Harrison","PPS":"8144050O","Dob":"08/11/2012","Gender":"2","Add1":"40 Oak Avenue","Add2":"Cnocan Rua","Add3":"Salthill","Add4":"","County":"27","Eircode":"","Nationality":"678","PupilSource":"1","MotherTongue":"1","Ethnicity":"10","Religion":"1"}]',
    'datetime' => $d->format('2018-04-06 10:57:34')
];*/



// cadoo call
$d = new DateTime();
$input = [
    'username' => 'databiz',
    'private_key' => '2154910792',
    'app' => 'messaging',
    'action' => 'cadoo_installed',

    'data' => [
        'numbers' => '0867345627_0879765684',//_03442411599
        //'override' => 1
    ],
    /*'data' => [
        'text' => 'Test SMS @ ' . $d->format('H:i') . '. No response needed.',
        'numbers' => '0867345627',
        'source_number' => '0879765684'
    ],*/
];

\JCT\Helper::show($input);



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