<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 30/05/2018
 * Time: 11:25
 */



namespace JCT;

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Database.php';
require_once '../ds_core/classes/Helper.php';

use Exception;
use JCT\Helper;

try
{
    $band_orders = [
        1 => [1,2],
        2 => [3,4],
        3 => [5,6],
        4 => [7,8]
    ];
    $orders_band = [
        1 => 1, 2 => 1,
        3 => 2, 4 => 2,
        5 => 3, 6 => 3,
        7 => 4, 8 => 4
    ];

    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'tp_strands.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $strands_raw = Helper::read_csv_to_array($source_path);

    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'tp_units.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $units = Helper::read_csv_to_array($source_path);

    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'tp_objectives.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $objectives = Helper::read_csv_to_array($source_path);

    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'tp_notes.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $notes = Helper::read_csv_to_array($source_path);




    // notes

    $objective_notes = [];
    foreach($notes as $i => $n)
    {
        $objective_id = intval($n['objective_id']);
        $title = trim($n['title_en_gb']);

        if(!isset($objective_notes[$objective_id]))
            $objective_notes[$objective_id] = [];

        $objective_notes[$objective_id][] = $title;
    }



    // objectives

    $unit_objectives = [];
    foreach($objectives as $i => $o)
    {
        $id = intval($o['﻿id']);
        $unit_id = intval($o['unit_id']);
        $title = trim($o['title_en_gb']);
        $notes = (isset($objective_notes[$id])) ? $objective_notes[$id] : [];

        if(!isset($unit_objectives[$unit_id]))
            $unit_objectives[$unit_id] = [];

        $unit_objectives[$unit_id][] = [
            'title' => $title,
            'notes' => $notes
        ];
    }



    // units

    $strand_units = [];
    foreach($units as $i => $u)
    {
        $id = intval($u['﻿id']);
        $strand_id = intval($u['strand_id']);
        $title = trim($u['title_en_gb']);
        $objectives = (isset($unit_objectives[$id])) ? $unit_objectives[$id] : [];

        if(!isset($strand_units[$strand_id]))
            $strand_units[$strand_id] = [];

        $strand_units[$strand_id][] = [
            'title' => $title,
            'objectives' => $objectives
        ];
    }



    // strands

    $strands = [];
    $new_id = 1;
    $strand_id_map = [];
    foreach($strands_raw as $i => $s)
    {
        $id = intval($s['﻿id']);
        $subject_id = intval($s['subject_id']);
        $academic_band = intval($s['academic_band']);
        $title = trim($s['title']);
        $units = (isset($strand_units[$id])) ? $strand_units[$id] : [];

        $academic_orders = [];
        switch($subject_id)
        {
            case(4): // irish teanga 1
                $standard_id = 1;
                $academic_orders = $band_orders[$academic_band];
                break;
            case(13): // irish teanga 2
                $standard_id = 2;
                $subject_id = 4;
                if($academic_band < 5)
                    $academic_orders = $band_orders[$academic_band];
                else
                {
                    $o = $academic_band - 4;
                    $academic_orders = [$o];
                    $academic_band = $orders_band[$o];
                }
                break;
            case(6): // maths
                $standard_id = 0;
                $o = $academic_band - 4;
                $academic_orders = [$o];
                $academic_band = $orders_band[$o];
                break;
            default:
                $standard_id = 0;
                $academic_orders = $band_orders[$academic_band];
                break;
        }

        foreach($academic_orders as $o)
        {
            $strands[$new_id] = [
                'subject_id' => $subject_id,
                'standard_id' => $standard_id,
                'academic_band' => $academic_band,
                'academic_order' => $o,
                'title' => $title,
                'units' => $units
            ];

            $new_id++;
        }
    }



    // import

    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');

    $db->beginTransaction();
    try
    {
        $subject_strands = [];

        foreach($strands as $i => $s)
        {
            $subject_id = $s['subject_id'];
            if(!isset($subject_strands[$subject_id]))
                $subject_strands[$subject_id] = 0;
            else
                $subject_strands[$subject_id]++;

            $db->query(" INSERT INTO tp_strand 
            ( subject_id, standard_id, academic_band, academic_order, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES 
            ( :subject_id, :standard_id, :academic_band, :academic_order, :title, :title, 1, :weight, NOW(), -1 ) ");
            $db->bind(':subject_id', $s['subject_id']);
            $db->bind(':standard_id', $s['standard_id']);
            $db->bind(':academic_band', $s['academic_band']);
            $db->bind(':academic_order', $s['academic_order']);
            $db->bind(':title', $s['title']);
            $db->bind(':weight', $subject_strands[$subject_id]);
            $db->execute();
            $strand_id = $db->lastInsertId();

            if(empty($s['units']))
                continue;

            foreach($s['units'] as $ii => $u)
            {
                $db->query(" INSERT INTO tp_unit 
                ( strand_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES 
                ( {$strand_id}, :title, :title, 1, {$ii}, NOW(), -1 ) ");
                $db->bind(':title', $u['title']);
                $db->execute();
                $unit_id = $db->lastInsertId();

                if(empty($u['objectives']))
                    continue;

                foreach($u['objectives'] as $iii => $o)
                {
                    $db->query(" INSERT INTO tp_objective 
                    ( unit_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES 
                    ( {$unit_id}, :title, :title, 1, {$iii}, NOW(), -1 ) ");
                    $db->bind(':title', $o['title']);
                    $db->execute();
                    $objective_id = $db->lastInsertId();

                    if(empty($o['notes']))
                        continue;

                    $db->query(" INSERT INTO tp_note 
                    ( objective_id, title_en_GB, title_ga_IE, active, weight, updated, updated_by ) VALUES 
                    ( {$objective_id}, :title, :title, 1, :weight, NOW(), -1 ) ");
                    foreach($o['notes'] as $iiii => $note)
                    {
                        $db->bind(':weight', $iiii);
                        $db->bind(':title', $note);
                        $db->execute();
                    }
                }
            }
        }

        $db->commit();
        echo 'done';
    }
    catch(Exception $e)
    {
        $db->rollBack();
        Helper::show($strands);
        throw new Exception($e->getMessage());
    }


    return true;
}
catch(Exception $e)
{
    echo $e->getMessage();
}