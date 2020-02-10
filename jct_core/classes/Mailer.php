<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/07/2017
 * Time: 16:30
 */

namespace JCT;


use Exception;
use DateTime;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private $_DB;
    private $_ORG_DB;
    private $org_guid;

    private $mailer;
    public $subject;

    private $document_header;
    private $document_footer;
    private $document_structure;

    private $use_smtp = true;
    public $charset = 'UTF-8';

    private $mail_server;
    private $username;
    private $mail_password;
    private $mail_port;
    private $smtp_auth = true;
    private $smtp_encryption = 'tls';

    private $recipients = [];

    public $error;



    function __construct(Database $default_db_connection, Database $org_db_connection, $org_guid)
    {
        $this->_DB = $default_db_connection;
        $this->_ORG_DB = $org_db_connection;
        $this->org_guid = trim(strtoupper($org_guid));

        try
        {
            if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'exception.php'))
                require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'exception.php';
            else
                throw new Exception('Mailer Exception class not found.');

            if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php'))
                require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
            else
                throw new Exception('Mailer class not found.');

            if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php'))
                require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';
            else
                throw new Exception('Mailer SMTP class not found.');

            $this->mailer = new PHPMailer(true);
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
        }
    }


    function set_source_account($source_account)
    {
        $this->mail_server = (!empty($source_account['mail_server'])) ? $source_account['mail_server'] : '';
        $this->username = (!empty($source_account['username'])) ? $source_account['username'] : '';
        $this->mail_password = (!empty($source_account['mail_password'])) ? Cryptor::Decrypt($source_account['mail_password']) : '';
        $this->mail_port = (!empty($source_account['mail_port'])) ? $source_account['mail_port'] : '';
        $this->smtp_auth = (!empty($source_account['smtp_auth'])) ? (intval($source_account['smtp_auth']) === 1) : true;
        $this->smtp_encryption = (!empty($source_account['smtp_encryption'])) ? $source_account['smtp_encryption'] : 'STARTTLS';
    }


    function set_email_subject($subject)
    {
        $this->subject = filter_var($subject, FILTER_SANITIZE_STRING);
    }

    function set_document_header($header_html)
    {
        if($header_html === 'default')
            $header = $this->set_default_document_header();
        else
            $header = $header_html;

        $this->document_header = $header;

        // if structure already set, insert header into structure
        if($this->document_structure !== null)
            $this->document_structure = str_replace('[document_header]', $header, $this->document_structure);
    }

    private function set_default_document_header()
    {
        $crest = null;
        $contact_colspan = 1;
        if(is_readable(JCT_PATH_MEDIA . $this->org_guid . JCT_DE . 'assets' . JCT_DE . 'crest.png'))
        {
            $crest = '<td width="100px" valign="top" align="center" style="padding: 20px 0 20px 20px;"><img src="' . JCT_URL_MEDIA . $this->org_guid . '/assets/crest.png' . '" width="100%" height="auto" /></td>';
            $contact_colspan = 2;
        }

        $db = $this->_DB;
        $db->query(" SELECT org_name, blurb, public_contact, add1, add2, add3, add4, city_town, postcode, eircode, 
        co.title as `county_title`, co.attribute as `county_prefix`, show_county  
        FROM org_details o 
        LEFT JOIN prm_county co on o.county_id = co.id 
        WHERE guid = :guid ");
        $db->bind(':guid', $this->org_guid);
        $tmp = $db->fetchSingleAssoc();

        $org_name = '<h1 style="letter-spacing: -1px;color: #fff;margin: 0;line-height: 1em;">' . $tmp['org_name'] . '</h1>';
        $org_blurb = (empty($tmp['blurb'])) ? '' : '<p class="org-blurb"  style="color: #fff;margin: 0;">' . $tmp['blurb'] . '</p>';
        $address = '<p class="address" style="margin: 0;text-align: center;font-size: 0.8rem;padding: 0.5em 1em;background: #268a2a;">' . Helper::build_address($tmp, true) . '</p>';
        $public_contact_arr = (empty($tmp['public_contact'])) ? [] : json_decode($tmp['public_contact'], true);

        $contact = '';
        if( (!empty($public_contact_arr['email'])) || (!empty($public_contact_arr['landline'])) )
        {
            $contact = '<p class="contact" style="text-align: center;margin: 0;padding: 0.5em 1em;background: #306e32;color: #fff;font-size: 0.8rem;">';
            if(!empty($public_contact_arr['email']))
                $contact.= '<span style="display: inline-block;padding: 0 0.5em;"><a style="color: inherit;text-decoration: none;" href="mailto:' . $public_contact_arr['email'] . '">' . $public_contact_arr['email'] . '</a></span>';
            if(!empty($public_contact_arr['landline']))
                $contact.= '<span style="display: inline-block;padding: 0 0.5em;">' . $public_contact_arr['landline'] . '</span>';
            $contact.= '</p>';
        }

        $h = <<<EOS
            <table width="100%" cellpadding="0" cellspacing="0" border="0"> 
                <tr>  
                    $crest
                    <td valign="top" align="left" style="padding: 20px;">
                        $org_name
                        $org_blurb
                    </td>
                </tr>
                <tr>  
                    <td colspan="$contact_colspan">  
                        $address 
                        $contact
                    </td>
                </tr>
            </table>
EOS;
        return $h;
    }

    function set_document_footer($footer_html)
    {
        if($footer_html === 'default')
            $footer = $this->set_default_document_footer();
        else
            $footer = $footer_html;

        $this->document_footer = $footer;

        // if structure already set, insert footer into structure
        if($this->document_structure !== null)
            $this->document_structure = str_replace('[document_footer]', $footer, $this->document_structure);
    }

    private function set_default_document_footer()
    {
        $h = <<<EOS
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="center" style="padding: 10px;font-family: sans-serif;font-size: 10px;background: #34393f;color: #83909c;">
                        Powered by <a href="https://databizsolutions.ie" style="color: inherit;">DataBiz Solutions</a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 10px 20px 10px 20px;color: #fff;font-family: sans-serif;font-size: 10px;text-align: justify;">
                        <p>This email is intended only for the addressee named above and may contain confidential or privileged information. If you are not the named addressee or the person responsible for delivering the message to the named addressee, please be kind enough to delete the message and notify us via <a href="mailto:info@databizsolutions.ie" style="color:inherit;">info@databizsolutions.ie</a>. Any unauthorised use (including disclosure, publication, copying or distribution) of the email or its attachments is prohibited. If you contact us by email, we may store your name and address to facilitate communication.</p>
                        <p>We take reasonable precautions to ensure that our emails are virus free. However, we accept no responsibility for any virus transmitted by us and recommend that you subject any incoming email to your own virus checking procedures.</p>
                        <p>Córais Sonraí Limited (t\a Databiz Solutions) is a registered limited company (413633) in Ireland having its registered offices at Ard Iosef, Moycullen, Co. Galway, Ireland</p>
                    </td>
                </tr>
            </table>
EOS;
        return $h;
    }

    function set_document_structure()
    {
        // if subject/header/body/footer are already set, then insert them; else insert placeholder
        $email_subject = ($this->subject !== null) ? $this->subject : '[email_subject]';
        $document_header = ($this->document_header !== null) ? $this->document_header : '[document_header]';
        $document_footer = ($this->document_footer !== null) ? $this->document_footer : '[document_footer]';

        $h = <<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>$email_subject</title>
</head>

<body style="margin: 0; padding: 0;font-family: Arial, Helvetica, sans-serif;">

<table width="100%" bgcolor="#eee" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 20px 0 40px"> <!-- body padding --> 
        
            <table width="600" align="center" cellpadding="0" cellspacing="0" border="0"> <!-- center -->
                <tr>
                    <td>
                    
                        <table bgcolor="#ffffff" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px;"> <!-- document -->
                            <tr><td bgcolor="#4CAF50">$document_header</td></tr>
                            <tr><td bgcolor="#ffffff" style="padding: 50px 20px 100px;">[document_body]</td></tr>
                            <tr><td bgcolor="#44525f">$document_footer</td></tr>
                        </table> <!-- end document -->
        
                    </td>
                </tr>
            </table> <!-- end centering -->
        
        </td> <!-- end body padding-->
    </tr>
</table>

</body>
</html>
EOS;
        $this->document_structure = $h;
    }


    function send(array $recipients)
    {
        if(empty($recipients))
            return false;


        // init log

        $log_text = '';
        $now = new DateTime();
        $year = $now->format('Y');
        $month = $now->format('m');

        if(!is_dir(JCT_PATH_CORE . 'logs'))
            mkdir(JCT_PATH_CORE . 'logs');

        if(!is_dir(JCT_PATH_CORE . 'logs' . JCT_DE . 'email'))
            mkdir(JCT_PATH_CORE . 'logs' . JCT_DE . 'email');

        if(!is_dir(JCT_PATH_CORE . 'logs' . JCT_DE . 'email' . JCT_DE . $year))
            mkdir(JCT_PATH_CORE . 'logs' . JCT_DE . 'email' . JCT_DE . $year);

        $log_dir_path = JCT_PATH_CORE . 'logs' . JCT_DE . 'email' . JCT_DE . $year . JCT_DE . $month;
        if(!is_dir($log_dir_path))
            mkdir($log_dir_path);

        $log_file_path = $log_dir_path . JCT_DE . $now->format('d') . '.log';

        try
        {
            if($this->document_structure === null)
                throw new Exception('Document structure not set.');

            if($this->document_header === null)
                throw new Exception('Document header not set.');

            if($this->document_footer === null)
                throw new Exception('Document footer not set.');

            $this->mailer->isSMTP();
            $this->mailer->SMTPDebug = 0;

            $this->mailer->Host = $this->mail_server;
            $this->mailer->Port = $this->mail_port;
            $this->mailer->Username = $this->username;
            $this->mailer->Password = $this->mail_password;

            $this->mailer->SMTPAuth = $this->smtp_auth;
            if($this->smtp_auth)
                $this->mailer->SMTPSecure = $this->smtp_encryption;


           # $this->mailer->SetFrom($this->username, 'FromEmail', 0);
            $this->mailer->From = $this->username;
            $this->mailer->FromName = $this->username;
            $this->mailer->Subject = (empty($this->subject)) ? 'DataBiz Solutions Online' : $this->subject;
            $this->mailer->AddReplyTo($this->username);

            $this->mailer->CharSet = $this->charset;
            $this->mailer->isHTML(true);


            $send_statuses = [];
            foreach ($recipients as $r)
            {
                $target_email = $r['email'];

                $this->mailer->addAddress($target_email, $this->username);
                $this->mailer->Body = str_replace('[document_body]', $r['document_body'], $this->document_structure);

                $sent = $this->mailer->send();

                $status = (!$sent) ? $this->mailer->ErrorInfo : 'No error';
                $result = $now->format('H:i:s') . ' :: FROM ' . $this->mailer->From . '; TO ' . $target_email . '; STATUS ' . $status;

                $send_statuses[] = $result;
                $log_text.= $result . PHP_EOL;

                $this->mailer->clearAllRecipients();
            }

            file_put_contents($log_file_path, $log_text, FILE_APPEND);
            return $send_statuses;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }
































    private function set_mailer_params($guid, $purpose)
    {
        if(!is_null($this->error))
            return false;

        $tmp = $this->set_org_mailer_params_by_purpose($purpose);
        if($tmp === false)
        {
            $this->error = null;
            $tmp = $this->set_org_default_mailer_params($guid);

            if($tmp === false)
            {
                $this->error = null;
                $tmp = $this->set_core_default_mailer_params();
            }
        }
        return $tmp;
    }

    private function set_org_mailer_params_by_purpose($purpose)
    {
        $db = $this->_ORG_DB;
        try
        {
            $db->query(" SELECT * FROM mailer_parameters WHERE purpose = :purpose ");
            $db->bind(':purpose', $purpose);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp))
                throw new Exception('No Mailer details retrieved for this Purpose.');

            $this->mail_server = $tmp['server'];
            $this->username = $tmp['username'];
            $this->from = $tmp['from_address'];
            $this->from_name = $tmp['from_name'];
            $this->reply_to = $tmp['reply_to_address'];
            $this->mail_password = Cryptor::Decrypt($tmp['password']);
            $this->mail_port = $tmp['port'];

            $this->use_smtp = ($tmp['type'] == 'SMTP');
            $this->smtp_auth = (intval($tmp['smtp_auth']) === 1);
            $this->smtp_encryption = $tmp['smtp_encryption'];

            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function set_org_default_mailer_params($guid)
    {
        $db = $this->_DB;
        try
        {
            $db->query(" SELECT mailer_params FROM org_details WHERE guid = :guid ");
            $db->bind(':guid', $guid);
            $db->execute();
            $tmp = $db->fetchSingleColumn();

            if(empty($tmp))
                throw new Exception('No default Mailer details retrieved for this Organisation.');

            $json = json_decode($tmp, true);

            $this->mail_server = $json['server'];
            $this->username = $json['user'];
            $this->from = (!empty($json['from_address'])) ? $json['from_address'] : $json['user'];
            $this->from_name = (!empty($json['from_name'])) ? $json['from_name'] : $json['user'];
            $this->reply_to = (!empty($json['reply_to_address'])) ? $json['reply_to_address'] : $json['user'];
            $this->mail_password = Cryptor::Decrypt($json['pass']);
            $this->mail_port = $json['port'];

            $this->use_smtp = ($json['type'] == 'SMTP');
            $this->smtp_auth = ($json['smtp_auth'] === 'true');
            $this->smtp_encryption = $json['smtp_encryption'];

            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function set_core_default_mailer_params()
    {
        $db = $this->_DB;
        try
        {
            $db->query(" SELECT mailer_params FROM org_details WHERE guid = 'DATABIZ' ");
            $db->execute();
            $tmp = $db->fetchSingleColumn();

            if(empty($tmp))
                throw new Exception('No default Mailer details retrieved for this Organisation.');

            $json = json_decode($tmp, true);

            $this->mail_server = $json['server'];
            $this->username = $json['user'];
            $this->from = (!empty($json['from_address'])) ? $json['from_address'] : $json['user'];
            $this->from_name = (!empty($json['from_name'])) ? $json['from_name'] : $json['user'];
            $this->reply_to = (!empty($json['reply_to_address'])) ? $json['reply_to_address'] : $json['user'];
            $this->mail_password = Cryptor::Decrypt($json['pass']);
            $this->mail_port = $json['port'];

            $this->use_smtp = ($json['type'] == 'SMTP');
            $this->smtp_auth = ($json['smtp_auth'] === 'true');
            $this->smtp_encryption = $json['smtp_encryption'];

            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }




    function set_recipients($recipients, $reset = true)
    {
        try
        {
            if($reset)
                $this->recipients = [];

            if(empty($recipients))
                throw new Exception('No recipients detected.');

            foreach($recipients as $r)
            {
                if(empty($r['name']))
                    throw new Exception('No name detected for recipient');
                if(empty($r['email']))
                    throw new Exception('No email detected for recipient');

                $this->recipients[] = [
                    'name' => $r['name'],
                    'email' => $r['email']
                ];
            }

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=> $e->getMessage()];
        }
    }

    function set_recipients_by_email($recipients = [])
    {
        $db = $this->_DB;
        try
        {
            if(empty($recipients))
                throw new Exception('No recipients defined.');

            $db->query(" SELECT CONCAT_WS(' ', fname, lname) AS full_name, salute_name 
            FROM person WHERE email = :email ");
            foreach($recipients as $email)
            {
                $db->bind(':email', $email);
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $salute_name = (!empty($tmp['salute_name'])) ? $tmp['salute_name'] : (!empty($tmp['full_name'])) ? $tmp['full_name'] : null;

                $this->recipients[] = [
                    'email' => $email,
                    'name' => $salute_name
                ];
            }

            if(empty($this->recipients))
                throw new Exception('No recipients set.');

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>'Email recipients could not be set: ' . $e->getMessage()];
        }
    }

    function get_recipients()
    {
        return $this->recipients;
    }
}