<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 20/03/2019
 * Time: 10:39
 */

namespace JCT;


use JCT\Helper;
use Exception;
use DateTime;
use JCT\Database;

class member_update
{
    public $guid;
    public $remote_api_call;

    public $success = 0;
    public $error = 0;
    public $response;

    private $_DB;
    private $_ORG_DB;
    private $input;

    private $data;
    private $now;

    private $group_classes_by_id = [];
    private $group_classes_by_ext_id = [];

    private $member_id_ext_id_map = [];

    private $submitted_members = [];


    function __construct(Database $_DB, Database $_ORG_DB, $guid, $input)
    {
        try
        {
            $this->_DB = $_DB;
            $this->_ORG_DB = $_ORG_DB;
            $this->guid = $guid;
            $this->input = $input;
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
        }
    }

    function execute()
    {
        try
        {
            $data = json_decode($this->input['data'], true, 512, JSON_UNESCAPED_UNICODE);

            switch (json_last_error())
            {
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

            if (!empty($error))
                throw new Exception($error);

            $data = array_change_key_case($data, CASE_LOWER);
            $this->data = $data;

            $now = new DateTime();
            $this->now = $now;

            $this->set_group_classes();

            if(!empty($this->data['member']))
            {
                $tmp = $this->parse_members();
                if(isset($tmp['error']))
                    throw new Exception('While parsing Members: ' . $tmp['error']);
            }

            if(!empty($this->member_id_ext_id_map))
            {
                $tmp = $this->update_members_in_group_classes();
                if(isset($tmp['error']))
                    throw new Exception('While updating Members in group_classes: ' . $tmp['error']);
            }

            $existing_ext_ids = (!empty($this->member_id_ext_id_map)) ? array_values($this->member_id_ext_id_map) : [];
            $submitted_ext_ids = array_keys($this->submitted_members);

            if(count($existing_ext_ids) < count($submitted_ext_ids))
            {
                // new to THIS org, not necessarily to the system
                // but there's no way to sort this, currently (maybe DPINS)

                $new_ext_ids = array_diff($submitted_ext_ids, $existing_ext_ids);
                $new_ext_ids_as_keys = array_flip($new_ext_ids);
                $new_members = array_intersect_key($this->submitted_members, $new_ext_ids_as_keys);

                $tmp = $this->insert_new_members($new_members);
                if(isset($tmp['error']))
                    throw new Exception('While inserting new Members: ' . $tmp['error']);
            }

            $tmp = $this->check_and_update_siblings();
            if(isset($tmp['error']))
                throw new Exception('While updating siblings: ' . $tmp['error']);

            if( (!empty($this->data['guardian'])) || (!empty($this->data['guardian_default'])) )
            {
                $tmp = $this->check_and_update_guardians();
                if(isset($tmp['error']))
                    throw new Exception('While updating guardians: ' . $tmp['error']);
            }

            $this->success = 1;
            $this->error = 0;
            $this->response = 'OK';
            return true;
        }
        catch (Exception $e)
        {
            $this->success = 0;
            $this->error = 1;
            $this->response = $e->getMessage();
            return false;
        }
    }


    private function set_group_classes()
    {
        $db = $this->_ORG_DB;

        $db->query(" SELECT id, ext_id, group_super_id FROM group_class WHERE ( active_end IS NULL ) ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        foreach($tmp as $t)
        {
            $this->group_classes_by_ext_id[ $t['ext_id'] ] = [
                'id' => $t['id'],
                'group_super_id' => $t['group_super_id']
            ];
            $this->group_classes_by_id[ $t['id'] ] = [
                'ext_id' => $t['ext_id'],
                'group_super_id' => $t['group_super_id']
            ];
        }
    }

    private function parse_members()
    {
        $db = $this->_ORG_DB;
        try
        {
            $db->query(" SELECT id FROM person WHERE ( ext_id = :ext_id AND is_member = 1 ) ");
            foreach($this->data['member'] as $m)
            {
                $m = array_change_key_case($m, CASE_LOWER);
                foreach ($m as $k => $v)
                    $m[$k] = Helper::nullify_empty_values($v);

                $ext_id = intval($m['id']);

                $db->bind(':ext_id', $ext_id);
                $db->execute();
                $id = intval($db->fetchSingleColumn());

                if($id)
                    $this->member_id_ext_id_map[ $id ] = $ext_id;

                $guardian_ext_ids = (!empty($m['guardians'])) ? array_map('intval', $m['guardians']) : [];
                $sibling_ext_ids = (!empty($m['siblings'])) ? array_map('intval', $m['siblings']) : [];

                $group_class_ext_id = intval($m['group_class']);
                if(!isset($this->group_classes_by_ext_id[$group_class_ext_id]))
                    throw new Exception('Invalid Group Class ID (' . $group_class_ext_id .  ').');

                $this->submitted_members[ $ext_id ] = [
                    'ext_id' => $ext_id,
                    'id' => $id,
                    'fname' => trim($m['fname']),
                    'lname' => trim($m['lname']),
                    'indexed_lname' => Helper::lname_as_index($m['lname']),
                    'new_group_class_ext_id' => $group_class_ext_id,
                    'new_group_class_id' => $this->group_classes_by_ext_id[$group_class_ext_id]['id'],
                    'new_group_super_id' => $this->group_classes_by_ext_id[$group_class_ext_id]['group_super_id'],
                    'guardians' => array_unique($guardian_ext_ids),
                    'siblings' => array_unique($sibling_ext_ids)
                ];
            }
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function update_members_in_group_classes()
    {
        $db = $this->_ORG_DB;
        $db->beginTransaction();
        try
        {
            $now = new DateTime();
            $now_date_str = $now->format('Y-m-d');

            $member_ids = array_keys($this->member_id_ext_id_map);
            $member_ids_str = implode(',',$member_ids);

            // get group_class for any existing members

            $db->query(" SELECT id, group_class_id 
            FROM member_group_class 
            WHERE (  
                id IN ({$member_ids_str}) AND 
                in_group_end IS NULL
            ) ");
            $current_group_per_member = $db->fetchAllAssoc('id', true);

            // determine which members have changed group, and end their current group memberships

            $db->query(" UPDATE member_group_class 
                SET in_group_end = '{$now_date_str}' 
                WHERE ( 
                    id = :id AND 
                    group_class_id = :group_class_id AND 
                    in_group_end IS NULL 
                ) ");
            $members_in_new_groups = [];
            foreach($this->member_id_ext_id_map as $id => $ext_id)
            {
                $new_group_class_id = $this->submitted_members[ $ext_id ]['new_group_class_id'];
                $current_group_class_id = $current_group_per_member[$id];

                if($new_group_class_id == $current_group_class_id)
                    continue;

                $db->bind(':id', $id);
                $db->bind(':group_class_id', $current_group_class_id);
                $db->execute();

                $members_in_new_groups[ $id ] = [
                    'group_class_id' => $new_group_class_id,
                    'group_super_id' => $this->group_classes_by_id[ $new_group_class_id ]['group_super_id']
                ];
            }

            // insert members that have changed group into their new group

            if(!empty($members_in_new_groups))
            {
                $db->query(" INSERT INTO member_group_class 
                ( id, group_class_id, group_super_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                ( :id, :group_class_id, :group_super_id, '{$now_date_str}', NULL, NOW(), -1 ) ");
                foreach($members_in_new_groups as $id => $grp)
                {
                    $db->bind(':id', $id);
                    $db->bind(':group_class_id', $grp['group_class_id']);
                    $db->bind(':group_super_id', $grp['group_super_id']);
                    $db->execute();
                }
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function insert_new_members($new_members)
    {
        try
        {

            $db = $this->_DB;
            $db->query(" SELECT county_id, country_id FROM org_details WHERE guid = '{$this->guid}' ");
            $db->execute();
            $tmp = $db->fetchSingleAssoc();
            $org_country_id = $tmp['country_id'];
            $org_county_id = $tmp['county_id'];


            // create as user on core DB

            $new_member_ids = [];
            $db->beginTransaction();
            try
            {
                foreach($new_members as $ext_id => $m)
                {
                    $db->query(" INSERT INTO user 
                    ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES 
                    ( 1, NULL, NULL, NULL, NULL, NOW(), -1 ) ");
                    $db->execute();
                    $id = $db->lastInsertId();

                    $db->query(" INSERT INTO user_org 
                    ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                    ( {$id}, '{$this->guid}', 7, {$ext_id}, NULL, 1, NOW(), -1 ) ");
                    $db->execute();

                    $new_member_ids[] = $id;
                    $new_members[ $ext_id ]['id'] = $id;
                    $this->submitted_members[ $ext_id ]['id'] = $id;
                }
                $db->commit();
            }
            catch(Exception $e)
            {
                $db->rollBack();
                throw new Exception($e->getMessage());
            }


            // insert into org DB

            try
            {
                $db = $this->_ORG_DB;
                $db->beginTransaction();
                try
                {

                    $now = new DateTime();
                    $now_date_str = $now->format('Y-m-d');

                    // create as person on org DB

                    $db->query(" INSERT INTO person 
                    ( id, ext_id, fname, lname, indexed_lname, salute_name, salt_id, 
                     landline, mobile, email, landline_alt, mobile_alt, email_alt, 
                     add1, add2, add3, add4, city_town, postcode, eircode, county_id, country_id, show_county, 
                     is_staff, is_guardian, is_member, 
                     active, created, deactivated, updated, updated_by ) VALUES 
                    ( :id, :ext_id, :fname, :lname, :indexed_lname, NULL, 0, 
                     NULL, NULL, NULL, NULL, NULL, NULL, 
                     NULL, NULL, NULL, NULL, NULL, 0, NULL, {$org_county_id}, {$org_country_id}, 0, 
                     0, 0, 1, 
                     1, NOW(), NULL, NOW(), -1 )");
                    foreach($new_members as $ext_id => $m)
                    {
                        $db->bind(':id', $m['id']);
                        $db->bind(':ext_id', $m['ext_id']);
                        $db->bind(':fname', $m['fname']);
                        $db->bind(':lname', $m['lname']);
                        $db->bind(':indexed_lname', $m['indexed_lname']);
                        $db->execute();
                    }

                    // insert into class groups

                    $db->query(" INSERT INTO member_group_class 
                    ( id, group_class_id, group_super_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                    ( :id, :group_class_id, :group_super_id, '{$now_date_str}', NULL, NOW(), -1 ) ");
                    foreach($new_members as $ext_id => $m)
                    {
                        $db->bind(':id', $m['id']);
                        $db->bind(':group_class_id', $m['new_group_class_id']);
                        $db->bind(':group_super_id', $m['new_group_super_id']);
                        $db->execute();
                    }

                    $db->commit();
                }
                catch(Exception $e)
                {
                    $db->rollBack();
                    throw new Exception($e->getMessage());
                }
            }
            catch(Exception $e)
            {
                // if error, remove from core DB

                $new_member_ids_str = implode(',',$new_member_ids);
                $db = $this->_DB;

                $db->query(" DELETE FROM user_org WHERE ( id IN {{$new_member_ids_str}} ) ");
                $db->execute();

                $db->query(" DELETE FROM user WHERE ( id IN {{$new_member_ids_str}} ) ");
                $db->execute();

                throw new Exception($e->getMessage());
            }


            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function check_and_update_siblings()
    {
        $db = $this->_ORG_DB;
        $db->beginTransaction();
        try
        {
            $now = new DateTime();
            $now_date_str = $now->format('Y-m-d');

            foreach($this->submitted_members as $ext_id => $m)
            {
                $m_id = $m['id'];

                // get current sibling IDs

                $db->query(" SELECT sibling_id FROM member_sibling WHERE ( id = {$m_id} AND sibling_end IS NULL ) ");
                $db->execute();
                $current_sibling_ids = $db->fetchAllColumn();

                if( (empty($m['siblings'])) && (empty($current_sibling_ids)) )
                    continue;

                // convert submitted ext_ids to system IDs

                $sibling_ext_ids_str = implode(',',$m['siblings']);
                $db->query(" SELECT id FROM person WHERE ext_id IN ({$sibling_ext_ids_str}) AND is_member = 1 ");
                $db->execute();
                $submitted_sibling_ids = $db->fetchAllColumn();

                // detect changes

                $new_sibling_ids = array_diff($submitted_sibling_ids, $current_sibling_ids);
                $ended_sibling_ids = array_diff($current_sibling_ids, $submitted_sibling_ids);

                if( (empty($new_sibling_ids)) && (empty($ended_sibling_ids)) )
                    continue;

                // end ended siblings

                if(!empty($ended_sibling_ids))
                {
                    $ended_sibling_ids_str = implode(',',$ended_sibling_ids);
                    $db->query(" UPDATE member_sibling SET 
                    sibling_end = '{$now_date_str}', updated = NOW(), updated_by = -1 
                    WHERE ( 
                        id = {$m_id} AND 
                        sibling_id IN ({$ended_sibling_ids_str}) AND 
                        sibling_end IS NULL 
                        ) 
                    ");
                    $db->execute();
                }

                // restart/insert new siblings

                $db->query(" SELECT sibling_id FROM member_sibling WHERE ( id = {$m_id} AND sibling_end = '{$now_date_str}' ) ");
                $db->execute();
                $ended_now_sibling_ids = $db->fetchAllColumn();

                $restarted_sibling_ids = array_intersect($ended_now_sibling_ids, $new_sibling_ids);

                if(!empty($restarted_sibling_ids))
                {
                    $new_sibling_ids = array_diff($new_sibling_ids, $restarted_sibling_ids);

                    $restarted_sibling_ids_str = implode(',',$restarted_sibling_ids);
                    $db->query(" UPDATE member_sibling SET 
                    sibling_end = NULL, updated = NOW(), updated_by = -1 
                    WHERE ( 
                        id = {$m_id} AND 
                        sibling_id IN ({$restarted_sibling_ids_str}) AND 
                        sibling_end = '{$now_date_str}'  
                        ) 
                    ");
                    $db->execute();
                }

                if(!empty($new_sibling_ids))
                {
                    $db->query(" INSERT INTO member_sibling 
                    ( id, sibling_id, sibling_begin, sibling_end, updated, updated_by ) VALUES 
                    ( {$m_id}, :sibling_id, '{$now_date_str}', NULL, NOW(), -1 ) ");
                    foreach($new_sibling_ids as $sib_id)
                    {
                        $db->bind(':sibling_id', $sib_id);
                        $db->execute();
                    }
                }
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function check_and_update_guardians()
    {
        try
        {
            $now = new DateTime();
            $now_date_str = $now->format('Y-m-d');

            $db = $this->_DB;
            $db->query(" SELECT county_id, country_id FROM org_details WHERE guid = '{$this->guid}' ");
            $db->execute();
            $tmp = $db->fetchSingleAssoc();
            $org_country_id = $tmp['country_id'];
            $org_county_id = $tmp['county_id'];



            // normalise data

            $submitted_guardians = [];

            $reg_guardians = (!empty($this->data['guardian'])) ? $this->data['guardian'] : [];
            foreach($reg_guardians as $i => $g)
            {
                if( (empty($g['email'])) && (empty($g['mobile'])) )
                    continue;

                $ext_id = $g['id'];
                $g['ext_id'] = $ext_id;
                $g['id'] = 0;
                $g['active'] = 1;
                $g['guardian_of'] = 0;
                $g['is_default'] = 0;
                $submitted_guardians[$ext_id] = Helper::normalise_person_parameters($g);
            }

            $guardian_default = (!empty($this->data['guardian_default'])) ? $this->data['guardian_default'] : [];
            foreach($guardian_default as $i => $g)
            {
                if( (empty($g['email'])) && (empty($g['mobile'])) )
                    continue;

                $ext_id = Helper::generate_random_string(6);
                $g['ext_id'] = $ext_id;
                $g['id'] = 0;
                $g['active'] = 1;
                $g['guardian_of'] = $g['id'];
                $g['is_default'] = 1;
                $submitted_guardians[$ext_id] = Helper::normalise_person_parameters($g);
            }

            if(empty($submitted_guardians))
            {
                // end all current guardianships for submitted members

                $member_ids = [];
                foreach($this->submitted_members as $ext_id => $m)
                    $member_ids[] = $m['id'];

                $member_ids_str = implode(',',$member_ids);

                $db = $this->_ORG_DB;
                $db->query(" UPDATE member_guardian SET  
                guardian_end = IF( guardian_end IS NULL, '{$now_date_str}', guardian_end ) 
                WHERE ( id IN ({$member_ids_str}) ) ");
                $db->execute();

                return ['success'=>1];
            }

            // get IDs of existing guardians, create new ones

            $db = $this->_DB;
            foreach($submitted_guardians as $ext_id_token => $g)
            {
                $id = 0;

                if( (!empty($g['email'])) && (!empty($g['mobile'])) )
                {
                    $db->query(" SELECT id FROM user WHERE ( email = :email AND mobile = :mobile ) ");
                    $db->bind(':email', $g['email']);
                    $db->bind(':mobile', $g['mobile']);
                    $db->execute();
                    $id = intval($db->fetchSingleColumn());
                }

                if($id)
                {
                    $g['id'] = $id;
                    $submitted_guardians[$ext_id_token] = $g;
                    continue;
                }

                if(!empty($g['mobile']))
                {
                    $db->query(" SELECT id FROM user WHERE ( mobile = :mobile ) ");
                    $db->bind(':mobile', $g['mobile']);
                    $db->execute();
                    $id = intval($db->fetchSingleColumn());
                }

                if($id)
                {
                    $g['id'] = $id;
                    $submitted_guardians[$ext_id_token] = $g;
                    continue;
                }

                if(!empty($g['email']))
                {
                    $db->query(" SELECT id FROM user WHERE ( email = :email ) ");
                    $db->bind(':email', $g['email']);
                    $db->execute();
                    $id = intval($db->fetchSingleColumn());
                }

                if($id)
                {
                    $g['id'] = $id;
                    $submitted_guardians[$ext_id_token] = $g;
                    continue;
                }

                $db->query(" INSERT INTO user 
                ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES 
                ( 1, NULL, :email, :mobile, NULL, NOW(), -1 ) ");
                $db->bind(':email', $g['email']);
                $db->bind(':mobile', $g['mobile']);
                $db->execute();
                $id = $db->lastInsertId();

                $g['id'] = $id;
                $submitted_guardians[$ext_id_token] = $g;
            }

            // ensure guardians are active for org
            // update ext IDs to current update values
            // update email & mobile to current update values

            foreach($submitted_guardians as $ext_id_token => $g)
            {
                $id = $g['id'];

                $db->query(" UPDATE user SET email = :email, mobile = :mobile WHERE ( id = {$id} ) ");
                $db->bind(':email', $g['email']);
                $db->bind(':mobile', $g['mobile']);
                $db->execute();

                $db->query(" SELECT tbl_id FROM user_org WHERE ( id = {$id} AND guid = '{$this->guid}' ) ");
                $db->execute();
                $has_record_for_guid = (intval($db->fetchSingleAssoc()) > 0);

                if($has_record_for_guid)
                {
                    $db->query(" UPDATE user_org SET ext_id = '{$ext_id_token}', active = 1 
                    WHERE ( id = {$id} AND guid = '{$this->guid}' ) ");
                    $db->execute();

                    continue;
                }

                $db->query(" INSERT INTO user_org 
                ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                ( {$id}, '{$this->guid}', 8, '{$ext_id_token}', NULL, 1, NOW(), -1 ) ");
                $db->execute();
            }


            // create new guardians as persons

            $db = $this->_ORG_DB;
            $db->beginTransaction();
            try
            {
                foreach($submitted_guardians as $ext_id_token => $g)
                {
                    $id = $g['id'];
                    $db->query(" SELECT id FROM person WHERE ( id = {$id} ) ");
                    $db->execute();
                    $has_record = (intval($db->fetchSingleColumn()) > 0);

                    if($has_record)
                    {
                        $db->query(" UPDATE person SET 
                        fname = :fname, lname = :lname, indexed_lname = :indexed_lname, 
                        email = :email, mobile = :mobile, updated = NOW(), updated_by = -1  
                        WHERE ( id = {$id} ) ");
                        $db->bind(':fname', $g['fname']);
                        $db->bind(':lname', $g['lname']);
                        $db->bind(':indexed_lname', $g['indexed_lname']);
                        $db->bind(':mobile', $g['mobile']);
                        $db->bind(':email', $g['email']);
                        $db->execute();

                        continue;
                    }

                    $db->query(" INSERT INTO person 
                    ( id, ext_id, fname, lname, indexed_lname, salute_name, salt_id, 
                     landline, mobile, email, landline_alt, mobile_alt, email_alt, 
                     add1, add2, add3, add4, city_town, postcode, eircode, county_id, country_id, show_county, 
                     is_staff, is_guardian, is_member, 
                     active, created, deactivated, updated, updated_by ) VALUES 
                    ( :id, :ext_id, :fname, :lname, :indexed_lname, NULL, 0, 
                     NULL, :mobile, :email, NULL, NULL, NULL, 
                     NULL, NULL, NULL, NULL, NULL, 0, NULL, {$org_county_id}, {$org_country_id}, 0, 
                     0, 1, 0, 
                     1, NOW(), NULL, NOW(), -1 )");
                    $db->bind(':id', $g['id']);
                    $db->bind(':ext_id', $g['ext_id']);
                    $db->bind(':fname', $g['fname']);
                    $db->bind(':lname', $g['lname']);
                    $db->bind(':indexed_lname', $g['indexed_lname']);
                    $db->bind(':mobile', $g['mobile']);
                    $db->bind(':email', $g['email']);
                    $db->execute();
                }

                $db->commit();
            }
            catch(Exception $e)
            {
                $db->rollBack();
                throw new Exception($e->getMessage() . ' @ ' . $e->getTraceAsString());
            }


            $unused_ids = [];
            $db = $this->_ORG_DB;
            $db->beginTransaction();
            try
            {
                // now pair member guardian IDs with submitted guardian IDs

                $member_ids = [];
                $default_guardians = [];
                foreach($this->submitted_members as $ext_id => $m)
                {
                    $m_id = $m['id'];
                    $m_ext_id = $m['ext_id'];
                    $member_ids[] = $m_id;

                    $db->query(" SELECT guardian_id FROM member_guardian WHERE ( id = {$m_id} AND guardian_end IS NULL ) ");
                    $db->execute();
                    $current_guardian_ids = $db->fetchAllColumn();

                    $m_guardian_ext_ids = $m['guardians'];


                    // if no guardians, unmatch current guardians and move on

                    if(empty($m_guardian_ext_ids))
                    {
                        $db->query(" UPDATE member_guardian SET 
                guardian_end = '{$now_date_str}', updated = NOW(), updated_by = -1  
                WHERE ( id = {$m_id} AND guardian_end IS NULL ) ");
                        $db->execute();

                        continue;
                    }


                    // match submitted guardians and get IDs

                    $submitted_guardian_ids = [];
                    foreach($submitted_guardians as $ext_id_token => $g)
                    {
                        if( ($g['guardian_of']) && ($g['guardian_of'] == $m_ext_id))
                        {
                            $default_guardians[ $m_id ] = $g['id'];
                            $submitted_guardian_ids[] = $g['id'];
                            continue;
                        }

                        if(in_array($g['ext_id'], $m_guardian_ext_ids))
                            $submitted_guardian_ids[] = $g['id'];
                    }

                    $ended_guardian_ids = array_diff($current_guardian_ids, $submitted_guardian_ids);
                    $new_guardian_ids = array_diff($submitted_guardian_ids, $current_guardian_ids);

                    if( (empty($ended_guardian_ids)) && (empty($new_guardian_ids)) )
                        continue;

                    // end ended guardians

                    if(!empty($ended_guardian_ids))
                    {
                        $ended_guardian_ids_str = implode(',',$ended_guardian_ids);
                        $db->query(" UPDATE member_guardian SET 
                        guardian_end = '{$now_date_str}', updated = NOW(), updated_by = -1  
                        WHERE ( 
                            id = {$m_id} AND 
                            guardian_id IN ({$ended_guardian_ids_str}) AND 
                            guardian_end IS NULL 
                            ) 
                        ");
                        $db->execute();
                    }

                    // restart/insert new guardians

                    $db->query(" SELECT guardian_id FROM member_guardian WHERE ( id = {$m_id} AND guardian_end = '{$now_date_str}' ) ");
                    $db->execute();
                    $ended_now_guardian_ids = $db->fetchAllColumn();

                    $restarted_guardian_ids = array_intersect($ended_now_guardian_ids, $new_guardian_ids);

                    if(!empty($restarted_guardian_ids))
                    {
                        $new_guardian_ids = array_diff($new_guardian_ids, $restarted_guardian_ids);

                        $restarted_guardian_ids_str = implode(',',$restarted_guardian_ids);
                        $db->query(" UPDATE member_guardian SET 
                        guardian_end = NULL, updated = NOW(), updated_by = -1 
                        WHERE ( 
                            id = {$m_id} AND 
                            guardian_id IN ({$restarted_guardian_ids_str}) AND 
                            guardian_end = '{$now_date_str}'  
                            ) 
                        ");
                        $db->execute();
                    }

                    if(!empty($new_guardian_ids))
                    {
                        $db->query(" INSERT INTO member_guardian 
                        ( id, guardian_id, guardian_begin, guardian_end, updated, updated_by ) VALUES 
                        ( {$m_id}, :guardian_id, '{$now_date_str}', NULL, NOW(), -1 ) ");
                        foreach($new_guardian_ids as $guardian_id)
                        {
                            $db->bind(':guardian_id', $guardian_id);
                            $db->execute();
                        }
                    }

                }

                // update default guardians

                $member_ids_str = implode(',',$member_ids);
                $db->query(" UPDATE member_guardian SET is_default = 0, include_in_email = 0, include_in_letter = 0, include_in_text = 0  
                WHERE ( id IN ({$member_ids_str}) ) ");
                $db->execute();

                if(!empty($default_guardians))
                {
                    $db->query(" UPDATE member_guardian SET is_default = 1, include_in_email = 1, include_in_letter = 1, include_in_text = 1  
                    WHERE ( id = :m_id AND guardian_id = :g_id AND guardian_end IS NULL ) ");
                    foreach($default_guardians as $m_id => $g_id)
                    {
                        $db->bind(':m_id', $m_id);
                        $db->bind(':g_id', $g_id);
                        $db->execute();
                    }
                }


                // clear access for unused guardians

                $db->query(" SELECT DISTINCT guardian_id FROM member_guardian WHERE ( guardian_end IS NULL ) ");
                $db->execute();
                $used_ids = $db->fetchAllColumn();

                $db->query(" SELECT DISTINCT guardian_id FROM member_guardian WHERE ( guardian_end IS NOT NULL ) ");
                $db->execute();
                $ended_ids = $db->fetchAllColumn();

                $unused_ids = array_diff($ended_ids, $used_ids);
                $unused_ids_str = implode(',',$unused_ids);

                $db->query(" DELETE FROM app_screen_user WHERE ( id IN ({$unused_ids_str}) AND role_id = 8 ) ");
                $db->execute();

                $db->commit();
            }
            catch(Exception $e)
            {
                $db->rollBack();
                throw new Exception($e->getMessage() . ' @ ' . $e->getTraceAsString());
            }


            // clear contact info for unused guardians

            if(!empty($unused_ids))
            {
                $unused_ids_str = implode(',',$unused_ids);

                // check guardians not also in other role

                $db = $this->_DB;
                $db->query(" SELECT DISTINCT id FROM user_org WHERE ( id IN ({$unused_ids_str}) AND role_id != 8 ) ");
                $db->execute();
                $used_ids = $db->fetchAllColumn();

                $unused_ids = array_diff($unused_ids, $used_ids);

                if(!empty($unused_ids))
                {
                    $unused_ids_str = implode(',',$unused_ids);

                    $db->query(" UPDATE user_org SET active = 0, updated = NOW(), updated_by = -1 WHERE ( id IN ({$unused_ids_str}) AND guid = '{$this->guid}' ) ");
                    $db->execute();

                    $db->query(" UPDATE user SET email = NULL, mobile = NULL, updated = NOW(), updated_by = -1  
                    WHERE ( id IN ({$unused_ids_str}) ) ");
                    $db->execute();
                }
            }

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}