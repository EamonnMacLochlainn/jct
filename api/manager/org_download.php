<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 20/03/2019
 * Time: 10:39
 */

namespace JCT;


use Exception;
use JCT\Database;
use JCT\Helper;

class org_download
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
    private $last_update;
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
            array_change_key_case($this->input, CASE_LOWER);
            if (empty($this->input['data']))
                throw new Exception('No data detected.');

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
            $this->last_update = $data['last_update'];

            $now = new \DateTime();
            $this->now = $now;

            $tmp = $this->download();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $this->success = 1;
            $this->error = 0;
            $this->response = $tmp;
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

    private function download()
    {
        $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, JCT_DB_CI_HOST, 'utf8');
        try
        {
            $database = JCT_PREFIX . '_core';

            $db->query(" SELECT uo.id, sr.title as role, IFNULL(u.email, '') as email, IFNULL(u.mobile,'') as mobile, uo.ext_id    
            FROM {$database}.user_org uo 
            LEFT JOIN {$database}.prm_staff_role sr on uo.role_id = sr.id 
            LEFT JOIN {$database}.user u on uo.id = u.id 
            WHERE ( uo.guid = :guid AND u.updated > :last_update ) ");
            $db->bind(':guid', $this->guid);
            $db->bind(':last_update', $this->last_update);
            $db->execute();
            $users_raw = $db->fetchAllAssoc();

            $users = [];
            $index_to_id = [];
            $index = 0;

            $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

            $db->query(" SELECT fname, lname FROM {$database}.person WHERE ( id = :id ) ");
            foreach($users_raw as $u)
            {
                if(empty($u['ext_id']))
                    continue;

                $db->bind(':id', $u['id']);
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $u['fname'] = $tmp['fname'];
                $u['lname'] = $tmp['lname'];

                $users[$index] = $u;

                $index_to_id[ $u['id'] ] = $index;
                $index++;
            }

            $db->query(" SELECT id FROM {$database}.person WHERE ( is_guardian = 1 AND updated > :last_update ) ");
            $db->bind(':last_update', $this->last_update);
            $db->execute();
            $guardian_ids = $db->fetchAllColumn();

            foreach($guardian_ids as $guardian_id)
            {
                $database = JCT_PREFIX . '_org_' . strtolower($this->guid);

                $db->query(" SELECT id 
                FROM {$database}.member_guardian 
                WHERE ( guardian_id = :guardian_id ) ");
                $db->bind(':guardian_id', $guardian_id);
                $db->execute();
                $ward_ids = $db->fetchAllColumn();

                if(empty($ward_ids))
                    continue;

                $ward_ids_str = implode(',',$ward_ids);

                $database = JCT_PREFIX . '_core';

                $db->query(" SELECT ext_id FROM {$database}.user_org WHERE ( id IN ({$ward_ids_str}) ) ");
                $db->execute();
                $ward_ext_ids = $db->fetchAllColumn();

                $index = $index_to_id[ $guardian_id ];
                $users[ $index ]['ward_ext_ids'] = $ward_ext_ids;
            }

            return $users;
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
}