<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/08/2016
 * Time: 14:40
 */

namespace JCT\mailer;


use Exception;
use JCT\Database;
use JCT\Core;

class mailer_model extends Core
{
    function __construct()
    {
        parent::__construct();
    }

    function get_template_messages()
    {
        $org_db = 'databizs_org_' . strtolower($this->user_org_guid);
        $db = new Database(null,null,$org_db);
        try
        {
            $db->query(" SELECT template_id, template_text FROM email_templates WHERE 1 ORDER BY template_id ASC ");
            $db->execute();
            $tmp = $db->fetchAllAssoc();

            $templates = [];
            foreach($tmp as $t)
                $templates[ $t['template_id'] ] = $t['template_text'];

            return ['success'=>'ok', 'templates'=>$templates];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_self_email_addresses()
    {
        $user_emails = [];
        $db = new Database();
        try
        {
            $db->query(" SELECT contact_detail 
            FROM user_contact 
            WHERE ( user_id = :user_id AND org_guid = :org_guid AND param = 'email' ) ");
            $db->bind(':user_id', $this->user_id);
            $db->bind(':org_guid', $this->user_org_guid);
            $db->execute();
            $user_emails[] = $db->fetchSingleColumn();

            if($this->user_org_rank < 20)
            {
                $db->query(" SELECT org_contact FROM org_details WHERE org_guid = :org_guid ");
                $db->bind(':org_guid', $this->user_org_guid);
                $db->execute();
                $tmp = $db->fetchSingleColumn();
                if(!empty($tmp))
                {
                    $arr = json_decode($tmp,true);
                    if(!empty($arr['email']))
                        $user_emails[] = $arr['email'][0];
                }
            }

            return ['success'=>'ok','emails'=>$user_emails];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    function get_to_email_addresses($ids)
    {
        $db = new Database();
        try
        {
            $id_str = implode(',',$ids);

            $sql = " SELECT DISTINCT contact_detail 
            FROM user_contact 
            WHERE ( param = 'email' AND user_id IN ({$id_str}) AND org_guid = :org_guid ) ";
            $db->query($sql);
            $db->bind(':org_guid', $this->user_org_guid);
            $db->execute();
            $tmp = $db->fetchAllColumn();

            if(empty($tmp))
                throw new Exception('No Email addresses were found for the selected IDs.');

            return ['success'=>'ok','emails'=>$tmp];
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
            if(!is_readable(DS_PATH_GLOBAL_APPS . 'mailer' . JCT_DE . 'phpmailer.php'))
                throw new Exception('Required PHPMailer class not found.');

            if(!is_readable(DS_PATH_GLOBAL_APPS . 'mailer' . JCT_DE . 'smtp.php'))
                throw new Exception('Required SMTP class not found.');

            require_once DS_PATH_GLOBAL_APPS . 'mailer' . JCT_DE . 'phpmailer.php';
            require_once DS_PATH_GLOBAL_APPS . 'mailer' . JCT_DE . 'smtp.php';

            $mail = new \PHPMailer();
            try
            {
                $mail->IsSMTP();
                $mail->CharSet = 'UTF-8';
                $mail->IsHTML(true);

                $mail->Host = "databizsolutions.ie";
                $mail->SMTPAuth = true;
                $mail->Username = "no-reply@databizsolutions.ie";
                $mail->Password = "mancini33";

                $mail->From = $args['from'];
                $mail->FromName = $args['from'];
                $mail->AddReplyTo($args['from']);
                $mail->Subject = $args['subject'];
                $mail->Body = $args['content'];

                foreach($args['bcc'] as $to)
                    $mail->addBCC($to);

                if(!empty($args['attachments']))
                {
                    $path = $args['attachments']['path'];
                    foreach($args['attachments']['file_names'] as $att)
                        $mail->addAttachment($path . JCT_DE . $att, $att);
                }

                $mail->Send();
                $mail->clearAllRecipients();

                //send copy to self
                $content = $this->build_archive_data($args['bcc']);
                $content.= $args['content'];

                $mail->From = 'no-reply@databizsolutions.ie';
                $mail->FromName = 'DataBiz Solutions Mailer';
                $mail->Subject = 'Email Sent: ' . $args['subject'];
                $mail->Body = $content;
                $mail->addAddress($args['from']);
                $mail->Send();
                $mail->clearAllRecipients();

                return ['success'=>'ok'];
            }
            catch(Exception $e)
            {
                throw new Exception($e->getMessage());
            }
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function build_archive_data($bcc)
    {
        $d = new \DateTime();
        $h = '<p style="font-weight:bold">The following email was sent by you on ' . $d->format('d/m/Y') . ' at ' . $d->format('H:i') . ', via DataBiz Solutions.</p>';

        $recipients_str = implode(', ', $bcc);
        $h.= '<p style="margin-bottom:16px">Recipients: ' . $recipients_str . '</p>';

        return $h;
    }
}