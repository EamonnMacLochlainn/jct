<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 11/07/2018
 * Time: 12:13
 */
namespace JCT;

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Database.php';
require_once '../ds_core/classes/Helper.php';

$org_guid = '19374W';

$org_db_name = 'databizs_op_' . strtolower($org_guid);
$org_db_host = JCT_DB_SIUD_HOST;


try
{
    $db_auth = new Database('databizs_authU', 'authPass', 'databizs_authorisation', $org_db_host, 'UTF8');
    if(!empty($db_auth->db_error))
        throw new \Exception($db_auth->db_error);

    $db_org = new Database('databizs_orgU', 'orgPass', $org_db_name, $org_db_host, 'UTF8');
    if(!empty($db_org->db_error))
        throw new \Exception($db_org->db_error);

    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');


    // get all charges from old DB
    $db_org->query(" SELECT ChargeID, 
    ChargeDesc AS `title`, ChargeYear AS `year_starting`, ChargeTypeID AS `type_id`, 
    ChargeDueFrom AS `due_from`, ChargeDueTo AS `due_to`, ChargeStatusID AS `active`, 
    bool_ChargeIsMandatory AS `mandatory`, bool_ChargeIsFixedAmt AS `fixed`, 
    ChargeStopped AS `stopped`, ChargeCreated AS `created`, ChargeModified AS `updated` 
    FROM charges WHERE 1 ");
    $db_org->execute();
    $charges = $db_org->fetchAllAssoc('ChargeID');

    // get all fees from old DB
    $db_org->query(" SELECT TblID AS `fee_id`, IndividualExtID AS `member_ext_id`,  
    ChargeID AS `charge_id`, InitialDue AS `initial_due`, 
    TotalDue AS `amount_due`, TotalPaid AS `amount_paid`, 
    FeeCreated AS `created`, FeeModified AS `updated`, 
    FeeCancelledDate AS `cancelled`, FeeNotificationSent AS `notification` 
    FROM charges_individuals ci 
    LEFT JOIN individuals i ON ( ci.IndividualID = i.IndividualID ) 
    WHERE 1 ");
    $db_org->execute();
    $fees = $db_org->fetchAllAssoc();

    $individuals_fees = [];
    foreach($fees as $f)
    {
        $member_ext_id = $f['member_ext_id'];
        unset($f['member_ext_id']);


        if(!isset($individuals_fees[ $member_ext_id ]))
            $individuals_fees[ $member_ext_id ] = [];

        $individuals_fees[ $member_ext_id ][] = $f;
    }

    $db_org->query(" SELECT IndividualExtID, 
    IndividualID AS `system_id`, 
    IndividualFName AS `fname`,
    IndividualLName AS `lname`
    FROM individuals WHERE 1 ");
    $db_org->execute();
    $individuals_wo_fees = $db_org->fetchAllAssoc('IndividualExtID');

    $individuals = [];
    foreach($individuals_wo_fees as $member_ext_id => $m)
    {
        if(!isset($individuals_fees[ $member_ext_id ]))
            continue;

        $m['fees'] = $individuals_fees[ $member_ext_id ];
        $individuals[ $member_ext_id ] = $m;
    }


    // get transactions and match with fee IDs
    $db_org->query(" SELECT TransactionID AS `transaction_id`,  
    TransactionAmt AS `amount`, ci.TblID AS `fee_id` 
    FROM transaction_charges tc 
    LEFT JOIN charges_individuals ci ON ( tc.IndividualID = ci.IndividualID AND tc.ChargeID = ci.ChargeID )
    WHERE 1 ");
    $db_org->execute();
    $transactions_raw = $db_org->fetchAllAssoc();

    Helper::show($individuals);


    // get all children from old db
    // get all their fees
    // get all transactions
    // get users per transaction

}
catch(\Exception $e)
{
    echo $e->getMessage();
}