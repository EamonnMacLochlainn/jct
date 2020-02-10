<?php

exit;
use JCT\Helper;

require_once '../jct_core/Config.php';
require_once '../jct_core/classes/Database.php';
require_once '../jct_core/classes/Helper.php';
require_once 'import_parameter_maps.php';

$db_mvs = new \JCT\Database('root', 'pass', 'jctregis_mvc_db', 'localhost', 'utf8');
if(!empty($db_mvs->db_error))
    die($db_mvs->db_error);

$db_oop = new \JCT\Database('root', 'pass', 'jctregis_jct_oop', 'localhost', 'utf8');
if(!empty($db_oop->db_error))
    die($db_oop->db_error);

try
{
    $db_oop->query(" SELECT * FROM jct_education_centers WHERE ( CenterID != 1 ) ");
    $db_oop->execute();
    $oop_centers = $db_oop->fetchAllAssoc();


    $db_mvs->beginTransaction();
    try
    {
        foreach($oop_centers as $i => $c)
        {
            $sub_str = 'EC_' . strtoupper(substr($c['CenterAbbr'], 0, 4));
            $county_id = $county_id_map[$c['CenterCountyID']];
            $region_id = $region_id_map[$c['CenterRegionID']];

            $landline = null;
            if(!empty($c['CenterPhone']))
                $landline = Helper::normalise_contact_number($area_code_map[$c['CenterAreaCodeID']] . $c['CenterPhone'], 'IE');

            $fax = null;
            if(!empty($c['CenterFax']))
                $fax = Helper::normalise_contact_number($area_code_map[$c['CenterAreaCodeID']] . $c['CenterFax'], 'IE');

            $mobile = null;
            if(!empty($c['CenterMobile']))
                $mobile = Helper::normalise_contact_number($network_code_map[$c['CenterNetworkCodeID']] . $c['CenterMobile'], 'IE');

            $email = (filter_var($c['CenterEmail'], FILTER_VALIDATE_EMAIL)) ? $c['CenterEmail'] : null;

            $db_mvs->query(" INSERT INTO org_details 
            ( guid, type_id, sub_type_id, country_id, country_code, 
             mode_of_communication, org_name, 
             add1, add2, add3, add4, city_town, postcode, eircode, county_id, show_county, 
             region_id, education_center_id, 
             landline, fax, mobile, email, notes, 
             has_unit_asd, has_unit_ebd, has_unit_mild, has_unit_moderate, 
             has_unit_sam, has_unit_sen, has_unit_other, is_deis_school, is_jcn_school, 
             hours, days_open, short_days, updated, updated_by, active ) VALUES 
            ( :guid, :type_id, :sub_type_id, :country_id, :country_code, 
             :mode_of_communication, :org_name, 
             :add1, :add2, :add3, NULL, :city_town, :postcode, :eircode, :county_id, :show_county, 
             :region_id, NULL, 
             :landline, :fax, :mobile, :email, NULL, 
             0, 0, 0, 0, 
             0, 0, 0, 0, 0, 
             NULL, NULL, NULL, NOW(), 1, 1 )");
            $db_mvs->bind(':guid', $sub_str);
            $db_mvs->bind(':type_id', 4);
            $db_mvs->bind(':sub_type_id', 1);
            $db_mvs->bind(':country_id', 372);
            $db_mvs->bind(':country_code', 'IE');
            $db_mvs->bind(':mode_of_communication', 'en');
            $db_mvs->bind(':org_name',$c['CenterName']);
            $db_mvs->bind(':add1', $c['CenterAdd1']);
            $db_mvs->bind(':add2', $c['CenterAdd2']);
            $db_mvs->bind(':add3', $c['CenterAdd3']);
            $db_mvs->bind(':city_town', $c['CenterCityTown']);
            $db_mvs->bind(':postcode', (empty($c['CenterPostCode'])) ? null : intval($c['CenterPostCode']));
            $db_mvs->bind(':eircode',(empty($c['CenterEirCode'])) ? null : intval($c['CenterEirCode']));
            $db_mvs->bind(':county_id', $county_id);
            $db_mvs->bind(':show_county', 1);
            $db_mvs->bind(':region_id', $region_id);
            $db_mvs->bind(':landline', $landline);
            $db_mvs->bind(':fax', $fax);
            $db_mvs->bind(':mobile', $mobile);
            $db_mvs->bind(':email', $email);
            $db_mvs->execute();

            $org_id = $db_mvs->lastInsertId();

            $contact_arr = ['contact_name'=>null,'landline'=>null,'fax'=>null,'mobile'=>null,'email'=>null];
            $contacts = [
                'principal' => $contact_arr,
                'secretary' => $contact_arr,
                'other' => $contact_arr
            ];
            $contacts['principal']['contact_name'] = $c['CenterDirector'];

            $db_mvs->query(" INSERT INTO org_contacts 
            ( org_id, contact_type, contact_name, landline, mobile, fax, email ) VALUES 
            ( {$org_id}, :contact_type, :contact_name, :landline, :mobile, :fax, :email )");
            foreach($contacts as $type => $params)
            {
                $db_mvs->bind(':contact_type',$type);
                foreach($params as $k => $v)
                    $db_mvs->bind(':' . $k, $v);
                $db_mvs->execute();
            }
        }

        $db_mvs->commit();
        echo 'Education Centers imported';
    }
    catch(Exception $e)
    {
        $db_mvs->rollBack();
        echo $e->getMessage();
        Helper::show($e->getTrace());
        throw new Exception();
    }

    return true;
}
catch(Exception $e)
{
    return ;
}