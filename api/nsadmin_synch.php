<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 03/10/2017
 * Time: 13:53
 */



error_reporting(E_ALL ^ E_WARNING);


$status = [];
$time_start = microtime(true);
$persons = [];
$persons_guardians = [];
try
{
    // load required

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Helper.php';


    // get input

    $opts = ['http' => ['header' => 'Accept-Charset: UTF-8, *;q=0']];
    $context = stream_context_create($opts);
    $post = file_get_contents('sample_data/19765o_json_upload.json',false, $context);
    #$post = file_get_contents('php://input',false, $context);
    #$post = \DS\Helper::clean_unicode_literals($post);

    $encoding = mb_detect_encoding($post);
    #echo $encoding;

    if(empty($post))
        throw new Exception('No input detected.');

    $status[] = 'Input detected';

    if(!mb_check_encoding($post, 'UTF-8'))
        throw new Exception('Invalid encoding detected.');

    $status[] = 'Encoding validated';



    // parse input

    $data = json_decode($post, true);

    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $error = '';
            break;
        case JSON_ERROR_DEPTH:
            $error = 'The maximum stack depth has been exceeded.';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $error = 'Invalid or malformed JSON.';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $error = 'Control character error, possibly incorrectly encoded.';
            break;
        case JSON_ERROR_SYNTAX:
            $error = 'Syntax error, malformed JSON.';
            break;
        case JSON_ERROR_UTF8:
            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
            break;
        case JSON_ERROR_RECURSION:
            $error = 'One or more recursive references in the value to be encoded.';
            break;
        case JSON_ERROR_INF_OR_NAN:
            $error = 'One or more NAN or INF values in the value to be encoded.';
            break;
        case JSON_ERROR_UNSUPPORTED_TYPE:
            $error = 'A value of a type that cannot be encoded was given.';
            break;
        default:
            $error = 'Unknown JSON error occurred.';
            break;
    }

    if(!empty($error))
        throw new Exception($error);

    $data = array_change_key_case($data, CASE_LOWER);

    $status[] = 'JSON parse successful';



    // check root values

    $required_fields = [
        'guid',
        'token',
        'version',
        'datetime'
    ];
    $omitted = array_diff($required_fields, array_keys($data));

    if(!empty($omitted))
    {
        $omitted_str = json_encode($omitted);
        throw new Exception('The following required fields were not found: ' . $omitted_str);
    }

    $blanks = [];
    foreach($required_fields as $field)
    {
        if(empty($data[$field]))
            $blanks[] = $field;
    }

    if(!empty($blanks))
    {
        $blanks_str = json_encode($blanks);
        throw new Exception('The following required fields were empty: ' . $blanks_str);
    }

    unset($required_fields);
    unset($blanks);
    $status[] = 'Required fields found';




    // check token

    // todo

    unset($data['token']);
    $status[] = 'Token verified';




    // check version

    if(floatval($data['version']) != 0.2)
        throw new Exception('Incorrect version number');

    unset($data['version']);
    $status[] = 'Version verified';




    // check datetime

    $upload_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $data['datetime']);
    if(!$upload_datetime)
        throw new Exception('Invalid upload datetime detected');

    unset($data['datetime']);
    $status[] = 'Datetime set';




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




    // check roll number

    $org_guid = strtoupper(trim($data['guid']));

    $db->query(" SELECT id, host_name, db_name, active FROM org_details WHERE guid = :guid ");
    $db->bind(':guid', $org_guid);
    $db->execute();
    $tmp = $db->fetchSingleAssoc();

    if(empty($tmp))
        throw new Exception('Unrecognised organisation GUID');

    if(intval($tmp['active']) < 1)
        throw new Exception('Inactive organisation GUID');

    if(empty($tmp['host_name']))
        throw new Exception('Organisation host not found');

    if(empty($tmp['db_name']))
        throw new Exception('Organisation database name not found');

    $org_db_host = $tmp['host_name'];
    $org_db_name = $tmp['db_name'];

    unset($data['guid']);
    $status[] = 'Organisation GUID set';




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

    #\DS\Helper::show($org_db);
    $status[] = 'Organisation database connection set';




    // set role ids

    $group_leader_role_id = 3;
    $group_leader_role_rank = 21;
    $ls_teacher_role_id = 4;
    $ls_teacher_role_rank = 25;
    $sna_role_id = 5;
    $sna_role_rank = 26;
    $ancillary_role_id = 6;
    $ancillary_role_rank = 27;
    $guardian_role_id = 8;
    $guardian_role_rank = 31;
    $member_role_id = 7;
    $member_role_rank = 41;

    /*$org_db->query(" SELECT id, parent_id, attribute FROM prm_staff_role WHERE 1 ORDER BY weight ASC ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    foreach($tmp as $t)
    {
        switch(intval($t['parent_id']))
        {
            case(3):
                if(empty($group_leader_role_id))
                {
                    $group_leader_role_id = $t['id'];
                    $group_leader_role_rank = $t['attribute'];
                }
                break;
            case(4):
                if(empty($ls_teacher_role_id))
                {
                    $ls_teacher_role_id = $t['id'];
                    $ls_teacher_role_rank = $t['attribute'];
                }
                break;
            case(5):
                if(empty($guardian_role_id))
                {
                    $guardian_role_id = $t['id'];
                    $guardian_role_rank = $t['attribute'];
                }
                break;
            case(6):
                if(empty($member_role_id))
                {
                    $member_role_id = $t['id'];
                    $member_role_rank = $t['attribute'];
                }
                break;
            default:
                continue;
            break;
        }
    }*/


    // set salutations


    $org_db->query(" SELECT id, LOWER(title) AS title FROM prm_salutation WHERE active = 1 ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    $salutations = [];
    if(!empty($tmp))
    {
        foreach($tmp as $t)
        {
            $title = preg_replace('/\PL/u', '', $t['title']);
            $salutations[ $title ] = $t['id'];
        }
    }

    $status[] = 'Organisation parameters set';




    // parse all persons


    // parse all persons_guardians

    foreach($data['guardian_other'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        $person['indexed_lname'] = $person['lname'];
        $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
        $person = \JCT\Helper::nullify_empty_values($person);
        $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

        $person['is_group_leader'] = 0;
        $person['is_ls_teacher'] = 0;
        $person['is_sna'] = 0;
        $person['is_ancillary'] = 0;
        $person['is_staff'] = 0;
        $person['is_guardian'] = 1;
        $person['is_guardian_default'] = 0;
        $person['is_member'] = 0;

        $person['user_key'] = $org_guid . '_' . $person['id'] . '_gua';
        $person['ext_id'] = $person['id'];
        $person['id'] = 0;
        $person['role_id'] = $guardian_role_id;
        $person['rank'] = $guardian_role_rank;

        $persons_guardians[] = $person;
    }
    $status[] = 'Other Guardians parsed';

    foreach($data['guardian_default'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        $person['indexed_lname'] = $person['lname'];
        $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
        $person = \JCT\Helper::nullify_empty_values($person);
        $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

        $person['is_group_leader'] = 0;
        $person['is_ls_teacher'] = 0;
        $person['is_sna'] = 0;
        $person['is_ancillary'] = 0;
        $person['is_staff'] = 0;
        $person['is_guardian'] = 1;
        $person['is_guardian_default'] = 1;
        $person['is_member'] = 0;

        $person['user_key'] = $org_guid . '_' . $person['guardian_of'] . '_gud';
        $person['ext_id'] = null;
        $person['id'] = 0;
        $person['role_id'] = $guardian_role_id;
        $person['rank'] = $guardian_role_rank;

        $persons_guardians[] = $person;
    }
    $status[] = 'Default Guardians parsed';

    $status[] = 'All guardians parsed: ' . count($persons_guardians);



    $job_sharing_leader_map = []; # id (of job_share) => [ id (of group_leader), id ... ]
    $staff_group_staff_map = []; # ext_id => group_ext_id

    foreach($data['group_leader'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);
        $p_id = (!empty($person['id'])) ? intval($person['id']) : null;

        # check for job-share teacher
        $is_job_share = ( strpos($person['alias'], '/') !== false );

        if($is_job_share)
        {
            # fname in these cases is [ext_id-ext_id]
            $job_sharing_leader_map[ $person['id'] ] = explode('-',$person['fname']);
            continue;
        }

        $person['indexed_lname'] = $person['lname'];
        $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
        $person = \JCT\Helper::nullify_empty_values($person);
        $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

        $person['is_group_leader'] = 1;
        $person['is_ls_teacher'] = 0;
        $person['is_sna'] = 0;
        $person['is_ancillary'] = 0;
        $person['is_staff'] = 1;
        $person['is_guardian'] = 0;
        $person['is_guardian_default'] = 0;
        $person['is_member'] = 0;

        $person['user_key'] = $org_guid . '_' . $p_id . '_grl';
        $person['ext_id'] = $p_id;
        $person['id'] = 0;
        $person['role_id'] = $group_leader_role_id;
        $person['rank'] = $group_leader_role_rank;

        $persons[] = $person;
    }
    $status[] = 'Group leaders parsed';

    foreach($data['ls_teacher'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        $person['indexed_lname'] = $person['lname'];
        $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
        $person = \JCT\Helper::nullify_empty_values($person);
        $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

        $person['is_group_leader'] = 0;
        $person['is_ls_teacher'] = 1;
        $person['is_sna'] = 0;
        $person['is_ancillary'] = 0;
        $person['is_staff'] = 1;
        $person['is_guardian'] = 0;
        $person['is_guardian_default'] = 0;
        $person['is_member'] = 0;

        $person['user_key'] = $org_guid . '_' . $person['id'] . '_lst';
        $person['ext_id'] = $person['id'];
        $person['id'] = 0;
        $person['role_id'] = $ls_teacher_role_id;
        $person['rank'] = $ls_teacher_role_rank;

        $persons[] = $person;
    }
    $status[] = 'LS Teachers parsed';





    $other_staff = [];
    $staff_mobile_map = [];
    $staff_email_map = [];
    $n = 0;
    foreach($data['sna'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        if( (array_key_exists($person['email'], $staff_email_map)) || (array_key_exists($person['mobile'], $staff_mobile_map)) )
        {
            if(array_key_exists($person['email'], $staff_email_map))
                $ext_id = $staff_email_map[ $person['email'] ];
            else
                $ext_id = $staff_mobile_map[ $person['mobile'] ];

            $other_staff[ $ext_id ]['is_sna'] = 1;
        }
        else
        {
            $person['ext_id'] = \JCT\Helper::generate_random_string(3) . str_pad($n, 2, 0, STR_PAD_LEFT);
            $n++;

            if(!empty($person['email']))
                $staff_email_map[ $person['email'] ] = $person['ext_id'];
            if(!empty($person['mobile']))
                $staff_mobile_map[ $person['mobile'] ] = $person['ext_id'];

            $person['indexed_lname'] = $person['lname'];
            $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
            $person = \JCT\Helper::nullify_empty_values($person);
            $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

            $person['is_group_leader'] = 0;
            $person['is_ls_teacher'] = 0;
            $person['is_sna'] = 1;
            $person['is_ancillary'] = 0;
            $person['is_staff'] = 1;
            $person['is_guardian'] = 0;
            $person['is_guardian_default'] = 0;
            $person['is_member'] = 0;

            $person['user_key'] = $org_guid . '_' . $person['id'] . '_sna';
            $person['id'] = 0;
            $person['role_id'] = $sna_role_id;
            $person['rank'] = $sna_role_rank;

            $other_staff[ $person['ext_id'] ] = $person;
        }
    }
    $status[] = 'SNAs parsed';

    $n = 0;
    foreach($data['staff'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        if( (array_key_exists($person['email'], $staff_email_map)) || (array_key_exists($person['mobile'], $staff_mobile_map)) )
        {
            if(array_key_exists($person['email'], $staff_email_map))
                $ext_id = $staff_email_map[ $person['email'] ];
            else
                $ext_id = $staff_mobile_map[ $person['mobile'] ];

            $other_staff[ $ext_id ]['group_staff_id'] = $person['group_staff_id'];
            $person = $other_staff[ $ext_id ];
        }
        else
        {
            $person['ext_id'] = \JCT\Helper::generate_random_string(3) . str_pad($n, 2, 0, STR_PAD_LEFT);
            $n++;

            $person['indexed_lname'] = $person['lname'];
            $person = \JCT\Helper::normalise_person_parameters($person, $salutations);
            $person = \JCT\Helper::nullify_empty_values($person);
            $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

            $person['is_group_leader'] = 0;
            $person['is_ls_teacher'] = 0;
            $person['is_sna'] = 0;
            $person['is_ancillary'] = 1;
            $person['is_staff'] = 1;
            $person['is_guardian'] = 0;
            $person['is_guardian_default'] = 0;
            $person['is_member'] = 0;

            $person['user_key'] = $org_guid . '_' . $person['id'] . '_anc';
            $person['id'] = 0;
            $person['role_id'] = $sna_role_id;
            $person['rank'] = $sna_role_rank;

            $other_staff[ $person['ext_id'] ] = $person;

            if(!empty($person['email']))
                $staff_email_map[ $person['email'] ] = $person['ext_id'];
            if(!empty($person['mobile']))
                $staff_mobile_map[ $person['mobile'] ] = $person['ext_id'];
        }


        if(!empty($person['group_staff_id']))
        {
            if(!isset($staff_group_staff_map[ $person['ext_id'] ]))
                $staff_group_staff_map[ $person['ext_id'] ] = [];

            $staff_group_staff_map[ $person['ext_id'] ][] = $person['group_staff_id'];
        }
    }
    $status[] = 'Ancillary Staff parsed';


    foreach($other_staff as $ext_id => $person)
        $persons[] = $person;

    unset($other_staff);
    $status[] = 'Ancillary Staff merged with Persons';




    foreach($data['member'] as $person)
    {
        $person = array_change_key_case($person, CASE_LOWER);

        $person['alias'] = (empty($person['alias'])) ? '' : $person['alias'];
        $person['email'] = (empty($person['email'])) ? '' : $person['email'];
        $person['mobile'] = (empty($person['mobile'])) ? '' : $person['mobile'];
        $person['indexed_lname'] = $person['lname'];
        $person = \JCT\Helper::normalise_person_parameters($person);
        $person = \JCT\Helper::nullify_empty_values($person);
        $person['salt_id'] = ( (empty($person['salt_id'])) || (is_null($person['salt_id'])) ) ? 0 : $person['salt_id'];

        $person['is_group_leader'] = 0;
        $person['is_ls_teacher'] = 0;
        $person['is_sna'] = 0;
        $person['is_ancillary'] = 0;
        $person['is_staff'] = 0;
        $person['is_guardian'] = 0;
        $person['is_guardian_default'] = 0;
        $person['is_member'] = 1;

        $person['user_key'] = $org_guid . '_mem_' . $person['id'];
        $person['ext_id'] = $person['id'];
        $person['id'] = 0;
        $person['role_id'] = $member_role_id;
        $person['rank'] = $member_role_rank;

        $persons[] = $person;
    }
    $status[] = 'Members parsed';

    $status[] = 'All persons parsed: ' . count($persons);


    unset($data['group_leader']);
    unset($data['ls_teacher']);
    unset($data['sna']);
    unset($data['staff']);
    unset($data['guardian']);
    unset($data['guardian_default']);
    unset($data['member']);
    unset($salutations);




    // begin user transaction

    $default_guardian_map = []; # ext_id (of member) => id (of guardian)
    $db->beginTransaction();
    $last_record = [];
    try
    {
        foreach($persons as $i => $person)
        {
            $last_record = $person;

            try
            {
                $db->query(" INSERT INTO user 
                ( ext_id, user_key, session_id, email, mobile, pass, settings, updated, updated_by ) VALUES 
                ( :ext_id, :user_key, NULL, :email, :mobile, NULL, NULL, NOW(), -1 ) 
                ON DUPLICATE KEY UPDATE 
                id = last_insert_id(id), ext_id = :ext_id, 
                email = :email, mobile = :mobile, 
                updated = NOW(), updated_by = -1 ");
                $db->bind(':user_key', $person['user_key']);
                $db->bind(':ext_id', $person['ext_id']);
                $db->bind(':email', $person['email']);
                $db->bind(':mobile', $person['mobile']);
                $db->execute();

                $id = $db->lastInsertId();

                $db->query(" INSERT IGNORE INTO user_org 
                ( id, guid, role ) VALUES 
                ( :id, :guid, :role ) ");
                $db->bind(':id', $id);
                $db->bind(':guid', $org_guid);
                $db->bind(':role', $person['role_id']);
                $db->execute();

                $persons[$i]['id'] = $id;
            }
            catch(Exception $e)
            {
                $code = $e->getCode();
                if($code == 23000)
                {
                    $person['email'] = null;
                    $person['mobile'] = null;

                    $db->query(" INSERT INTO user 
                        ( user_key, session_id, email, mobile, pass, settings, updated, updated_by ) VALUES 
                        ( :user_key, NULL, :email, :mobile, NULL, NULL, NOW(), -1 ) 
                        ON DUPLICATE KEY UPDATE 
                        id = last_insert_id(id), 
                        email = :email, mobile = :mobile, 
                        updated = NOW(), updated_by = -1 ");
                    $db->bind(':user_key', $person['user_key']);
                    $db->bind(':email', $person['email']);
                    $db->bind(':mobile', $person['mobile']);
                    $db->execute();

                    $id = $db->lastInsertId();

                    $db->query(" INSERT IGNORE INTO user_org 
                        ( id, guid, role ) VALUES 
                        ( :id, :guid, :role ) ");
                    $db->bind(':id', $id);
                    $db->bind(':guid', $org_guid);
                    $db->bind(':role', $person['role_id']);
                    $db->execute();

                    $persons[$i]['id'] = $id;
                    $persons[$i]['email'] = $person['email'];
                    $persons[$i]['mobile'] = $person['mobile'];

                    if($person['is_guardian_default'])
                        $default_guardian_map[ $person['guardian_of'] ] = $id;
                }
                else
                    throw new Exception('Failed to insert or update User: ' . $e->getMessage());
            }
        }

        foreach($persons_guardians as $i => $person)
        {
            $last_record = $person;

            try
            {
                $p_email = $person['email'];
                $p_mobile = $person['mobile'];
                $p_ext_id = $person['ext_id'];

                $db->query(" SELECT id, u.ext_id, email, mobile FROM user u 
                  WHERE ( email = :email AND email IS NOT NULL ) OR ( mobile = :mobile AND mobile IS NOT NULL ) ");
                $db->bind(':email', $p_email);
                $db->bind(':mobile', $p_mobile);
                $db->execute();
                $alts = $db->fetchAllAssoc();

                $num_alts = count($alts);
                $id = 0;

                if($num_alts == 0) // no existing match
                {
                    $db->query(" INSERT INTO user 
                        ( ext_id, user_key, session_id, email, mobile, pass, settings, updated, updated_by ) VALUES 
                        ( :ext_id, :user_key, NULL, :email, :mobile, NULL, NULL, NOW(), -1 ) 
                        ON DUPLICATE KEY UPDATE 
                        id = last_insert_id(id), ext_id = :ext_id, 
                        email = :email, mobile = :mobile, 
                        updated = NOW(), updated_by = -1 ");
                    $db->bind(':user_key', $person['user_key']);
                    $db->bind(':ext_id', $p_ext_id);
                    $db->bind(':email', $p_email);
                    $db->bind(':mobile', $p_mobile);
                    $db->execute();

                    $id = $db->lastInsertId();
                }
                else
                {
                    if($num_alts == 1)
                    {
                        $alt = $alts[0];

                        $alt_email = (!empty($alt['email'])) ? $alt['email'] : null;
                        $alt_mobile = (!empty($alt['mobile'])) ? $alt['mobile'] : null;

                        // giving priority here to stored values (because possibly that of staff)
                        $p_email = (!is_null($alt_email)) ? $alt_email : $p_email;
                        $p_mobile = (!is_null($alt_mobile)) ? $alt_mobile : $p_mobile;
                        $id = $alt['id'];
                        $p_ext_id = $alt['ext_id'];
                    }
                    else
                    {
                        $have_match_email = false;
                        $have_match_mobile = false;
                        foreach($alts as $n => $alt)
                        {
                            $alt_email = (!empty($alt['email'])) ? $alt['email'] : null;
                            $alt_mobile = (!empty($alt['mobile'])) ? $alt['mobile'] : null;

                            if($alt_email == $p_email)
                                $have_match_email = true;

                            if($alt_mobile == $p_mobile)
                                $have_match_mobile = true;

                            if(($have_match_email) && ($have_match_mobile))
                            {
                                $id = $alt['id'];
                                $p_ext_id = $alt['ext_id'];
                                break;
                            }

                            // match on emails first, overwrite email
                            if(($have_match_email) && (!$have_match_mobile) && (!is_null($alt_email)))
                            {
                                $p_mobile = null;
                                $id = $alt['id'];
                                $p_ext_id = $alt['ext_id'];
                                break;
                            }

                            // now match on mobile, overwrite email
                            if((!$have_match_email) && ($have_match_mobile) && (!is_null($alt_mobile)))
                            {
                                $p_email = null;
                                $id = $alt['id'];
                                $p_ext_id = $alt['ext_id'];
                                break;
                            }
                        }
                    }

                    if(empty($id))
                        throw new Exception('Guardian not inserted nor updated: ' . json_encode($last_record));

                    $db->query(" UPDATE user u SET 
                      u.ext_id = :ext_id, email = :email, mobile = :mobile WHERE id = :id ");
                    $db->bind(':id', $id);
                    $db->bind(':ext_id', $p_ext_id);
                    $db->bind(':email', $p_email);
                    $db->bind(':mobile', $p_mobile);
                    $db->execute();
                }

                $db->query(" INSERT IGNORE INTO user_org 
                ( id, guid, role ) VALUES 
                ( :id, :guid, :role ) ");
                $db->bind(':id', $id);
                $db->bind(':guid', $org_guid);
                $db->bind(':role', $person['role_id']);
                $db->execute();

                $persons_guardians[$i]['id'] = $id;
                $persons_guardians[$i]['ext_id'] = $p_ext_id;
                $persons_guardians[$i]['email'] = $p_email;
                $persons_guardians[$i]['mobile'] = $p_mobile;

                $guardian_of_ext_id = (isset($person['guardian_of'])) ? intval($person['guardian_of']) : 0;
                if($guardian_of_ext_id)
                {
                    if(!isset($default_guardian_map[ $guardian_of_ext_id ]))
                        $default_guardian_map[ $guardian_of_ext_id ] = [];

                    $default_guardian_map[ $guardian_of_ext_id ] = $id;
                }
            }
            catch(Exception $e)
            {
                $code = $e->getCode();
                if($code == 23000)
                {
                    $person['email'] = null;
                    $person['mobile'] = null;

                    $db->query(" INSERT INTO user 
                        ( user_key, session_id, email, mobile, pass, settings, updated, updated_by ) VALUES 
                        ( :user_key, NULL, :email, :mobile, NULL, NULL, NOW(), -1 ) 
                        ON DUPLICATE KEY UPDATE 
                        id = last_insert_id(id), 
                        email = :email, mobile = :mobile, 
                        updated = NOW(), updated_by = -1 ");
                    $db->bind(':user_key', $person['user_key']);
                    $db->bind(':email', $person['email']);
                    $db->bind(':mobile', $person['mobile']);
                    $db->execute();

                    $id = $db->lastInsertId();

                    $db->query(" INSERT IGNORE INTO user_org 
                        ( id, guid, role ) VALUES 
                        ( :id, :guid, :role ) ");
                    $db->bind(':id', $id);
                    $db->bind(':guid', $org_guid);
                    $db->bind(':role', $person['role_id']);
                    $db->execute();

                    $persons[$i]['id'] = $id;
                    $persons[$i]['email'] = $person['email'];
                    $persons[$i]['mobile'] = $person['mobile'];

                    if($person['is_guardian_default'])
                        $default_guardian_map[ $person['guardian_of'] ] = $id;
                }
                else
                    throw new Exception('Failed to insert or update User: ' . $e->getMessage());
            }
        }

        $db->commit();
    }
    catch(Exception $e)
    {
        $db->rollback();
        throw new Exception('An error occurred during User insert: '. $e->getMessage() . json_encode($last_record));
    }


    unset($last_record);
    $status[] = 'User accounts updated';


    // begin org transaction

    $last_record = [];
    $duplicate_records = [];
    $org_db->beginTransaction();
    try
    {
        // deactivate all existing persons, preserving original deactivation dates if present

        $org_db->query(" UPDATE person SET active = 0, is_staff = 0, is_guardian = 0, is_member = 0, 
            deactivated = IF(deactivated IS NULL, DATE(NOW()), deactivated),  
            updated = NOW(), updated_by = -1 
            WHERE 1 ");
        $org_db->execute();

        $status[] = 'Persons deactivated';


        // insert/update group_leader, ls_teacher, sna, ancillary, and guardian person records

        $group_leader_map = []; # ext_id => id
        $ls_teacher_map = []; # ext_id => id
        $sna_map = []; # ext_id => id
        $ancillary_map = []; # ext_id => id
        $member_map = []; # ext_id => [ id, group_class_id, ls_teacher_id, guardians, default_guardian, siblings ]
        $staff_id_map = [];
        $staff_ids_by_type = [
            'group_leader' => [],
            'ls_teacher' => [],
            'sna' => [],
            'ancillary' => []
        ];

        foreach($persons as $i => $person)
        {
            $last_record = $person;


            $org_db->query(" INSERT INTO person  
                    ( id, ext_id, fname, lname, salt_id, salute_name, indexed_lname, landline, mobile, email, 
                      is_staff, is_guardian, is_member, active, created, deactivated, updated, updated_by ) VALUES 
                    ( :id, :ext_id, :fname, :lname, :salt_id, :salute_name, :indexed_lname, NULL, :mobile, :email, 
                      :is_staff, 0, :is_member, :active, NOW(), NOW(), NOW(), -1 ) 
                    ON DUPLICATE KEY UPDATE 
                    fname = :fname, lname = :lname, salt_id = :salt_id, salute_name = :salute_name, indexed_lname = :indexed_lname,  
                    mobile = :mobile, email = :email, 
                    is_staff = :is_staff, is_member = :is_member,  
                    active = :active, deactivated = NULL, updated = NOW(), updated_by = -1 " );

            $person['salt_id'] = (is_null($person['salt_id'])) ? 0 : $person['salt_id'];
            $person['is_staff'] = (!empty($person['is_staff'])) ? 1 : 0;
            $person['is_member'] = (!empty($person['is_member'])) ? 1 : 0;
            $person['active'] = (!empty($person['active'])) ? 1 : 0;
            $person['alias'] = (empty($person['alias'])) ? null : $person['alias'];

            $org_db->bind(':id', $person['id']);
            $org_db->bind(':ext_id', $person['ext_id']);
            $org_db->bind(':fname', $person['fname']);
            $org_db->bind(':lname', $person['lname']);
            $org_db->bind(':salt_id', $person['salt_id']);
            $org_db->bind(':salute_name', $person['alias']);
            $org_db->bind(':indexed_lname', $person['indexed_lname']);
            $org_db->bind(':mobile', $person['mobile']);
            $org_db->bind(':email', $person['email']);
            $org_db->bind(':is_staff', $person['is_staff']);
            $org_db->bind(':is_member', $person['is_member']);
            $org_db->bind(':active', $person['active']);
            $org_db->execute();


            if($person['is_staff'])
            {
                $staff_id_map[ $person['ext_id'] ] = $person['id'];

                if($person['is_group_leader'])
                {
                    $group_leader_map[ $person['ext_id'] ] = $person['id'];
                    $staff_ids_by_type['group_leader'][] = $person['id'];
                }
                elseif($person['is_ls_teacher'])
                {
                    $ls_teacher_map[ $person['ext_id'] ] = $person['id'];
                    $staff_ids_by_type['ls_teacher'][] = $person['id'];
                }
                elseif($person['is_sna'])
                {
                    $sna_map[ $person['ext_id'] ] = $person['id'];
                    $staff_ids_by_type['sna'][] = $person['id'];
                }
                else
                {
                    $ancillary_map[ $person['ext_id'] ] = $person['id'];
                    $staff_ids_by_type['ancillary'][] = $person['id'];
                }
            }
            else
                $member_map[ $person['ext_id'] ] = [
                    'id' => $person['id'],
                    'group_class_id' => $person['group_class_id'],
                    'ls_teacher_id' => $person['ls_teacher_id'],
                    'guardians' => $person['guardians'],
                    'siblings' => $person['siblings']
                ];
        }

        $status[] = 'Persons updated';

        $org_db->query(" INSERT INTO person  
                    ( id, ext_id, fname, lname, salt_id, salute_name, indexed_lname, landline, mobile, email, 
                      is_guardian, active, created, deactivated, updated, updated_by ) VALUES 
                    ( :id, NULL, :fname, :lname, :salt_id, :salute_name, :indexed_lname, NULL, :mobile, :email, 
                      1, :active, NOW(), NOW(), NOW(), -1 ) 
                    ON DUPLICATE KEY UPDATE 
                    fname = :fname, lname = :lname, salt_id = :salt_id, salute_name = :salute_name, indexed_lname = :indexed_lname,  
                    mobile = :mobile, email = :email, 
                    is_guardian = 1, active = :active, deactivated = NULL, updated = NOW(), updated_by = -1 " );

        $guardian_map = []; # ext_id => id
        foreach($persons_guardians as $i => $person)
        {
            $last_record = $person;

            $person['salt_id'] = (is_null($person['salt_id'])) ? 0 : $person['salt_id'];
            $person['active'] = (!empty($person['active'])) ? 1 : 0;
            $person['alias'] = (empty($person['alias'])) ? null : $person['alias'];

            $org_db->bind(':id', $person['id']);
            $org_db->bind(':fname', $person['fname']);
            $org_db->bind(':lname', $person['lname']);
            $org_db->bind(':salt_id', $person['salt_id']);
            $org_db->bind(':salute_name', $person['alias']);
            $org_db->bind(':indexed_lname', $person['indexed_lname']);
            $org_db->bind(':mobile', $person['mobile']);
            $org_db->bind(':email', $person['email']);
            $org_db->bind(':active', $person['active']);
            $org_db->execute();

            if(!$person['is_guardian_default'])
                $guardian_map[ $person['ext_id'] ] = $person['id'];
        }

        $status[] = 'Guardians updated';






        // deactivate all staff roles

        $org_db->query(" SELECT tbl_id, id, role FROM staff_role WHERE role_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_roles = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_roles[ $t['id'] ]))
                $concurrent_roles[ $t['id'] ] = [];

            $concurrent_roles[ $t['id'] ][] = [ 'tbl_id'=>$t['tbl_id'], 'role'=>$t['role'] ];
        }

        $org_db->query(" UPDATE staff_role SET role_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE 1 ");
        $org_db->execute();

        $status[] = 'Staff roles deactivated';


        // update/insert staff to roles

        foreach($staff_ids_by_type['group_leader'] as $staff_id)
        {
            if(array_key_exists($staff_id, $concurrent_roles))
            {
                $concurrent_role_record = 0;
                foreach($concurrent_roles[$staff_id] as $role)
                {
                    if($role['role'] == $group_leader_role_id)
                        $concurrent_role_record = $role['tbl_id'];
                }

                if($concurrent_role_record)
                {
                    $org_db->query(" UPDATE staff_role SET role_end = NULL WHERE tbl_id = {$concurrent_role_record} ");
                    $org_db->execute();
                    continue;
                }
            }

            $org_db->query(" INSERT IGNORE INTO staff_role 
                    ( id, role, role_begin, role_end, updated, updated_by ) VALUES 
                    ( {$staff_id}, {$group_leader_role_id}, NOW(), NULL, NOW(), -1 ) ");
            $org_db->execute();
        }

        foreach($staff_ids_by_type['ls_teacher'] as $staff_id)
        {
            if(array_key_exists($staff_id, $concurrent_roles))
            {
                $concurrent_role_record = 0;
                foreach($concurrent_roles[$staff_id] as $role)
                {
                    if($role['role'] == $ls_teacher_role_id)
                        $concurrent_role_record = $role['tbl_id'];
                }

                if($concurrent_role_record)
                {
                    $org_db->query(" UPDATE staff_role SET role_end = NULL WHERE tbl_id = {$concurrent_role_record} ");
                    $org_db->execute();
                    continue;
                }
            }

            $org_db->query(" INSERT IGNORE INTO staff_role 
                    ( id, role, role_begin, role_end, updated, updated_by ) VALUES 
                    ( {$staff_id}, {$ls_teacher_role_id}, NOW(), NULL, NOW(), -1 ) ");
            $org_db->execute();
        }

        foreach($staff_ids_by_type['sna'] as $staff_id)
        {
            if(array_key_exists($staff_id, $concurrent_roles))
            {
                $concurrent_role_record = 0;
                foreach($concurrent_roles[$staff_id] as $role)
                {
                    if($role['role'] == $sna_role_id)
                        $concurrent_role_record = $role['tbl_id'];
                }

                if($concurrent_role_record)
                {
                    $org_db->query(" UPDATE staff_role SET role_end = NULL WHERE tbl_id = {$concurrent_role_record} ");
                    $org_db->execute();
                    continue;
                }
            }

            $org_db->query(" INSERT IGNORE INTO staff_role 
                    ( id, role, role_begin, role_end, updated, updated_by ) VALUES 
                    ( {$staff_id}, {$sna_role_id}, NOW(), NULL, NOW(), -1 ) ");
            $org_db->execute();
        }

        foreach($staff_ids_by_type['ancillary'] as $staff_id)
        {
            if(array_key_exists($staff_id, $concurrent_roles))
            {
                $concurrent_role_record = 0;
                foreach($concurrent_roles[$staff_id] as $role)
                {
                    if($role['role'] == $ancillary_role_id)
                        $concurrent_role_record = $role['tbl_id'];
                }

                if($concurrent_role_record)
                {
                    $org_db->query(" UPDATE staff_role SET role_end = NULL WHERE tbl_id = {$concurrent_role_record} ");
                    $org_db->execute();
                    continue;
                }
            }

            $org_db->query(" INSERT IGNORE INTO staff_role 
                    ( id, role, role_begin, role_end, updated, updated_by ) VALUES 
                    ( {$staff_id}, {$ancillary_role_id}, NOW(), NULL, NOW(), -1 ) ");
            $org_db->execute();
        }

        unset($staff_ids_by_type);
        $status[] = 'Staff roles updated';





        // deactivate all group_staff

        $org_db->query(" UPDATE group_staff SET active = 0, updated = NOW(), updated_by = -1 WHERE 1 ");
        $org_db->execute();
        $status[] = 'Staff groups deactivated';


        // insert/update group_staff records

        $group_staff_map = []; # ext_id => id
        $org_db->query(" INSERT INTO group_staff 
                ( ext_id, title, title_eng_gae_variant, abbr, active, 
                  weight, updated, updated_by ) VALUES 
                ( :ext_id, :title, NULL, :abbr, :active, 0, NOW(), -1 ) 
                ON DUPLICATE KEY UPDATE 
                id = last_insert_id(id), ext_id = :ext_id, title = :title, abbr = :abbr, 
                active = :active, updated = NOW(), updated_by = -1 " );

        foreach($data['group_staff'] as $group_staff)
        {
            $last_record = $group_staff;
            $group_staff = array_change_key_case($group_staff, CASE_LOWER);

            $ext_id = intval($group_staff['id']);
            if(empty($ext_id))
                throw new Exception('Class group set with invalid ID: ' . json_encode($group_staff));

            if(array_key_exists($ext_id, $group_staff_map))
                throw new Exception('Class group set with duplicate ID: ' . json_encode($group_staff));

            $active = (!isset($group_staff['active'])) ? 1 : (intval($group_staff['active']) > 0) ? 1 : 0;
            $title = trim($group_staff['title']);
            $abbr = null;
            if(empty($group_staff['abbr']))
            {
                $parts = explode(' ', $title);
                foreach($parts as $part)
                    $abbr.= substr($part, 0, 3);
            }
            $abbr = substr($abbr,0,5);

            $org_db->bind(':ext_id', $ext_id);
            $org_db->bind(':title', $title);
            $org_db->bind(':abbr', $abbr);
            $org_db->bind(':active', $active);
            $org_db->execute();

            $group_staff_map[ $ext_id ] = $org_db->lastInsertId();
        }
        unset($data['group_staff']);
        $status[] = 'Staff groups updated';



        // deactivate all staff group associations

        $org_db->query(" SELECT tbl_id, id, group_staff_id FROM staff_group_staff WHERE in_group_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_groupings = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_groupings[ $t['id'] ]))
                $concurrent_groupings[ $t['id'] ] = [];

            $concurrent_groupings[ $t['id'] ][] = [ 'tbl_id'=>$t['tbl_id'], 'group_staff_id'=>$t['group_staff_id'] ];
        }

        $org_db->query(" UPDATE staff_group_staff SET in_group_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE in_group_end IS NULL ");
        $org_db->execute();

        $status[] = 'Staff Group members deactivated';


        // sort staff assignments in new and concurrent

        $concurrent_records = [];
        $new_records = [];
        foreach($staff_group_staff_map as $staff_ext_id => $group_ext_ids)
        {
            if(!isset($staff_id_map[ $staff_ext_id ]))
                throw new Exception('Invalid Staff Ext. ID found.');

            $staff_id = $staff_id_map[ $staff_ext_id ];
            if(!isset($new_records[$staff_id]))
                $new_records[$staff_id] = [];

            $group_ids = [];
            foreach($group_ext_ids as $group_ext_id)
            {
                if(!isset($group_staff_map[ $group_ext_id ]))
                    throw new Exception('Invalid Staff Group Ext. ID found.');

                $group_ids[] = $group_staff_map[ $group_ext_id ];
                $new_records[$staff_id][] = $group_staff_map[ $group_ext_id ];
            }


            if(array_key_exists($staff_id, $concurrent_groupings))
            {
                foreach($concurrent_groupings[$staff_id] as $i => $record)
                {
                    $key = array_search($record['group_staff_id'], $group_ids);
                    $new_key = array_search($record['group_staff_id'], $new_records[$staff_id]);

                    if($key !== false)
                    {
                        $concurrent_records[] = $record['tbl_id'];
                        unset($concurrent_groupings[$staff_id][$key]);

                        if(empty($concurrent_groupings[$staff_id]))
                            unset($concurrent_groupings[$staff_id]);
                    }

                    if($new_key !== false)
                    {
                        unset($new_records[$staff_id][$new_key]);

                        if(empty($new_records[$staff_id]))
                            unset($new_records[$staff_id]);
                    }
                }
            }
        }


        // update concurrent assignments

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE staff_group_staff SET in_group_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($new_records))
        {
            $org_db->query(" INSERT INTO staff_group_staff 
                    ( id, group_staff_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                    ( :id, :group_staff_id, NOW(), NULL, NOW(), -1 ) ");
            foreach($new_records as $staff_id => $group_ids)
            {
                foreach($group_ids as $group_id)
                {
                    $org_db->bind(':id', $staff_id);
                    $org_db->bind(':group_staff_id', $group_id);
                    $org_db->execute();
                }
            }
        }

        $concurrent_leaders = null;
        $new_records = null;
        unset($staff_group_staff_map);
        unset($group_staff_map);
        $status[] = 'Staff Group members updated';





        // deactivate all group_super

        $org_db->query(" UPDATE group_super SET active = 0, updated = NOW(), updated_by = -1 WHERE 1 ");
        $org_db->execute();
        $status[] = 'Super groups deactivated';


        // insert/update group_super records

        $group_super_map = []; # ext_id => id
        $org_db->query(" INSERT INTO group_super 
                ( ext_id, title, title_eng_gae_variant, abbr, active, 
                  academic_standard, academic_standard_band, 
                  current, weight, updated, updated_by ) VALUES 
                ( :ext_id, :title, NULL, :abbr, :active, 0, 0, :current_, 0, NOW(), -1 ) 
                ON DUPLICATE KEY UPDATE 
                id = last_insert_id(id), ext_id = :ext_id, title = :title, abbr = :abbr, 
                active = :active, current = :current_, updated = NOW(), updated_by = -1 " );

        foreach($data['group_super'] as $group_super)
        {
            $last_record = $group_super;
            $group_super = array_change_key_case($group_super, CASE_LOWER);

            $ext_id = intval($group_super['id']);
            if(empty($ext_id))
                throw new Exception('Class group set with invalid ID: ' . json_encode($group_super));

            if(array_key_exists($ext_id, $group_super_map))
                throw new Exception('Class group set with duplicate ID: ' . json_encode($group_super));

            $active = (!isset($group_super['active'])) ? 1 : (intval($group_super['active']) > 0) ? 1 : 0;
            $current = (intval($group_super['current']) > 0) ? 1 : 0;
            $title = trim($group_super['title']);
            $abbr = trim($group_super['abbr']);
            if(empty($abbr))
            {
                $parts = explode(' ', $title);
                foreach($parts as $part)
                    $abbr.= substr($part, 0, 3);
            }
            $abbr = substr($abbr,0,5);

            $org_db->bind(':ext_id', $ext_id);
            $org_db->bind(':title', $title);
            $org_db->bind(':abbr', $abbr);
            $org_db->bind(':active', $active);
            $org_db->bind(':current_', $current);
            $org_db->execute();

            $group_super_map[ $ext_id ] = $org_db->lastInsertId();
        }
        unset($data['group_super']);
        $status[] = 'Super groups updated';






        // deactivate all group_class

        $org_db->query(" UPDATE group_class SET active = 0, updated = NOW(), updated_by = -1 WHERE 1 ");
        $org_db->execute();
        $status[] = 'Class groups deactivated';


        // insert/update group_class records
        $group_class_map = []; # ext_id => id
        $group_class_super_map = []; # id => super_id
        $leader_to_groups_map = []; # leader id => [ group_class id, group_class id, ... ]
        $group_class_abbrs = [];
        $org_db->query(" INSERT INTO group_class 
                ( ext_id, title, title_eng_gae_variant, abbr, group_super_id, gender, active, weight, updated, updated_by ) VALUES 
                ( :ext_id, :title, NULL, :abbr, :group_super_id, :gender, :active, 0, NOW(), -1 ) 
                ON DUPLICATE KEY UPDATE 
                id = last_insert_id(id), ext_id = :ext_id, title = :title, abbr = :abbr, 
                group_super_id = :group_super_id, gender = :gender, active = :active, updated = NOW(), updated_by = -1 " );
        foreach($data['group_class'] as $group_class)
        {
            $last_record = $group_class;
            $group_class = array_change_key_case($group_class, CASE_LOWER);

            $ext_id = intval($group_class['id']);
            if(empty($ext_id))
                throw new Exception('Class group set with invalid ID: ' . json_encode($group_class));

            if(array_key_exists($ext_id, $group_class_map))
                throw new Exception('Class group set with duplicate ID: ' . json_encode($group_class));

            $super_ext_id = intval($group_class['group_super_id']);
            $group_super_id = (array_key_exists($super_ext_id, $group_super_map)) ? $group_super_map[ $super_ext_id ] : 0;
            if(empty($group_super_id))
            {
                throw new Exception('Class group does not belong to valid super group: ' . json_encode($group_class));
            }

            $leader_ext_id = $group_class['group_leader_id'];
            $leader_ids = [];
            if(array_key_exists($leader_ext_id, $group_leader_map))
                $leader_ids[] = $group_leader_map[ $leader_ext_id ];
            elseif(array_key_exists($leader_ext_id, $job_sharing_leader_map))
            {
                foreach($job_sharing_leader_map[ $leader_ext_id ] as $js_ext_id)
                    $leader_ids[] = $group_leader_map[ $js_ext_id ];
            }

            if(empty($leader_ids))
                throw new Exception('Class group set with invalid leader ID: ' . json_encode($group_class));

            $active = 1;
            $gender = strtoupper(trim($group_class['gender']));
            $gender = (in_array($gender, ['A','M','F'])) ? $gender : 'A';
            $title = trim($group_class['title']);
            $abbr = trim($group_class['abbr']);
            if(empty($abbr))
            {
                $parts = explode(' ', $title);
                foreach($parts as $part)
                    $abbr.= substr($part, 0, 3);
            }
            $abbr = substr($abbr,0,7);

            $org_db->bind(':ext_id', $ext_id);
            $org_db->bind(':title', $title);
            $org_db->bind(':abbr', $abbr);
            $org_db->bind(':group_super_id', $group_super_id);
            $org_db->bind(':gender', $gender);
            $org_db->bind(':active', $active);
            $org_db->execute();

            $id = $org_db->lastInsertId();

            $group_class_map[ $ext_id ] = $id;
            $group_class_super_map[ $id ] = $group_super_id;

            foreach($leader_ids as $leader_id)
            {
                if(!isset($leader_to_groups_map[ $leader_id ]))
                    $leader_to_groups_map[ $leader_id ] = [];

                $leader_to_groups_map[ $leader_id ][] = $id;
            }
        }
        unset($data['group_class']);
        $status[] = 'Class groups updated';





        // deactivate all group_leader associations

        $org_db->query(" SELECT tbl_id, id, group_class_id FROM group_leader WHERE leader_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_leaders = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_leaders[ $t['id'] ]))
                $concurrent_leaders[ $t['id'] ] = [];

            $concurrent_leaders[ $t['id'] ][] = [ 'tbl_id'=>$t['tbl_id'], 'group_class_id'=>$t['group_class_id'] ];
        }

        $org_db->query(" UPDATE group_leader SET leader_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE leader_end IS NULL ");
        $org_db->execute();

        $status[] = 'Group leaders deactivated';


        // sort leader assignments in new and concurrent

        $concurrent_records = [];
        foreach($leader_to_groups_map as $leader_id => $group_ids)
        {
            if(array_key_exists($leader_id, $concurrent_leaders))
            {
                foreach($concurrent_leaders[$leader_id] as $i => $record)
                {
                    $key = array_search($record['group_class_id'], $group_ids);
                    if($key !== false)
                    {
                        $concurrent_records[] = $record['tbl_id'];
                        unset($leader_to_groups_map[$leader_id][$key]);

                        if(empty($leader_to_groups_map[$leader_id]))
                            unset($leader_to_groups_map[$leader_id]);
                    }
                }
            }
        }


        // update concurrent assignments

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE group_leader SET leader_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($leader_to_groups_map))
        {
            $org_db->query(" INSERT INTO group_leader 
                    ( id, group_class_id, group_super_id, leader_begin, leader_end, updated, updated_by ) VALUES 
                    ( :id, :group_class_id, :group_super_id, NOW(), NULL, NOW(), -1 ) ");
            foreach($leader_to_groups_map as $leader_id => $group_ids)
            {
                foreach($group_ids as $group_id)
                {
                    if(empty($group_class_super_map[$group_id]))
                        throw new Exception('No Super ID was matched for the current role assignment: ' . $leader_id . '-' . $group_id);

                    $group_super_id = $group_class_super_map[$group_id];

                    $org_db->bind(':id', $leader_id);
                    $org_db->bind(':group_class_id', $group_id);
                    $org_db->bind(':group_super_id', $group_super_id);
                    $org_db->execute();
                }
            }
        }

        $concurrent_leaders = null;
        $concurrent_records = null;
        unset($leader_to_groups_map);
        unset($group_leader_map);
        $status[] = 'Group leaders updated';







        // deactivate all member_group_class associations

        $org_db->query(" SELECT tbl_id, id, group_class_id FROM member_group_class WHERE in_group_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_members = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_members[ $t['id'] ]))
                $concurrent_members[ $t['id'] ] = [];

            $concurrent_members[ $t['id'] ] = [ 'tbl_id'=>$t['tbl_id'], 'group_class_id'=>$t['group_class_id'] ];
        }

        $org_db->query(" UPDATE member_group_class SET in_group_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE in_group_end IS NULL ");
        $org_db->execute();

        $status[] = 'Group members deactivated';


        // sort member assignments into new and concurrent

        $concurrent_records = [];
        $new_records = [];
        foreach($member_map as $member_ext_id => $m)
        {
            $id = $m['id'];

            if(!array_key_exists($m['group_class_id'], $group_class_map))
                throw new Exception('Invalid Class assignment found: ' . json_encode($m));
            $group_class_id = $group_class_map[ $m['group_class_id'] ];

            if(array_key_exists($id, $concurrent_members))
            {
                if($concurrent_members[ $id ]['group_class_id'] == $group_class_id)
                    $concurrent_records[] = $concurrent_members[ $id ]['tbl_id'];
            }
            else
                $new_records[] = [
                    'id' => $id,
                    'group_class_id' => $group_class_id
                ];
        }


        // update/insert new and concurrent assignments

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE member_group_class SET in_group_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($new_records))
        {
            $org_db->query(" INSERT INTO member_group_class 
                    ( id, group_class_id, group_super_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                    ( :id, :group_class_id, :group_super_id, NOW(), NULL, NOW(), -1 ) ");
            foreach($new_records as $m)
            {
                if(empty($group_class_super_map[ $m['group_class_id'] ]))
                    throw new Exception('No Super ID was matched for the current role assignment: ' . $m['id'] . '-' . $m['group_class_id']);

                $group_super_id = $group_class_super_map[ $m['group_class_id'] ];

                if(empty($group_super_id))
                    throw new Exception('No Super ID was matched for the current role assignment: ' . $m['id'] . '-' . $m['group_class_id']);

                $org_db->bind(':id', $m['id']);
                $org_db->bind(':group_class_id', $m['group_class_id']);
                $org_db->bind(':group_super_id', $group_super_id);
                $org_db->execute();
            }
        }

        $concurrent_records = null;
        $new_records = null;
        unset($concurrent_members);
        unset($group_class_map);
        unset($group_super_map);
        unset($group_class_super_map);
        $status[] = 'Group members updated';







        // deactivate all member_ls_teacher associations

        $org_db->query(" SELECT tbl_id, id, ls_teacher_id FROM member_ls_teacher WHERE ls_teacher_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_assisted = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_assisted[ $t['id'] ]))
                $concurrent_assisted[ $t['id'] ] = [];

            $concurrent_assisted[ $t['id'] ] = [ 'tbl_id'=>$t['tbl_id'], 'ls_teacher_id'=>$t['ls_teacher_id'] ];
        }

        $org_db->query(" UPDATE member_ls_teacher SET ls_teacher_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE ls_teacher_end IS NULL ");
        $org_db->execute();

        $status[] = 'Member ls_teachers deactivated';


        // sort member assignments into new and concurrent

        $concurrent_records = [];
        $new_records = [];
        foreach($member_map as $member_ext_id => $m)
        {
            $ls_teacher_id = intval($m['ls_teacher_id']);
            $id = $m['id'];

            if(empty($ls_teacher_id))
                continue;

            if(!array_key_exists($ls_teacher_id, $ls_teacher_map))
                continue;

            $ls_teacher_id = $ls_teacher_map[$m['ls_teacher_id']];

            if(array_key_exists($id, $concurrent_assisted))
            {
                if($concurrent_assisted[ $id ]['ls_teacher_id'] == $ls_teacher_id)
                    $concurrent_records[] = $concurrent_assisted[ $id ]['tbl_id'];
            }
            else
                $new_records[] = [
                    'id' => $id,
                    'ls_teacher_id' => $ls_teacher_id
                ];
        }

        // update/insert new and concurrent assignments

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE member_ls_teacher SET ls_teacher_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($new_records))
        {
            $org_db->query(" INSERT INTO member_ls_teacher 
                    ( id, ls_teacher_id, ls_teacher_begin, ls_teacher_end, updated, updated_by ) VALUES 
                    ( :id, :ls_teacher_id, NOW(), NULL, NOW(), -1 ) ");
            foreach($new_records as $m)
            {
                $org_db->bind(':id', $m['id']);
                $org_db->bind(':ls_teacher_id', $m['ls_teacher_id']);
                $org_db->execute();
            }
        }

        $concurrent_records = null;
        $new_records = null;
        unset($concurrent_assisted);
        unset($ls_teacher_map);
        $status[] = 'Member ls_teachers updated';







        // deactivate all member_sibling associations

        $org_db->query(" SELECT tbl_id, id, sibling_id FROM member_sibling WHERE sibling_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_sibling = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_sibling[ $t['id'] ]))
                $concurrent_sibling[ $t['id'] ] = [];

            $concurrent_sibling[ $t['id'] ][] = [ 'tbl_id'=>$t['tbl_id'], 'sibling_id'=>$t['sibling_id'] ];
        }

        $org_db->query(" UPDATE member_sibling SET sibling_end = DATE(NOW()), updated = NOW(), updated_by = -1 WHERE sibling_end IS NULL ");
        $org_db->execute();

        $status[] = 'Member siblings deactivated';

        $concurrent_records = [];
        $new_records = [];
        foreach($member_map as $member_ext_id => $m)
        {
            $member_id = $m['id'];
            $sibling_ids = [];

            if(empty($m['siblings']))
                continue;

            #update ids to database ids
            foreach($m['siblings'] as $ext_id)
            {
                if(!isset($member_map[ $ext_id ]))
                    continue;

                $sibling_ids[] = $member_map[ $ext_id ]['id'];
            }

            if(empty($sibling_ids))
                continue;

            # did not have siblings before, therefore all records are new
            if(!array_key_exists($member_id, $concurrent_sibling))
            {
                if(!isset($new_records[ $member_id ]))
                    $new_records[ $member_id ] = [];

                foreach($sibling_ids as $sibling_id)
                    $new_records[ $member_id ][] = $sibling_id;

                continue;
            }

            # did have siblings before, so check which are concurrent and which are new
            $tmp = $concurrent_sibling[ $member_id ];
            foreach($tmp as $t)
            {
                $key = array_search($t['sibling_id'], $sibling_ids);
                if($key !== false)
                {
                    $concurrent_records[] = $t['tbl_id'];
                    unset($sibling_ids[$key]);
                    continue;
                }
            }
            unset($tmp);

            if(empty($sibling_ids))
                continue;

            foreach($sibling_ids as $sibling_id)
            {
                if(!isset($new_records[ $member_id ]))
                    $new_records[ $member_id ] = [];

                $new_records[ $member_id ][] = $sibling_id;
            }
        }

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE member_sibling SET sibling_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($new_records))
        {
            $org_db->query(" INSERT INTO member_sibling 
                ( id, sibling_id, sibling_begin, sibling_end, updated, updated_by ) VALUES 
                ( :id, :sibling_id, NOW(), NULL, NOW(), -1 ) ");
            foreach($new_records as $id => $siblings)
            {
                foreach($siblings as $sibling_id)
                {
                    $org_db->bind(':id', $id);
                    $org_db->bind(':sibling_id', $sibling_id);
                    $org_db->execute();
                }
            }
        }

        $concurrent_records = null;
        $new_records = null;
        $concurrent_records_str = null;
        unset($concurrent_sibling);
        $status[] = 'Member siblings updated';







        // deactivate all member_guardian associations

        $org_db->query(" SELECT tbl_id, id, guardian_id FROM member_guardian WHERE guardian_end IS NULL ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $concurrent_guardian = [];
        foreach($tmp as $t)
        {
            if(!isset($concurrent_guardian[ $t['id'] ]))
                $concurrent_guardian[ $t['id'] ] = [];

            $concurrent_guardian[ $t['id'] ][] = [ 'tbl_id'=>$t['tbl_id'], 'guardian_id'=>$t['guardian_id'] ];
        }

        $org_db->query(" UPDATE member_guardian SET 
            guardian_end = DATE(NOW()), is_default= 0, updated = NOW(), updated_by = -1 
            WHERE guardian_end IS NULL ");
        $org_db->execute();

        $status[] = 'Member guardians deactivated';

        $concurrent_records = [];
        $new_records = [];
        $default_guardians = [];
        foreach($member_map as $member_ext_id => $m)
        {
            $member_id = $m['id'];
            $guardian_ext_ids = $m['guardians'];
            $default_guardian_id = (isset($default_guardian_map[ $member_ext_id ])) ? $default_guardian_map[ $member_ext_id ] : 0;

            #update guardian ext ids to database ids
            $guardian_ids = [];
            foreach($guardian_ext_ids as $ext_id)
            {
                if(!isset($guardian_map[ $ext_id ]))
                    continue;

                $guardian_ids[] = $guardian_map[ $ext_id ];
            }

            #add default guardian to the mix
            if($default_guardian_id)
            {
                $guardian_ids[] = $default_guardian_id;
                $default_guardians[$member_id] = $default_guardian_id;
            }

            if(empty($guardian_ids))
                continue;


            # did not have guardians before, therefore all records are new
            if(!array_key_exists($member_id, $concurrent_guardian))
            {
                if(!isset($new_records[ $member_id ]))
                    $new_records[ $member_id ] = [];
                $new_records[ $member_id ] = $guardian_ids;
                unset($guardian_ids);
                continue;
            }

            # did have guardians before, so remove concurrent matches from uploaded guardian_ids
            $tmp = $concurrent_guardian[ $member_id ];
            foreach($tmp as $t)
            {
                $key = array_search($t['guardian_id'], $guardian_ids);
                if($key !== false)
                {
                    $concurrent_records[] = $t['tbl_id'];
                    unset($guardian_ids[$key]);
                    continue;
                }
            }
            unset($tmp);

            if(empty($guardian_ids))
                continue;

            # then set new ones
            foreach($guardian_ids as $guardian_id)
            {
                if(!isset($new_records[ $member_id ]))
                    $new_records[ $member_id ] = [];

                $new_records[ $member_id ][] = $guardian_id;
            }
        }

        if(!empty($concurrent_records))
        {
            $concurrent_records_str = implode(',',$concurrent_records);
            $org_db->query(" UPDATE member_guardian SET guardian_end = NULL WHERE tbl_id IN ({$concurrent_records_str}) ");
            $org_db->execute();
        }

        if(!empty($new_records))
        {
            $org_db->query(" INSERT INTO member_guardian 
                ( id, guardian_id, guardian_begin, guardian_end, is_default, updated, updated_by ) VALUES 
                ( :id, :guardian_id, NOW(), NULL, 0, NOW(), -1 ) ");
            foreach($new_records as $member_id => $guardians)
            {
                $last_record = $guardians;

                foreach($guardians as $guardian_id)
                {
                    $org_db->bind(':id', $member_id);
                    $org_db->bind(':guardian_id', $guardian_id);
                    $org_db->execute();
                }
            }
        }

        $concurrent_guardian = null;
        $concurrent_records = null;
        $new_records = null;
        $concurrent_records_str = null;
        unset($guardian_map);
        $status[] = 'Member guardians updated';

        if(!empty($default_guardians))
        {
            $org_db->query(" UPDATE member_guardian SET is_default = 1 WHERE id = :id AND guardian_id = :guardian_id ");
            foreach($default_guardians as $member_id => $guardian_id)
            {
                $org_db->bind(':id', $member_id);
                $org_db->bind(':guardian_id', $guardian_id);
                $org_db->execute();
            }
        }

        # set any guardians that are the only active guardian for a pupil as the default guardian
        $org_db->query(" SELECT id, COUNT(DISTINCT guardian_id) AS num_guardians 
            FROM member_guardian 
            WHERE ( guardian_end IS NULL ) 
            GROUP BY id 
            ORDER BY num_guardians ASC ");
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc();

        $db->query(" UPDATE member_guardian SET is_default = 1 WHERE id = :id AND guardian_end IS NULL ");
        foreach($tmp as $t)
        {
            $n = intval($t['num_guardians']);
            if($n > 1)
                break;

            $org_db->bind(':id', $t['id']);
            $org_db->execute();
        }
        unset($tmp);
        unset($member_map);
        unset($default_guardians);
        $status[] = 'Member default guardians updated';








        $status[] = 'Update complete';
        $org_db->commit();
    }
    catch(Exception $e)
    {
        $org_db->rollback();
        echo json_encode($last_record);
        \JCT\Helper::show($e->getTrace());
        throw new Exception($e->getMessage() . ' @ ' . $e->getLine() . ' : ' . json_encode($last_record));
    }




    // begin user transaction

    $db->beginTransaction();

    // record upload


    # add DATABIZ users as devs to guid

    # add DATABIZ users to app_screen_users for org

    $db->query(" INSERT INTO api_record 
            ( api_action, guid, api_datetime, source_ip ) VALUES  
            ( 'nsadmin_synch', :guid, NOW(), :source_ip )");
    $db->bind(':guid', $org_guid);
    $db->bind(':source_ip', NULL);
    $db->execute();

    $status[] = 'Upload recorded';




    foreach($status as $s)
        echo $s . "<br/>";

    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Total Execution Time: '.$execution_time.' seconds';

}
catch (Exception $e)
{
    $time_end = microtime(true);
    $status[] = $e->getMessage();

    foreach($status as $s)
        echo $s . "<br/>";

    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Transaction failed. Total Execution Time: '.$execution_time.' seconds';
}