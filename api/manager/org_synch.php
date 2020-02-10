<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 01/03/2018
 * Time: 13:36
 */

namespace JCT;


use JCT\Helper;
use Exception;
use JCT\Database;

class org_synch implements api_interface
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

    private $roles_and_ranks = [
        'administrator' => [
            'role_id' => 2,
            'rank' => 11,
            'abbr' => 'adm',
            'is_staff' => 1,
            'is_guardian' => 0,
            'is_member' => 0
        ],
        'group_leader' => [
            'role_id' => 3,
            'rank' => 21,
            'abbr' => 'gl',
            'is_staff' => 1,
            'is_guardian' => 0,
            'is_member' => 0
        ],
        'group_assistant' => [
            'role_id' => 4,
            'rank' => 25,
            'abbr' => 'ga',
            'is_staff' => 1,
            'is_guardian' => 0,
            'is_member' => 0
        ],
        'member_assistant' => [
            'role_id' => 5,
            'rank' => 26,
            'abbr' => 'ma',
            'is_staff' => 1,
            'is_guardian' => 0,
            'is_member' => 0
        ],
        'general_staff' => [
            'role_id' => 6,
            'rank' => 27,
            'abbr' => 'gs',
            'is_staff' => 1,
            'is_guardian' => 0,
            'is_member' => 0
        ],
        'guardian' => [
            'role_id' => 8,
            'rank' => 31,
            'abbr' => 'gua',
            'is_staff' => 0,
            'is_guardian' => 1,
            'is_member' => 0
        ],
        'member' => [
            'role_id' => 7,
            'rank' => 41,
            'abbr' => 'mem',
            'is_staff' => 0,
            'is_guardian' => 0,
            'is_member' => 1
        ]
    ];
    private $role_ids = [
        2 => 'administrator',
        3 => 'group_leader',
        4 => 'group_assistant',
        5 => 'member_assistant',
        6 => 'general_staff',
        7 => 'member',
        8 => 'guardian'
    ];
    private $salutations = [];

    private $persons = [];
    private $groups_super = [];
    private $groups_class = [];
    private $groups_custom = [];
    private $groups_staff = [];

    private $group_super_map = [];
    private $group_class_map = [];
    private $group_custom_map = [];
    private $group_staff_map = [];

    private $id_token_map = []; // this id => [by role] => this token
    private $group_class_leader_map = []; // leader_ext_id => class ext IDS
    private $group_assistant_map = []; // assistant_ext_id => member tokens
    private $member_assistant_map = []; // assistant_ext_id => member tokens

    private $mobile_persons = [];
    private $email_persons = [];
    private $merged_persons = [];

    private $job_share_persons = [];
    private $job_share_map = []; // this ext_id => [these tokens]


    private $default_guardian_map = []; // member ID => default guardian token

    private $tokens_by_type = []; // type => [tokens]


    function __construct(Database $_DB, Database $_ORG_DB, $guid, $input)
    {
        try {
            $this->_DB = $_DB;
            $this->_ORG_DB = $_ORG_DB;
            $this->guid = $guid;
            $this->input = $input;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    function execute()
    {
        try {
            array_change_key_case($this->input, CASE_LOWER);
            if (empty($this->input['data']))
                throw new Exception('No data detected.');

            $data = json_decode($this->input['data'], true, 512, JSON_UNESCAPED_UNICODE);

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

            if (!empty($error))
                throw new Exception($error);

            $data = array_change_key_case($data, CASE_LOWER);
            $this->data = $data;

            $now = new \DateTime();
            $this->now = $now;

            $tmp = $this->synch();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $this->record_synch();

            #$this->cleanup();


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

    private function synch()
    {
        $status = [];
        $status['setting salutations'] = 'start';
        $this->set_salutations();
        $status['setting salutations'] = 'ok';


        $now_str_date = $this->now->format('Y-m-d');
        $rec = null;
        try
        {
            // do parsing

            $status['parsing'] = 'start';

            try
            {
                $status['parsing persons'] = 'start';
                $tmp = $this->parse_persons();
                if ($tmp === false)
                    throw new Exception('No Persons set from data.');
                $status['parsing persons'] = 'ok';

                $status['parsing group_super'] = 'start';
                $tmp = $this->parse_groups_super();
                if (isset($tmp['error']))
                    throw new Exception($tmp['error']);
                $status['parsing group_super'] = 'ok';

                $status['parsing group_class'] = 'start';
                $tmp = $this->parse_groups_class();
                if (isset($tmp['error']))
                    throw new Exception($tmp['error']);
                $status['parsing group_class'] = 'ok';

                $status['parsing group_custom'] = 'start';
                $tmp = $this->parse_groups_custom();
                if (isset($tmp['error']))
                    throw new Exception($tmp['error']);
                $status['parsing group_custom'] = 'ok';

                $status['parsing group_staff'] = 'start';
                $tmp = $this->parse_groups_staff();
                if (isset($tmp['error']))
                    throw new Exception($tmp['error']);
                $status['parsing group_staff'] = 'ok';



                // match job-share ID to actual group_leader ids

                if (!empty($this->job_share_persons))
                {
                    foreach ($this->job_share_persons as $fake_id => $p)
                    {
                        $id_split = explode('-', $p['fname']);
                        $mapped_classes = (!empty($this->group_class_leader_map[$fake_id])) ? $this->group_class_leader_map[$fake_id] : [];

                        foreach ($id_split as $ext_id)
                        {
                            if(!isset($this->group_class_leader_map[$ext_id]))
                                $this->group_class_leader_map[$ext_id] = [];

                            foreach($mapped_classes as $grp_ext_id)
                                $this->group_class_leader_map[$ext_id][] = $grp_ext_id;
                        }

                        unset($this->group_class_leader_map[$fake_id]);
                    }
                }
                $this->job_share_persons = null;


            }
            catch (Exception $e)
            {
                throw new Exception($e->getMessage());
            }
            $status['parsing'] = 'ok';


            // do saving

            $status['saving'] = 'start';

            $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');

            $tmp = ['user','user_org'];
            $core_tables_ai = [];
            $db->query(" SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = :tbl_name ");
            foreach($tmp as $tbl)
            {
                $db->bind(':tbl_name', $tbl);
                $core_tables_ai[$tbl] = $db->fetchSingleColumn();
            }

            $tmp = ['group_super','group_class','group_custom','group_staff'];
            $org_db_tables_ai = [];
            $db->query(" SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = :tbl_name ");
            foreach($tmp as $tbl)
            {
                $db->bind(':tbl_name', $tbl);
                $org_db_tables_ai[$tbl] = $db->fetchSingleColumn();
            }



            $db->beginTransaction();
            try
            {
                $database = JCT_PREFIX . '_core';

                $status['get all users associated with Org'] = 'start';
                $db->query(" SELECT id, ext_id, role_id   
                FROM {$database}.user_org
                WHERE guid = :guid ");
                $db->bind(':guid', $this->guid);
                $db->execute();
                $org_users = $db->fetchAllAssoc();
                $status['get all users associated with Org'] = 'ok';


                $status['get user IDs and roles'] = 'start';
                $org_all_user_ids = [];
                $org_all_user_ext_id_map = [];
                foreach ($this->role_ids as $role_id => $role_title)
                    $org_all_user_ext_id_map[$role_id] = [];

                foreach ($org_users as $i => $u) {
                    $id = $u['id'];
                    $org_all_user_ids[] = $id;
                    $org_all_user_ext_id_map[$u['role_id']][$u['ext_id']] = $id;
                }

                $org_all_user_ids_str = (!empty($org_all_user_ids)) ? implode(',', $org_all_user_ids) : '';
                $status['get user IDs and roles'] = 'ok';


                $status['split multi-org users from single org users'] = 'start';
                $multi_org_user_ids = $org_only_user_ids = [];
                $multi_org_user_ids_str = $org_only_user_ids_str = '';
                if (!empty($org_users))
                {
                    $db->query(" SELECT id FROM {$database}.user_org WHERE 
                    id IN ({$org_all_user_ids_str}) AND guid != :guid ");
                    $db->bind(':guid', $this->guid);
                    $db->execute();
                    $multi_org_user_ids = $db->fetchAllColumn();
                    $multi_org_user_ids_str = (!empty($multi_org_user_ids)) ? implode(',', $multi_org_user_ids) : '';

                    $org_only_user_ids = (!empty($multi_org_user_ids)) ? array_diff($org_all_user_ids, $multi_org_user_ids) : $org_all_user_ids;
                    $org_only_user_ids_str = implode(',', $org_only_user_ids);
                }
                $status['split multi-org users from single org users'] = 'ok';


                $status['de-activate all associated User accounts'] = 'start';

                $db->query(" UPDATE {$database}.user_org SET active = 0, updated = NOW(), updated_by = -1 WHERE ( active = 1 AND guid = :guid ) ");
                $db->bind(':guid', $this->guid);
                $db->execute();

                if (!empty($org_only_user_ids))
                {
                    $db->query(" UPDATE {$database}.user SET 
                    active = 0, updated = NOW(), updated_by = -1, session_id = NULL 
                    WHERE ( id IN ({$org_only_user_ids_str}) AND active = 1 ) ");
                    $db->execute();
                }
                $status['de-activate all associated User accounts'] = 'ok';

                $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

                $status['de-activate all Person accounts in Org'] = 'start';
                $db->query(" UPDATE {$database}.person SET 
                active = 0, deactivated = NOW(), updated = NOW(), updated_by = -1 WHERE 1 ");
                $db->execute();
                $status['de-activate all Person accounts in Org'] = 'ok';



                $status['end all group_leader => group periods'] = 'start';
                $db->query(" UPDATE {$database}.group_class_leader SET 
                leader_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all group_leader => group periods'] = 'ok';

                $status['end all group_assistant => member periods'] = 'start';
                $db->query(" UPDATE {$database}.member_group_assistant SET 
                assistant_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all group_assistant => member periods'] = 'ok';

                $status['end all member_assistant => member periods'] = 'start';
                $db->query(" UPDATE {$database}.member_member_assistant SET 
                assistant_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all member_assistant => member periods'] = 'ok';



                $status['end all staff => staff_group_periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_staff_staff SET in_group_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all staff => staff_group_periods'] = 'ok';

                $status['end all staff => custom_group periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_custom_leader SET leader_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all staff => custom_group periods'] = 'ok';

                $status['end all member => group periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.member_group_class SET in_group_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all member => group periods'] = 'ok';

                $status['end all member => guardian periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.member_guardian SET guardian_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all member => guardian periods'] = 'ok';

                $status['end all member => sibling periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.member_sibling SET sibling_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all member => sibling periods'] = 'ok';

                $status['end all member => custom_group periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.member_group_custom SET in_group_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all member => custom_group periods'] = 'ok';

                $status['end all group_super active periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_super SET active_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all group_super active periods'] = 'ok';

                $status['end all group_class active periods'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_class SET active_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['end all group_class active periods'] = 'ok';

                $status['deactivate all group_custom groups'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_custom SET active = 0 WHERE 1 ");
                $db->execute();
                $status['deactivate all group_custom groups'] = 'ok';

                $status['deactivate all group_staff groups'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.group_staff SET active = 0 WHERE 1 ");
                $db->execute();
                $status['deactivate all group_staff groups'] = 'ok';

                $status['deactivate all staff_role assignments'] = 'start';
                $db->query(" UPDATE IGNORE {$database}.staff_role SET role_end = '{$now_str_date}' WHERE 1 ");
                $db->execute();
                $status['deactivate all staff_role assignments'] = 'ok';


                // super groups

                $status['re-activate matched (ext_id) group_super groups'] = 'start';
                $super_group_ext_ids = array_keys($this->groups_super);

                $db->query(" SELECT ext_id, active_end FROM {$database}.group_super WHERE 1 ");
                $db->execute();
                $stored_super_groups = $db->fetchAllAssoc('ext_id', true);

                $done = [];
                if (count($stored_super_groups) > 0)
                {
                    $reactivate_ext_ids = [];
                    $new_period_ext_ids = [];

                    foreach ($stored_super_groups as $ext_id => $date) {
                        if (in_array($ext_id, $super_group_ext_ids)) {
                            if ($date === $now_str_date)
                                $reactivate_ext_ids[] = $ext_id;
                            else
                                $new_period_ext_ids[] = $ext_id;
                        }
                    }

                    if (!empty($reactivate_ext_ids))
                    {
                        $db->query(" UPDATE {$database}.group_super 
                        SET active_end = NULL, title = :title, abbr = :abbr, 
                        academic_band = :band, academic_order = :ext_id, 
                        updated = NOW(), updated_by = -1    
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($reactivate_ext_ids as $re_ext_id)
                        {
                            $band = ($re_ext_id % 2) ? (($re_ext_id + 1) / 2) : ($re_ext_id / 2);
                            $db->bind(':ext_id', $re_ext_id);
                            $db->bind(':title', $this->groups_super[$re_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_super[$re_ext_id]['abbr']);
                            $db->bind(':band', $band);
                            $db->execute();

                            $done[] = $re_ext_id;
                        }
                    }

                    if (!empty($new_period_ext_ids))
                    {
                        $db->query(" UPDATE {$database}.group_super 
                        SET active_begin = NOW(), active_end = NULL, title = :title, abbr = :abbr, 
                        academic_band = :band, academic_order = :ext_id, 
                        updated = NOW(), updated_by = -1      
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($new_period_ext_ids as $np_ext_id)
                        {
                            $band = ($np_ext_id % 2) ? (($np_ext_id + 1) / 2) : ($np_ext_id / 2);
                            $db->bind(':ext_id', $np_ext_id);
                            $db->bind(':title', $this->groups_super[$np_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_super[$np_ext_id]['abbr']);
                            $db->bind(':band', $band);
                            $db->execute();

                            $done[] = $np_ext_id;
                        }
                    }

                    $db->query(" SELECT id FROM {$database}.group_super WHERE active_end IS NULL ");
                    $db->execute();
                    $deactivated = $db->fetchAllColumn();

                    if (!empty($deactivated))
                    {
                        $deactivated_str = implode(',', $deactivated);
                        $abbr = $this->now->format('siH');
                        $db->query(" UPDATE {$database}.group_super SET 
                        title = CONCAT(title,{$now_str_date}), abbr = CONCAT(abbr, {$abbr}) 
                        WHERE id IN({$deactivated_str}) ");
                        $db->execute();
                    }
                }

                $diff = array_diff(array_keys($this->groups_super), $done);
                if (!empty($diff))
                {
                    $db->query(" INSERT INTO {$database}.group_super 
                    ( ext_id, title, title_eng_gae_variant, abbr, 
                      active_begin, active_end, current, academic_band, academic_order, 
                      weight, updated, updated_by ) VALUES 
                    ( :ext_id, :title, NULL, :abbr, 
                      NOW(), NULL, :current, :band, :ext_id, 
                      :ext_id, NOW(), -1 ) ");
                    foreach ($diff as $ext_id)
                    {
                        $band = ($ext_id % 2) ? (($ext_id + 1) / 2) : ($ext_id / 2);
                        $db->bind(':ext_id', $ext_id);
                        $db->bind(':title', $this->groups_super[$ext_id]['title']);
                        $db->bind(':abbr', $this->groups_super[$ext_id]['abbr']);
                        $db->bind(':current', $this->groups_super[$ext_id]['current']);
                        $db->bind(':band', $band);
                        $db->execute();
                    }
                }
                $status['re-activate matched (ext_id) group_super groups'] = 'ok';

                $status['map group_super groups'] = 'start';
                $db->query(" SELECT id, ext_id FROM {$database}.group_super WHERE active_end IS NULL ");
                $db->execute();
                $this->group_super_map = $db->fetchAllAssoc('ext_id', true);
                if (empty($this->group_super_map))
                    throw new Exception('No active Super Groups remain.');
                $status['map group_super groups'] = 'ok';

                unset($super_group_ext_ids);
                unset($stored_super_groups);
                unset($reactivate_ext_ids);
                unset($new_period_ext_ids);
                unset($deactivated);
                unset($done);
                unset($diff);


                // class groups

                $status['re-activate matched (ext_id) group_class groups'] = 'start';
                $class_group_ext_ids = array_keys($this->groups_class);

                $db->query(" SELECT ext_id, active_end FROM {$database}.group_class WHERE 1 ");
                $db->execute();
                $stored_class_groups = $db->fetchAllAssoc('ext_id', true);

                $done = [];
                if (count($stored_class_groups) > 0)
                {
                    $reactivate_ext_ids = [];
                    $new_period_ext_ids = [];

                    foreach ($stored_class_groups as $ext_id => $date) {
                        if (in_array($ext_id, $class_group_ext_ids)) {
                            if ($date === $now_str_date)
                                $reactivate_ext_ids[] = $ext_id;
                            else
                                $new_period_ext_ids[] = $ext_id;
                        }
                    }

                    if (!empty($reactivate_ext_ids)) {
                        $db->query(" UPDATE {$database}.group_class 
                        SET active_end = NULL, title = :title, abbr = :abbr, 
                        group_super_id = :group_super_id, gender = :gender, type_id = :type, 
                        updated = NOW(), updated_by = -1       
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($reactivate_ext_ids as $re_ext_id) {
                            $db->bind(':ext_id', $re_ext_id);
                            $db->bind(':title', $this->groups_class[$re_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_class[$re_ext_id]['abbr']);
                            $db->bind(':group_super_id', $this->group_super_map[$this->groups_class[$re_ext_id]['group_super_ext_id']]);
                            $db->bind(':gender', $this->groups_class[$re_ext_id]['gender']);
                            $db->bind(':type', $this->groups_class[$re_ext_id]['type_id']);
                            $db->execute();

                            $done[] = $re_ext_id;
                        }
                    }

                    if (!empty($new_period_ext_ids))
                    {
                        $db->query(" UPDATE {$database}.group_class  
                        SET active_begin = NOW(), active_end = NULL, title = :title, abbr = :abbr, 
                        group_super_id = :group_super_id, gender = :gender, type_id = :type, 
                        updated = NOW(), updated_by = -1       
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($new_period_ext_ids as $np_ext_id) {
                            $db->bind(':ext_id', $np_ext_id);
                            $db->bind(':title', $this->groups_class[$np_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_class[$np_ext_id]['abbr']);
                            $db->bind(':group_super_id', $this->group_super_map[$this->groups_class[$np_ext_id]['group_super_ext_id']]);
                            $db->bind(':gender', $this->groups_class[$np_ext_id]['gender']);
                            $db->bind(':type', $this->groups_class[$np_ext_id]['type_id']);
                            $db->execute();

                            $done[] = $np_ext_id;
                        }
                    }

                    $db->query(" SELECT id FROM {$database}.group_class WHERE active_end IS NULL ");
                    $db->execute();
                    $deactivated = $db->fetchAllColumn();

                    if (!empty($deactivated)) {
                        $deactivated_str = implode(',', $deactivated);
                        $abbr = $this->now->format('siH');
                        $db->query(" UPDATE {$database}.group_class SET 
                        title = CONCAT(title,{$now_str_date}), abbr = CONCAT(abbr, {$abbr}) 
                        WHERE id IN({$deactivated_str}) ");
                        $db->execute();
                    }
                }

                $diff = array_diff(array_keys($this->groups_class), $done);
                if (!empty($diff)) {
                    $db->query(" INSERT INTO {$database}.group_class 
                    ( ext_id, title, title_eng_gae_variant, abbr, 
                    group_super_id, gender, type_id, 
                    active_begin, active_end, 
                    weight, updated, updated_by ) VALUES 
                    ( :ext_id, :title, NULL, :abbr, 
                    :group_super_id, :gender, :type_id, 
                    NOW(), NULL, 
                    0, NOW(), -1 ) ");
                    foreach ($diff as $ext_id) {
                        $group_super_ext_id = $this->groups_class[$ext_id]['group_super_ext_id'];
                        $group_super_id = $this->group_super_map[$group_super_ext_id];

                        $db->bind(':ext_id', $ext_id);
                        $db->bind(':title', $this->groups_class[$ext_id]['title']);
                        $db->bind(':abbr', $this->groups_class[$ext_id]['abbr']);
                        $db->bind(':group_super_id', $group_super_id);
                        $db->bind(':gender', $this->groups_class[$ext_id]['gender']);
                        $db->bind(':type_id', $this->groups_class[$ext_id]['type_id']);
                        $db->execute();
                    }
                }
                $status['re-activate matched (ext_id) group_class groups'] = 'ok';

                $status['map group_class groups'] = 'start';
                $db->query(" SELECT id, ext_id FROM {$database}.group_class WHERE active_end IS NULL ");
                $db->execute();
                $this->group_class_map = $db->fetchAllAssoc('ext_id', true);
                if (empty($this->group_class_map))
                    throw new Exception('No active Class Groups remain.');

                foreach ($this->groups_class as $ext_id => $c) {
                    $group_super_ext_id = $this->groups_class[$ext_id]['group_super_ext_id'];
                    $this->groups_class[$ext_id]['group_super_id'] = $this->group_super_map[$group_super_ext_id];
                }

                $status['map group_class groups'] = 'ok';

                unset($class_group_ext_ids);
                unset($stored_class_groups);
                unset($reactivate_ext_ids);
                unset($new_period_ext_ids);
                unset($deactivated);
                unset($done);
                unset($diff);


                // custom groups

                $status['re-activate matched (ext_id) group_custom groups'] = 'start';
                $custom_group_ext_ids = array_keys($this->groups_custom);

                $db->query(" SELECT ext_id FROM {$database}.group_custom WHERE 1 ");
                $db->execute();
                $stored_custom_groups = $db->fetchAllColumn();

                $done = [];
                if (count($stored_custom_groups) > 0) {
                    $reactivate_ext_ids = [];

                    foreach ($stored_custom_groups as $ext_id) {
                        if (in_array($ext_id, $custom_group_ext_ids))
                            $reactivate_ext_ids[] = $ext_id;
                    }

                    if (!empty($reactivate_ext_ids))
                    {
                        $db->query(" UPDATE {$database}.group_custom 
                        SET `active` = 1, title = :title, abbr = :abbr, 
                        updated = NOW(), updated_by = -1       
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($reactivate_ext_ids as $re_ext_id) {
                            $db->bind(':ext_id', $re_ext_id);
                            $db->bind(':title', $this->groups_custom[$re_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_custom[$re_ext_id]['abbr']);
                            $db->execute();

                            $done[] = $re_ext_id;
                        }
                    }

                    $db->query(" SELECT id FROM {$database}.group_custom WHERE `active` = 0 ");
                    $db->execute();
                    $deactivated = $db->fetchAllColumn();

                    if (!empty($deactivated)) {
                        $deactivated_str = implode(',', $deactivated);
                        $abbr = $this->now->format('siH');
                        $db->query(" UPDATE {$database}.group_custom SET 
                        title = CONCAT(title,{$now_str_date}), abbr = CONCAT(abbr, {$abbr}) 
                        WHERE id IN({$deactivated_str}) ");
                        $db->execute();
                    }
                }

                $diff = array_diff(array_keys($this->groups_custom), $done);
                if(!empty($diff))
                {
                    $db->query(" INSERT INTO {$database}.group_custom 
                    ( ext_id, title, title_eng_gae_variant, abbr, `active`, weight, updated, updated_by ) VALUES 
                    ( :ext_id, :title, NULL, :abbr, 1, 0, NOW(), -1 ) ");
                    foreach ($diff as $ext_id)
                    {
                        $title = $this->groups_custom[$ext_id]['title'];
                        if(strlen($title) > 50)
                            $title = substr($title, 0, 35);

                        $db->bind(':ext_id', $ext_id);
                        $db->bind(':title', $title);
                        $db->bind(':abbr', $this->groups_custom[$ext_id]['abbr']);
                        $db->execute();
                    }
                }
                $status['re-activate matched (ext_id) group_custom groups'] = 'ok';

                $status['map group_custom groups'] = 'start';
                $db->query(" SELECT id, ext_id FROM {$database}.group_custom WHERE `active` = 1 ");
                $db->execute();
                $this->group_custom_map = $db->fetchAllAssoc('ext_id', true);
                $status['map group_custom groups'] = 'ok';

                unset($custom_group_ext_ids);
                unset($stored_custom_groups);
                unset($reactivate_ext_ids);
                unset($deactivated);
                unset($done);
                unset($diff);


                // staff groups

                $status['re-activate matched (ext_id) group_staff groups'] = 'start';
                $staff_group_ext_ids = array_keys($this->groups_staff);

                $db->query(" SELECT ext_id FROM {$database}.group_staff WHERE 1 ");
                $db->execute();
                $stored_staff_groups = $db->fetchAllColumn();

                $done = [];
                if (count($stored_staff_groups) > 0) {
                    $reactivate_ext_ids = [];

                    foreach ($stored_staff_groups as $ext_id) {
                        if (in_array($ext_id, $staff_group_ext_ids))
                            $reactivate_ext_ids[] = $ext_id;
                    }

                    if (!empty($reactivate_ext_ids)) {
                        $db->query(" UPDATE {$database}.group_staff 
                        SET active = 1, title = :title, abbr = :abbr, 
                        updated = NOW(), updated_by = -1       
                        WHERE ( ext_id = :ext_id ) ");
                        foreach ($reactivate_ext_ids as $re_ext_id) {
                            $db->bind(':ext_id', $re_ext_id);
                            $db->bind(':title', $this->groups_staff[$re_ext_id]['title']);
                            $db->bind(':abbr', $this->groups_staff[$re_ext_id]['abbr']);
                            $db->execute();

                            $done[] = $re_ext_id;
                        }
                    }

                    $db->query(" SELECT id FROM {$database}.group_staff WHERE active = 0 ");
                    $db->execute();
                    $deactivated = $db->fetchAllColumn();

                    if (!empty($deactivated)) {
                        $deactivated_str = implode(',', $deactivated);
                        $abbr = $this->now->format('siH');
                        $db->query(" UPDATE {$database}.group_staff SET 
                        title = CONCAT(title,{$now_str_date}), abbr = CONCAT(abbr, {$abbr}) 
                        WHERE id IN({$deactivated_str}) ");
                        $db->execute();
                    }
                }

                $diff = array_diff(array_keys($this->groups_staff), $done);
                if (!empty($diff)) {
                    $db->query(" INSERT INTO {$database}.group_staff 
                    ( ext_id, title, title_eng_gae_variant, abbr, active, weight, updated, updated_by ) VALUES 
                    ( :ext_id, :title, NULL, :abbr, 1, 0, NOW(), -1 ) ");
                    foreach ($diff as $ext_id) {
                        $db->bind(':ext_id', $ext_id);
                        $db->bind(':title', $this->groups_staff[$ext_id]['title']);
                        $db->bind(':abbr', $this->groups_staff[$ext_id]['abbr']);
                        $db->execute();
                    }
                }
                $status['re-activate matched (ext_id) group_staff groups'] = 'ok';

                $status['map group_staff groups'] = 'start';
                $db->query(" SELECT id, ext_id FROM {$database}.group_staff WHERE active = 1 ");
                $db->execute();
                $this->group_staff_map = $db->fetchAllAssoc('ext_id', true);
                $status['map group_staff groups'] = 'ok';

                unset($staff_group_ext_ids);
                unset($stored_staff_groups);
                unset($reactivate_ext_ids);
                unset($deactivated);
                unset($done);
                unset($diff);


                ################## could break transaction here ##########



                // loop through persons to try and match with existing ext_ids (within role) and/or mobiles and emails

                $status['get system IDs for persons'] = 'start';
                foreach ($this->persons as $token => $p)
                {
                    // Try match to ext_id (within role) first

                    if ($p['is_guardian_default'] == 0) // All but default guardians should have their own ext_id
                    {
                        $role_ids = $p['role_ids'];

                        $database = JCT_PREFIX . '_core';
                        $db->query(" SELECT id 
                        FROM {$database}.user_org 
                        WHERE ( guid = :guid AND role_id = :role_id AND ext_id = :ext_id ) ");
                        foreach ($role_ids as $role_id => $ext_id) {
                            $db->bind(':guid', $this->guid);
                            $db->bind(':role_id', $role_id);
                            $db->bind(':ext_id', $ext_id);
                            $db->execute();
                            $id = intval($db->fetchSingleColumn());

                            if ($id > 0)
                            {
                                $this->persons[$token]['id'] = $id;
                                continue 2;
                            }
                        }
                    }

                    // Now try to match by mobile or email

                    $db->query(" SELECT id FROM {$database}.user 
                    WHERE ( ( mobile IS NOT NULL AND mobile = :mobile ) OR ( email IS NOT NULL AND email = :email ) ) ");
                    $db->bind(':mobile', $p['mobile']);
                    $db->bind(':email', $p['email']);
                    $db->execute();
                    $id = intval($db->fetchSingleColumn());

                    if ($id > 0)
                    {
                        $this->persons[$token]['id'] = $id;
                        continue;
                    }
                }
                $status['get system IDs for persons'] = 'ok';




                // get default county / country ids

                $db->query(" SELECT county_id, country_id FROM {$database}.org_details WHERE ( guid = '{$this->guid}' ) ");
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $default_country_id = (!empty($tmp['country_id'])) ? $tmp['country_id'] : 372; // ireland
                $default_county_id = (!empty($tmp['county_id'])) ? $tmp['county_id'] : 2; //dublin



                $status['insert persons'] = 'start';
                $order = ['guardian', 'administrator', 'group_leader', 'group_assistant', 'member_assistant', 'general_staff', 'member'];
                foreach ($order as $role_title)
                {
                    if (!isset($this->id_token_map[$role_title]))
                        continue;

                    $role_id = array_search($role_title, $this->role_ids);
                    foreach ($this->id_token_map[$role_title] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];

                        $id = intval($p['id']);
                        $ext_id = $p['role_ids'][$role_id];
                        $email = $p['email'];
                        $mobile = $p['mobile'];

                        //user

                        $database = JCT_PREFIX . '_core';
                        if($id > 0)
                        {
                            $db->query(" UPDATE {$database}.user SET 
                            email = NULL, mobile = NULL, updated = NOW(), updated_by = -1 
                            WHERE ( ( ( email IS NOT NULL AND email = '{$email}' ) OR ( mobile IS NOT NULL AND mobile = '{$mobile}' ) ) AND id != {$id} ) ");
                            $db->execute();

                            $db->query(" UPDATE {$database}.user SET 
                            email = :email, mobile = :mobile, active = 1, updated = NOW(), updated_by = -1 
                            WHERE id = {$id} ");
                            $db->bind(':email', $email);
                            $db->bind(':mobile', $mobile);
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.user SET 
                            email = NULL, mobile = NULL, updated = NOW(), updated_by = -1 
                            WHERE ( ( email IS NOT NULL AND email = '{$email}' ) OR ( mobile IS NOT NULL AND mobile = '{$mobile}' ) ) ");
                            $db->execute();

                            $db->query(" INSERT INTO {$database}.user 
                            ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES 
                            ( 1, NULL, :email, :mobile, NULL, NOW(), -1 ) ");
                            $db->bind(':email', $email);
                            $db->bind(':mobile', $mobile);
                            $db->execute();
                            $id = $db->lastInsertId();

                            $p['id'] = $id;
                            $this->persons[$token]['id'] = $id;
                        }


                        // user_org

                        $db->query(" INSERT INTO {$database}.user_org 
                            ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                            ( {$id}, '{$this->guid}', {$role_id}, :ext_id, '{$token}', 1, NOW(), -1 ) 
                            ON DUPLICATE KEY UPDATE guid = '{$this->guid}' ");
                        $db->bind(':ext_id', $ext_id);
                        $db->execute();


                        // person

                        $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

                        $db->query(" UPDATE {$database}.person SET 
                            ext_id = '{$ext_id}', email = NULL, mobile = NULL, updated = NOW(), updated_by = -1 
                            WHERE ( ( ( email IS NOT NULL AND email = '{$email}' ) OR ( mobile IS NOT NULL AND mobile = '{$mobile}' ) ) AND id != {$id} ) ");
                        $db->execute();


                        $country_id = (empty($p['country_id'])) ? $default_country_id : intval($p['country_id']);
                        $county_id = (empty($p['county_id'])) ? $default_county_id : intval($p['county_id']);

                        $fname = html_entity_decode($p['fname'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $lname = html_entity_decode($p['lname'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $indexed_lname = $p['indexed_lname'];
                        $salt_id = intval($p['salt_id']);
                        $is_staff = intval($p['is_staff']);
                        $is_guardian = intval($p['is_guardian']);
                        $is_member = intval($p['is_member']);

                        $sql = " INSERT INTO {$database}.person 
                            ( id, ext_id, fname, lname, 
                            indexed_lname, salute_name, salt_id, 
                            mobile, email, 
                            is_staff, is_guardian, is_member, 
                            county_id, country_id,  
                            active, created, deactivated, updated, updated_by ) VALUES 
                            ( {$id}, '{$ext_id}', '{$fname}', :lname, 
                            '{$indexed_lname}', :salute_name, '{$salt_id}', 
                            :mobile, :email, 
                            {$is_staff}, {$is_guardian}, {$is_member}, 
                            {$county_id}, {$country_id},
                            1, NOW(), NULL, NOW(), -1 ) 
                            ON DUPLICATE KEY UPDATE 
                            fname = '{$fname}', lname = :lname, indexed_lname = '{$indexed_lname}', salute_name = :salute_name, salt_id = '{$salt_id}', 
                            mobile = :mobile, email = :email, 
                            is_staff = {$is_staff}, is_guardian = {$is_guardian}, is_member = {$is_member}, 
                            active = 1, deactivated = NULL, updated = NOW(), updated_by = -1 ";
                        $db->query($sql);
                        $db->bind(':lname', $lname);
                        $db->bind(':salute_name', $p['salute_name']);
                        $db->bind(':mobile', $mobile);
                        $db->bind(':email', $email);

                        $db->execute();

                        $db->query(" SELECT id, fname, lname FROM {$database}.person WHERE ( id = {$id} ) ");
                        $db->execute();
                        if(empty($db->fetchSingleColumn()))
                            throw new Exception('person not inserted');
                    }
                }

                $status['insert persons'] = 'ok';


                ################## could break transaction here ##########


                $status['assign persons to roles'] = 'start';

                $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

                /**
                 * I think I will not auto assign screen permissions based on role upon upload -
                 * this may not always be appropriate, and some apps need module permissions, etc.
                 * So, an admin will get auto access to the current apps, but that's it. Existing perms
                 * will be unaffected.
                 */

                // administrators
                $status['administrators'] = 'start';
                if(isset($this->id_token_map['administrator']))
                {
                    $role_id = array_search('administrator',$this->role_ids);

                    foreach($this->id_token_map['administrator'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);

                        $db->query(" INSERT INTO {$database}.app_screen_user 
                                ( id, role_id, app_slug, module, model, method, updated, updated_by ) VALUES 
                                ( {$id}, {$role_id}, 'manager', NULL, NULL, NULL, NOW(), -1 ) 
                                ON DUPLICATE KEY UPDATE app_slug = app_slug ");
                        $db->execute();

                        $db->query(" SELECT tbl_id FROM {$database}.staff_role 
                                WHERE ( id = {$id} AND role_id = {$role_id} AND role_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new
                        {
                            $db->query(" INSERT INTO {$database}.staff_role 
                                    ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$role_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.staff_role SET role_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->execute();
                        }
                    }
                }
                $status['administrators'] = 'ok';

                // group leaders
                $status['group leaders'] = 'start';
                if(isset($this->id_token_map['group_leader']))
                {
                    $role_id = array_search('group_leader',$this->role_ids);

                    foreach($this->id_token_map['group_leader'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);

                        $db->query(" SELECT tbl_id FROM {$database}.staff_role 
                                WHERE ( id = {$id} AND role_id = {$role_id} AND role_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new
                        {
                            $db->query(" INSERT INTO {$database}.staff_role 
                                    ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$role_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.staff_role SET role_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->bind(':tbl_id', $tbl_id);
                            $db->execute();
                        }

                        $class_ext_ids = (!empty($this->group_class_leader_map[$ext_id])) ? $this->group_class_leader_map[$ext_id] : [];
                        if(empty($class_ext_ids))
                            continue;

                        foreach($class_ext_ids as $class_ext_id)
                        {
                            $class_id = intval($this->group_class_map[ $class_ext_id ]);
                            $class_super_id = intval($this->groups_class[$class_ext_id]['group_super_id']);

                            $db->query(" SELECT tbl_id FROM {$database}.group_class_leader 
                                    WHERE ( group_class_id = {$class_id} AND id = {$id} AND leader_end = '{$now_str_date}' ) ");
                            $db->execute();
                            $tbl_id = $db->fetchSingleColumn();

                            if(empty($tbl_id)) // new
                            {
                                $db->query(" INSERT INTO {$database}.group_class_leader 
                                        ( id, group_class_id, group_super_id, leader_begin, leader_end, updated, updated_by ) VALUES 
                                        ( {$id}, {$class_id}, {$class_super_id}, '{$now_str_date}', NULL, NOW(), -1 )");
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.group_class_leader SET leader_end = NULL WHERE tbl_id = {$tbl_id} ");
                                $db->bind(':tbl_id', $tbl_id);
                                $db->execute();
                            }
                        }

                    }
                }
                $status['group leaders'] = 'ok';

                // members
                $status['members'] = 'start';
                if(isset($this->id_token_map['member']))
                {
                    foreach($this->id_token_map['member'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);
                        $group_class_ext_id = intval($p['group_class']);

                        if(!isset($this->group_class_map[$group_class_ext_id]))
                            throw new Exception('Member uploaded with invalid Class ID.');

                        $group_class_id = intval($this->group_class_map[$group_class_ext_id]);
                        $group_super_id = intval($this->groups_class[$group_class_ext_id]['group_super_id']);

                        $db->query(" SELECT tbl_id FROM {$database}.member_group_class   
                                    WHERE ( id = {$id} AND group_class_id = {$group_class_id} AND in_group_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new
                        {
                            $db->query(" INSERT INTO {$database}.member_group_class 
                                    ( id, group_class_id, group_super_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$group_class_id}, {$group_super_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.member_group_class SET in_group_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->execute();
                        }

                        if( (empty($p['guardians'])) && (empty($p['siblings'])) )
                            continue;

                        foreach($p['guardians'] as $guardian_token)
                        {
                            $guardian_id = $this->persons[$guardian_token]['id'];
                            $is_default = $this->persons[$guardian_token]['is_guardian_default'];
                            $include_in_email = ($is_default) ? 1 : 0;
                            $include_in_text = ($is_default) ? 1 : 0;
                            $include_in_letter = ($is_default) ? 1 : 0;

                            $db->query(" SELECT tbl_id FROM {$database}.member_guardian  
                                    WHERE ( id = {$id} AND guardian_id = {$guardian_id} AND guardian_end = '{$now_str_date}' ) ");
                            $db->execute();
                            $tbl_id = $db->fetchSingleColumn();

                            if(empty($tbl_id)) // new
                            {
                                $db->query(" INSERT INTO {$database}.member_guardian 
                                        ( id, guardian_id, guardian_begin, guardian_end, is_default, 
                                        include_in_email, include_in_letter, include_in_text, updated, updated_by ) VALUES 
                                        ( {$id}, {$guardian_id}, '{$now_str_date}', NULL, {$is_default}, 
                                        {$include_in_email}, {$include_in_letter}, {$include_in_text}, NOW(), -1 ) ");
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.member_guardian SET guardian_end = NULL WHERE tbl_id = {$tbl_id} ");
                                $db->execute();
                            }
                        }

                        if(empty($p['siblings']))
                            continue;

                        foreach($p['siblings'] as $sibling_token)
                        {
                            $sibling_id = $this->persons[$sibling_token]['id'];

                            $db->query(" SELECT tbl_id FROM {$database}.member_sibling  
                                    WHERE ( id = {$id} AND sibling_id = {$sibling_id} AND sibling_end = '{$now_str_date}' ) ");
                            $db->execute();
                            $tbl_id = $db->fetchSingleColumn();

                            if(empty($tbl_id)) // new
                            {
                                $db->query(" INSERT INTO {$database}.member_sibling 
                                        ( id, sibling_id, sibling_begin, sibling_end, updated, updated_by ) VALUES 
                                        ( {$id}, {$sibling_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.member_sibling SET sibling_end = NULL WHERE tbl_id = {$tbl_id} ");
                                $db->execute();
                            }
                        }
                    }
                }
                $status['members'] = 'ok';

                // group assistants
                $status['group assistants'] = 'start';
                if(isset($this->id_token_map['group_assistant']))
                {
                    $role_id = array_search('group_assistant',$this->role_ids);

                    foreach($this->id_token_map['group_assistant'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);

                        $db->query(" SELECT tbl_id FROM {$database}.staff_role 
                                WHERE ( id = {$id} AND role_id = {$role_id} AND role_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new role assignment
                        {
                            $db->query(" INSERT INTO {$database}.staff_role 
                                    ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$role_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.staff_role SET role_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->execute();
                        }

                        if(!isset($this->group_assistant_map[ $ext_id ]))
                            continue;

                        foreach($this->group_assistant_map[$ext_id] as $mem_token)
                        {
                            $mem_id = $this->persons[$mem_token]['id'];

                            $db->query(" SELECT tbl_id FROM {$database}.member_group_assistant 
                                    WHERE ( id = {$mem_id} AND group_assistant_id = {$id} AND assistant_end = '{$now_str_date}' ) ");
                            $db->execute();
                            $tbl_id = intval($db->fetchSingleColumn());

                            if(!$tbl_id) // new assignment
                            {
                                $db->query(" INSERT INTO {$database}.member_group_assistant 
                                        ( id, group_assistant_id, assistant_begin, assistant_end, updated, updated_by ) VALUES 
                                        ( {$mem_id}, {$id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.member_group_assistant SET assistant_end = NULL WHERE ( tbl_id = {$tbl_id} ) ");
                                $db->bind(':tbl_id', $tbl_id);
                                $db->execute();
                            }
                        }
                    }
                }
                $status['group assistants'] = 'ok';

                // member assistants
                $status['member assistants'] = 'start';
                if(isset($this->id_token_map['member_assistant']))
                {
                    $role_id = array_search('member_assistant',$this->role_ids);

                    foreach($this->id_token_map['member_assistant'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);

                        $db->query(" SELECT tbl_id FROM {$database}.staff_role 
                                WHERE ( id = {$id} AND role_id = {$role_id} AND role_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new role assignment
                        {
                            $db->query(" INSERT INTO {$database}.staff_role 
                                    ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$role_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.staff_role SET role_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->execute();
                        }

                        if(!isset($this->member_assistant_map[ $ext_id ]))
                            continue;

                        foreach($this->member_assistant_map[$ext_id] as $mem_token)
                        {
                            $mem_id = $this->persons[$mem_token]['id'];

                            $db->query(" SELECT tbl_id FROM {$database}.member_member_assistant 
                                    WHERE ( id = {$mem_id} AND member_assistant_id = {$id} AND assistant_end = '{$now_str_date}' ) ");
                            $db->execute();
                            $tbl_id = intval($db->fetchSingleColumn());

                            if(!$tbl_id) // new
                            {
                                $db->query(" INSERT INTO {$database}.member_member_assistant 
                                        ( id, member_assistant_id, assistant_begin, assistant_end, updated, updated_by ) VALUES 
                                        ( {$mem_id}, {$id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.member_member_assistant SET assistant_end = NULL WHERE ( tbl_id = {$tbl_id} ) ");
                                $db->bind(':tbl_id', $tbl_id);
                                $db->execute();
                            }
                        }
                    }
                }
                $status['member assistants'] = 'ok';

                // general staff
                $status['general staff'] = 'start';
                if(isset($this->id_token_map['general_staff']))
                {
                    $role_id = array_search('general_staff',$this->role_ids);

                    foreach($this->id_token_map['general_staff'] as $ext_id => $token)
                    {
                        $p = $this->persons[$token];
                        $id = intval($p['id']);

                        $db->query(" SELECT tbl_id FROM {$database}.staff_role 
                                WHERE ( id = {$id} AND role_id = {$role_id} AND role_end = '{$now_str_date}' ) ");
                        $db->execute();
                        $tbl_id = $db->fetchSingleColumn();

                        if(empty($tbl_id)) // new
                        {
                            $db->query(" INSERT INTO {$database}.staff_role 
                                    ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                                    ( {$id}, {$role_id}, '{$now_str_date}', NULL, NOW(), -1 ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" UPDATE {$database}.staff_role SET role_end = NULL WHERE tbl_id = {$tbl_id} ");
                            $db->bind(':tbl_id', $tbl_id);
                            $db->execute();
                        }
                    }
                }
                $status['general staff'] = 'ok';

                $status['assign persons to roles'] = 'ok';




                $status['assign persons to group_custom'] = 'start';
                if(!empty($this->groups_custom))
                {
                    foreach($this->groups_custom as $ext_id => $grp)
                    {
                        $member_tokens = $grp['members'];
                        $grp_id = $this->group_custom_map[$ext_id];

                        foreach($member_tokens as $token)
                        {
                            $mem_id = $this->persons[$token]['id'];

                            $db->query(" SELECT tbl_id FROM {$database}.member_group_custom WHERE 
                            ( id = :mem_id AND group_custom_id = :group_custom_id AND in_group_end = :now_str ) ");
                            $db->bind(':mem_id', $mem_id);
                            $db->bind(':group_custom_id', $grp_id);
                            $db->bind(':now_str', $now_str_date);
                            $db->execute();
                            $tbl_id = intval($db->fetchSingleColumn());

                            if(!$tbl_id) // new assignment
                            {
                                $db->query(" INSERT INTO {$database}.member_group_custom 
                                ( id, group_custom_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                                ( :mem_id, :group_custom_id, NOW(), NULL, NOW(), -1 ) ");
                                $db->bind(':mem_id', $mem_id);
                                $db->bind(':group_custom_id', $grp_id);
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.member_group_custom SET in_group_end = NULL WHERE ( tbl_id = :tbl_id ) ");
                                $db->bind(':tbl_id', $tbl_id);
                                $db->execute();
                            }
                        }
                    }
                }
                $status['assign persons to group_custom'] = 'ok';


                $status['assign persons to group_staff'] = 'start';
                if(!empty($this->groups_staff))
                {
                    foreach($this->groups_staff as $ext_id => $grp)
                    {
                        $member_tokens = $grp['members'];
                        $grp_id = $this->group_staff_map[$ext_id];

                        foreach($member_tokens as $token)
                        {
                            $mem_id = $this->persons[$token]['id'];

                            $db->query(" SELECT tbl_id FROM {$database}.group_staff_staff WHERE 
                            ( id = :mem_id AND group_staff_id = :group_staff_id AND in_group_end = :now_str ) ");
                            $db->bind(':mem_id', $mem_id);
                            $db->bind(':group_staff_id', $grp_id);
                            $db->bind(':now_str', $now_str_date);
                            $db->execute();
                            $tbl_id = intval($db->fetchSingleColumn());

                            if(!$tbl_id) // new assignment
                            {
                                $db->query(" INSERT INTO {$database}.group_staff_staff 
                                ( id, group_staff_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                                ( :mem_id, :group_staff_id, NOW(), NULL, NOW(), -1 ) ");
                                $db->bind(':mem_id', $mem_id);
                                $db->bind(':group_staff_id', $grp_id);
                                $db->execute();
                            }
                            else
                            {
                                $db->query(" UPDATE {$database}.group_staff_staff SET in_group_end = NULL WHERE ( tbl_id = :tbl_id ) ");
                                $db->bind(':tbl_id', $tbl_id);
                                $db->execute();
                            }
                        }
                    }
                }
                $status['assign persons to group_staff'] = 'ok';


                /**
                 * Could clear screen access here for de-activated accounts,
                 * but they wouldn't get that far anyway. May as well keep them
                 * so that they can be re-activated.
                 */


                $database = JCT_PREFIX . '_core';
                $status['re-activate all associated Admin accounts'] = 'start';

                $db->query(" SELECT id FROM {$database}.user_org WHERE ( guid = :guid AND role_id < 3 ) ");
                $db->bind(':guid', $this->guid);
                $db->execute();
                $admin_user_ids = $db->fetchAllColumn();

                if(!empty($admin_user_ids))
                {
                    $db->query(" UPDATE {$database}.user_org SET active = 1, updated = NOW(), updated_by = -1 WHERE ( guid = :guid AND role_id < 3 ) ");
                    $db->bind(':guid', $this->guid);
                    $db->execute();


                    $database = JCT_PREFIX . '_org_' . strtolower($this->guid);
                    $admin_user_ids_str = implode(',',$admin_user_ids);

                    $db->query(" UPDATE {$database}.person SET active = 1 WHERE id IN ({$admin_user_ids_str}) ");
                    $db->execute();
                }

                $status['re-activate all associated Admin accounts'] = 'ok';


                // todo
                // improve lname indexing
                // save contact details per person as their uploaded_with contact info
                // give permission to user screen for all persons by default
                // loop through members, get guardians of, merge guardians with same name but no email / mobile
                // delete old guardians with no uploaded_with / any contact info (?)


                $status['commit'] = 'start';
                $db->commit();
                if(!empty($db->db_error))
                    $status['Commit Error'] = $db->db_error;


            }
            catch (Exception $e)
            {
                $db->rollBack();

                if(!empty($db->db_error))
                    $status['rollback error'] = $db->db_error;

                // reset AI values

                $database = JCT_PREFIX . '_core';
                foreach($core_tables_ai as $tbl_name => $ai_value)
                {
                    $tbl = $database . '.' . $tbl_name;
                    $db->query(" ALTER TABLE {$tbl} auto_increment = {$ai_value}; ");
                    $db->execute();
                }

                $database = JCT_PREFIX . '_org_' . strtolower($this->guid);
                foreach($org_db_tables_ai as $tbl_name => $ai_value)
                {
                    $tbl = $database . '.' . $tbl_name;
                    $db->query(" ALTER TABLE {$tbl} auto_increment = {$ai_value}; ");
                    $db->execute();
                }

                throw new Exception($e->getMessage());
            }

            $status['commit'] = 'ok';
            $status['saving'] = 'ok';

            $this->input = null;
            $this->data = null;
            $this->persons = null;
            $this->mobile_persons = null;
            $this->email_persons = null;
            $this->email_persons = null;
            $this->merged_persons = null;
            $this->default_guardian_map = null;
            $this->id_token_map = null;


            // todo go through each family and ensure that there is a default guardian per child, and that guardian is included in emails & texts

            return ['success'=>1];
        }
        catch (Exception $e)
        {
            $this->success = 0;
            $ex = $e->getMessage();

            $status['Exception'] = $ex;
            $this->response = $ex;
            return ['error'=>$ex];
        }
    }

    private function set_salutations()
    {
        $db = $this->_ORG_DB;
        $db->query(" SELECT id, LOWER(title) AS title FROM prm_salutation WHERE active = 1 ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        $salutations = [];
        if (!empty($tmp)) {
            foreach ($tmp as $t) {
                $title = preg_replace('/\PL/u', '', $t['title']);
                $salutations[$title] = $t['id'];
            }
        }

        $this->salutations = $salutations;
    }

    private function make_persons(Array $arr, $role, $is_default_guardian = false)
    {
        $role_id = $this->roles_and_ranks[$role]['role_id'];

        $n = 0;
        foreach ($arr as $x => $p)
        {
            // generate random token per person

            $token = Helper::generate_random_string(6) . str_pad($n, 2, 0, STR_PAD_LEFT);
            $n++;


            // set role

            $is_staff = ($role_id < 7);
            $is_guardian = ($role_id == 8);
            $is_member = ($role_id == 7);


            // clean and normalise values

            $p = array_change_key_case($p, CASE_LOWER);
            foreach ($p as $k => $v)
                $p[$k] = Helper::nullify_empty_values($v);

            $p['indexed_lname'] = $p['lname'];
            $p = Helper::normalise_person_parameters($p, $this->salutations);


            // drop empty guardian records

            $mobile = (!empty($p['mobile'])) ? $p['mobile'] : null;
            $email = (!empty($p['email'])) ? $p['email'] : null;

            if (($is_guardian) && ($email === null) && ($mobile === null))
                continue;


            // skip job-sharing records, but store them for later matching
            // of share ID to teacher IDs

            if (strpos($p['salute_name'], '/') !== false) {
                $id_split = explode('-', $p['fname']);
                if (count($id_split) > 1) {
                    $this->job_share_persons[$p['id']] = $p;
                    continue;
                }
            }


            // set flags

            $p['is_staff'] = 0;
            $p['is_guardian'] = ($is_guardian) ? 1 : 0;
            $p['is_member'] = ($is_member) ? 1 : 0;

            $p['is_administrator'] = 0;
            $p['is_group_leader'] = 0;
            $p['is_group_assistant'] = 0;
            $p['is_member_assistant'] = 0;
            $p['is_general_staff'] = 0;
            $p['is_guardian_default'] = 0;
            $p['default_guardian_of'] = 0;


            // set staff roles

            if ($is_staff) {
                $p['is_' . $role] = 1;
                $p['is_staff'] = 1;
            }


            // the id provided for default_guardians is actually the id of their child

            $guardian_of = 0;
            $p['is_guardian_default'] = 0;
            if ($is_default_guardian)
            {
                $key_g = (isset($p['guardian_of'])) ? 'guardian_of' : 'id';
                $guardian_of = $p[$key_g];
                unset($p[$key_g]);

                $p['is_guardian_default'] = 1;
                $p['default_guardian_of'] = $guardian_of;
                $this->default_guardian_map[$guardian_of] = $token;
            }


            // set remaining keys

            $ext_id = (isset($p['id'])) ? $p['id'] : null;
            if($ext_id === null)
                $ext_id = $token;

            $SET_key = (isset($p['assistant'])) ? 'assistant' : 'set_teacher_id';
            $SET_teacher_ext_ids = (empty($p[$SET_key])) ? [] : $p[$SET_key];
            unset($p[$SET_key]);

            if(!is_array($SET_teacher_ext_ids))
                $SET_teacher_ext_ids = [$SET_teacher_ext_ids];

            $SET_teacher_ext_ids = array_map('intval', $SET_teacher_ext_ids);
            $p['set_teacher_ids'] = $SET_teacher_ext_ids;
            if($SET_teacher_ext_ids)
            {
                foreach($SET_teacher_ext_ids as $SET_ext_id)
                {
                    if(empty($SET_ext_id))
                        continue;

                    if(!isset($this->group_assistant_map[$SET_ext_id]))
                        $this->group_assistant_map[$SET_ext_id] = [];

                    $this->group_assistant_map[$SET_ext_id][] = $token;
                }
            }

            $SNA_ext_id = (empty($p['sna_id'])) ? 0 : $p['sna_id'];
            $p['sna_id'] = $SNA_ext_id;

            $p['guardians'] = (isset($p['guardians'])) ? $p['guardians'] : [];

            $p['siblings'] = (isset($p['siblings'])) ? $p['siblings'] : [];


            $p['role_ids'] = [];
            $p['role_ids'][$role_id] = $ext_id;
            $p['id'] = 0;
            $p['token'] = $token;



            if($SNA_ext_id)
            {
                if(!isset($this->member_assistant_map[$SNA_ext_id]))
                    $this->member_assistant_map[$SNA_ext_id] = [];

                $this->member_assistant_map[$SNA_ext_id][] = $token;
            }



            // merge duplicate records of staff & guardians

            if(!$is_member)
            {
                $fname_key = (!empty($p['fname'])) ? strtoupper(Helper::latinise_string($p['fname'])) : null;
                $fname_key = (strlen($fname_key) > 0) ? $fname_key : null;

                $alt = [];
                if ((($fname_key !== null) && ($mobile !== null)) ||
                    (($fname_key !== null) && ($email !== null)) ||
                    (($mobile !== null) && ($email !== null))
                )
                {
                    if($mobile !== null)
                    {
                        if (!isset($this->mobile_persons[$mobile]))
                            $this->mobile_persons[$mobile] = [
                                'email' => $email,
                                'fname_key' => $fname_key,
                                'token' => $token
                            ];
                        else
                        {
                            $tmp = $this->mobile_persons[$mobile];

                            if ($tmp['email'] == $email)
                                $alt = (isset($this->persons[$tmp['token']])) ? $this->persons[$tmp['token']] : []; // other is alt (same mobile and email)
                            else if ($tmp['fname_key'] == $fname_key) // other is alt (same mobile and fname_key)
                                $alt = (isset($this->persons[$tmp['token']])) ? $this->persons[$tmp['token']] : [];
                            else
                                $p['mobile'] = null;
                        }
                    }

                    if((empty($alt)) && ($email !== null))
                    {
                        if (!isset($this->email_persons[$email]))
                            $this->email_persons[$email] = [
                                'mobile' => $mobile,
                                'fname_key' => $fname_key,
                                'token' => $token
                            ];
                        else {
                            $tmp = $this->email_persons[$email];

                            if ($tmp['mobile'] == $mobile)
                                $alt = (isset($this->persons[$tmp['token']])) ? $this->persons[$tmp['token']] : []; // other is alt (same mobile and email)
                            else if ($tmp['fname_key'] == $fname_key) // other is alt (same email and fname_key)
                                $alt = (isset($this->persons[$tmp['token']])) ? $this->persons[$tmp['token']] : [];
                            else
                                $p['email'] = null;
                        }
                    }

                    if(($is_guardian) && (empty($alt)) && ($p['mobile'] === null) && ($p['email'] === null))
                        continue;
                }

                if(!empty($alt))
                {
                    $p = $this->merge_persons($p, $alt);
                    $token = $p['token'];
                }
            }

            // clean up member mobile & email conflicts later,
            // when all others have been parsed


            if ($is_default_guardian && ($guardian_of > 0))
                $this->default_guardian_map[$guardian_of] = $token;


            // record tokens to role

            if (!isset($this->id_token_map[$role]))
                $this->id_token_map[$role] = [];


            $this->id_token_map[$role][$ext_id] = $token;


            // store person

            $this->persons[$token] = $p;
        }
    }

    private function merge_persons($p, $alt)
    {
        $arr = [
            json_encode($p),
            json_encode($alt)
        ];

        /**
         * THIS person's values are over-written/appended with
         * the ALT person's values. Then THIS person's token
         * becomes the ALT person's token (ultimately meaning
         * that THIS record is the preserved one and the ALT
         * is itself over-written, but under the ALT token).
         */


        @$p['ext_id'] = (empty($alt['ext_id'])) ? $p['ext_id'] : $alt['ext_id'];
        $p['fname'] = (empty($alt['fname'])) ? $p['fname'] : $alt['fname'];
        $p['lname'] = (empty($alt['lname'])) ? $p['lname'] : $alt['lname'];
        $p['indexed_lname'] = (empty($alt['indexed_lname'])) ? $p['indexed_lname'] : $alt['indexed_lname'];
        $p['salute_name'] = (empty($alt['salute_name'])) ? $p['salute_name'] : $alt['salute_name'];
        $p['salt_id'] = (empty($alt['salt_id'])) ? $p['salt_id'] : $alt['salt_id'];

        $p['email'] = (empty($alt['email'])) ? $p['email'] : $alt['email'];
        $p['mobile'] = (empty($alt['mobile'])) ? $p['mobile'] : $alt['mobile'];

        $p['is_staff'] = (!empty($alt['is_staff'])) ? $alt['is_staff'] : $p['is_staff'];
        $p['is_guardian'] = (!empty($alt['is_guardian'])) ? $alt['is_guardian'] : $p['is_guardian'];
        $p['is_member'] = (!empty($alt['is_member'])) ? $alt['is_member'] : $p['is_member'];

        $p['is_administrator'] = (!empty($alt['is_administrator'])) ? $alt['is_administrator'] : $p['is_administrator'];
        $p['is_group_leader'] = (!empty($alt['is_group_leader'])) ? $alt['is_group_leader'] : $p['is_group_leader'];
        $p['is_group_assistant'] = (!empty($alt['is_group_assistant'])) ? $alt['is_group_assistant'] : $p['is_group_assistant'];
        $p['is_member_assistant'] = (!empty($alt['is_member_assistant'])) ? $alt['is_member_assistant'] : $p['is_member_assistant'];
        $p['is_general_staff'] = (!empty($alt['is_general_staff'])) ? $alt['is_general_staff'] : $p['is_general_staff'];
        $p['is_guardian_default'] = (!empty($alt['is_guardian_default'])) ? $alt['is_guardian_default'] : $p['is_guardian_default'];

        foreach ($alt['guardians'] as $g_id)
            $p['guardians'][] = $g_id;

        foreach ($alt['siblings'] as $g_id)
            $p['siblings'][] = $g_id;

        $p['set_teacher_ids'] = (!empty($alt['set_teacher_ids'])) ? $alt['set_teacher_ids'] : $p['set_teacher_ids'];

        foreach ($alt['role_ids'] as $role_id => $ext_id) {
            $ext_id = ($ext_id === $p['token']) ? $alt['token'] : $ext_id;
            $p['role_ids'][$role_id] = $ext_id;
        }

        foreach ($p['role_ids'] as $role_id => $ext_id) {
            $ext_id = ($ext_id === $p['token']) ? $alt['token'] : $ext_id;
            $p['role_ids'][$role_id] = $ext_id;
        }

        $p['token'] = $alt['token'];

        $arr[] = json_encode($p);
        $this->merged_persons[] = $arr;

        return $p;
    }

    private function parse_groups_super()
    {
        if (empty($this->data['group_super']))
            return ['error' => 'No Super Groups detected.'];

        try
        {
            $groups = $this->data['group_super'];
            foreach ($groups as $grp)
            {
                if (empty($grp['id']))
                    throw new Exception('Super Group uploaded without ID.');

                if (empty($grp['title']))
                    throw new Exception('Super Group uploaded without Title.');

                $ext_id = intval($grp['id']);
                $title = trim($grp['title']);
                $abbr = (empty($grp['abbr'])) ? null : $grp['abbr'];
                $abbr = (empty(Helper::strip_all_white_space($abbr))) ? null : trim($abbr);
                $abbr = ($abbr === null) ? substr($title, 0, 4) . '_' . Helper::generate_random_string(2) : $abbr;
                $current = (!empty($grp['current'])) ? intval($grp['current']) : 1;
                $current = ($current > 0) ? 1 : 0;

                $this->groups_super[$ext_id] = [
                    'title' => $title,
                    'abbr' => $abbr,
                    'current' => $current
                ];
            }

            return ['success' => 1];
        }
        catch (Exception $e)
        {
            return ['error' => $e->getMessage()];
        }
    }

    private function parse_groups_class()
    {
        if (empty($this->data['group_class']))
            return ['error' => 'No Class Groups detected.'];

        try
        {
            $groups = $this->data['group_class'];

            foreach ($groups as $grp)
            {
                if (empty($grp['id']))
                    throw new Exception('Class Group uploaded without ID.');

                if (empty($grp['title']))
                    throw new Exception('Class Group uploaded without Title.');

                if (empty($grp['group_super']))
                    throw new Exception('Class Group uploaded without Super Group ID.');

                if (empty($grp['leader']))
                    throw new Exception('Class Group uploaded without Leader ID.');


                $group_super_ext_id = intval($grp['group_super']);
                if (!empty($this->groups_super)) {
                    if (!array_key_exists($group_super_ext_id, $this->groups_super))
                        throw new Exception('Class Group uploaded with invalid Super Group ID.');
                }

                $grp_ext_id = intval($grp['id']);
                $title = trim($grp['title']);
                $abbr = (empty($grp['abbr'])) ? null : $grp['abbr'];
                $abbr = (empty(Helper::strip_all_white_space($abbr))) ? null : trim($abbr);
                $abbr = ($abbr === null) ? substr($title, 0, 4) . '_' . Helper::generate_random_string(2) : $abbr;
                $leader_ext_id = intval($grp['leader']);
                $gender = (!empty($grp['gender'])) ? trim(strtoupper($grp['gender'])) : 'A';
                $gender = (!in_array($gender, ['A', 'M', 'F'])) ? 'A' : $gender;
                $active = (!empty($grp['active'])) ? intval($grp['active']) : 1;
                $active = ($active > 0) ? 1 : 0;
                $type = (!empty($grp['type'])) ? intval($grp['type']) : 1;

                $this->groups_class[$grp_ext_id] = [
                    'title' => $title,
                    'abbr' => $abbr,
                    'group_super_ext_id' => $group_super_ext_id,
                    'leader_ext_id' => $leader_ext_id,
                    'gender' => $gender,
                    'type_id' => $type,
                    'active' => $active
                ];

                if(!isset($this->group_class_leader_map[ $leader_ext_id ]))
                    $this->group_class_leader_map[ $leader_ext_id ] = [];

                $this->group_class_leader_map[ $leader_ext_id ][] = $grp_ext_id;
            }

            return ['success' => 1];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function parse_groups_custom()
    {
        if (empty($this->data['group_custom']))
            return ['success' => 1];

        try {
            $groups = $this->data['group_custom'];

            foreach ($groups as $grp) {
                if (empty($grp['id']))
                    throw new Exception('Class Group uploaded without ID.');

                if (empty($grp['title']))
                    throw new Exception('Class Group uploaded without Title.');

                $ext_id = intval($grp['id']);
                $title = trim($grp['title']);
                $abbr = (empty($grp['abbr'])) ? null : $grp['abbr'];
                $abbr = (empty(Helper::strip_all_white_space($abbr))) ? null : trim($abbr);
                $abbr = ($abbr === null) ? substr($title, 0, 4) . '_' . Helper::generate_random_string(2) : $abbr;
                $active = (!empty($grp['active'])) ? intval($grp['active']) : 1;
                $active = ($active > 0) ? 1 : 0;

                $members = (!empty($grp['members'])) ? $grp['members'] : [];
                $member_tokens = [];
                foreach($members as $mem_ext_id)
                {
                    $token = $this->id_token_map['member'][$mem_ext_id];
                    $member_tokens[] = $token;
                }

                $this->groups_custom[$ext_id] = [
                    'title' => $title,
                    'abbr' => $abbr,
                    'active' => $active,
                    'members' => $member_tokens
                ];
            }

            return ['success' => 1];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function parse_groups_staff()
    {
        if (empty($this->data['group_staff']))
            return ['success' => 1];

        try {
            $groups = $this->data['group_staff'];

            foreach ($groups as $grp) {
                if (empty($grp['id']))
                    throw new Exception('Staff Group uploaded without ID.');

                if (empty($grp['title']))
                    throw new Exception('Staff Group uploaded without Title.');

                $ext_id = intval($grp['id']);
                $title = trim($grp['title']);
                $abbr = (empty($grp['abbr'])) ? null : $grp['abbr'];
                $abbr = (empty(Helper::strip_all_white_space($abbr))) ? null : trim($abbr);
                $abbr = ($abbr === null) ? substr($title, 0, 4) . '_' . Helper::generate_random_string(2) : $abbr;
                $active = (!empty($grp['active'])) ? intval($grp['active']) : 1;
                $active = ($active > 0) ? 1 : 0;

                $members = (!empty($grp['members'])) ? $grp['members'] : [];
                $member_tokens = [];
                foreach($members as $mem_ext_id)
                {
                    $token = $this->id_token_map['general_staff'][$mem_ext_id];
                    $member_tokens[] = $token;
                }

                $this->groups_staff[$ext_id] = [
                    'title' => $title,
                    'abbr' => $abbr,
                    'active' => $active,
                    'members' => $member_tokens
                ];
            }

            return ['success' => 1];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function parse_persons()
    {
        if (!empty($this->data['administrator']))
            $this->make_persons($this->data['administrator'], 'administrator');

        if (!empty($this->data['group_leader']))
            $this->make_persons($this->data['group_leader'], 'group_leader');

        $key = (!empty($this->data['ls_teacher'])) ? 'assistant' : 'group_assistant';
        if (!empty($this->data[$key]))
            $this->make_persons($this->data[$key], 'group_assistant');

        $key = (!empty($this->data['sna'])) ? 'sna' : 'member_assistant';
        if (!empty($this->data[$key]))
            $this->make_persons($this->data[$key], 'member_assistant');

        $key = (!empty($this->data['staff'])) ? 'staff' : 'general_staff';
        if (!empty($this->data[$key]))
            $this->make_persons($this->data[$key], 'general_staff');

        $key = (!empty($this->data['guardian_other'])) ? 'guardian_other' : 'guardian';
        if (!empty($this->data[$key]))
            $this->make_persons($this->data[$key], 'guardian');

        if (!empty($this->data['guardian_default']))
            $this->make_persons($this->data['guardian_default'], 'guardian', true);

        if (!empty($this->data['member']))
            $this->make_persons($this->data['member'], 'member');

        //Helper::show($this->merged_persons);



        // update all guardian and sibling ids to tokens,
        // and include default guardians in member's guardians array.
        // clean up any mobile & email conflicts here also

        $member_role_id = array_search('member', $this->role_ids);
        foreach ($this->id_token_map['member'] as $ext_id => $token)
        {
            $p = $this->persons[$token];
            $siblings = [];
            if (!empty($p['siblings'])) {
                foreach ($p['siblings'] as $sib_id)
                    $siblings[] = $this->id_token_map['member'][$sib_id];
            }
            $this->persons[$token]['siblings'] = $siblings;


            $guardians = [];
            if (!empty($p['guardians'])) {
                foreach ($p['guardians'] as $ext_id) {
                    $tmp = (isset($this->id_token_map['guardian'][$ext_id])) ? $this->id_token_map['guardian'][$ext_id] : null;
                    if (isset($this->persons[$tmp]))
                        $guardians[] = $tmp;
                }
            }

            $ext_id = $p['role_ids'][$member_role_id];
            $default_guardian_token = (isset($this->default_guardian_map[$ext_id])) ? $this->default_guardian_map[$ext_id] : null;
            if (isset($this->persons[$default_guardian_token]))
                $guardians[] = $default_guardian_token;

            $this->persons[$token]['guardians'] = $guardians;


            if ((!empty($p['mobile'])) && (isset($this->mobile_persons[$p['mobile']])))
                $this->persons[$token]['mobile'] = null;

            if ((!empty($p['email'])) && (isset($this->email_persons[$p['email']])))
                $this->persons[$token]['email'] = null;
        }

        return (!empty($this->persons));
    }


    private function record_synch()
    {
        $database = JCT_PREFIX . '_core';
        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');

        $db->query(" INSERT INTO {$database}.activity_log 
        ( id, org_guid, action_datetime, app, module, model, method ) VALUES 
        ( -1, '{$this->guid}', :now_str, 'api', 'manager', 'org_synch', 'upload' ) ");
        $db->bind(':now_str', $this->now->format('Y-m-d H:i:s'));
        $db->execute();
    }

    private function cleanup()
    {
        $database = JCT_PREFIX . '_core';
        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');

        $db->query(" SELECT app_slug FROM {$database}.org_apps 
                WHERE ( org_guid = :guid AND active = 1 AND app_slug != 'manager' ) ");
        $db->bind(':guid', $this->guid);
        $db->execute();
        $org_app_slugs = $db->fetchAllColumn();

        if(!empty($org_app_slugs))
        {
            foreach($org_app_slugs as $app_slug)
            {
                $fn = $app_slug . '_cleanup';
                if(method_exists($this, $fn))
                    $this->$fn();
            }
        }
    }

    private function pm_scheduler_cleanup()
    {
        $database = JCT_PREFIX . '_org_' . strtolower($this->guid);
        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');


        $db->query(" SELECT DISTINCT event_id FROM {$database}.pm_day WHERE DATE(day_date) >= DATE(:now_str) ");
        $db->bind(":now_str", $this->now->format('Y-m-d'));
        $db->execute();
        $upcoming_event_ids = $db->fetchAllColumn();

        if (empty($upcoming_event_ids))
            return true;

        $upcoming_event_ids_str = implode(',', $upcoming_event_ids);


        // get active pupils
        $db->query(" SELECT id FROM {$database}.person WHERE ( active = 1 AND is_member = 1 ) ");
        $db->execute();
        $active_pupil_ids = $db->fetchAllColumn();
        $active_pupil_ids_str = implode(',', $active_pupil_ids);


        // check for inactive pupil reservations
        $db->query(" SELECT id FROM {$database}.pm_reservation_member 
        WHERE ( event_id IN ({$upcoming_event_ids_str}) AND id NOT IN ({$active_pupil_ids_str}) ) ");
        $db->execute();
        $inactive_pupil_ids = $db->fetchAllColumn();

        $inactive_pupil_reservations = [];
        if(!empty($inactive_pupil_ids))
        {
            $inactive_pupil_ids_str = implode(',', $inactive_pupil_ids);
            $db->query(" SELECT id AS reservation_id, member_id, event_id  
            FROM {$database}.pm_reservation_member 
            WHERE ( event_id IN ({$upcoming_event_ids_str}) member_id IN ({$inactive_pupil_ids_str}) ) ");
            $db->execute();
            $inactive_pupil_reservations = $db->fetchAllAssoc('event_id'); // event_id => member_id, reservation_id
        }

        $db->query(" SELECT id, group_class_id FROM {$database}.member_group_class 
        WHERE ( id IN ({$active_pupil_ids_str}) AND in_group_end IS NULL ) ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        $pupils = [];
        foreach ($tmp as $res)
            $pupils[$res['id']] = ['group_class_id' => $res['group_class_id'], 'staff_ids' => []];

        $db->query(" SELECT id, group_class_id FROM {$database}.group_class_leader WHERE ( leader_end IS NULL ) ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        $group_class_leaders = [];
        foreach ($tmp as $res)
        {
            $grp_id = $res['group_class_id'];
            if(!isset($group_class_leaders[ $grp_id ]))
                $group_class_leaders[ $grp_id ] = [];

            $group_class_leaders[ $grp_id ][] = $res['id'];
        }

        $db->query(" SELECT id, assistant_id FROM {$database}.member_group_assistant WHERE ( assistant_end IS NULL ) ");
        $db->execute();
        $SET_teachers = $db->fetchAllAssoc('id', true);

        $db->query(" SELECT id, assistant_id FROM {$database}.member_member_assistant WHERE ( assistant_end IS NULL ) ");
        $db->execute();
        $SNAs = $db->fetchAllAssoc('id', true);



        // check for pupil reservations with invalid / missing teacher

        $incorrect_reservations = [
            'inactive_pupil_reservations' => $inactive_pupil_reservations,
            'incorrect_reservations' => []
        ];
        foreach($pupils as $pupil_id => $p)
        {
            $db->query(" SELECT res.reservation_id, res.staff_id, sr.role_id, ev.staff_option, res.event_id     
            FROM {$database}.pm_reservation_staff res 
            LEFT JOIN {$database}.staff_role sr ON ( res.staff_id = sr.id AND role_end IS NULL ) 
            LEFT JOIN {$database}.pm_event ev ON ( res.event_id = ev.id ) 
            WHERE ( member_id = {$pupil_id} AND event_id IN ({$upcoming_event_ids_str}) ) ");
            $db->execute();
            $reservations = $db->fetchAllAssoc();

            if(empty($reservations))
                continue;

            $assigned_staff_ids = [];
            foreach($reservations as $res)
            {
                $staff_id = $res['staff_id'];
                $role_id = $res['role_id'];
                $reservation_id = $res['reservation_id'];

                if(!isset($assigned_staff_ids[ $reservation_id ]))
                    $assigned_staff_ids[ $reservation_id ] = [
                        'staff_option' => $res['staff_option'],
                        'event_id' => $res['event_id'],
                        'staff' => []
                    ];
                if(!isset($assigned_staff_ids[ $reservation_id ]['staff'][ $role_id ]))
                    $assigned_staff_ids[ $reservation_id ]['staff'][ $role_id ] = [];

                $assigned_staff_ids[ $reservation_id ]['staff'][ $role_id ][] = $staff_id;
            }


            // 3 => teacher, 4 => SET, 5 => SNA
            foreach($assigned_staff_ids as $reservation_id => $res)
            {
                $staff_option = $res['staff_option'];
                $event_id = $res['event_id'];

                $has_teacher = $has_SET = $has_SNA = false;
                switch($staff_option)
                {
                    case('inc_teacher_inc_set_inc_sna'):
                        $has_teacher = $has_SET = $has_SNA = true;
                        break;
                    case('inc_teacher_inc_set_ex_sna'):
                        $has_teacher = $has_SET = true;
                        break;
                    case('inc_teacher_ex_set_ex_sna'):
                        $has_teacher = true;
                        break;
                    case('ex_teacher_inc_set_inc_sna'):
                        $has_SET = $has_SNA = true;
                        break;
                    case('ex_teacher_inc_set_ex_sna'):
                        $has_SET = true;
                        break;
                    case('ex_teacher_ex_set_inc_sna'):
                        $has_SNA = true;
                        break;
                }


                foreach($res['staff'] as $role_id => $staff_ids)
                {
                    $invalid = $missing = [];
                    if(empty($staff_ids))
                        continue;
                    else
                    {
                        switch($role_id)
                        {
                            case(3): // teachers
                                if(!$has_teacher)
                                    $invalid = $staff_ids;
                                else
                                {
                                    $invalid = array_diff($staff_ids, $group_class_leaders[$p['group_class_id']]);
                                    $missing = array_diff($group_class_leaders[$p['group_class_id']], $staff_ids);
                                }
                                break;
                            case(4): // SET teachers
                                if(!$has_SET)
                                    $invalid = $staff_ids;
                                else
                                {
                                    if(!isset($SET_teachers[$pupil_id]))
                                        $invalid = $staff_ids;
                                    else
                                    {
                                        $invalid = array_diff($staff_ids, [ $SET_teachers[$pupil_id] ]);
                                        $missing = array_diff([ $SET_teachers[$pupil_id] ], $staff_ids);
                                    }
                                }
                                break;
                            case(5): // SNAs
                                if(!$has_SNA)
                                    $invalid = $staff_ids;
                                else
                                {
                                    if(!isset($SNAs[$pupil_id]))
                                        $invalid = $staff_ids;
                                    else
                                    {
                                        $invalid = array_diff($staff_ids, [ $SNAs[$pupil_id] ]);
                                        $missing = array_diff([ $SNAs[$pupil_id] ], $staff_ids);
                                    }
                                }
                                break;
                            default:
                                break;
                        }
                    }

                    if( (empty($invalid)) && (empty($missing)) )
                        continue;

                    if(!isset($incorrect_reservations['incorrect_reservations'][$event_id]))
                        $incorrect_reservations['incorrect_reservations'][$event_id] = [
                            'invalid' => [],
                            'missing' => []
                        ];

                    if(!empty($invalid))
                    {
                        if(!isset($incorrect_reservations['incorrect_reservations'][$event_id]['invalid'][$role_id]))
                            $incorrect_reservations['incorrect_reservations'][$event_id]['invalid'][$role_id] = [];

                        $incorrect_reservations['incorrect_reservations'][$event_id]['invalid'][$role_id] = [
                            'reservation_id' => $reservation_id,
                            'member_id' => $pupil_id,
                            'staff_ids' => $invalid
                        ];
                    }

                    if(!empty($missing))
                    {
                        if (!isset($incorrect_reservations['incorrect_reservations'][$event_id]['missing'][$role_id]))
                            $incorrect_reservations['incorrect_reservations'][$event_id]['missing'][$role_id] = [];

                        $incorrect_reservations['incorrect_reservations'][$event_id]['invalid'][$role_id] = [
                            'reservation_id' => $reservation_id,
                            'member_id' => $pupil_id,
                            'staff_ids' => $missing
                        ];
                    }
                }
            }
        }


        // save incorrect reservations to DB

        if(empty($incorrect_reservations))
            return true;


        $db->query(" DELETE FROM {$database}.pm_incorrect_reservations WHERE 1 ");
        $db->execute();

        $db->query(" INSERT IGNORE INTO {$database}.pm_incorrect_reservations 
        ( event_id, reservation_id, member_id, staff_id, role_id, cause, notification_seen, updated, updated_by ) VALUES
        ( :event_id, :reservation_id, :member_id, :staff_id, :role_id, :cause, 0, NOW(), -1 )  ");
        foreach($incorrect_reservations as $key => $inc)
        {
            if( ($key === 'inactive_pupil_reservations') && (!empty($inc)) )
            {
                foreach($inc as $event_id => $res)
                {
                    $db->bind(':event_id', $event_id);
                    $db->bind(':reservation_id', $res['reservation_id']);
                    $db->bind(':member_id', $res['member_id']);
                    $db->bind(':staff_id', 0);
                    $db->bind(':role_id', 0);
                    $db->bind(':cause', 'inactive_pupil');
                    $db->execute();
                }
            }

            if( ($key === 'incorrect_reservations') && (!empty($inc)) )
            {
                foreach($inc as $event_id => $types)
                {
                    foreach($types as $type => $roles)
                    {
                        if(empty($roles))
                            continue;

                        foreach($roles as $role_id => $res)
                        {
                            foreach($res['staff_ids'] as $staff_id)
                            {
                                $db->bind(':event_id', $event_id);
                                $db->bind(':reservation_id', $res['reservation_id']);
                                $db->bind(':member_id', $res['member_id']);
                                $db->bind(':staff_id', $staff_id);
                                $db->bind(':role_id', $role_id);
                                $db->bind(':cause', $type);
                                $db->execute();
                            }
                        }
                    }
                }
            }
        }


        return true;
    }
}