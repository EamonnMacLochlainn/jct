<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 16:24
 */

namespace JCT\site;


use JCT\Helper;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class ContactController
{
    private $model;

    function __construct(ContactModel $model)
    {
        $this->model = $model;
        require_once JCT_PATH_CORE . 'classes/Cryptor.php';
        require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
        require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';
    }

    function submit_message($args)
    {
        require_once JCT_PATH_CORE . 'classes/Cryptor.php';
        //require_once '../../../ds_core/vendors/phpmailer/phpmailer.php';
        //require_once '../../../ds_core/vendors/phpmailer/smtp.php';

        require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
        require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';

        try
        {
            if(empty($args['name']))
                throw new Exception('No Name detected.');
            $name = (empty(preg_replace('/\s+/', '', $args['name']))) ? '' : ucwords(strtolower(trim($args['name'])));
            $name = filter_var($name, FILTER_SANITIZE_STRING);
            if(empty($name))
                throw new Exception('No Name detected.');

            if(empty($args['email']))
                throw new Exception('No Email detected.');
            $email = (empty(preg_replace('/\s+/', '', $args['email']))) ? '' : strtolower(trim($args['email']));
            if(empty($email))
                throw new Exception('No Email detected.');
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if(!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new Exception('Invalid Email detected.');

            if(empty($args['message']))
                throw new Exception('No Message detected.');
            $message = (empty(preg_replace('/\s+/', '', $args['message']))) ? '' : trim($args['message']);
            $message = filter_var($message, FILTER_SANITIZE_STRING);
            if(empty($message))
                throw new Exception('No Message detected.');
            $message = htmlentities($message);

            $captcha = $args['captcha'];
            $secret = '6Lf85EwUAAAAAMudPOk9Vz7FJ5fpcBL2Ivj_-yeg';
            $verify=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$captcha}");
            $captcha_success=json_decode($verify);
            if($captcha_success->success == false)
                throw new Exception('Invalid Captcha detected.');

            $number = preg_replace('/[^0-9+]/', '', $args['contact_number']);
            $number = preg_replace('/\s+/', '', $number);

            $callback = false;
            if(!empty($number))
                $callback = (!empty($args['contact_by_phone']));

            $subject = (empty(preg_replace('/\s+/', '', $args['subject']))) ? '' : ucwords(strtolower(trim($args['subject'])));
            $subject = filter_var($subject, FILTER_SANITIZE_STRING);






            try
            {
                $db = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
                if($db->db_error)
                    throw new Exception($db->db_error);
            }
            catch(Exception $e)
            {
                throw new Exception('Error in connecting to core Database: ' . $e->getMessage());
            }





            $db->query(" SELECT mailer_params FROM org_details WHERE guid = 'DATABIZ' ");
            $db->execute();
            $tmp = $db->fetchSingleColumn();

            if(empty($tmp))
                throw new Exception('No Mailer details retrieved.');

            $mailer_settings = json_decode($tmp, true);

            if(empty($mailer_settings['server']))
                throw new Exception('No mail server defined for this Organisation.');
            if(empty($mailer_settings['user']))
                throw new Exception('No mail server user defined for this Organisation.');
            if(empty($mailer_settings['pass']))
                throw new Exception('No mail server password defined for this Organisation.');
            if(empty($mailer_settings['port']))
                throw new Exception('No mail server port defined for this Organisation.');
            if(empty($mailer_settings['type']))
                throw new Exception('No mail server type defined for this Organisation.');
            if(empty($mailer_settings['smtp_auth']))
                throw new Exception('Use of SMTP authentication has not been defined for this Organisation.');
            if(empty($mailer_settings['smtp_encryption']))
                throw new Exception('No SMTP encryption type defined for this Organisation.');

            $mail_server = $mailer_settings['server'];
            $mail_user = $mailer_settings['user']; // no-reply@databizsolutions.ie
            $mail_from = $mailer_settings['user'];
            $mail_from_name = 'Website Contact Form';
            $mail_reply_to = $mailer_settings['user'];
            $mail_pass = \JCT\Cryptor::Decrypt($mailer_settings['pass']);
            $mail_port = $mailer_settings['port'];

            $mail_use_smtp = ($mailer_settings['type'] == 'SMTP');
            $mail_smtp_auth = ($mailer_settings['smtp_auth'] == 'true');
            $mail_smtp_encryption = $mailer_settings['smtp_encryption'];


            $root_path = JCT_PATH_APPS . 'assets' . JCT_DE . 'templates' . JCT_DE . 'email' . JCT_DE;
            $html = file_get_contents($root_path . 'html' . JCT_DE . 'default.html');


            $d = new \DateTime();
            $datetime_str = $d->format('l, \t\h\e j\t\h \o\f F, \a\t G:i a');

            $body_html = <<<EOS
<p style="margin: 0 0 1rem">A new message was received via the <a href="https://databizsolutions.ie">databizsolutions.ie</a> contact form, on $datetime_str:</p>
<p style="margin: 0 0 0.5rem">From: $name</p>
<p style="margin: 0 0 0.5rem">Email: $email</p>
<p style="margin: 0 0 2rem">Phone: $number</p>
<p style="margin: 0 0 0.5rem">$message</p>
EOS;

            if($callback)
                $body_html.= '<p style="font-weight: bold">This contact has requested a phone call-back.</p>';

            $icon = '<table width="80" align="left" border="0" cellpadding="0" cellspacing="0"><tr>';
            $icon.= '<td height="80" style="padding: 0 10px 10px 0;">';
            $icon.= '<img src="http://databizsolutions.ie/ds_media/DATABIZ/assets/crest.png" width="80" height="80" border="0" alt="" />';
            $icon.= '</td>';
            $icon.= '</tr></table>';

            $html = strtr($html,
                [
                    '$_SUBJECT'=>$subject,
                    '$_ORG_CREST'=>$icon,
                    '$_ORG_NAME'=>'DataBiz Solutions',
                    '$_ORG_BLURB'=>'Website Contact Form',
                    '$_TITLE'=>$subject,
                    '$_BODY_HTML'=>$body_html
                ]
            );



            $mailer = new PHPMailer();
            $mailer->IsSMTP();

            $mailer->Host = $mail_server;
            $mailer->Port = $mail_port;
            $mailer->SMTPSecure = $mail_smtp_encryption;
            $mailer->SMTPAuth = true;
            $mailer->Username = $mail_user;
            $mailer->Password = $mail_pass;

            $mailer->SetFrom($mail_user, $mail_from_name);
            $mailer->FromName = $mail_from_name;
            $mailer->Subject = $subject;
            $mailer->AddReplyTo($mail_user);

            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(true);


            $mailer->Body = $html;

            $mailer->addAddress('info@databizsolutions.ie');
            $sent = $mailer->send();

            if(!$sent)
                throw new Exception($mailer->ErrorInfo);
        }
        catch(Exception $e)
        {
            return ['error'=>'Your message was not submitted due to the following error: ' . $e->getMessage()];
        }

        return ['success'=>1];
    }
}