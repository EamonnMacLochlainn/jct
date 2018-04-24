<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/08/2016
 * Time: 16:25
 */

namespace JCT\texter;


use JCT\Core;
use JCT\Database;
use Exception;

class texter_model extends Core
{
    function __construct()
    {
        parent::__construct();
    }

    function get_user_service_provider($org_guid)
    {
        $db = new Database();
        try
        {
            $db->query(" SELECT org_sms_provider FROM org_details WHERE org_guid = :org_guid ");
            $db->bind(':org_guid', strtoupper($org_guid));
            $db->execute();
            $tmp = $db->fetchSingleColumn();

            return ['success'=>'ok', 'provider'=>$tmp];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_neon_account_parameters($org_guid)
    {
        $db = new Database();
        try
        {
            $db->query(" SELECT neon_username, neon_pass FROM neon_parameters WHERE org_guid = :org_guid ");
            $db->bind(':org_guid', $org_guid);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp))
                throw new Exception('No user parameters were found for this Organisation, for this Provider (NeonSMS).');

            return ['success'=>'ok', 'account_parameters'=>$tmp];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}