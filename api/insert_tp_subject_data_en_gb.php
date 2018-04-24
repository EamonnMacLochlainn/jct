<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/12/2017
 * Time: 16:51
 */


namespace JCT;
setlocale(LC_ALL, 'ga_IE');
header('Content-type: text/html; charset=utf-8');

use Exception;

$status = [];
$time_start = microtime(true);

function fgetcsvUTF8(&$handle, $length, $separator = ';')
{
    if (($buffer = fgets($handle, $length)) !== false)
    {
        $buffer = autoUTF($buffer);
        return str_getcsv($buffer, $separator);
    }
    return false;
}

/**
 * automatic convertion windows-1250 and iso-8859-2 info utf-8 string
 *
 * @param   string  $s
 *
 * @return  string
 */
function autoUTF($s)
{
    // detect UTF-8
    if (preg_match('#[\x80-\x{1FF}\x{2000}-\x{3FFF}]#u', $s))
        return $s;

    // detect WINDOWS-1250
    if (preg_match('#[\x7F-\x9F\xBC]#', $s))
        return iconv('WINDOWS-1250', 'UTF-8', $s);

    // assume ISO-8859-1
    return iconv('ISO-8859-1', 'UTF-8', $s);
}


try
{
    // load required

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Connection.php';
    require_once '../ds_core/classes/Helper.php';


    // check core database

    try
    {
        $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
        if($db->db_error)
            throw new Exception($db->db_error);

        $db->query("SET NAMES 'utf8'; SET CHARACTER SET utf8;" );
        $db->execute();
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to core Database: ' . $e->getMessage());
    }

    $status[] = 'Core database connection set';


    // get starting strand ID
    $db->query(" SELECT MAX(id) FROM databizs_core.tp_primary_strand WHERE 1 ");
    $db->execute();
    $ai_strand_id = intval($db->fetchSingleColumn()) + 1;


    // get starting unit ID
    $db->query(" SELECT MAX(id) FROM databizs_core.tp_primary_strand_unit WHERE 1 ");
    $db->execute();
    $ai_unit_id = intval($db->fetchSingleColumn()) + 1;


    // get starting objective ID
    $db->query(" SELECT MAX(id) FROM databizs_core.tp_primary_strand_unit_objective WHERE 1 ");
    $db->execute();
    $ai_objective_id = intval($db->fetchSingleColumn()) + 1;


    // get starting objective note ID
    $db->query(" SELECT MAX(id) FROM databizs_core.tp_primary_strand_unit_objective_note WHERE 1 ");
    $db->execute();
    $ai_objective_note_id = intval($db->fetchSingleColumn()) + 1;






    $locale = 'en_GB';
    $subject_name = 'visual_arts';
    $subject_id = 1;



    $_group_standards = [];
    $_strands = [];
    $_strand_units = [];
    $_strand_unit_objectives = [];
    $_objective_notes = [];


    $file_names = [];

    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE;
    $file_name = $locale . '_' . $subject_name . '.csv';

    // check without standard first
    if(is_readable($source_path . $file_name))
        $file_names[0] = $file_name;
    else
    {
        foreach (range(1, 2) as $standard_id) {
            $tmp_file_name = $locale . '_' . $subject_name . '_' . $standard_id . '.csv';
            if (is_readable($source_path . $tmp_file_name))
                $file_names[$standard_id] = $tmp_file_name;
        }
    }


    if(empty($file_names))
        throw new Exception('Could not find any data files for this subject (' . $file_name . ').');


    foreach ($file_names as $standard_id => $file_name)
    {
        $handle = fopen($source_path . $file_name, 'r');


        // write data rows to memory

        $row_data = [];

        $fields = [
            'Subject', 'Class', 'Strand', 'Strand Unit', 'Content Objectives', 'Further Information'
        ];



        $i = 0;
        while (($row = fgetcsv($handle, 4096, ';')) !== false)
        {
            if (empty($row))
                continue;

            if ($i === 0)
            {
                $i++;
                continue;
            }
            else
            {
                $row_id = $i - 1;
                foreach ($row as $k => $v)
                {
                    // some docs have separate rows per note, others
                    // append notes in further columns in that row
                    $further_information_index = 1;

                    if($k < 5)
                        $field = $fields[$k];
                    else
                    {
                        if(isset($fields[$k]))
                            $field = $fields[$k] . $further_information_index;
                        else
                        {
                            $further_information_index++;
                            $field = $fields[5] . $further_information_index;
                        }
                    }

                    $row_data[$row_id][$field] = $v;
                }
            }

            $i++;
        }
        fclose($handle);


        // now go through row data and normalise data


        // here we set out manually the string expressions per class standard
        $_group_standard_substring = [
            1 => 'infants',
            2 => 'infants',
            3 => '1st',
            4 => '2nd',
            5 => '3rd',
            6 => '4th',
            7 => '5th',
            8 => '6th'
        ];

        #echo $file_name . ': ' . mb_detect_encoding($row_data[1]['Subject']) . '<br/>';

        foreach ($row_data as $row_id => $row)
        {

            // set min / max group standard

            $class_string = strtolower(Helper::latinise_string($row['Class']));
            $tmp_group_standards = [];

            foreach($_group_standard_substring as $group_standard => $group_substring)
            {
                if(strpos($class_string, $group_substring) !== false)
                    $tmp_group_standards[] = $group_standard;
            }

            foreach($_group_standard_substring as $group_standard => $group_substring)
            {
                if(strpos($class_string, $group_substring) !== false)
                    $tmp_group_standards[] = $group_standard;
            }

            if(empty($tmp_group_standards))
                throw new Exception('Class string not recognised (' . $row['Class'] . ' => ' . $class_string . ').');

            $min_group_standard = min($tmp_group_standards);
            $max_group_standard = max($tmp_group_standards);

            $row_group_standard_key = $min_group_standard . ':' . $max_group_standard;
            if(!isset($_group_standards[ $standard_id ]))
                $_group_standards[ $standard_id ] = [];

            if(!isset($_group_standards[ $standard_id ][ $row_group_standard_key ]))
                $_group_standards[ $standard_id ][ $row_group_standard_key ] = [];

            #$row_data[$row_id]['standard'] = $standard_id;
            #$row_data[$row_id]['min_group_standard'] = $min_group_standard;
            #$row_data[$row_id]['max_group_standard'] = $max_group_standard;



            // set unique strands, get strand ID

            $strand_string = trim(strtolower($row['Strand']));

            if(!isset($_strands[ $standard_id ]))
                $_strands[ $standard_id ] = [];
            if(!isset($_strands[ $standard_id ][ $row_group_standard_key ]))
                $_strands[ $standard_id ][ $row_group_standard_key ] = [];

            if(!isset($_strands[ $standard_id ][ $row_group_standard_key ][ $strand_string ]))
            {
                $_strands[ $standard_id ][ $row_group_standard_key ][ $strand_string ] = [
                    'id' => $ai_strand_id,
                    'min_group_standard' => $min_group_standard,
                    'max_group_standard' => $max_group_standard
                ];
                $ai_strand_id++;
            }

            $row_strand_id = $_strands[ $standard_id ][ $row_group_standard_key ][ $strand_string ]['id'];
            #$row_data[$row_id]['strand_id'] = $row_strand_id;



            // set unique strand units, get unit ID

            $unit_string = trim(strtolower($row['Strand Unit']));

            if(!isset($_strand_units[ $standard_id ]))
                $_strand_units[ $standard_id ] = [];
            if(!isset($_strand_units[ $standard_id ][ $row_group_standard_key ]))
                $_strand_units[ $standard_id ][ $row_group_standard_key ] = [];
            if(!isset($_strand_units[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ]))
                $_strand_units[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ] = [];

            if(!isset($_strand_units[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $unit_string ]))
            {
                $_strand_units[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $unit_string ] = [
                    'id' => $ai_unit_id,
                    'min_group_standard' => $min_group_standard,
                    'max_group_standard' => $max_group_standard
                ];
                $ai_unit_id++;
            }

            $row_unit_id = $_strand_units[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $unit_string ]['id'];
            #$row_data[$row_id]['unit_id'] = $row_unit_id;



            // set unique strand unit objectives, get objective ID

            $objective_string = trim(strtolower($row['Content Objectives']));

            if(!isset($_strand_unit_objectives[ $standard_id ]))
                $_strand_unit_objectives[ $standard_id ] = [];
            if(!isset($_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ]))
                $_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ] = [];
            if(!isset($_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ]))
                $_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ] = [];
            if(!isset($_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $row_unit_id ]))
                $_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $row_unit_id ] = [];

            if(!isset($_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $row_unit_id ][$objective_string]))
            {
                $_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $row_unit_id ][ $objective_string ] = [
                    'id' => $ai_objective_id,
                    'min_group_standard' => $min_group_standard,
                    'max_group_standard' => $max_group_standard
                ];
                $ai_objective_id++;
            }

            $row_objective_id = $_strand_unit_objectives[ $standard_id ][ $row_group_standard_key ][ $row_strand_id ][ $row_unit_id ][ $objective_string ]['id'];
            #$row_data[$row_id]['objective_id'] = $row_objective_id;



            // set further information / notes per objective

            foreach(range(1, 10) as $further_information_index)
            {
                $key = 'Further Information' . $further_information_index;
                if(!isset($row[ $key ]))
                    break;

                $note_string = trim(strtolower($row[ $key ]));
                if(empty($note_string))
                    break;

                if(!isset($_objective_notes[ $row_objective_id ]))
                    $_objective_notes[ $row_objective_id ] = [];

                $_objective_notes[ $row_objective_id ][] = [
                    'standard_id' => $standard_id,
                    'strand_id' => $row_strand_id,
                    'unit_id' => $row_unit_id,
                    'min_group_standard' => $min_group_standard,
                    'max_group_standard' => $max_group_standard,
                    'title' => $note_string
                ];
            }

        }

        $status[] = $i . ' rows parsed from ' . $file_name;
    }

    $status[] = $locale . ' data set';


    // begin writing to DB
    // for en_GB, as ga_IE will not be ready yet,
    // insert both title variations in english

    #Helper::show($_strand_unit_objectives);

    $db->beginTransaction();
    try
    {
        $db->query(" INSERT INTO tp_primary_strand
        ( id, subject_id, standard_id, min_group_standard, max_group_standard, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES  
        ( :id, {$subject_id}, :standard_id, :min_group_standard, :max_group_standard, :title, :title, 1, :weight, NOW(), -1 ) ");
        foreach($_strands as $standard_id => $group_standard_keys)
        {
            foreach($group_standard_keys as $group_standard_key => $strands)
            {
                $n = 0;
                foreach($strands as $strand_string => $strand_params)
                {
                    $db->bind(':id', $strand_params['id']);
                    $db->bind(':standard_id', $standard_id);
                    $db->bind(':min_group_standard', $strand_params['min_group_standard']);
                    $db->bind(':max_group_standard', $strand_params['max_group_standard']);
                    $db->bind(':title', $strand_string);
                    $db->bind(':weight', $n);
                    $db->execute();

                    $n++;
                }
            }
        }

        $db->query(" INSERT INTO tp_primary_strand_unit 
        ( id, subject_id, standard_id, min_group_standard, max_group_standard, strand_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES  
        ( :id, {$subject_id}, :standard_id, :min_group_standard, :max_group_standard, :strand_id, :title, :title, 1, :weight, NOW(), -1 ) ");
        foreach($_strand_units as $standard_id => $group_standard_keys)
        {
            foreach($group_standard_keys as $group_standard_key => $strands)
            {
                foreach($strands as $strand_id => $units)
                {
                    $n = 0;
                    foreach($units as $unit_string => $unit_params)
                    {
                        $db->bind(':id', $unit_params['id']);
                        $db->bind(':standard_id', $standard_id);
                        $db->bind(':strand_id', $strand_id);
                        $db->bind(':min_group_standard', $unit_params['min_group_standard']);
                        $db->bind(':max_group_standard', $unit_params['max_group_standard']);
                        $db->bind(':title', $unit_string);
                        $db->bind(':weight', $n);
                        $db->execute();

                        $n++;
                    }
                }
            }
        }

        $db->query(" INSERT INTO tp_primary_strand_unit_objective
            ( id, subject_id, standard_id, min_group_standard, max_group_standard, strand_id, unit_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES  
            ( :id, {$subject_id}, :standard_id, :min_group_standard, :max_group_standard, :strand_id, :unit_id, :title, :title, 1, :weight, NOW(), -1 ) ");
        foreach($_strand_unit_objectives as $standard_id => $group_standard_keys)
        {
            foreach($group_standard_keys as $group_standard_key => $strands)
            {
                foreach($strands as $strand_id => $units)
                {
                    foreach($units as $unit_id => $objectives)
                    {
                        $n = 0;
                        foreach($objectives as $objective_string => $objective_params)
                        {
                            $db->bind(':id', $objective_params['id']);
                            $db->bind(':standard_id', $standard_id);
                            $db->bind(':strand_id', $strand_id);
                            $db->bind(':unit_id', $unit_id);
                            $db->bind(':min_group_standard', $objective_params['min_group_standard']);
                            $db->bind(':max_group_standard', $objective_params['max_group_standard']);
                            $db->bind(':title', $objective_string);
                            $db->bind(':weight', $n);
                            $db->execute();

                            $n++;
                        }
                    }
                }
            }
        }

        $db->query(" INSERT INTO tp_primary_strand_unit_objective_note 
            ( subject_id, standard_id, min_group_standard, max_group_standard, strand_id, unit_id, objective_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES 
            ( {$subject_id}, :standard_id, :min_group_standard, :max_group_standard, :strand_id, :unit_id, :objective_id, :title, :title, 1, :weight, NOW(), -1 ) ");
        foreach($_objective_notes as $objective_id => $notes)
        {
            $n = 0;
            foreach($notes as $i => $params)
            {
                $db->bind(':standard_id', $params['standard_id']);
                $db->bind(':min_group_standard', $params['min_group_standard']);
                $db->bind(':max_group_standard', $params['max_group_standard']);
                $db->bind(':strand_id', $params['strand_id']);
                $db->bind(':unit_id', $params['unit_id']);
                $db->bind(':objective_id', $objective_id);
                $db->bind(':title', $params['title']);
                $db->bind(':weight', $n);
                $db->execute();
                $n++;
            }
        }

        $db->commit();
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage());
    }




    foreach($status as $s)
        echo $s . "<br/>";

    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Total Execution Time: '.$execution_time.' seconds';
}
catch(Exception $e)
{
    $time_end = microtime(true);
    $status[] = $e->getMessage();

    foreach($status as $s)
        echo $s . "<br/>";

    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Transaction failed. Total Execution Time: '.$execution_time.' seconds';
}