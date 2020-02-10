<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/05/2019
 * Time: 10:57
 */

namespace JCT;


use JCT\Helper;
use Exception;
use DateTime;
use JCT\Database;

class upload
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

    private $uploaded_year_starting;
    private $current_year_starting;
    private $year_start;
    private $year_end;

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


            // check the type of upload, and serve data to relevant function

            if(empty($this->data['upload_type']))
                throw new Exception('No upload_type detected.');

            // merge files with the accompanying data

            $files_w_properties = $this->merge_files_w_properties();
            if(isset($files_w_properties['error']))
                throw new Exception($files_w_properties['error']);

            if(empty($files_w_properties[0]['year_starting']))
                throw new Exception('No year_starting property found.');

            $this->uploaded_year_starting = $files_w_properties[0]['year_starting'];
            $tmp = $this->set_calendar();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);


            $upload_type = $this->data['upload_type'];
            switch($upload_type)
            {
                case("eoy_reports_upload"):

                    $tmp = $this->eoy_reports_upload($files_w_properties);
                    if(isset($tmp['error']))
                        throw new Exception($tmp['error']);

                    break;
                default:
                    throw new Exception('Invalid upload_type detected.');
                    break;
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



    private function set_calendar()
    {
        try
        {
            $db = $this->_ORG_DB;
            $db->query(" SELECT setting_value FROM manager_setting WHERE ( setting_key = 'year_starting' ) ");
            $db->execute();
            $cur_year_starting = intval($db->fetchSingleColumn());

            if(empty($cur_year_starting))
                throw new Exception('Year starting not set in Manager settings.');

            $this->current_year_starting = $cur_year_starting;

            $db->query(" SELECT MIN(open_date) as year_start, MAX(open_date) as year_end 
                FROM calendar_open_date 
                WHERE ( year_starting = {$this->uploaded_year_starting} ) ");
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp['year_start']))
            {
                $now = new DateTime();
                $yr = intval($now->format('Y'));
                $mth = intval($now->format('m'));

                $yr_of_end = ($mth > 6) ? $yr + 1 : $yr;
                $yr_of_start = $yr_of_end - 1;

                $year_start = DateTime::createFromFormat('Y-m-d', $yr_of_start . '-08-01');
                $year_end = DateTime::createFromFormat('Y-m-d', $yr_of_end . '-06-29');
            }
            else
            {
                $year_start = DateTime::createFromFormat('Y-m-d', $tmp['year_start']);
                $year_end = DateTime::createFromFormat('Y-m-d', $tmp['year_end']);
            }

            $year_start->setTime(0,0,0);

            $year_end->modify('+1 day');
            $year_end->setTime(0,0,0);

            $this->year_start = $year_start->format('Y-m-d H:i:s');
            $this->year_end = $year_end->format('Y-m-d H:i:s');

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function normalize_files_array($files = [])
    {
        $arr = [];
        $n = 0;
        foreach($files as $i => $file)
        {
            if(!is_array($file['name']))
            {
                $arr[$n] = $file;
                $n++;
                continue;
            }

            foreach($file['name'] as $x => $name)
            {
                $arr[$n] = [
                    'name' => $name,
                    'type' => $file['type'][$x],
                    'tmp_name' => $file['tmp_name'][$x],
                    'error' => $file['error'][$x],
                    'size' => $file['size'][$x]
                ];
                $n++;
            }

        }

        return $arr;
    }

    private function merge_files_w_properties()
    {
        try
        {
            if(empty($_FILES))
                throw new Exception('There are no uploaded files detected.');

            if(empty($this->data['file_properties']))
                throw new Exception('No file properties detected.');

            $file_properties = $this->data['file_properties'];
            $files = $this->normalize_files_array($_FILES);

            $num_files = count($files);
            $num_properties = count($file_properties);

            if($num_files !== $num_properties)
                throw new Exception('File properties to files mis-match.');


            // merge files with properties

            $files_w_properties = [];
            foreach($file_properties as $fp)
            {
                $fp = array_change_key_case($fp, CASE_LOWER);

                $id = $fp['id'];
                if(!isset($files[ $id ]))
                    throw new Exception('No file found for properties (' . $id . ').');

                $fp['file'] = $files[$id];
                $files_w_properties[] = $fp;
            }

            return $files_w_properties;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function eoy_reports_upload($files_w_properties)
    {
        try
        {
            $db = $this->_ORG_DB;
            foreach($files_w_properties as $fp)
            {
                $fp_json = json_encode($fp); // for error reporting

                $member_ext_ids = array_map('intval', $fp['member_ids']);
                if(empty($member_ext_ids))
                    throw new Exception('No Member external IDs set for document: ' . $fp_json);

                $member_ext_ids_str = implode(',',$member_ext_ids);

                $db->query(" SELECT id FROM person WHERE ( ext_id IN ({$member_ext_ids_str}) AND is_member = 1 ) ");
                $db->execute();
                $member_ids = array_map('intval', $db->fetchAllColumn());
                if(empty($member_ids))
                    throw new Exception('No Member IDs set for document: ' . $fp_json);

                $member_ids_str = implode(',',$member_ids);



                // write to file

                $tmp_name = $fp['file']['tmp_name'];
                $hash_name = Helper::generate_random_string(55) . '.pdf';

                $path_to_file = JCT_DOCUMENT_PATH . $hash_name;
                $tmp = move_uploaded_file($tmp_name, $path_to_file);

                if($tmp === false)
                    throw new Exception('Uploaded document could not be saved: ' . $fp_json);


                // record properties

                $file_year_starting = (!empty($fp['year_starting'])) ? intval($fp['year_starting']) : $this->current_year_starting;
                $unique_for_year = (!isset($fp['unique_for_year'])) ? true : (!empty($fp['unique_for_year']));

                if($unique_for_year)
                {
                    // remove duplicate report for same year

                    $db->query(" SELECT d.id  
                    FROM document_target dt 
                    LEFT JOIN document d on dt.document_id = d.id 
                    WHERE (
                      d.type = 'eoy_report' AND 
                      d.year_starting = {$file_year_starting} AND 
                      dt.target_id IN ({$member_ids_str}) 
                    ) ");
                    $db->execute();
                    $existing_document_ids = $db->fetchAllColumn();

                    if(!empty($existing_document_ids))
                    {
                        $existing_document_ids = array_map('intval', $existing_document_ids);
                        $existing_document_ids_str = implode(',',$existing_document_ids);

                        // make sure document not being used for someone else in the meantime

                        $db->query(" SELECT DISTINCT target_id 
                        FROM document_target 
                        WHERE ( document_id IN ({$existing_document_ids_str}) ) ");
                        $db->execute();
                        $target_ids = $db->fetchAllColumn();
                        $target_ids = array_map('intval', $target_ids);

                        $diff = array_diff($target_ids, $member_ids);
                        $existing_export_id = 0;
                        if(empty($diff))
                        {
                            $db->query(" DELETE FROM document_target 
                            WHERE ( document_id IN ({$existing_document_ids_str}) ) ");
                            $db->execute();

                            $db->query(" SELECT export_id FROM document WHERE ( id IN ({$existing_document_ids_str}) ) LIMIT 0,1 ");
                            $db->execute();
                            $existing_export_id = $db->fetchSingleColumn();

                            $db->query(" DELETE FROM document WHERE ( id IN ({$existing_document_ids_str}) ) ");
                            $db->execute();
                        }
                        else
                        {
                            $db->query(" DELETE FROM document_target 
                            WHERE ( 
                              document_id IN ({$existing_document_ids_str}) AND 
                              target_id IN ({$member_ids_str}) 
                            ) ");
                            $db->execute();
                        }

                        if($existing_export_id)
                        {
                            $db = $this->_DB;
                            $db->query(" SELECT export_name FROM export_property WHERE ( id = {$existing_export_id} ) ");
                            $db->execute();
                            $old_export_name = $db->fetchSingleColumn();

                            $path_to_old = JCT_DOCUMENT_PATH . $old_export_name;
                            if(is_readable($path_to_old))
                                unlink($path_to_old);

                            $db->query(" UPDATE export_property SET last_action = 'deleted' WHERE ( id = {$existing_export_id} ) ");
                            $db->execute();

                            $db = $this->_ORG_DB;
                            $db->query(" DELETE FROM document WHERE ( export_id = {$existing_export_id} ) ");
                            $db->execute();
                        }

                    }
                }

                // save export / document data

                $db = $this->_DB;
                $db->query(" INSERT INTO export_property 
                ( export_name, app_slug, last_action, created_by, created, updated, updated_by ) VALUES 
                ( '{$hash_name}', 'ns_admin', 'uploaded', -1, NOW(), NOW(), -1 ) ");
                $db->execute();
                $export_id = $db->lastInsertId();

                $db = $this->_ORG_DB;
                $unique_for_year_token = ($unique_for_year) ? 1 : 0;
                $db->query(" INSERT INTO document 
                ( export_id, audience_type, audience_sub_type, 
                  title, status, type, year_starting, unique_for_year, meta, 
                 updated, updated_by ) VALUES 
                ( {$export_id}, 'guardians_of_member_ids', NULL, 
                  :title, 'visible', 'eoy_report', {$file_year_starting}, {$unique_for_year_token}, 
                 NULL, NOW(), -1 )");
                $db->bind(':title', $fp['file_name']);
                $db->execute();
                $document_id = $db->lastInsertId();

                $db->query(" INSERT INTO document_target 
                ( document_id, export_id, target_id, accessed ) VALUES 
                ( {$document_id}, {$export_id}, :member_id, 0 ) ");
                foreach($member_ids as $member_id)
                {
                    $db->bind(':member_id', $member_id);
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