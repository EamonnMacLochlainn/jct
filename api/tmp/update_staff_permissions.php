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
    $app_slug = 'pm_scheduler';


    // check core database

    try
    {
        $_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
        if($_DB->db_error)
            throw new Exception($_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to core Database: ' . $e->getMessage());
    }

    $status[] = 'Core database connection set';


    $db = $_DB;
    $db->query(" SELECT db_name FROM org_details WHERE guid = '{$org_guid}' ");
    $db->execute();
    $org_db_name = $db->fetchSingleColumn();


    // check org database

    try
    {
        $_ORG_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, $org_db_host, 'UTF8');
        if(!empty($_ORG_DB->db_error))
            throw new Exception($_ORG_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }


    $db->query(" SELECT id, role_id FROM staff_role WHERE role_end IS NULL ");
    $db->execute();
    $staff = $db->fetchAllAssoc();

    $model_methods = [
        'home' => ['{"index":"rw"}'],
        'pupilmeeting' => ['{"index":"rw"}'],
        'pupilreservations' => ['{"index":"rw"}'],
        'stafftimetables' => ['{"index":"rw"}']
    ];

    foreach($staff as $u)
    {
        $id = $u['id'];
        $role_id = $u['role_id'];

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
                            ( :id, :role_id, 'user', NULL, 'home', NULL, NOW(), {$this->user_id} ) ");
            $db->bind(':id', $id);
            $db->bind(':role_id', $role_id);
            $db->execute();
        }

        foreach($model_methods as $model => $methods)
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
                            ( :id, :role_id, 'pm_scheduler', NULL, :model, :method, NOW(), {$this->user_id} ) ");
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