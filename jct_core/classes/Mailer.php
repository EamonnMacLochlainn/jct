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

    private $mailer;

    private $server = 'jctregistration.ie';
    private $user = 'support@jctregistration.ie';
    private $from = 'support@jctregistration.ie';
    private $from_name = 'JCT Registration.ie';
    private $reply_to = 'support@jctregistration.ie';
    private $pass = 'MacLochlainn2016';
    private $port = 587;
    private $use_smtp = true;
    private $smtp_auth = true;
    private $smtp_encryption = 'tls';
    public $charset = 'UTF-8';

    private $recipients = [];

    // whether to send individual emails to each, or
    // include all recipients in one email
    public $send_as_group = false;

    // flat array of email addresses
    public $successful_recipients = [];
    // associative array of email addresses to mail errors
    public $unsuccessful_recipients = [];

    private $default_template = 'en_default';

    public $subject;
    public $attachments = [];



    public $error;


    function __construct(Database $default_db_connection, $purpose)
    {
        $this->_DB = $default_db_connection;

        $tmp = $this->set_mailer_params($purpose);
        if($tmp === false)
            return false;

        if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'exception.php'))
            require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'exception.php';
        else
            $this->error = 'Mailer Exception class not found.';

        if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php'))
            require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
        else
            $this->error = 'Mailer class not found.';

        if(is_readable(JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php'))
            require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';
        else
            $this->error = 'Mailer SMTP class not found.';

        $this->mailer = new PHPMailer(true);
        return true;
    }

    private function set_mailer_params($purpose)
    {
        if(!is_null($this->error))
            return false;

        $db = $this->_DB;
        try
        {
            $db->query(" SELECT * FROM mailer_parameters WHERE purpose = :purpose ");
            $db->bind(':purpose', $purpose);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            if(empty($tmp))
                throw new Exception('No Mailer details retrieved for this Purpose.');

            $this->server = $tmp['server'];
            $this->user = $tmp['username'];
            $this->from = $tmp['from_address'];
            $this->from_name = $tmp['from_address'];
            $this->reply_to = $tmp['reply_to_address'];
            $this->pass = Cryptor::Decrypt($tmp['password']);
            $this->port = $tmp['port'];

            $this->use_smtp = ($tmp['type'] == 'SMTP');
            $this->smtp_auth = (!empty($tmp['smtp_auth']));
            $this->smtp_encryption = $tmp['smtp_encryption'];

            Helper::write($this);
            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    function set_recipients($recipients = [])
    {
        $db = $this->_DB;
        try
        {
            if(empty($recipients))
                throw new Exception('No recipients defined.');

            $db->query(" SELECT CONCAT_WS(' ', fname, lname) AS full_name, salute_name, mode_of_communication, mode_of_email_content FROM person WHERE email = :email ");
            foreach($recipients as $email)
            {
                $db->bind(':email', $email);
                $db->execute();
                $tmp = $db->fetchSingleAssoc();

                $m_com = (!empty($tmp['mode_of_communication'])) ? $tmp['mode_of_communication'] : 'en';
                $m_ec = (!empty($tmp['mode_of_email_content'])) ? $tmp['mode_of_email_content'] : 'html';
                $salute_name = (!empty($tmp['salute_name'])) ? $tmp['salute_name'] : (!empty($tmp['full_name'])) ? $tmp['full_name'] : null;
                if($salute_name === null)
                    $salute_name = ($m_com === 'en') ? 'Educator' : 'Oideoir';

                $this->recipients[] = [
                    'email' => $email,
                    'mode_of_communication' => $m_com,
                    'mode_of_email_content' => $m_ec,
                    'salute_name' => $salute_name
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

    function fetch_templates($template = null)
    {
        $template = (is_null($template)) ? $this->default_template : trim(strtolower($template));
        $path_html = JCT_PATH_TEMPLATES . 'email' . JCT_DE . 'html' . JCT_DE . $template . '.html';
        $path_text = JCT_PATH_TEMPLATES . 'email' . JCT_DE . 'text' . JCT_DE . $template . '.txt';

        $html_template = $text_template = '';
        if(is_readable($path_html))
            $html_template = file_get_contents($path_html);
        if(is_readable($path_text))
            $text_template = file_get_contents($path_text);

        if( (empty($html_template)) && (empty($text_template)) )
            return ['error'=>'No template was found matching the supplied description.'];

        if(empty($html_template))
            $html_template = $text_template;

        if(empty($text_template))
            $text_template = $html_template;

        return ['html'=>$html_template, 'text'=>$text_template];
    }


    /**
     * recipients initially set by this model, as a flat
     * array with an 'email' value. Now it returns from the calling Controller,
     * which will have appended a 'content' parameter as well
     * e.g:
     * [
     *  'mode_oc_communication' => '',
     *  'mode_of_email_content' => '',
     *  'salute_name' => '',
     *  'email' => '',
     *  'content' => ''
     * ]
     * @param array $recipients
     * @return bool
     */
    function send(array $recipients)
    {
        if(empty($recipients))
            return false;

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

        $log_file_path = $log_dir_path . JCT_DE . $now->format('d');

        try
        {
            if($this->use_smtp)
                $this->mailer->IsSMTP();
            //$this->mailer->SMTPDebug = 2;
            //$this->mailer->SMTPSecure = $this->smtp_encryption;
            $this->mailer->SMTPAuth = ($this->use_smtp);

            $this->mailer->Host = $this->server;
            $this->mailer->Port = $this->port;
            $this->mailer->Username = $this->user;
            $this->mailer->Password = $this->pass;

            $this->mailer->SetFrom($this->from, 'FromEmail', 0);
            $this->mailer->FromName = $this->from_name;
            $this->mailer->Subject = (empty($this->subject)) ? 'JCT Registration.ie Mail' : $this->subject;
            if(!is_null($this->reply_to))
                $this->mailer->AddReplyTo($this->reply_to);

            $this->mailer->CharSet = $this->charset;
            $this->mailer->isHTML(true);

            if(!$this->send_as_group)
            {
                foreach ($recipients as $r)
                {
                    $this->mailer->Body = $r['content'];
                    $this->mailer->addAddress($r['email'], 'ToEmail');
                    $sent = $this->mailer->send();
                    $this->mailer->clearAllRecipients();

                    $status = (!$sent) ? $this->mailer->ErrorInfo : 'No error';
                    if(!$sent)
                        $this->unsuccessful_recipients[$r['email']] = [
                            'salute_name' => $r['salute_name'],
                            'error' => $status,
                        ];
                    else
                        $this->successful_recipients[$r['email']] = [
                            'salute_name' => $r['salute_name']
                        ];

                    $log_text.= $now->format('H:i:s') . ' :: FROM ' . $this->mailer->From . '; TO ' . $r['email'] . '; STATUS ' . $status . PHP_EOL;
                }
            }
            else
            {
                $this->mailer->SMTPKeepAlive = true;

                $i = 0;
                foreach ($recipients as $r)
                {
                    if(!$i === 0)
                        $this->mailer->Body = $r['content'];
                    $i++;

                    $this->mailer->addCC($r['email'], 'ToEmail');
                }

                $sent = $this->mailer->send();
                $this->mailer->clearAllRecipients();

                $status = (!$sent) ? $this->mailer->ErrorInfo : 'No error';
                if(!$sent)
                    $this->unsuccessful_recipients['group_email'] = [
                        'salute_name' => 'group_email',
                        'error' => $status,
                    ];
                else
                    $this->successful_recipients['group_email'] = [
                        'salute_name' => 'group_email'
                    ];

                $log_text.= $now->format('H:i:s') . ' :: FROM ' . $this->mailer->From . '; TO ' . 'group_email' . '; STATUS ' . $status . PHP_EOL;
            }

            file_put_contents($log_file_path, $log_text, FILE_APPEND);
            return true;
        }
        catch(Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }
}