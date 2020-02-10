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
    $source_path = JCT_PATH_ROOT . 'api' . JCT_DE . 'sample_data' . JCT_DE . 'tp_data' . JCT_DE . 'csvs' . JCT_DE . 'tp_strands_w_gaeilge.csv';
    if(!is_readable($source_path))
        throw new Exception('File not found.');

    $translations = Helper::read_csv_to_array($source_path);

    $db = new Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'UTF8');
    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE tp_strand SET title_ga_IE = :ir WHERE title_en_GB = :en ");
        foreach($translations as $tr)
        {
            $db->bind(':ir', $tr['title']);
            $db->bind(':en', $tr['strand']);
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