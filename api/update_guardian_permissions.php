<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */

$status = [];
$time_start = microtime(true);
try
{
    // load required

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Connection.php';
    require_once '../ds_core/classes/Cryptor.php';
    require_once '../ds_core/classes/Helper.php';
    require_once '../ds_core/classes/Mailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';

    $org_guid = '19374W';
    $org_name = 'Our Lady\'s Grove Primary School';
    $org_neon_pass = 'claudine';
    $event_id = 1;
    $day_details = [];




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

    $org_guid = strtoupper($org_guid);

    $db->query(" SELECT id, org_name, blurb, host_name, db_name, active, mailer_params FROM org_details WHERE guid = :guid ");
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

    $status[] = 'Organisation GUID set';

    if(empty($tmp['mailer_params']))
        throw new Exception('No Mailer details retrieved for this Organisation.');

    $org_name = $tmp['org_name'];
    $org_blurb = $tmp['blurb'];
    $mailer_settings = json_decode($tmp['mailer_params'], true);

    if(empty($org_name))
        throw new Exception('No name defined for this Organisation.');
    if(empty($mailer_settings['server']))
        throw new Exception('No mail server defined for this Organisation.');
    if(empty($mailer_settings['user']))
        throw new Exception('No mail server user defined for this Organisation.');
    if(empty($mailer_settings['pass']))
        throw new Exception('No mail server password defined for this Organisation.');
    if(empty($mailer_settings['port']))
        throw new Exception('No mail server port defined for this Organisation.');
    if(empty($mailer_settings['type']))
        throw new Exception('No mail server type defined for this Organisation.');
    if(empty($mailer_settings['smtp_auth']))
        throw new Exception('Use of SMTP authentication has not been defined for this Organisation.');
    if(empty($mailer_settings['smtp_encryption']))
        throw new Exception('No SMTP encryption type defined for this Organisation.');

    $mail_server = $mailer_settings['server'];
    $mail_user = $mailer_settings['user'];
    $mail_from = $mailer_settings['user'];
    $mail_from_name = $mailer_settings['user'];
    $mail_reply_to = $mailer_settings['user'];
    $mail_pass = \JCT\Cryptor::Decrypt($mailer_settings['pass']);
    $mail_port = $mailer_settings['port'];

    $mail_use_smtp = ($mailer_settings['type'] == 'SMTP');
    $mail_smtp_auth = ($mailer_settings['smtp_auth'] == 'true');
    $mail_smtp_encryption = $mailer_settings['smtp_encryption'];


    $status[] = 'Organisation Email Settings set';




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




    $org_db->query(" SELECT id FROM person WHERE is_guardian = 1 ");
$org_db->execute();
$ids = $org_db->fetchAllColumn();

    foreach($ids as $id)
    {
        $org_db->query(" INSERT IGNORE INTO app_screen_user 
        ( id, app_slug, model, method ) VALUES 
        ( {$id}, 'nsadmin', 'publichome', :json_str ) ");
        $org_db->bind(':json_str', '{"index":"r"}');
        $org_db->execute();


        $org_db->query(" INSERT IGNORE INTO app_screen_user 
        ( id, app_slug, model, method ) VALUES 
        ( {$id}, 'nsadmin', 'parentmeetingplanner', :json_str ) ");
        $org_db->bind(':json_str', '{"guardiantimetables":"rw"}');
        $org_db->execute();
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