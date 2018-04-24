<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 16/03/2018
 * Time: 12:23
 */
require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Helper.php';


$nums = [
    '0867345627','353867345627','03442 411 599','00443442 411 599','0443442 411 599'
];

$arr = [];
foreach($nums as $num)
{
    $normalised = \JCT\Helper::normalise_contact_number($num);
    $arr[$num] = $normalised;
}

\JCT\Helper::show($arr);