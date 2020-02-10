<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */


#exit();
use JCT\Mailer;

$status = [];
$time_start = microtime(true);
try
{
    // load required

    require_once '../../ds_core/Config.php';
    require_once '../../ds_core/classes/Database.php';
    require_once '../../ds_core/classes/Connection.php';
    require_once '../../ds_core/classes/Cryptor.php';
    require_once '../../ds_core/classes/Helper.php';
    require_once '../../ds_core/classes/Mailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'exception.php';

    $org_guid = '19374W';
    $org_db_name = 'databizs_org_' . strtolower($org_guid);
    $subject = 'Annual School Report 2018-19';




    // check core database

    try
    {
        $_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, JCT_DB_SIUD_NAME, JCT_DB_SIUD_HOST, 'utf8');
        if($_DB->db_error)
            throw new Exception($_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to core Database: ' . $e->getMessage());
    }

    $status[] = 'Core database connection set';


    // check org database

    try
    {
        $_ORG_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, 'localhost', 'UTF8');
        if(!empty($_ORG_DB->db_error))
            throw new Exception($_ORG_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }

    #\DS\Helper::show($org_db_name);
    $status[] = 'Organisation database connection set';


    /*$db = $_DB;

    $db->query(" SELECT org_name, blurb FROM org_details WHERE guid = :guid ");
    $db->bind(':guid', $org_guid);
    $db->execute();
    $tmp = $db->fetchSingleAssoc();

    $org_name = $tmp['org_name'];
    $org_blurb = $tmp['blurb'];*/

    $db = $_ORG_DB;


    /*$db->query(" SELECT * FROM email_account WHERE 1 LIMIT 0,1 ");
    $db->execute();
    $mailer_settings = $db->fetchSingleAssoc();

    $mail_server = $mailer_settings['mail_server'];
    $mail_user = $mailer_settings['username'];
    $mail_from = $mailer_settings['username'];
    $mail_from_name = $mailer_settings['username'];
    $mail_reply_to = $mailer_settings['username'];
    $mail_pass = \DS\Cryptor::Decrypt($mailer_settings['mail_password']);
    $mail_port = $mailer_settings['mail_port'];

    $mail_use_smtp = ($mailer_settings['type'] == 'SMTP');
    $mail_smtp_auth = ($mailer_settings['smtp_auth'] == 1);
    $mail_smtp_encryption = $mailer_settings['smtp_encryption'];

    $status[] = 'Organisation Email Settings set';*/



    $db->query(" SELECT DISTINCT id FROM member_group_class WHERE ( in_group_end IS NULL ) ");
    $db->execute();
    $member_ids = $db->fetchAllColumn();

    $guardians = [];
    $not_informed = [];
    $db->query(" SELECT guardian_id, email
    FROM member_guardian mg
    LEFT JOIN person p on mg.guardian_id = p.id
    WHERE ( mg.id = :id AND guardian_end IS NULL )
    ORDER BY ( email IS NOT NULL ) DESC, ( mg.is_default = 1 ) DESC
    LIMIT 0,1 ");
    foreach($member_ids as $id)
    {
        $db->bind(':id', $id);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        $email = trim($tmp['email']);
        if(!empty($email))
            $guardians[ $tmp['guardian_id'] ] = $email;
        else
            $not_informed[] = $id;
    }

    $status[] = 'Guardian emails retrieved';

    $email_body = <<<EOS
<p>Dear Parents/Guardians,</p>
<p>The Annual School Reports will be ready for each family on <strong>Friday, 14th June 2019</strong>. We will be using our in-school database provider DataBiz Solutions to make these reports available to you. All parents are invited to login into the DataBiz Solutions online portal to view their child&rsquo;s report.</p>
<p>The link for the DataBiz Solutions website is: <a href="https://databizsolutions.ie">https://databizsolutions.ie</a></p>
<p>Once there, the login form is available through the 'DataBiz Apps' link from the menu at the top of your screen.</p>
<p>For parents who have already used DataBiz Apps you can login as before.</p>
<p>For parents who have <strong>not</strong> already used DataBiz Apps, you will need to request a password in order to be able to log in:</p>
<ul>
<li>When presented with the login form, click the 'Sign Up' option</li>
<li>Enter your email address (i.e. this email address) and mobile number in the pop-up screen and click 'Send Request'</li>
<li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder)</li>
</ul>
<p><strong>Please Note:</strong> <em>Requesting a password requires you to provide your email address and mobile number in combination. Please use the same email address / mobile number you provided to the School as your contact information - different details will not be recognised. When only a partial match is made to your email address / mobile number, the registration form will provide you with a hint as to the correct details to use.</em></p>
<p><strong>Once you have successfully logged in, please select the 'My Family' icon at the centre of the home page to view your child&rsquo;s Annual School Report 2018-19.</strong></p>
<p>Please contact the school if you are experiencing any difficulties.</p>
<p>Kind regards,<br />Anne Kernan</p>
EOS;

    /*$root_path = DS_PATH_APPS . 'assets' . DS_DE . 'templates' . DS_DE . 'email' . DS_DE;
    $html_template = file_get_contents($root_path . 'html' . DS_DE . 'default.html');
    $html_footer = <<<EOS
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center" style="padding: 10px;font-family: sans-serif;font-size: 10px;">
        Powered by <a href="#" style="color: #ffffff">DataBiz Solutions</a>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding: 20px 20px 10px 20px;color: #fff;font-family: sans-serif;font-size: 10px;text-align: justify;">
            <p>This email is intended only for the addressee named above and may contain confidential or privileged information. If you are not the named addressee or the person responsible for delivering the message to the named addressee, please be kind enough to delete the message and notify us via <a href="mailto:info@databizsolutions.ie">info@databizsolutions.ie</a>. Any unauthorised use (including disclosure, publication, copying or distribution) of the email or its attachments is prohibited. If you contact us by email, we may store your name and address to facilitate communication.</p>
            <p>We take reasonable precautions to ensure that our emails are virus free. However, we accept no responsibility for any virus transmitted by us and recommend that you subject any incoming email to your own virus checking procedures.</p>
            <p>Córais Sonraí Limited (t\a Databiz Solutions) is a registered limited company (413633) in Ireland having its registered offices at Ard Iosef, Moycullen, Co. Galway, Ireland</p>
        </td>
    </tr>

</table>
EOS;*/


    /*$icon = null;
    if(is_readable(DS_PATH_MEDIA . $org_guid . DS_DE . 'assets' . DS_DE . 'crest.png'))
    {
        $icon = '<table width="80" align="left" border="0" cellpadding="0" cellspacing="0"><tr>';
        $icon.= '<td height="80" style="padding: 0 10px 10px 0;">';
        $icon.= '<img src="' . DS_URL_MEDIA . $org_guid . '/assets/crest.png" width="80" height="80" border="0" alt="logo" />';
        $icon.= '</td>';
        $icon.= '</tr></table>';
    }

    $html_template = strtr($html_template,
        [
            '$_SUBJECT'=>$subject,
            '$_ORG_CREST'=>$icon,
            '$_ORG_NAME'=>$org_name,
            '$_ORG_BLURB'=>$org_blurb,
            '$_TITLE'=>$subject,
            '$_FOOTER'=>$html_footer
        ]
    );

    $email_content = str_replace('$_BODY_HTML', $email_body, $html_template);*/


    $mailer = new Mailer($_DB, $_ORG_DB, $org_guid);
    if(!empty($mailer->error))
        throw new Exception($mailer->error);


    $db->query(" SELECT * FROM email_account WHERE (id = 1) ");
    $db->execute();
    $source_account = $db->fetchSingleAssoc();
    $mailer->set_source_account($source_account);

    $mailer->subject = $subject;
    $mailer->set_document_header('default');
    $mailer->set_document_footer('default');
    $mailer->set_document_structure();

    $recipients = [];
    $guardians = array_slice($guardians, 0, 3);
    foreach($guardians as $id => $email)
    {
        $recipients[] = [
            'email' => 'eamonn@databizsolutions.ie',#$email,
            'document_body' => $email_body
        ];
    }

    $send_statuses = $mailer->send($recipients);
    if($send_statuses === false)
        throw new Exception('Email(s) failed to send: ' . $mailer->error);

    /*$mailer = new \PHPMailer\PHPMailer\PHPMailer();
    $now = new DateTime();
    $unsuccessful_recipients = [];
    $successful_recipients = [];



    $log_text = '';
    try
    {
        $mailer->IsSMTP();
        $mailer->SMTPDebug = 0;

        $mailer->Host = $mail_server;
        $mailer->Port = $mail_port;
        $mailer->SMTPSecure = $mail_smtp_encryption;
        $mailer->SMTPAuth = true;
        $mailer->Username = $mail_user;
        $mailer->Password = $mail_pass;

        $mailer->SetFrom($mail_user, $org_name);
        $mailer->FromName = $org_name;
        $mailer->Subject = $subject;
        $mailer->AddReplyTo($mail_user);

        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);

        $i = 0;
        foreach($guardians as $id => $email)
        {

            $mailer->Body = $email_content;

            #$mailer->addAddress($email);
            $mailer->addAddress('eamonn@databizsolutions.ie');
            if ($i % 10 == 0)
                $mailer->addBCC('eamonn@databizsolutions.ie');
            $sent =$mailer->send();

            $mail_status = (!$sent) ? $mailer->ErrorInfo : 'No error';
            if(!$sent)
                $unsuccessful_recipients[ $email ] = $mail_status;
            else
                $successful_recipients[] = $email;

            $log_text.= $now->format('H:i:s') . ' :: FROM ' . $mail_from . '; TO ' . $email . '; STATUS ' . $mail_status . PHP_EOL;
            $mailer->clearAllRecipients();
            $i++;
        }

        file_put_contents('email_status.txt', $log_text, FILE_APPEND);
    }
    catch(\PHPMailer\PHPMailer\Exception $e)
    {
        \DS\Helper::show($e);
        return false;
    }
    catch(Exception $e)
    {
        #$this->error = $e->getMessage();
        \DS\Helper::show($e);
        return false;
    }

    $status[] = count($successful_recipients) . ' Emails sent.';


    if(count($unsuccessful_recipients) > 0)
        throw new Exception('Email failed to send to the following addresses: ' . implode(', ', $unsuccessful_recipients));

    foreach($status as $s)
        echo $s . "<br/>";*/

    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Total Execution Time: '.$execution_time.' seconds';
}
catch(Exception $e)
{
    $time_end = microtime(true);
    $status[] = $e->getMessage();

    foreach($status as $s)
        echo $s . "<br/>";

    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo 'Transaction failed. Total Execution Time: '.$execution_time.' seconds';
}