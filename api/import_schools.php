<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 17/04/2018
 * Time: 13:29
 */


namespace JCT;

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Database.php';
require_once '../ds_core/classes/Helper.php';

use Exception;
use JCT\Helper;

try
{
    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'school_import_template.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $data = Helper::read_csv_to_array($source_path);


    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');

    $db->query(" SELECT id, title FROM prm_county WHERE active = 1 ");
    $db->execute();
    $tmp = $db->fetchAllAssoc();

    $counties = [];
    foreach($tmp as $t)
        $counties[ $t['id'] ] = $t['title'];

    $d = new \DateTime();

    $schools = [];
    foreach($data as $s)
    {
        $address = [];
        if(!empty($s['add_1']))
            $address[] = $s['add_1'];
        if(!empty($s['add_2']))
            $address[] = $s['add_2'];
        if(!empty($s['add_3']))
            $address[] = $s['add_3'];

        $city_town = $s['city_town'];
        if(!empty($s['post_code']))
        {
            $address[] = $city_town;
            $city_town = 'Dublin';
        }


        $county_id = array_search($s['county'], $counties);
        $show_county = ($city_town == $s['county']) ? 0 : 1;

        $landline = Helper::normalise_contact_number($s['public_landline'], 'IE');
        $mobile = Helper::normalise_contact_number($s['public_mobile'], 'IE');
        $fax = Helper::normalise_contact_number($s['public_fax'], 'IE');

        $public_contact = [
            'landline' => ($landline === null) ? '' : $landline,
            'mobile' => ($mobile === null) ? '' : $mobile,
            'fax' => ($fax === null) ? '' : $fax,
            'email' => (filter_var($s['public_email'], FILTER_VALIDATE_EMAIL)) ? strtolower($s['public_email']) : ''
        ];
        $public_contact_json = json_encode($public_contact);

        $principal_contact = [
            'name' => (!empty($s['principal_name'])) ? $s['principal_name'] : '',
            'landline' => '',
            'mobile' => '',
            'fax' => '',
            'email' => ''
        ];
        $principal_contact_json = json_encode($principal_contact);

        $contact_arr = [
            'name'=>'', 'landline'=>'', 'mobile'=>'', 'fax'=>'', 'email'=>''
        ];
        $contact_json = json_encode($contact_arr);


        /*
         *
            [roll_number] => 20332N
            [school_name] => Gaelscoil Éadan Doire
            [add_1] => Killanna
            [add_2] =>
            [add_3] =>
            [city_town] => Éadan Doire
            [post_code] =>
            [county] => Offaly
            [eircode] =>
            [public_landline] => 046-9773322
            [public_mobile] => 087-6274352
            [public_fax] =>
            [public_email] => oifig@gseadandoire.ie
            [principal_name] => Póla Ní Chinnsealaigh
            [secretarial_name] =>
         * */

        $schools[] = [
            'guid' => strtoupper(trim($s['roll_number'])),
            'host_name' => null,
            'db_name' => null,
            'db_version' => 0,
            'mailer_params' => null,
            'type_id' => 1,
            'sub_type_id' => 0,
            'country_id' => 372,
            'country_code' => 'IE',
            'mode_of_communication' => 1,
            'org_name' => $s['school_name'],
            'blurb' => null,
            'add1' => (!empty($address[0])) ? $address[0] : null,
            'add2' => (!empty($address[1])) ? $address[1] : null,
            'add3' => (!empty($address[2])) ? $address[2] : null,
            'add4' => (!empty($address[3])) ? $address[3] : null,
            'city_town' => $city_town,
            'postcode' => (empty($s['post_code'])) ? NULL : $s['post_code'],
            'eircode' => (empty($s['eircode'])) ? NULL : $s['eircode'],
            'county_id' => $county_id,
            'show_county' => $show_county,
            'notes' => null,
            'public_contact' => $public_contact_json,
            'principal_contact' => $principal_contact_json,
            'secretarial_contact' => $contact_json,
            'other_contact' => $contact_json,
            'hours' => '{"day_begins":"","day_ends":"","break_begins":"","break_ends":"","lunch_begins":"","lunch_ends":""}',
            'days_open' => '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
            'short_days' => null,
            'last_backup' => null,
            'updated' => $d->format('Y-m-d H:i:s'),
            'updated_by' => -1,
            'active' => 0
        ];
    }


    $db->beginTransaction();
    try
    {
        $db->query(" INSERT INTO org_details 
            ( guid, host_name, db_name, db_version, mailer_params, 
            type_id, sub_type_id, country_id, country_code, mode_of_communication, 
            org_name, blurb, add1, add2, add3, add4, 
            city_town, postcode, eircode, county_id, show_county, notes, 
            public_contact, principal_contact, secretarial_contact, other_contact, 
            hours, days_open, short_days, last_backup, updated, updated_by ) VALUES 
            ( :guid, NULL, NULL, 0, NULL, 
            1, 0, 372, 'IE', 1, 
            :org_name, NULL, :add1, :add2, :add3, :add4, 
            :city_town, :postcode, :eircode, :county_id, :show_county, NULL, 
            :public_contact, :principal_contact, :secretarial_contact, :other_contact, 
            :hours, :days_open, NULL, NULL, NOW(), -1 )");
        foreach($schools as $s)
        {
            $db->bind(':guid', $s['guid']);
            $db->bind(':org_name', $s['org_name']);
            $db->bind(':add1', $s['add1']);
            $db->bind(':add2', $s['add2']);
            $db->bind(':add3', $s['add3']);
            $db->bind(':add4', $s['add4']);
            $db->bind(':city_town', $s['city_town']);
            $db->bind(':postcode', $s['postcode']);
            $db->bind(':eircode', $s['eircode']);
            $db->bind(':county_id', $s['county_id']);
            $db->bind(':show_county', $s['show_county']);
            $db->bind(':public_contact', $s['public_contact']);
            $db->bind(':principal_contact', $s['principal_contact']);
            $db->bind(':secretarial_contact', $s['secretarial_contact']);
            $db->bind(':other_contact', $s['other_contact']);
            $db->bind(':hours', $s['hours']);
            $db->bind(':days_open', $s['days_open']);
            $db->execute();
        }

        $db->commit();
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage());
    }

    return true;
}
catch(Exception $e)
{
    echo $e->getMessage();
}