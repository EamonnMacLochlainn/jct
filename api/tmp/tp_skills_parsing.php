<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/09/2018
 * Time: 15:28
 */



namespace JCT;

require_once '../../ds_core/Config.php';
require_once '../../ds_core/classes/Database.php';
require_once '../../ds_core/classes/Helper.php';

use Exception;
use JCT\Helper;

$academic_orders = [
    1 => 'JUNIOR INFANTS',
    2 => 'SENIOR INFANTS',
    3 => '1ST CLASS',
    4 => '2ND CLASS',
    5 => '3RD CLASS',
    6 => '4TH CLASS',
    7 => '5TH CLASS',
    8 => '6TH CLASS'
];

$academic_bands = [
    1 => 1,
    2 => 1,
    3 => 2,
    4 => 2,
    5 => 3,
    6 => 3,
    7 => 4,
    8 => 4
];

try
{
    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'csvs' . JCT_DE . 'skills_concepts_raw.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $raw = Helper::read_csv_to_array($source_path);
    #Helper::show($raw);

    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');
    $db->beginTransaction();
    try
    {
        $db->query(" INSERT INTO tp_skill_concept  
        ( subject_id, standard_id, academic_band, academic_order, title_en_GB, title_ga_IE, active, updated, updated_by ) VALUES 
        ( :subject_id, :standard_id, :academic_band, :academic_order, :title_en_GB, :title_ga_IE, 1, NOW(), -1 )  ");
        foreach($raw as $r)
        {
            $classes = explode('&', $r['classes']);
            $classes = array_map('strtoupper', $classes);
            $classes = array_map('trim', $classes);

            foreach($classes as $class)
            {
                if(empty($class))
                {
                    Helper::show($r);
                    continue;
                }

                $academic_order = array_search($class, $academic_orders);
                if($academic_order === false)
                {
                    Helper::show($r);
                    continue;
                }

                $academic_band = $academic_bands[ $academic_order ];

                $db->bind(':subject_id', $r['﻿subject_id']);
                $db->bind(':standard_id', $r['standard_id']);
                $db->bind(':academic_band', $academic_band);
                $db->bind(':academic_order', $academic_order);
                $db->bind(':title_en_GB', $r['english']);
                $db->bind(':title_ga_IE', $r['irish']);
                $db->execute();
            }
        }

        $db->commit();
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage());
    }

    echo 'done';
    return true;
}
catch(Exception $e)
{
    echo $e->getMessage();
}