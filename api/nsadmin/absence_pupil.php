<?php


namespace JCT;


use JCT\Database;
use JCT\Helper;
use Exception;
use DateTime;

class absence_pupil
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

            $this->data = array_change_key_case($data, CASE_LOWER);

            $absences = $this->parse_absences();
            if(isset($absences['error']))
                throw new Exception($absences['error']);

            $tmp = $this->update_absences($absences);
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

    private function parse_absences()
    {
        $absences = [];
        $reason_codes = ['A','B','C','D','E','F'];

        try
        {
            if(empty($this->data['absences']))
                throw new Exception('Absences container empty.');

            $db =  $this->_ORG_DB;
            $db->query(" SELECT id, ext_id FROM person WHERE ( is_member = 1 ) ");
            $db->execute();
            $id_map = $db->fetchAllAssoc('ext_id', true);

            foreach($this->data['absences'] as $i => $a)
            {
                $a = array_change_key_case($a, CASE_LOWER);

                if(empty($a['id']))
                    throw new Exception('Pupil uploaded without ID (no. ' . $i . ').');

                $member_ext_id = $a['id'];
                if(!isset($id_map[$member_ext_id]))
                    throw new Exception('Pupil uploaded with unrecognised (ID ' . $member_ext_id . ').');
                $member_id = $id_map[$member_ext_id];

                if(empty($a['absence_date']))
                    throw new Exception('Pupil uploaded without absence date (ID ' . $member_ext_id . ').');

                $date = DateTime::createFromFormat('Y-m-d', $a['absence_date']);
                if($date === false)
                    throw new Exception('Pupil uploaded with invalid absence date (ID ' . $member_ext_id . ').');

                $absence_date = $date->format('Y-m-d');
                $m = intval($date->format('m'));
                $y = intval($date->format('Y'));

                $year_starting = ($m < 7) ? ($y - 1) : $y;

                if(empty($a['reason_code']))
                    throw new Exception('Pupil uploaded without reason code (ID ' . $member_ext_id . ').');

                $reason_code = strtoupper(trim($a['reason_code']));
                if(!in_array($reason_code, $reason_codes))
                    throw new Exception('Pupil uploaded with invalid reason code (ID ' . $member_ext_id . ').');

                $additional_detail = (!empty($a['additional_detail'])) ? trim($a['additional_detail']) : null;

                if(!isset($absences[$member_id]))
                    $absences[$member_id] = [];

                $absences[$member_id][] = [
                    'year_starting' => $year_starting,
                    'absence_date' => $absence_date,
                    'reason_code' => $reason_code,
                    'additional_detail' => $additional_detail
                ];
            }

            return $absences;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function update_absences($absences)
    {
        $db = $this->_ORG_DB;
        $db->beginTransaction();
        try
        {
            $db->query(" INSERT INTO nsa_absence_pupil 
            ( member_id, year_starting, absence_date, reason_code, additional_detail, updated, updated_by ) VALUES 
            ( :member_id, :year_starting, :absence_date, :reason_code, :additional_detail, NOW(), -1 ) 
            ON DUPLICATE KEY UPDATE 
            reason_code = :reason_code, additional_detail = :additional_detail, updated = NOW(), updated_by = -1 ");
            foreach($absences as $id => $abs)
            {
                foreach($abs as $a)
                {
                    $db->bind(':member_id', $id);
                    $db->bind(':year_starting', $a['year_starting']);
                    $db->bind(':absence_date', $a['absence_date']);
                    $db->bind(':reason_code', $a['reason_code']);
                    $db->bind(':additional_detail', $a['additional_detail']);
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
}