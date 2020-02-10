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

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Connection.php';
    require_once '../ds_core/classes/Helper.php';

    $org_guid = '16333Q';
    $pass = \JCT\Helper::hash_password('letmein');


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


    $db->query(" SELECT db_name FROM org_details WHERE guid = '{$org_guid}' ");
    $db->execute();
    $org_db_name = $db->fetchSingleColumn();


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


    $org_db->query(" SELECT id, role_id FROM staff_role WHERE role_end IS NULL ");
    $org_db->execute();
    $staff = $org_db->fetchAllAssoc();



    foreach($staff as $u)
    {
        $id = $u['id'];
        $role_id = $u['role_id'];


        $db->query(" UPDATE user SET pass = '{$pass}' WHERE id = {$id} ");
        $db->execute();

        $db->query(" SELECT tbl_id, active FROM user_org WHERE ( id = {$id} AND role_id = {$role_id} AND guid = '{$org_guid}' ) ");
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if( (!empty($tmp)) && (!empty($tmp['active'])) )
            continue;

        $tbl_id = $tmp['tbl_id'];
        if(!empty($tmp))
        {
            $db->query(" UPDATE user_org SET active = 1 WHERE tbl_id = {$tbl_id} ");
            $db->execute();
            continue;
        }

        $db->query(" INSERT INTO user_org 
        ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
        ( {$id}, '{$org_guid}', {$role_id}, NULL, NULL, 1, NOW(), -1 ) ");
        $db->execute();
    }



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