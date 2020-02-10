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

try
{
    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'csvs' . JCT_DE . 'objective_translations.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $translations = Helper::read_csv_to_array($source_path);

    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');
    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE tp_objective SET title_en_GB = :en WHERE title_ga_IE = :ir ");
        foreach($translations as $i => $tr)
        {
            $db->bind(':ir', $tr['irish']);
            $db->bind(':en', $tr['﻿english']);
            $db->execute();
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