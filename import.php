<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/04/2018
 * Time: 08:02
 */

namespace JCT;


use Exception;

class import
{
    private $_DB;
    public $db_error;

    private $old_db_name;
    private $new_db_name;

    private $area_codes = [];
    private $network_codes = [];
    private $salutations = [];

    private $school_ids = [];
    private $teacher_ids = [];

    public $stage = [];

    function __construct(Database $db, $old_db_name, $new_db_name)
    {
        $this->_DB = $db;
        $this->old_db_name = $old_db_name;
        $this->new_db_name = $new_db_name;
        $this->stage[] = 'DB connection set.';
    }

    function import()
    {
        try
        {
            $db = $this->_DB;
            $old_db = $this->old_db_name;
            $new_db = $this->new_db_name;

            $db->query(" DELETE FROM {$new_db}.org_school WHERE 1 ");
            $db->execute();

            $db->query(" DELETE FROM {$new_db}.ind_person WHERE 1 ");
            $db->execute();

            $db->query(" DELETE FROM {$new_db}.org_education_center WHERE 1 ");
            $db->execute();



            $tmp = $this->import_parameter_tables();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->set_phone_codes();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->import_education_centers();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->import_schools();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->set_teacher_ids();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->set_salutations();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->import_school_users();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);


            Helper::show($this->stage);
            echo 'Import complete.';
            exit;
        }
        catch(Exception $e)
        {
            Helper::show($this->stage);
            echo $e->getMessage();
            exit;
        }
    }



    private function import_education_centers()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->query(" SELECT * FROM {$old_db}.jct_education_centers WHERE 1 ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();


        $db->beginTransaction();
        try
        {
            $db->query(" INSERT INTO {$new_db}.org_education_center 
              ( id, title, abbr, add1, add2, add3, add4, city_town, post_code, county_id, include_county, eircode, 
                public_landline, public_fax, public_mobile, public_email, 
                is_active, merged_with, created, updated, updated_by ) VALUES 
              ( :id, :title, :abbr, :add1, :add2, :add3, NULL, :city_town, :post_code, :county_id, 1, :eircode, 
                :public_landline, :public_fax, :public_mobile, :public_email, 
                1, 0, NOW(), NOW(), 1 ) ");
            foreach($tmp as $t)
            {
                $add1 = (!empty($t['CenterAdd1'])) ? $t['CenterAdd1'] : null;
                $add2 = (!empty($t['CenterAdd2'])) ? $t['CenterAdd2'] : null;
                $add3 = (!empty($t['CenterAdd3'])) ? $t['CenterAdd3'] : null;
                $city_town = (!empty($t['CenterCityTown'])) ? $t['CenterCityTown'] : null;
                $post_code = (!empty(intval($t['CenterPostCode']))) ? intval($t['CenterPostCode']) : 0;

                if($city_town === null)
                {
                    if($add3 !== null)
                    {
                        $city_town = $add3;
                        $add3 = null;
                    }
                    elseif($add2 !== null)
                    {
                        $city_town = $add2;
                        $add2 = null;
                    }
                    else
                    {
                        $city_town = $add1;
                        $add1 = null;
                    }
                }

                $landline = $fax = $mobile = null;

                if(!empty($t['CenterPhone']))
                {
                    $area_code = $this->area_codes[ $t['CenterAreaCodeID'] ];
                    $landline = Helper::normalise_contact_number($area_code . $t['CenterPhone'], 'IE');
                }

                if(!empty($t['CenterFax']))
                {
                    $area_code = $this->area_codes[ $t['CenterAreaCodeID'] ];
                    $fax = Helper::normalise_contact_number($area_code . $t['CenterFax'], 'IE');
                }

                if(!empty($t['CenterMobile']))
                {
                    $network_code = $this->network_codes[ $t['CenterNetworkCodeID'] ];
                    $mobile = Helper::normalise_contact_number($network_code . $t['CenterMobile'], 'IE');
                }


                $db->bind(':id', $t['CenterID']);
                $db->bind(':title', $t['CenterName']);
                $db->bind(':abbr', $t['CenterAbbr']);
                $db->bind(':add1', $add1);
                $db->bind(':add2', $add2);
                $db->bind(':add3', $add3);
                $db->bind(':city_town', $city_town);
                $db->bind(':post_code', $post_code);
                $db->bind(':county_id', $t['CenterCountyID']);
                $db->bind(':eircode', $t['CenterEirCode']);
                $db->bind(':public_landline', $landline);
                $db->bind(':public_fax', $fax);
                $db->bind(':public_mobile', $mobile);
                $db->bind(':public_email', $t['CenterEmail']);
                $db->execute();
            }

            $db->commit();
            $this->stage[] = 'Education centers imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }


    private function import_schools()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->query(" SELECT s.*, 
              sc.SchoolName, sc.SchoolRegionID, sc.SchoolAdd1, sc.SchoolAdd2, sc.SchoolAdd3, sc.SchoolCityTown, sc.SchoolCountyID, sc.SchoolCityPostCode, sc.SchoolEirCode, 
              sc.SchoolAreaCodeID, sc.SchoolPhoneNumber, sc.SchoolFaxNumber, sc.SchoolNetworkCodeID, sc.SchoolMobileNumber, sc.SchoolEmail 
              FROM {$old_db}.jct_schools s 
              LEFT JOIN {$old_db}.jct_school_contact sc ON ( s.SchoolID = sc.SchoolID )  
              WHERE 1 ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();


        $db->beginTransaction();
        try
        {
            foreach($tmp as $t)
            {
                $roll_number = $t['SchoolRollNumber'];

                if($roll_number == '11111A' || $roll_number == '10000Z')
                    continue;

                if(empty($t['SchoolName']))
                    continue;

                $this->school_ids[ $t['SchoolID'] ] = $roll_number;



                $add1 = (!empty($t['SchoolAdd1'])) ? $t['SchoolAdd1'] : null;
                $add2 = (!empty($t['SchoolAdd2'])) ? $t['SchoolAdd2'] : null;
                $add3 = (!empty($t['SchoolAdd3'])) ? $t['SchoolAdd3'] : null;
                $city_town = (!empty($t['SchoolCityTown'])) ? $t['SchoolCityTown'] : null;
                $post_code = (!empty(intval($t['SchoolCityPostCode']))) ? intval($t['SchoolCityPostCode']) : 0;

                $is_deis = (intval($t['SchoolIsDEIS']) > 0) ? 1 : 0;
                $is_jcn = (intval($t['SchoolIsJCNSchool']) > 0) ? 1 : 0;
                $has_spu = (intval($t['SchoolHasSpecialUnitClass']) > 0) ? 1 : 0;
                $mode_of_instruction = (intval($t['SchoolLanguageID']) == 1) ? 'en' : 'ga';

                if($city_town === null)
                {
                    if($add3 !== null)
                    {
                        $city_town = $add3;
                        $add3 = null;
                    }
                    elseif($add2 !== null)
                    {
                        $city_town = $add2;
                        $add2 = null;
                    }
                    else
                    {
                        $city_town = $add1;
                        $add1 = null;
                    }
                }

                $landline = $fax = $mobile = null;

                if( (!empty($t['SchoolPhoneNumber'])) && (isset($this->area_codes[ $t['SchoolAreaCodeID'] ])) )
                {
                    $area_code = $this->area_codes[ $t['SchoolAreaCodeID'] ];
                    $landline = Helper::normalise_contact_number($area_code . $t['SchoolPhoneNumber'], 'IE');
                }

                if( (!empty($t['SchoolFaxNumber'])) && (isset($this->area_codes[ $t['SchoolAreaCodeID'] ])) )
                {
                    $area_code = $this->area_codes[ $t['SchoolAreaCodeID'] ];
                    $fax = Helper::normalise_contact_number($area_code . $t['SchoolFaxNumber'], 'IE');
                }

                if( (!empty($t['SchoolMobileNumber'])) && (isset($this->network_codes[ $t['SchoolNetworkCodeID'] ])) )
                {
                    $network_code = $this->network_codes[ $t['SchoolNetworkCodeID'] ];
                    $mobile = Helper::normalise_contact_number($network_code . $t['SchoolMobileNumber'], 'IE');
                }


                $db->query(" INSERT INTO {$new_db}.org_school 
              ( roll_number, title, type_id, is_deis, is_jcn, has_special_unit_class, 
                region_id, center_id, mode_of_communication, mode_of_instruction, 
                add1, add2, add3, add4, city_town, post_code, county_id, include_county, eircode, 
                public_landline, public_fax, public_mobile, public_email, 
                is_active, merged_with, created, updated, updated_by ) VALUES 
              ( :roll_number, :title, :type_id, :is_deis, :is_jcn, :has_special_unit_class, 
                :region_id, :center_id, :mode_of_communication, :mode_of_instruction, 
                :add1, :add2, :add3, NULL, :city_town, :post_code, :county_id, 1, :eircode, 
                :public_landline, :public_fax, :public_mobile, :public_email, 
                :is_active, NULL, NOW(), NOW(), 1 ) ");
                $db->bind(':roll_number', $t['SchoolRollNumber']);
                $db->bind(':title', $t['SchoolName']);
                $db->bind(':type_id', $t['SchoolTypeID']);
                $db->bind(':is_deis', $is_deis);
                $db->bind(':is_jcn', $is_jcn);
                $db->bind(':has_special_unit_class', $has_spu);
                $db->bind(':region_id', $t['SchoolRegionID']);
                $db->bind(':center_id', $t['SchoolTeachersEdCenterID']);
                $db->bind(':mode_of_communication', $mode_of_instruction);
                $db->bind(':mode_of_instruction', $mode_of_instruction);
                $db->bind(':add1', $add1);
                $db->bind(':add2', $add2);
                $db->bind(':add3', $add3);
                $db->bind(':city_town', $city_town);
                $db->bind(':post_code', $post_code);
                $db->bind(':county_id', $t['SchoolCountyID']);
                $db->bind(':eircode', $t['SchoolEirCode']);
                $db->bind(':public_landline', $landline);
                $db->bind(':public_fax', $fax);
                $db->bind(':public_mobile', $mobile);
                $db->bind(':public_email', $t['SchoolEmail']);
                $db->bind(':is_active', $t['SchoolIsActive']);
                $db->execute();



                $day_begins = (!empty($t['SchoolDayBegins'])) ? $t['SchoolDayBegins'] : '07:00:00';
                $day_ends = (!empty($t['SchoolDayEnds'])) ? $t['SchoolDayEnds'] : '07:00:00';
                $break_begins = (!empty($t['BreakTimeBegins'])) ? $t['BreakTimeBegins'] : '07:00:00';
                $break_ends = (!empty($t['BreakTimeEnds'])) ? $t['BreakTimeEnds'] : '07:00:00';
                $lunch_begins = (!empty($t['LunchTimeBegins'])) ? $t['LunchTimeBegins'] : '07:00:00';
                $lunch_ends = (!empty($t['LunchTimeEnds'])) ? $t['LunchTimeEnds'] : '07:00:00';

                $days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
                $short_days_json = null;
                if($t['AnyShortenedDay'])
                {
                    $short_days = [];
                    if($t['ShortenedDay1'])
                        $short_days[ $days[ $t['ShortenedDay1'] ] ] = $t['ShortenedDay1EndTime'];
                    if($t['ShortenedDay2'])
                        $short_days[ $days[ $t['ShortenedDay2'] ] ] = $t['ShortenedDay2EndTime'];

                    if(!empty($short_days))
                        $short_days_json = json_encode($short_days);
                }

                $db->query(" INSERT INTO {$new_db}.org_school_hours 
              ( roll_number, day_begins, day_ends, break_begins, break_ends, lunch_begins, lunch_ends, short_days, updated, updated_by ) VALUES 
              ( '{$roll_number}', '{$day_begins}', '{$day_ends}', '{$break_begins}', '{$break_ends}', '{$lunch_begins}', '{$lunch_ends}', '{$short_days_json}', NOW(), 1 ) ");
                $db->execute();




                $units = [];
                if(intval($t['SchoolHasSAMUnit']) > 0)
                    $units[] = 'SAM';
                if(intval($t['SchoolHasSENUnit']) > 0)
                    $units[] = 'SEN';
                if(intval($t['SchoolHasASDUnit']) > 0)
                    $units[] = 'ASD';
                if(intval($t['SchoolHasEBDUnit']) > 0)
                    $units[] = 'EBD';
                if(intval($t['SchoolHasModerateUnit']) > 0)
                    $units[] = 'Mod.';
                if(intval($t['SchoolHasMildUnit']) > 0)
                    $units[] = 'Mild';
                if(intval($t['SchoolHasOtherUnit']) > 0)
                    $units[] = 'Other';

                if(!empty($units))
                {
                    $db->query(" INSERT INTO {$new_db}.org_school_units 
                  ( roll_number, unit, unit_started, unit_ended, is_active, updated, updated_by ) VALUES 
                  ( '{$roll_number}', :unit, NULL, NULL, 1, NOW(), 1 ) ");
                    foreach($units as $unit)
                    {
                        $db->bind(':unit', $unit);
                        $db->execute();
                    }
                }


            }

            $db->commit();
            $this->stage[] = 'School IDs set.';
            $this->stage[] = 'Schools imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function import_school_users()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->query(" SELECT SchoolPrincipalID, SchoolCPDContactID, SchoolL2LPContactID, SchoolID FROM {$old_db}.jct_schools WHERE 1 ");
        $db->execute();
        $tmp = $db->fetchAllAssoc('SchoolID');


        $db->beginTransaction();
        try
        {
            foreach($tmp as $school_id => $users)
            {
                if(!isset($this->school_ids[ $school_id ]))
                    continue;

                $roll_number = $this->school_ids[ $school_id ];

                $db->query(" SELECT SchoolStr, SchoolLanguageID FROM {$old_db}.jct_schools WHERE SchoolID = {$school_id} ");
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $raw_pass = $tmp['SchoolStr'];
                $pass = Helper::hash_password($raw_pass);
                $mode_of_communication = ($tmp['SchoolLanguageID'] == 1) ? 'en' : 'ga';

                $principals_id = intval($users['SchoolPrincipalID']);
                $cpd_contact_id = intval($users['SchoolCPDContactID']);
                $l2lp_contact_id = intval($users['SchoolL2LPContactID']);

                $principal_set = false;
                $cpd_contact_set = false;
                $l2lp_contact_set = false;

                foreach($users as $t)
                {
                    $u_id = intval($t);

                    if(empty($u_id))
                        continue;

                    $db->query(" SELECT * FROM {$old_db}.jct_user_contact uc WHERE UserID = {$u_id} ");
                    $db->execute();
                    $u = $db->fetchSingleAssoc();

                    if(empty($u))
                        continue;

                    $is_principal = ($u_id == $principals_id);
                    $is_cpd_contact = ($u_id == $cpd_contact_id);
                    $is_l2lp_contact = ($u_id == $l2lp_contact_id);


                    $db->query(" INSERT INTO {$new_db}.ind_person 
                      ( id, tc_number, fname, lname, salute_name, 
                        mode_of_communication, mode_of_email_content, 
                        landline, fax, mobile, email, 
                        add1, add2, add3, add4, city_town, post_code, county_id, include_county, eircode, 
                        created, last_updated, last_updated_by ) VALUES 
                      ( {$u_id}, NULL, :fname, :lname, :salute_name, 
                        '{$mode_of_communication}', '{$mode_of_communication}', 
                        :landline, :fax, :mobile, :email, 
                        :add1, :add2, :add3, NULL, :city_town, :post_code, :county_id, 1, :eircode, 
                        NOW(), NOW(), 1 ) ");

                    $salute_name = null;
                    if(isset($this->salutations[ $u['UserSalutationID'] ]))
                    {
                        $salutation = $this->salutations[ $u['UserSalutationID'] ];
                        $salute_name = $salutation . ' ' . $u['UserLName'];
                    }
                    if($salute_name == null)
                        $salute_name = $u['UserFName'] . ' ' . $u['UserLName'];

                    $landline = $fax = $mobile = null;

                    if( (!empty($u['UserPhoneNumber'])) && (isset($this->area_codes[ $u['UserAreaCodeID'] ])) )
                    {
                        $area_code = $this->area_codes[ $u['UserAreaCodeID'] ];
                        $landline = Helper::normalise_contact_number($area_code . $u['UserPhoneNumber'], 'IE');
                    }

                    if( (!empty($u['UserFaxNumber'])) && (isset($this->area_codes[ $u['UserAreaCodeID'] ])) )
                    {
                        $area_code = $this->area_codes[ $u['UserAreaCodeID'] ];
                        $fax = Helper::normalise_contact_number($area_code . $u['UserFaxNumber'], 'IE');
                    }

                    if( (!empty($u['UserMobileNumber'])) && (isset($this->network_codes[ $u['UserNetworkCodeID'] ])) )
                    {
                        $network_code = $this->network_codes[ $u['UserNetworkCodeID'] ];
                        $mobile = Helper::normalise_contact_number($network_code . $u['UserMobileNumber'], 'IE');
                    }

                    $add1 = (!empty($u['UserAdd1'])) ? $u['UserAdd1'] : null;
                    $add2 = (!empty($u['UserAdd2'])) ? $u['UserAdd2'] : null;
                    $add3 = (!empty($u['UserAdd3'])) ? $u['UserAdd3'] : null;
                    $city_town = (!empty($u['UserCityTown'])) ? $u['UserCityTown'] : null;
                    $post_code = (!empty(intval($u['UserCityPostCode']))) ? intval($u['UserCityPostCode']) : 0;

                    if($city_town === null)
                    {
                        if($add3 !== null)
                        {
                            $city_town = $add3;
                            $add3 = null;
                        }
                        elseif($add2 !== null)
                        {
                            $city_town = $add2;
                            $add2 = null;
                        }
                        else
                        {
                            $city_town = $add1;
                            $add1 = null;
                        }
                    }

                    $db->bind(':fname', $u['UserFName']);
                    $db->bind(':lname', $u['UserLName']);
                    $db->bind(':salute_name', $salute_name);
                    $db->bind(':landline', $landline);
                    $db->bind(':fax', $fax);
                    $db->bind(':mobile', $mobile);
                    $db->bind(':email', $u['UserEmail']);
                    $db->bind(':email', $u['UserEmail']);
                    $db->bind(':add1', $add1);
                    $db->bind(':add2', $add2);
                    $db->bind(':add3', $add3);
                    $db->bind(':city_town', $city_town);
                    $db->bind(':post_code', $post_code);
                    $db->bind(':county_id', $u['UserCountyID']);
                    $db->bind(':eircode', $u['UserEirCode']);

                    try
                    {
                        $db->execute();
                    }
                    catch(Exception $e)
                    {
                        Helper::show($e);
                        throw new Exception($e->getMessage());
                    }

                    $role = null;
                    $username = $roll_number;
                    if($u_id === $principals_id)
                    {
                        $principal_set = true;
                        $role = 'admin';
                    }
                    elseif($u_id === $cpd_contact_id)
                    {
                        $cpd_contact_set = true;
                        $role = 'cpd_leader';
                        $username = $roll_number . '_cpd';
                    }
                    elseif($u_id === $l2lp_contact_id)
                    {
                        $l2lp_contact_set = true;
                        $role = 'l2lp_leader';
                        $username = $roll_number . '_l2lp';
                    }

                    if($role === null)
                        continue;

                    $db->query(" INSERT INTO {$new_db}.ind_user 
                        ( id, username, pass, session_id, position, org_type, org_id, created, last_updated, last_updated_by ) VALUES 
                        ( {$u_id}, '{$username}', '{$pass}', NULL, '{$role}', 'sch', '{$roll_number}', NOW(), NOW(), 1 ) ");
                    $db->execute();
                }
            }

            $db->commit();
            $this->stage[] = 'School Persons imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }




    private function import_parameter_tables()
    {
        try
        {
            $tmp = $this->import_school_types();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->import_counties();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $tmp = $this->import_educational_regions();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $this->stage[] = 'Parameter tables imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function import_school_types()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->beginTransaction();
        try
        {
            $db->query(" SELECT * FROM {$old_db}.jct_school_types WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc();

            $db->query(" DELETE FROM {$new_db}.prm_school_type WHERE 1 ");
            $db->execute();

            $db->query(" INSERT INTO {$new_db}.prm_school_type 
              ( id, title_en, title_ga, attribute, weight, is_active, updated, updated_by ) VALUES 
              ( :id, :title_en, :title_ga, :attribute, 0, 1, NOW(), 1 ) ");
            foreach($tmp as $t)
            {
                $db->bind(':id', $t['SchoolTypeID']);
                $db->bind(':title_en', $t['SchoolTypeName']);
                $db->bind(':title_ga', $t['SchoolTypeIrishName']);
                $db->bind(':attribute', $t['SchoolTypeBody']);
                $db->execute();
            }

            $db->commit();
            $this->stage[] = 'School Types imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function import_counties()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->beginTransaction();
        try
        {
            $db->query(" SELECT * FROM {$old_db}.jct_counties WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc();

            $db->query(" DELETE FROM {$new_db}.prm_county WHERE 1 ");
            $db->execute();

            $db->query(" INSERT INTO {$new_db}.prm_county 
              ( id, title_en, title_ga, attribute, weight, is_active, updated, updated_by ) VALUES 
              ( :id, :title_en, :title_ga, :attribute, 0, 1, NOW(), 1 ) ");
            foreach($tmp as $t)
            {
                $db->bind(':id', $t['CountyID']);
                $db->bind(':title_en', $t['CountyName']);
                $db->bind(':title_ga', $t['CountyIrishName']);
                $db->bind(':attribute', null);
                $db->execute();
            }

            $db->commit();
            $this->stage[] = 'Counties imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function import_educational_regions()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;
        $new_db = $this->new_db_name;

        $db->beginTransaction();
        try
        {
            $db->query(" SELECT * FROM {$old_db}.jct_regions WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc();

            $db->query(" DELETE FROM {$new_db}.prm_educational_region WHERE 1 ");
            $db->execute();

            $db->query(" INSERT INTO {$new_db}.prm_educational_region 
              ( id, title_en, title_ga, attribute, weight, is_active, updated, updated_by ) VALUES 
              ( :id, :title_en, :title_ga, :attribute, 0, 1, NOW(), 1 ) ");
            foreach($tmp as $t)
            {
                $db->bind(':id', $t['RegionID']);
                $db->bind(':title_en', $t['RegionName']);
                $db->bind(':title_ga', $t['RegionIrishName']);
                $db->bind(':attribute', null);
                $db->execute();
            }

            $db->commit();
            $this->stage[] = 'Regions imported.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }



    private function set_phone_codes()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;

        try
        {
            $db->query(" SELECT * FROM {$old_db}.jct_areacodes WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc('AreaCodeID');

            $this->area_codes = $tmp;

            $db->query(" SELECT * FROM {$old_db}.jct_networkcodes WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc('NetworkCodeID');

            $this->network_codes = $tmp;

            $this->stage[] = 'Telephone codes set.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function set_teacher_ids()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;

        try
        {
            $db->query(" SELECT TeacherID, TeacherTCNumber FROM {$old_db}.jct_teachers WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc('TeacherID');

            $this->teacher_ids = $tmp;

            $this->stage[] = 'Teacher IDs set.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function set_salutations()
    {
        $db = $this->_DB;
        $old_db = $this->old_db_name;

        try
        {
            $db->query(" SELECT SalutationID, SalutationText FROM {$old_db}.jct_salutations WHERE 1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc('SalutationID');

            $this->salutations = $tmp;

            $this->stage[] = 'Salutations set.';
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}





require_once 'jct_core/Config.php';
require_once 'jct_core/classes/Autoloader.php';
require_once 'jct_core/classes/Helper.php';


$db_user_name = 'root';
$db_user_pass = 'pass';
$old_db_name = 'jctregis_jct_oop';
$new_db_name = 'jctregis_db';

$db = new Database($db_user_name, $db_user_pass, null, 'localhost', 'UTF8');
if(!empty($db->db_error))
{
    echo $db->db_error;
    exit;
}



$import = new import($db, $old_db_name, $new_db_name);
$import->import();
