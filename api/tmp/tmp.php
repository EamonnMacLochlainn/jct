<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 16/03/2018
 * Time: 12:23
 */


use JCT\Database;

require_once '../../ds_core/Config.php';
require_once '../../ds_core/classes/Database.php';
require_once '../../ds_core/classes/Helper.php';



$prefix = 'databizs';
$org_guid = '17961E';
$org_guid_lwr = strtolower($org_guid);
$org_op_db_name = $prefix . '_op_' . $org_guid_lwr;

$org = new Database(
    $prefix . '_create',
    JCT_DB_CI_PASS,
    $prefix . '_org_' . $org_guid_lwr,
    JCT_DB_CI_HOST,
    'utf8'
);

if(!$org->db_valid)
    exit('While making org DB connection: ' . $org->db_error);

$db = $org;

$db->query(" INSERT INTO op_charge_history 
                ( id, charge_status, updated, updated_by ) VALUES 
                ( :charge_id, 'initial_fees_set', NOW(), 1 ) ");
foreach(range(1,130) as $charge_id)
{
    $db->bind(':charge_id', $charge_id);
    $db->execute();
}

echo 'done';