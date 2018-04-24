<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/08/2016
 * Time: 14:40
 */

namespace JCT\mailer;


use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class mailer_controller
{
    private $model;

    function __construct(mailer_model $model)
    {
        $this->model = $model;
    }

    function get_template_messages()
    {
        $tmp = $this->model->get_template_messages();

        if(!empty($tmp['error']))
            return ['error'=>'Organisation Template Emails could not be retrieved: ' . $tmp['error']];

        return $tmp;
    }

    function delete_attachment($args)
    {
        try
        {
            if(empty($args))
                throw new Exception('No data was received by the Controller.');

            if(empty($args['id']))
                throw new Exception('No Email ID was detected by the Controller.');

            if(empty($args['file_name']))
                throw new Exception('No file name was detected by the Controller.');

            $target_dir = DS_PATH_MEDIA_ATTACHMENTS . $args['id'];
            if(!is_dir($target_dir))
                throw new Exception('No repository of attachments was found for this Email ID.');

            $target_file = $target_dir . JCT_DE . $args['file_name'];
            if(!file_exists($target_file))
                throw new Exception('This attachment could not be found.');

            unlink($target_file);

            //delete dir if empty
            $fi = new FilesystemIterator($target_dir, FilesystemIterator::SKIP_DOTS);
            $num_remaining = iterator_count($fi);
            if(!$num_remaining)
                rmdir($target_dir);

            return ['success'=>'ok'];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function delete_email_attachments($args)
    {
        try
        {
            if(empty($args))
                throw new Exception('No data was received by the Controller.');

            if(empty($args['id']))
                throw new Exception('No Email ID was detected by the Controller.');

            $target_dir = DS_PATH_MEDIA_ATTACHMENTS . $args['id'];
            if(!is_dir($target_dir))
                return ['success'=>'ok'];

            foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $target_dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ), RecursiveIteratorIterator::CHILD_FIRST ) as $value )
                $value->isFile() ? unlink( $value ) : rmdir( $value );

            rmdir( $target_dir );

            return ['success'=>'ok'];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_self_email_addresses()
    {
        try
        {
            if(empty($this->model->user_id))
                throw new Exception('User ID could not be detected.');

            if(empty($this->model->user_org_guid))
                throw new Exception('User Organisation GUID could not be detected.');

            return $this->model->get_self_email_addresses();
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_to_email_addresses($args)
    {
        try
        {
            if(empty($args))
                throw new Exception('No data received by the Controller at ' . __LINE__);

            if(empty($args['to_ids']))
                throw new Exception('No ids detected for populating BCC field.');

            $ids = [];
            foreach($args['to_ids'] as $id)
            {
                if(!empty(intval($id)))
                    $ids[] = intval($id);
            }

            return $this->model->get_to_email_addresses($ids);
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function send_email($args)
    {
        try
        {
            if(empty($args))
                throw new Exception('No data was received by the controller at ' . __LINE__ );

            if(empty($args['id']))
                throw new Exception('No Email ID was detected.');

            $args['attachments'] = [];
            $path_to_attachments = DS_PATH_MEDIA_ATTACHMENTS . $args['id'];
            if(is_dir($path_to_attachments))
            {
                $tmp = array_diff( scandir($path_to_attachments), ['.', '..'] );
                if(!empty($tmp))
                {
                    $args['attachments']['path'] = $path_to_attachments;
                    $args['attachments']['file_names'] = [];
                    foreach($tmp as $t)
                        $args['attachments']['file_names'][] = $t;
                }
            }

            if(!isset($args['from']))
                throw new Exception('No ID was detected for the \'From\' field.');
            $sender_id = intval($args['from']);
            $tmp = $this->get_self_email_addresses();
            if(empty($tmp['emails'][$sender_id]))
                throw new Exception('The supplied sender ID could not be matched with an email address.');

            $args['from'] = $tmp['emails'][$sender_id];

            if(empty($args['bcc']))
                throw new Exception('No Emails were detected for the \'Bcc\' field.');

            $tmp = explode(',', $args['bcc']);
            $to = [];
            foreach($tmp as $t)
            {
                $e = strtolower(trim($t));
                if(!filter_var($e, FILTER_VALIDATE_EMAIL))
                    throw new Exception('An invalid email address (' . $t . ') was detected in the Bcc field.');

                $to[] = $e;
            }
            $args['bcc'] = $to;

            $args['subject'] = trim($args['subject']);
            $args['content'] = trim($args['content']);

            $tmp = $this->model->send_email($args);
            if(!empty($tmp['error']))
                throw new Exception($tmp['error']);

            $this->delete_email_attachments(['id'=>$args['id']]);

            return ['success'=>'ok'];
        }
        catch(Exception $e)
        {
            return ['error'=> 'Your email could not be sent: ' . $e->getMessage()];
        }
    }
}