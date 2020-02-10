<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */

exit();
$status = [];
$time_start = microtime(true);
try
{
    // load required

    require_once '../../ds_core/Config.php';
    require_once '../../ds_core/classes/Database.php';
    require_once '../../ds_core/classes/Connection.php';
    require_once '../../ds_core/classes/Cryptor.php';
    require_once '../../ds_core/classes/Helper.php';

    $org_guid = '19339U';
    $event_id = 1;
    $org_db_host = 'localhost';
    $org_db_name = 'databizs_org_' . strtolower($org_guid);


    // check org database

    try
    {
        $_ORG_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, $org_db_host, 'UTF8');
        if(!empty($_ORG_DB->db_error))
            throw new Exception($_ORG_DB->db_error);


        $_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, 'databizs_core', $org_db_host, 'UTF8');
        if(!empty($_ORG_DB->db_error))
            throw new Exception($_ORG_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }

    $status[] = 'Organisation database connection set';




    $_ORG_DB->query(" SELECT id FROM person WHERE is_guardian = 1 ");
    $_ORG_DB->execute();
    $ids = $_ORG_DB->fetchAllColumn();

    $models = [
        'public' => ['{"index":"rw"}']
    ];


    $db = $_ORG_DB;
    $db->query(" SELECT id FROM dashboard_notification WHERE app_specific_id = {$event_id} ");
    $db->execute();
    $notification_id = $db->fetchSingleColumn();

    if(empty($notification_id))
        throw new Exception('No Notification ID found.');

    foreach($ids as $id)
    {
        $role_id = 8;
        $db = $_ORG_DB;

        $db->query(" SELECT tbl_id FROM app_screen_user WHERE 
                        ( id = :id AND role_id = :role_id AND app_slug = 'user' 
                         AND model = 'home' ) ");
        $db->bind(':id', $id);
        $db->bind(':role_id', $role_id);
        $db->execute();
        $tbl_id = $db->fetchSingleColumn();

        if(empty($tbl_id))
        {
            $db->query(" INSERT INTO app_screen_user 
                            ( id, role_id, app_slug, module, model, method, updated, updated_by ) VALUES 
                            ( :id, :role_id, 'user', NULL, 'home', NULL, NOW(), {$id} ) ");
            $db->bind(':id', $id);
            $db->bind(':role_id', $role_id);
            $db->execute();
        }

        $db->query(" SELECT tbl_id FROM dashboard_notification_user WHERE ( id = :id AND role_id = :role_id AND notification_id = :notification_id ) ");
        $db->bind(':id', $id);
        $db->bind(':role_id', $role_id);
        $db->bind(':notification_id', $notification_id);
        $db->execute();
        $tmp = $db->fetchSingleColumn();

        if(empty($tmp))
        {
            $db->query(" INSERT INTO dashboard_notification_user 
            ( id, role_id, notification_id, is_read ) VALUES 
            ( :id, :role_id, :notification_id, 0) ");
            $db->bind(':id', $id);
            $db->bind(':role_id', $role_id);
            $db->bind(':notification_id', $notification_id);
            $db->execute();
        }

        foreach($models as $model => $methods)
        {
            foreach($methods as $method)
            {
                $db->query(" SELECT tbl_id FROM app_screen_user WHERE 
                        ( id = :id AND role_id = :role_id AND app_slug = 'pm_scheduler' 
                         AND model = :model AND method = :method ) ");
                $db->bind(':id', $id);
                $db->bind(':role_id', $role_id);
                $db->bind(':model', $model);
                $db->bind(':method', $method);
                $db->execute();
                $tbl_id = $db->fetchSingleColumn();

                if(empty($tbl_id))
                {
                    $db->query(" INSERT INTO app_screen_user 
                            ( id, role_id, app_slug, module, model, method, updated, updated_by ) VALUES 
                            ( :id, :role_id, 'pm_scheduler', NULL, :model, :method, NOW(), {$id} ) ");
                    $db->bind(':id', $id);
                    $db->bind(':role_id', $role_id);
                    $db->bind(':model', $model);
                    $db->bind(':method', $method);
                    $db->execute();
                }
            }
        }

        $db = $_DB;

        $db->query(" SELECT tbl_id FROM user_org WHERE ( id = :id AND role_id = :role_id AND guid = '{$org_guid}' ) ");
        $db->bind(':id', $id);
        $db->bind(':role_id', $role_id);
        $db->execute();
        $db->execute();
        $tbl_id = $db->fetchSingleColumn();

        if(empty($tbl_id))
        {
            $db->query(" INSERT INTO user_org 
                            ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                            ( :id, '{$org_guid}', :role_id, NULL, NULL, 1, NOW(), -1 ) ");
            $db->bind(':id', $id);
            $db->bind(':role_id', $role_id);
            $db->execute();
        }
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