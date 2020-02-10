<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/12/2018
 * Time: 13:13
 */

require_once '../ds_core/classes/Helper.php';

$dir = getcwd();
$target_dir = '\\uploads\\';

$allowable_exts = [];

file_put_contents('upload.txt', print_r($_FILES, true));

if(empty($_FILES))
    exit;

try
{
    $uploaded = [];
    $not_uploaded = [];

    $params = ['name','type','tmp_name','error','size'];
    $files = [];
    foreach($_FILES as $upload_label => $f)
    {
        $max_index = count($f['name']) - 1;
        for($i = 0; $i <= $max_index; $i++)
            $files[$i] = [];

        foreach($params as $param)
        {
            for($i = 0; $i <= $max_index; $i++)
                $files[$i][$param] = $f[$param][$i];
        }
    }

    foreach($files as $file)
    {
        $name = $file['name'];
        $size = $file['size'];
        $tmp_name  = $file['tmp_name'];
        $type = $file['type'];

        $tmp = explode('.',$name);
        $ext = strtolower(end($tmp));

        /*if(! in_array($ext,$allowable_exts))
        {
            $not_uploaded[] = $name . ' is an invalid file type.';
            continue;
        }

        if($size > 2000000)
        {
            $not_uploaded[] = $name . ' is too large.';
            continue;
        }*/

        $target = $dir . $target_dir . basename($name);
        $status = move_uploaded_file($tmp_name, $target);

        if($status)
            $uploaded[] = $name;
        else
            $not_uploaded[] = $name;
    }

    $arr = [
        'uploaded' => $uploaded,
        'not_uploaded' => $not_uploaded
    ];

    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
}
catch(Exception $e)
{
    echo $e->getMessage();
}

