<?php


exit();

$status = [];
$time_start = microtime(true);

$_DB = null;
$_ORG_DB = null;

$user_ai_value = 0;
$user_org_ai_value = 0;
$person_ai_value = 0;
$staff_role_ai_value = 0;

$sql = '';

try
{
    // load required

    require_once '../../ds_core/Config.php';
    require_once '../../ds_core/classes/Database.php';
    require_once '../../ds_core/classes/Connection.php';
    require_once '../../ds_core/classes/Helper.php';

    $org_guid = '19374W';
    $role_id = 5;
    $source_csv_path = 'olgps_snas.csv';



    // check core database

    $_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
    if($_DB->db_error)
        throw new Exception('While connecting to Core DB: ' . $_DB->db_error);

    $status[] = 'Core database connection set';


    $db = $_DB;
    $db->query(" SELECT db_name FROM org_details WHERE guid = '{$org_guid}' ");
    $db->execute();
    $org_db_name = $db->fetchSingleColumn();


    $db->query(" SELECT MAX(id) FROM user WHERE 1 = 1 ");
    $db->execute();
    $user_ai_value = intval($db->fetchSingleColumn()) + 1;

    $db->query(" SELECT MAX(tbl_id) FROM user_org WHERE 1 = 1 ");
    $db->execute();
    $user_org_ai_value = intval($db->fetchSingleColumn()) + 1;


    // check org database

    $_ORG_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, JCT_DB_SIUD_HOST, 'UTF8');
    if(!empty($_ORG_DB->db_error))
        throw new Exception('While connecting to ORG DB: ' . $_ORG_DB->db_error);

    $db = $_ORG_DB;

    $db->query(" SELECT MAX(id) FROM person WHERE 1 = 1 ");
    $db->execute();
    $person_ai_value = intval($db->fetchSingleColumn()) + 1;

    $db->query(" SELECT MAX(tbl_id) FROM staff_role WHERE 1 = 1 ");
    $db->execute();
    $staff_role_ai_value = intval($db->fetchSingleColumn()) + 1;


    // get some default values

    $db = $_DB;

    $db->query(" SELECT county_id, country_id, country_code FROM org_details WHERE ( guid = '{$org_guid}' ) ");
    $db->execute();
    $tmp = $db->fetchSingleAssoc();
    $default_county_id = intval($tmp['county_id']);
    $default_country_id = intval($tmp['country_id']);
    $country_code = $tmp['country_code'];



    // read CSV

    $handle = fopen($source_csv_path, 'r');

    if(!$handle)
        throw new Exception('CSV file could not be read');

    $staff = $fields = [];
    $i = 0;
    while (($row = fgetcsv($handle, 4096, ',')) !== false)
    {
        if($i == 0)
            $fields = $row;

        if($i > 0) // assume first row is fields
        {
            foreach ($row as $n => $value)
                $staff[($i - 1)][ $fields[$n] ] = $value;
        }

        $i++;
    }
    if (!feof($handle))
        throw new Exception("Unexpected fgets() fail");

    fclose($handle);

    if(empty($staff))
        throw new Exception('Error: no staff records read.');


    // get columns from person table

    $db = $_ORG_DB;

    $db->query(" DESCRIBE person ");
    $db->execute();
    $person_columns = $db->fetchAllAssoc();

    #\DS\Helper::show($person_columns);



    // parse staff into person objects

    $now = new DateTime();
    $now_str = $now->format('Y-m-d H:i:s');
    $persons = [];
    foreach($staff as $i => $s)
    {
        $arr = [];
        foreach($person_columns as $x => $col)
        {
            $field = $col['Field'];

            switch($field)
            {
                case('id'):
                    $arr[$field] = 0;
                    break;
                case('ext_id'):
                    if(empty($s[$field]))
                        throw new Exception('No External ID for record: ' . \JCT\Helper::show($s, true));
                    $arr[$field] = intval($s[$field]);
                    break;
                case('is_staff'):
                case('active'):
                    $arr[$field] = 1;
                    break;
                case('indexed_lname'):
                    if(empty($staff[$i]['lname']))
                        $arr[$field] = null;
                    else
                        $arr[$field] = \JCT\Helper::lname_as_index($staff[$i]['lname']);
                    break;
                case('mobile'):
                case('landline'):
                    $arr[$field] = (empty($s[$field])) ? null : \JCT\Helper::normalise_contact_number($s[$field], $country_code);
                    break;
                case('email'):
                    $email = (empty($s[$field])) ? null : $s[$field];
                    if($email !== null)
                        $email = filter_var($s[$field], FILTER_VALIDATE_EMAIL);
                    $arr[$field] = ($email !== false) ? $email : null;
                    break;
                case('county_id'):
                    $arr[$field] = (empty($s[$field])) ? $default_county_id : $s[$field];
                    break;
                case('country_id'):
                    $arr[$field] = (empty($s[$field])) ? $default_country_id : $s[$field];
                    break;
                case('created'):
                case('updated'):
                    $arr[$field] = $now_str;
                    break;
                case('updated_by'):
                    $arr[$field] = -1;
                    break;
                default:
                    $default_value = $col['Default'];
                    $val = (empty($s[$field])) ? $default_value : $s[$field];

                    $can_be_null = ($col['Null'] === 'YES');
                    if( ($val === null) && (!$can_be_null) )
                        throw new Exception('Invalid NULL value for field `' . $field . '` for record: ' . \JCT\Helper::show($s, true));

                    $arr[ $field ] = $val;
                    break;
            }
        }

        $persons[] = $arr;
    }

    #\DS\Helper::show($persons);



    // determine if user already exists via mobile or email
    // store ID if available, create and store ID if not

    foreach($persons as $i => $p)
    {
        $mobile = $p['mobile'];
        $email = $p['email'];

        $id = 0;

        $db = $_DB;

        if( (!empty($mobile)) && (!empty($email)) )
        {
            $db->query(" SELECT id FROM user WHERE ( email = :email AND mobile = :mobile ) ");
            $db->bind(':mobile', $mobile);
            $db->bind(':email', $email);
            $db->execute();
            $id = intval($db->fetchSingleColumn());
        }

        if($id)
        {
            $persons[$i]['id'] = $id;
            continue;
        }

        if(!empty($mobile))
        {
            $db->query(" SELECT id FROM user WHERE ( mobile = :mobile ) ");
            $db->bind(':mobile', $mobile);
            $db->execute();
            $id = intval($db->fetchSingleColumn());
        }

        if($id)
        {
            if(!empty($email))
            {
                $db->query(" SELECT id, pass FROM user WHERE ( email = :email ) ");
                $db->bind(':email', $email);
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $email_id = intval($tmp['id']);

                if($id !== $email_id)
                {
                    if(!empty($tmp['pass']))
                        $persons[$i]['email'] = null;
                    else
                    {
                        $db->query(" UPDATE user SET email = NULL WHERE ( id = {$email_id} ) ");
                        $db->execute();

                        $db = $_ORG_DB;
                        $db->query(" UPDATE person SET email = NULL WHERE ( id = {$email_id} ) ");
                        $db->execute();
                        $db = $_DB;
                    }
                }
            }

            $persons[$i]['id'] = $id;
            continue;
        }

        if(!empty($email))
        {
            $db->query(" SELECT id FROM user WHERE ( email = :email ) ");
            $db->bind(':email', $email);
            $db->execute();
            $id = intval($db->fetchSingleColumn());
        }

        if($id)
        {
            if(!empty($mobile))
            {
                $db->query(" SELECT id, pass FROM user WHERE ( mobile = :mobile ) ");
                $db->bind(':mobile', $mobile);
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $mobile_id = intval($tmp['id']);

                if($id !== $mobile_id)
                {
                    if(!empty($tmp['pass']))
                        $persons[$i]['mobile'] = null;
                    else
                    {
                        $db->query(" UPDATE user SET mobile = NULL WHERE ( id = {$mobile_id} ) ");
                        $db->execute();

                        $db = $_ORG_DB;
                        $db->query(" UPDATE person SET mobile = NULL WHERE ( id = {$mobile_id} ) ");
                        $db->execute();
                        $db = $_DB;
                    }
                }
            }

            $persons[$i]['id'] = $id;
            continue;
        }

        // if not IDed, determine if user exists via role_id and ext_id

        if(!$id)
        {
            $db = $_ORG_DB;

            $ext_id = $p['ext_id'];
            $db->query(" SELECT sr.id 
            FROM staff_role sr 
            LEFT JOIN person p on sr.id = p.id 
            WHERE ( 
                sr.role_id = {$role_id} AND 
                p.ext_id = {$ext_id}
            ) ");
            $db->execute();
            $id = intval($db->fetchSingleColumn());

            if($id)
                $persons[$i]['id'] = $id;
        }

    }

    \JCT\Helper::show($persons);



    // create / update person records

    foreach($persons as $i => $p)
    {
        $id = intval($p['id']);
        $ext_id = $p['ext_id'];
        $mobile = $p['mobile'];
        $email = $p['email'];
        $pass = \JCT\Helper::hash_password('letmein');

        $db = $_DB;

        if(!$id)
        {
            $db->query(" INSERT INTO user
                ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES
                ( 1, NULL, :email, :mobile, :pass, NOW(), -1 ) ");
            $db->bind(':mobile', $mobile);
            $db->bind(':email', $email);
            $db->bind(':pass', $pass);
            $db->execute();
            $id = $db->lastInsertId();
            $p['id'] = $id;
        }

        $db->query(" INSERT INTO user_org
            ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES
            ( {$id}, '{$org_guid}', {$role_id}, {$ext_id}, NULL, 1, NOW(), -1 ) 
            ON DUPLICATE KEY UPDATE ext_id = {$ext_id} ");
        $db->execute();

        $db = $_ORG_DB;

        $db->query(" INSERT INTO person 
            ( id, ext_id, fname, lname, indexed_lname, salute_name, salt_id, 
             landline, mobile, email, landline_alt, mobile_alt, email_alt, 
             add1, add2, add3, add4, city_town, postcode, eircode, county_id, country_id, show_county, 
             is_staff, is_guardian, is_member, active, 
             created, deactivated, updated, updated_by ) VALUES 
            ( :id, :ext_id, :fname, :lname, :indexed_lname, :salute_name, :salt_id, 
             :landline, :mobile, :email, :landline_alt, :mobile_alt, :email_alt, 
             :add1, :add2, :add3, :add4, :city_town, :postcode, :eircode, :county_id, :country_id, :show_county, 
             :is_staff, :is_guardian, :is_member, :active, 
             :created, :deactivated, :updated, :updated_by ) 
             ON DUPLICATE KEY UPDATE 
             ext_id = :ext_id, fname = :fname, lname = :lname, indexed_lname = :indexed_lname, salt_id = :salt_id, 
             landline = :landline, mobile = :mobile, email = :email, 
             landline_alt = :landline_alt, mobile_alt = :mobile_alt, email_alt = :email_alt, 
             add1 = :add1, add2 = :add2, add3 = :add3, city_town = :city_town, postcode = :postcode, eircode = :eircode, 
             county_id = :county_id, country_id = :country_id, show_county = :show_county, 
             is_staff = :is_staff, active = :active, updated = :updated, updated_by = :updated_by ");
        foreach($p as $k => $v)
            $db->bind(':' . $k, $v);
        $db->execute();

        $db->query(" INSERT INTO staff_role 
        ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
        ( {$id}, {$role_id}, NOW(), NULL, NOW(), -1 ) 
        ON DUPLICATE KEY UPDATE role_id = {$role_id} ");
        $db->execute();
    }


    echo 'done';
}
catch(Exception $e)
{
    $db = $_ORG_DB;
    \JCT\Helper::show($sql);

    if($staff_role_ai_value)
    {
        $db->query(" DELETE FROM staff_role WHERE ( tbl_id >= {$staff_role_ai_value} ); 
                ALTER TABLE staff_role auto_increment = {$staff_role_ai_value}; ");
        $db->execute();
    }
    if($person_ai_value)
    {
        $db->query(" DELETE FROM person WHERE ( id >= {$person_ai_value} ); 
                ALTER TABLE person auto_increment = {$person_ai_value}; ");
        $db->execute();
    }

    $db = $_DB;

    if($user_org_ai_value)
    {
        $db->query(" DELETE FROM user_org WHERE ( tbl_id >= {$user_org_ai_value} ); 
                ALTER TABLE user_org auto_increment = {$user_org_ai_value}; ");
        $db->execute();
    }
    if($user_ai_value)
    {
        $db->query(" DELETE FROM user WHERE ( id >= {$user_ai_value} ); 
                ALTER TABLE user auto_increment = {$user_ai_value}; ");
        $db->execute();
    }

    echo $e->getMessage();
    \JCT\Helper::show($e->getTrace());
}