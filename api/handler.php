<?php

if(empty($_POST))
    die();

require_once 'password_compat.php';
date_default_timezone_set('UTC');
define('DS_REMOTE_PASS', '157963245785');

try
{

    if(empty($_POST['password']))
        throw new Exception('No Password detected.');

    if(empty($_POST['sub_dir']))
        throw new Exception('No sub directory detected.');

    if(empty($_POST['purpose']))
        throw new Exception('No purpose detected.');


    // validate

    $submitted_pass = $_POST['password'];

    $now = new DateTime();
    $pass_raw = JCT_REMOTE_PASS . $now->format('Ymd');
    //$pass = password_hash($pass_raw, CRYPT_BLOWFISH, ['cost'=>8]);

    //if(!password_verify($submitted_pass, $pass))
    if($pass_raw !== $submitted_pass)
        throw new Exception('Authentication failed.');


    // determine target

    $sub_dir = trim($_POST['sub_dir']);
    $sub_dir = rtrim($sub_dir, '/\\');
    $sub_dir = ltrim($sub_dir, '/\\');

    $root = __DIR__;
    $target = $root . '/' . $sub_dir;
    if(!is_dir($target))
        throw new Exception('Invalid target directory.');


    // determine purpose

    $purpose = trim(strtolower($_POST['purpose']));
    if(!in_array($purpose, ['upload','download','delete']))
        throw new Exception('Invalid purpose.');


    // do purpose

    switch($purpose)
    {
        case('upload'):

            if(empty($_FILES))
                throw new Exception('No file detected.');

            $target.= '/' . $_FILES['file']['name'];
            move_uploaded_file($_FILES['file']['tmp_name'], $target);

            break;
        case('upload_multiple'):



            break;
        case('delete'):

            if(empty($_POST['file_name']))
                throw new Exception('No file name detected.');

            $target.= '/' . $_POST['file_name'];
            $res = unlink($target);

            if($res === false)
                throw new Exception('File was not unlinked.');

            break;
        default:
        case('download'):

            throw new Exception('No download function is possible at this time.');

            break;
    }

    echo 'OK';
}
catch(Exception $e)
{
    echo $e->getMessage();
}
exit;
