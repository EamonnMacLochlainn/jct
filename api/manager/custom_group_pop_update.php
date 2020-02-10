<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 20/03/2019
 * Time: 10:39
 */

namespace JCT;


use JCT\Database;
use JCT\Helper;
use Exception;
use DateTime;

class custom_group_pop_update
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

            $now = new \DateTime();
            $this->now = $now;

            $tmp = $this->update();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

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

    private function update()
    {
        try
        {
            $members_to_groups = (isset($this->data['members'])) ? $this->data['members'] : [];
            $staff_to_groups = (isset($this->data['staff'])) ? $this->data['staff'] : [];
            $groups = (isset($this->data['groups'])) ? $this->data['groups'] : [];

            if(!empty($groups))
            {
                $tmp = $this->update_custom_groups($groups);
                if(isset($tmp['error']))
                    throw new Exception('Group error: ' . $tmp['error']);
            }

            if(!empty($members_to_groups))
            {
                $ass_array = [];
                foreach($members_to_groups as $i => $m)
                    $ass_array[ $m['id'] ] = $m['groups'];

                $tmp = $this->update_members_to_custom_groups($ass_array);
                if(isset($tmp['error']))
                    throw new Exception('Member error: ' . $tmp['error']);
            }

            if(!empty($staff_to_groups))
            {
                $ass_array = [];
                foreach($staff_to_groups as $i => $m)
                    $ass_array[ $m['id'] ] = $m['groups'];

                $tmp = $this->update_staff_to_custom_groups($ass_array);
                if(isset($tmp['error']))
                    throw new Exception('Staff error: ' . $tmp['error']);
            }

            $this->success = 1;
            $this->error = 0;
            $this->response = 'OK';
            return true;
        }
        catch(Exception $e)
        {
            $this->success = 0;
            $ex = $e->getMessage();

            $status['Exception'] = $ex;
            $this->response = $ex;
            return ['error'=>$ex];
        }
    }

    private function update_custom_groups($groups)
    {
        $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');
        $db->beginTransaction();
        try
        {
            $submitted_group_ids = [];

            $db->query(" SELECT MAX(weight) FROM {$database}.group_custom ");
            $db->execute();
            $weight = intval($db->fetchSingleColumn());
            $weight++;

            foreach($groups as $g)
            {
                $ext_id = intval($g['id']);
                $title = trim($g['title']);
                $is_active = (!empty($g['active']));
                $active_tok = (!empty($g['active'])) ? 1 : 0;

                $db->query(" SELECT id, title, active FROM {$database}.group_custom WHERE ( ext_id = {$ext_id} ) ");
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                if(!empty($tmp))
                {
                    $id = intval($tmp['id']);

                    $was_title = trim($tmp['title']);
                    $was_active = (intval($tmp['active']) === 1);

                    if( ($was_title == $title) && ($was_active === $is_active) )
                        continue;

                    $db->query(" UPDATE {$database}.group_custom SET 
                    title = '{$title}', active = {$active_tok}, updated = NOW(), updated_by = -1  
                    WHERE ( id = {$id} ) ");
                    $db->execute();

                    if( ($was_active) && (!$is_active) )
                    {
                        $db->query(" UPDATE {$database}.member_group_custom SET 
                        in_group_end = IF(in_group_end IS NULL, NOW(), in_group_end) 
                        WHERE ( group_custom_id = {$id} ) ");
                        $db->execute();
                    }
                }
                else
                {
                    $abbr = (strlen($title) > 7) ? substr($title, 0, 4) . '_' . Helper::generate_random_string(2) : $title;

                    $db->query(" INSERT INTO {$database}.group_custom 
                    ( ext_id, title, title_eng_gae_variant, abbr, active, weight, updated, updated_by ) VALUES 
                    ( {$ext_id}, '{$title}', NULL, '{$abbr}', {$active_tok}, {$weight}, NOW(), -1 )");
                    $db->execute();
                    $id = intval($db->lastInsertId());

                    $weight++;
                }

                $submitted_group_ids[] = $id;
            }


            $db->query(" SELECT id FROM {$database}.group_custom ");
            $db->execute();
            $existing_group_ids = $db->fetchAllColumn();

            $diff = array_diff($submitted_group_ids, $existing_group_ids);

            if(!empty($diff))
            {
                $diff_str = implode(',',$diff);

                $db->query(" UPDATE {$database}.group_custom SET active = 0 WHERE ( id IN ({$diff_str}) ) ");
                $db->execute();

                $db->query(" UPDATE {$database}.member_group_custom SET 
                        in_group_end = IF(in_group_end IS NULL, NOW(), in_group_end) 
                        WHERE ( group_custom_id IN ({$diff_str}) ) ");
                $db->execute();
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

    private function update_members_to_custom_groups($members_to_groups)
    {
        $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');
        $db->beginTransaction();
        try
        {
            $member_ext_ids = array_keys($members_to_groups);
            $member_ext_ids_str = implode(',',$member_ext_ids);

            $now = new DateTime();
            $now_date = $now->format('Y-m-d');

            // get platform IDs for submitted members

            $db->query(" SELECT id, ext_id 
            FROM {$database}.person 
            WHERE ( 
              ext_id IN ({$member_ext_ids_str}) AND 
              is_member = 1  
            ) ");
            $db->execute();
            $member_id_map = $db->fetchAllAssoc('id', true);

            $member_ids = array_keys($member_id_map);
            $member_ids_str = implode(',',$member_ids);

            $matched_ext_ids = array_values($member_id_map);

            // verify that all submitted members exist on platform

            if(count($matched_ext_ids) < count($member_ext_ids))
            {
                $unmatched_ext_ids = array_diff($member_ext_ids, $matched_ext_ids);
                return ['error'=>'Unrecognised external Member IDs detected.', 'ids'=>$unmatched_ext_ids];
            }

            // get platform IDs for groups as map {{id => ext_id}}

            $db->query(" SELECT id, ext_id, active FROM {$database}.group_custom ");
            $db->execute();
            $grps = $db->fetchAllAssoc();

            $group_id_map = [];
            $group_active_map = [];
            foreach($grps as $grp)
            {
                $group_id_map[ $grp['id'] ] = $grp['ext_id'];
                $group_active_map[ $grp['id'] ] = $grp['active'];
            }


            // end the in_group period for all submitted members

            $db->query(" UPDATE {$database}.member_group_custom SET 
            in_group_end = IF(in_group_end IS NULL, '{$now_date}', in_group_end) 
            WHERE ( id IN ({$member_ids_str}) ) ");
            $db->execute();

            // go through and add / update group memberships

            foreach($members_to_groups as $member_ext_id => $submitted_group_ext_ids)
            {
                if(empty($submitted_group_ext_ids))
                    continue;

                $member_id = array_search($member_ext_id, $member_id_map);

                $submitted_group_ext_ids = array_map('intval', $submitted_group_ext_ids);

                $submitted_group_ids = [];
                foreach($submitted_group_ext_ids as $ext_id)
                {
                    $id = array_search($ext_id, $group_id_map);
                    if($id === false)
                        throw new Exception('Unrecognised Custom Group ID.');

                    // if group itself is inactive, don't add new members
                    $active = $group_active_map[$id];
                    if(intval($active) === 0)
                        continue;

                    $submitted_group_ids[] = array_search($ext_id, $group_id_map);
                }
                $submitted_group_ids_str = implode(',',$submitted_group_ids);

                $sql = " SELECT group_custom_id  
                FROM {$database}.member_group_custom 
                WHERE (
                  id = {$member_id} AND
                  group_custom_id IN ({$submitted_group_ids_str}) AND 
                  in_group_end = '{$now_date}' 
                ) ";
                $db->query($sql);
                $db->execute();
                $still_in_group_ids = $db->fetchAllColumn();
                $still_in_group_ids = (empty($still_in_group_ids)) ? [] : $still_in_group_ids;

                if(!empty($still_in_group_ids))
                {
                    $still_in_group_ids_str = implode(',',$still_in_group_ids);
                    $sql = " UPDATE {$database}.member_group_custom SET 
                    in_group_end = NULL 
                    WHERE (
                      id = {$member_id} AND 
                      group_custom_id IN ({$still_in_group_ids_str}) AND 
                      in_group_end = '{$now_date}' 
                    ) ";
                    $db->query($sql);
                    $db->execute();
                }

                $new_group_ids = array_diff($submitted_group_ids, $still_in_group_ids);
                if(!empty($new_group_ids))
                {
                    $db->query(" INSERT INTO {$database}.member_group_custom 
                    ( id, group_custom_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                    ( {$member_id}, :group_custom_id, '{$now_date}', NULL, NOW(), -1 ) ");
                    foreach($new_group_ids as $group_id)
                    {
                        $db->bind(':group_custom_id', $group_id);
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

    private function update_staff_to_custom_groups($staff_to_groups)
    {
        $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');
        $db->beginTransaction();
        try
        {
            $staff_ext_ids = array_keys($staff_to_groups);
            $staff_ext_ids_str = implode(',',$staff_ext_ids);

            $now = new DateTime();
            $now_date = $now->format('Y-m-d');

            // get platform IDs for submitted members

            $db->query(" SELECT id, ext_id 
            FROM {$database}.person 
            WHERE ( 
              ext_id IN ({$staff_ext_ids_str}) AND 
              is_staff = 1  
            ) ");
            $db->execute();
            $staff_id_map = $db->fetchAllAssoc('id', true);

            $staff_ids = array_keys($staff_id_map);
            $staff_ids_str = implode(',',$staff_ids);

            $matched_ext_ids = array_values($staff_id_map);

            // verify that all submitted members exist on platform

            if(count($matched_ext_ids) < count($staff_ext_ids))
            {
                $unmatched_ext_ids = array_diff($staff_ext_ids, $matched_ext_ids);
                $unmatched_ext_ids_str = implode(',',$unmatched_ext_ids);
                return ['error'=>'Unrecognised external Staff IDs detected: ' . $unmatched_ext_ids_str];
            }

            // get platform IDs for groups as map {{id => ext_id}}

            $db->query(" SELECT id, ext_id FROM {$database}.group_custom ");
            $db->execute();
            $group_id_map = $db->fetchAllAssoc('id', true);


            // end the in_group period for all submitted members

            $db->query(" UPDATE {$database}.group_custom_leader SET 
            leader_end = IF(leader_end IS NULL, '{$now_date}', leader_end) 
            WHERE ( id IN ({$staff_ids_str}) ) ");
            $db->execute();

            // go through and add / update group memberships

            foreach($staff_to_groups as $staff_ext_id => $submitted_group_ext_ids)
            {
                if(empty($submitted_group_ext_ids))
                    continue;

                $staff_id = array_search($staff_ext_id, $staff_id_map);

                $submitted_group_ext_ids = array_map('intval', $submitted_group_ext_ids);

                $submitted_group_ids = [];
                foreach($submitted_group_ext_ids as $ext_id)
                    $submitted_group_ids[] = array_search($ext_id, $group_id_map);

                $submitted_group_ids_str = implode(',',$submitted_group_ids);

                $db->query(" SELECT group_custom_id  
                FROM {$database}.group_custom_leader  
                WHERE (
                  id = {$staff_id} AND
                  group_custom_id IN ({$submitted_group_ids_str}) AND 
                  leader_end = '{$now_date}' 
                ) ");
                $db->execute();
                $still_in_group_ids = $db->fetchAllColumn();
                $still_in_group_ids = (empty($still_in_group_ids)) ? [] : $still_in_group_ids;

                if(!empty($still_in_group_ids))
                {
                    $still_in_group_ids_str = implode(',',$still_in_group_ids);
                    $db->query(" UPDATE {$database}.group_custom_leader SET 
                    leader_end = NULL 
                    WHERE (
                      id = {$staff_id} AND 
                      group_custom_id IN ({$still_in_group_ids_str}) AND 
                      leader_end = '{$now_date}' 
                    ) ");
                    $db->execute();
                }

                $new_group_ids = array_diff($submitted_group_ids, $still_in_group_ids);
                if(!empty($new_group_ids))
                {
                    $db->query(" INSERT INTO {$database}.group_custom_leader 
                    ( id, group_custom_id, leader_begin, leader_end, updated, updated_by ) VALUES 
                    ( {$staff_id}, :group_custom_id, '{$now_date}', NULL, NOW(), -1 ) ");
                    foreach($new_group_ids as $group_id)
                    {
                        $db->bind(':group_custom_id', $group_id);
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
}