<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/10/2017
 * Time: 11:35
 */


error_reporting(E_ALL ^ E_WARNING);


$status = [];
$time_start = microtime(true);

try
{
    // load required

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Helper.php';


    // get input

    $opts = ['http' => ['header' => 'Accept-Charset: UTF-8, *;q=0']];
    $context = stream_context_create($opts);
    $post = file_get_contents('sample_data/19374w_json_upload.json',false, $context);
    #$post = file_get_contents('php://input',false, $context);
    #$post = \DS\Helper::clean_unicode_literals($post);

    $encoding = mb_detect_encoding($post);
    #echo $encoding;

    if(empty($post))
        throw new Exception('No input detected.');

    $status[] = 'Input detected';

    if(!mb_check_encoding($post, 'UTF-8'))
        throw new Exception('Invalid encoding detected.');

    $status[] = 'Encoding validated';



    // parse input

    $data = json_decode($post, true);

    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $error = '';
            break;
        case JSON_ERROR_DEPTH:
            $error = 'The maximum stack depth has been exceeded.';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $error = 'Invalid or malformed JSON.';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $error = 'Control character error, possibly incorrectly encoded.';
            break;
        case JSON_ERROR_SYNTAX:
            $error = 'Syntax error, malformed JSON.';
            break;
        case JSON_ERROR_UTF8:
            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
            break;
        case JSON_ERROR_RECURSION:
            $error = 'One or more recursive references in the value to be encoded.';
            break;
        case JSON_ERROR_INF_OR_NAN:
            $error = 'One or more NAN or INF values in the value to be encoded.';
            break;
        case JSON_ERROR_UNSUPPORTED_TYPE:
            $error = 'A value of a type that cannot be encoded was given.';
            break;
        default:
            $error = 'Unknown JSON error occurred.';
            break;
    }

    if(!empty($error))
        throw new Exception($error);

    $data = array_change_key_case($data, CASE_LOWER);

    $status[] = 'JSON parse successful';



    // check root values

    $required_fields = ['guid','token','version','datetime','group_leader','assistant','member','guardian_default','guardian','group_super','group_class'];
    $omitted = array_diff($required_fields, array_keys($data));

    if(!empty($omitted))
    {
        $omitted_str = json_encode($omitted);
        throw new Exception('The following required fields were not found: ' . $omitted_str);
    }

    $blanks = [];
    foreach($required_fields as $field)
    {
        if(empty($data[$field]))
            $blanks[] = $field;
    }

    if(!empty($blanks))
    {
        $blanks_str = json_encode($blanks);
        throw new Exception('The following required fields were empty: ' . $blanks_str);
    }

    unset($required_fields);
    unset($blanks);
    $status[] = 'Required fields found';




    // check token

    // todo

    unset($data['token']);
    $status[] = 'Token verified';




    // check version

    if(floatval($data['version']) != 0.2)
        throw new Exception('Incorrect version number');

    unset($data['version']);
    $status[] = 'Version verified';




    // check datetime

    $upload_datetime = DateTime::createFromFormat('Y:m:d H:i:s', $data['datetime']);
    if(!$upload_datetime)
        throw new Exception('Invalid upload datetime detected');

    unset($data['datetime']);
    $status[] = 'Datetime set';




    // check core database

    try
    {
        $db = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
        if($db->db_error)
            throw new Exception($db->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to core Database: ' . $e->getMessage());
    }

    $status[] = 'Core database connection set';




    // check roll number

    $org_guid = strtoupper(trim($data['guid']));

    $db->query(" SELECT id, host_name, db_name, active FROM org_details WHERE guid = :guid ");
    $db->bind(':guid', $org_guid);
    $db->execute();
    $tmp = $db->fetchSingleAssoc();

    if(empty($tmp))
        throw new Exception('Unrecognised organisation GUID');

    if(intval($tmp['active']) < 1)
        throw new Exception('Inactive organisation GUID');

    if(empty($tmp['host_name']))
        throw new Exception('Organisation host not found');

    if(empty($tmp['db_name']))
        throw new Exception('Organisation database name not found');

    $org_db_host = $tmp['host_name'];
    $org_db_name = $tmp['db_name'];

    unset($data['guid']);
    $status[] = 'Organisation GUID set';




    // check org database

    try
    {
        $org_db = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, $org_db_host, 'UTF8');
        if(!empty($org_db->db_error))
            throw new Exception($org_db->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }

    #\DS\Helper::show($org_db);
    $status[] = 'Organisation database connection set';


    // get system IDs for members

    $member_id_map = []; # ext_id => id
    $org_db->query(" SELECT id, ext_id FROM person WHERE is_member = 1 ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    foreach($tmp as $t)
        $member_id_map[ $t['ext_id'] ] = $t['id'];

    // get system IDs for class groups

    $group_class_map = []; # ext_id => id
    $org_db->query(" SELECT id, ext_id FROM group_class WHERE 1 ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    foreach($tmp as $t)
        $group_class_map[ $t['ext_id'] ] = $t['id'];



    // check members are in correct class groups

    $member_class_map = []; # member_id => class_id
    $org_db->query(" SELECT id, group_class_id FROM member_group_class WHERE in_group_end IS NULL ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    foreach($tmp as $t)
        $member_class_map[ $t['id'] ] = $t['group_class_id'];


    foreach($data['member'] as $m)
    {
        $ext_id = $m['id'];
        if(!isset($member_id_map[ $ext_id ]))
        {
            \JCT\Helper::show($member_id_map);
            throw new Exception('Pupil found whose external ID did not map: (' . $ext_id . ') ' . json_encode($m));
        }

        $id = $member_id_map[ $ext_id ];

        $class_ext_id = $m['group_class'];
        if(!isset($group_class_map[ $class_ext_id ]))
        {
            \JCT\Helper::show($member_id_map);
            throw new Exception('Pupil found whose external class ID did not map: (' . $class_ext_id . ') ' . json_encode($m));
        }

        $class_id = $group_class_map[ $class_ext_id ];

        if(!isset($member_class_map[ $id ]))
        {
            \JCT\Helper::show($member_class_map);
            throw new Exception('Pupil found whose internal class ID did not map: ' . json_encode($m));
        }
        $member_set_class_id = $member_class_map[ $id ];

        if($class_id !== $member_set_class_id)
            throw new Exception('Pupil found that was saved to the wrong class: (' . $class_id . ' => ' . $member_set_class_id . ') ' . json_encode($m));
    }



    foreach($status as $s)
        echo $s . "<br/>";

    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Total Execution Time: '.$execution_time.' seconds';
}
catch(Exception $e)
{
    $time_end = microtime(true);
    $status[] = $e->getMessage();

    foreach($status as $s)
        echo $s . "<br/>";

    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Transaction failed. Total Execution Time: '.$execution_time.' seconds';
}