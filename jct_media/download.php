<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 18/04/2018
 * Time: 11:54
 */

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Helper.php';

if(!isset($_REQUEST['p']) || empty($_REQUEST['p']))
{
    header("HTTP/1.1 400 Bad Request");
    exit;
}

$file_name_raw  = urldecode($_REQUEST['p']);
$file_path = JCT_PATH_MEDIA . 'downloads' . JCT_DE . $file_name_raw;
$file_url = JCT_URL_MEDIA . 'downloads/' . $file_name_raw;
if(!is_file($file_path))
{
    header("HTTP/1.0 404 Not Found");
    exit;
}

$path_parts = pathinfo($file_path);
$file_name  = $path_parts['basename'];
$file_ext   = $path_parts['extension'];
clearstatcache();
$file_size  = filesize($file_path);

$content_type_default = 'application/octet-stream';
$content_types = [
    'exe' => 'application/octet-stream',
    'mde' => 'application/octet-stream',
    'dmg' => 'application/octet-stream',
    // more options as necessary
];
$content_type = isset($content_types[$file_ext]) ? $content_types[$file_ext] : $content_type_default;

header("Cache-Control: private");
header("Connection: Keep-Alive");
header("Content-Disposition: attachment; filename=\"$file_name\"");
header("Content-Length: $file_size");
header("Content-Type: " . $content_type);
header("Pragma: no-cache");
header("Content-Transfer-Encoding: Binary");

readfile($file_url);
exit;