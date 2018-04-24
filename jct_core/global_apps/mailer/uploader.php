<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/08/2016
 * Time: 14:15
 */

require_once '../../../ds_core/config.php';
require_once '../../../ds_core/database_connection.php';
require_once '../../../ds_core/functions.php';

if(!isset($_GET['files']))
{
    echo json_encode(['success'=>'Files attached successfully']);
    exit();
}

$return  = [];
try
{
    $id = (!empty($_GET['files'])) ? (is_numeric($_GET['files'])) ? $_GET['files'] : 0 : 0;

    if(empty($id))
        throw new Exception('No Message ID accompanied the submitted attachments.');

    if(empty($_FILES))
        throw new Exception('No attachments were detected.');

    $target_dir = DS_PATH_MEDIA_ATTACHMENTS . $id;
    if(!is_dir($target_dir))
        mkdir($target_dir);

    if(!is_dir($target_dir))
        throw new Exception('No repository was created for this messages attachments.');

    $files = [];
    foreach($_FILES as $file)
    {
        if(move_uploaded_file($file['tmp_name'], $target_dir . JCT_DE . basename($file['name'])))
            $files[] = $target_dir . JCT_DE . $file['name'];
    }

    if(empty($files))
        throw new Exception('The submitted attachments were not saved.');


    /**
     * Here also delete any uploaded attachments for ANY EMAIL that are over 5 hours old.
     * This is garbage collection for users that unloaded their pages, without sending, after uploading attachments.
     */

    $d = dir(DS_PATH_MEDIA_ATTACHMENTS);
    while(false !== ($entry = $d->read()))
    {
        if($entry[0] == ".")
            continue;

        if(is_dir(DS_PATH_MEDIA_ATTACHMENTS . $entry))
        {
            $last_mod_time = filemtime(DS_PATH_MEDIA_ATTACHMENTS . $entry);
            if ( (time() - $last_mod_time) > (5 * 3600))
            {
                $m = new JCT\mailer\mailer_model();
                $c = new \JCT\mailer\mailer_controller($m);

                $c->delete_email_attachments($entry);
            }
        }
    }
    $d->close();

    $return = ['success'=>'ok', 'files'=>$files];
}
catch(Exception $e)
{
    $return = ['error'=>$e->getMessage()];
}

echo json_encode($return);
exit();