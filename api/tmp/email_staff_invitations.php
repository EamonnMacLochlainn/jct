<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */

$status = [];
$time_start = microtime(true);
try
{
    // load required

    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Connection.php';
    require_once '../ds_core/classes/Cryptor.php';
    require_once '../ds_core/classes/Helper.php';
    require_once '../ds_core/classes/Mailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'phpmailer.php';
    require_once JCT_PATH_CORE_VENDORS . 'phpmailer' . JCT_DE . 'smtp.php';

    $org_guid = '19374W';
    $org_name = 'Our Lady\'s Grove Primary School';
    $org_neon_pass = 'claudine';
    $event_id = 5;
    $day_details = [];




    // check core database

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

    $status[] = 'Core database connection set';




    // check roll number

    $org_guid = strtoupper($org_guid);

    $db->query(" SELECT id, org_name, blurb, host_name, db_name, active, mailer_params FROM org_details WHERE guid = :guid ");
    $db->bind(':guid', $org_guid);
    $db->execute();
    $tmp = $db->fetchSingleAssoc();

    if(empty($tmp))
        throw new Exception('Unrecognised organisation GUID');

    if(intval($tmp['active']) < 1)
        throw new Exception('Inactive organisation GUID');

    if(empty($tmp['host_name']))
        throw new Exception('Organisation host not found');

    if(empty($tmp['db_name']))
        throw new Exception('Organisation database name not found');

    $org_db_host = $tmp['host_name'];
    $org_db_name = $tmp['db_name'];

    $status[] = 'Organisation GUID set';

    if(empty($tmp['mailer_params']))
        throw new Exception('No Mailer details retrieved for this Organisation.');

    $org_name = $tmp['org_name'];
    $org_blurb = $tmp['blurb'];
    $mailer_settings = json_decode($tmp['mailer_params'], true);

    if(empty($org_name))
        throw new Exception('No name defined for this Organisation.');
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
    $mail_user = $mailer_settings['user'];
    $mail_from = $mailer_settings['user'];
    $mail_from_name = $mailer_settings['user'];
    $mail_reply_to = $mailer_settings['user'];
    $mail_pass = \JCT\Cryptor::Decrypt($mailer_settings['pass']);
    $mail_port = $mailer_settings['port'];

    $mail_use_smtp = ($mailer_settings['type'] == 'SMTP');
    $mail_smtp_auth = ($mailer_settings['smtp_auth'] == 'true');
    $mail_smtp_encryption = $mailer_settings['smtp_encryption'];


    $status[] = 'Organisation Email Settings set';




    // check org database

    try
    {
        $org_db = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, $org_db_host, 'UTF8');
        if(!empty($org_db->db_error))
            throw new Exception($org_db->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }

    #\DS\Helper::show($org_db);
    $status[] = 'Organisation database connection set';




    // get event title

    $org_db->query(" SELECT title FROM nsadmin_appointment_event WHERE id = :event_id ");
    $org_db->bind(':event_id', $event_id);
    $event_title = $org_db->fetchSingleColumn();

    if(empty($event_title))
        throw new Exception('No Event title was found matching the supplied ID (' . $event_id . ')');

    $status[] = 'Event Title set';




    // get email addresses

    $org_db->query(" SELECT p.email  
     FROM nsadmin_appointment_candidates c 
     LEFT JOIN person p ON ( c.id = p.id ) 
     WHERE c.event_id = {$event_id} ");
    $org_db->execute();
    $emails = $org_db->fetchAllColumn();

    $emails = ['elainemolyneaux@gmail.com'];

    if(empty($emails))
        throw new Exception('No email addresses were found.');

    $status[] = count($emails) . ' email addresses set';


    $root_path = JCT_PATH_APPS . 'assets' . JCT_DE . 'templates' . JCT_DE . 'email' . JCT_DE;
    $html = file_get_contents($root_path . 'html' . JCT_DE . 'en_default.html');


    $body_html = <<<EOS
<p style="margin: 0 0 0.5rem">Dear Staff Member,</p>
<p style="margin: 0 0 0.5rem; font-weight: bold">This is an automated email, informing you that Appointment times have been made available for the event '$event_title'.   
We ask that you make use of our online facility (instructions below) at the earliest opportunity in order to reserve a convenient time-slot for your own meeting.</p>
<p style="margin: 0 0 1rem">DataBiz Solutions have provided a pilot website to host this new facility while in the initial phase of testing. The website address is:</p>
<p style="margin: 0 0 1rem; font-weight: bold; text-align: center"><a href="https://databizsolutions.ie/databiz/">https://databizsolutions.ie/databiz</a></p>


<p style="margin: 0 0 0">There are two steps to follow:</p>
<ol style="margin: 0 0 1rem">
    <li>Register with the DataBiz Solutions website to receive a password</li>
    <li>Select an appointment time-slot that suits you, and click 'Save'</li>
</ol>
    
<p style="margin: 0 0 0; font-weight: bold;">To register with the DataBiz Solutions application:</p>
<ol style="margin: 0 0 1rem">
    <li>Follow the <a href="https://databizsolutions.ie/databiz/">link above</a> to access the DataBiz Solutions application</li>
    <li>Click on the 'Log In' option at the top of the screen</li>
    <li>When presented with the login form, click the 'Request a New Password' option</li>
    <li>Enter your email address (i.e. this email address) and mobile number in the pop-up screen and click 'Send Request'</li>
    <li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder)</li>
</ol>

<p style="margin: 0 0 0; font-weight: bold;">To log in and reserve your time-slot:</p>
<ol style="margin: 0 0 1rem">
    <li>Go back to the <a href="https://databizsolutions.ie/databiz/login/">login form</a> and enter your email address and the new password that you have just received in your email and click 'Log In'</li>
    <li>Choose the 'NS Admin' option</li>
    <li>Using the menu to the left of your screen (three black bars icon), navigate to Appointment Reservations</li>
</ol>

EOS;
    if(!empty($mail_from))
        $body_html.= '<p style="margin: 0;">Please contact <a href="mailto:' . $mail_from . '">' . $mail_from . '</a> if you have any concerns regarding the above.</p>';

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
EOS;

    $icon = '<table width="80" align="left" border="0" cellpadding="0" cellspacing="0"><tr>';
    $icon.= '<td height="80" style="padding: 0 10px 10px 0;">';
    $icon.= '<img src="http://databizsolutions.ie/databiz/ds_media/19374W/assets/crest.png" width="80" height="80" border="0" alt="logo" />';
    $icon.= '</td>';
    $icon.= '</tr></table>';

    $html = strtr($html,
        [
            '$_SUBJECT'=>$event_title,
            '$_ORG_CREST'=>$icon,
            '$_ORG_NAME'=>$org_name,
            '$_ORG_BLURB'=>$org_blurb,
            '$_TITLE'=>'Principal / Staff Meetings',
            '$_BODY_HTML'=>$body_html,
            '$_FOOTER'=>$html_footer
        ]
    );


    $mailer = new PHPMailer();
    $now = new DateTime();
    $unsuccessful_recipients = [];
    $successful_recipients = [];

    #$emails_content = array_slice($emails_content, 0, 2);

    try
    {
        $mailer->IsSMTP();
        #$mailer->SMTPDebug = 1;

        $mailer->Host = $mail_server;
        $mailer->Port = $mail_port;
        $mailer->SMTPSecure = $mail_smtp_encryption;
        $mailer->SMTPAuth = true;
        $mailer->Username = $mail_user;
        $mailer->Password = $mail_pass;

        $mailer->SetFrom($mail_user, $org_name);
        $mailer->FromName = $org_name;
        $mailer->Subject = $event_title;
        $mailer->AddReplyTo($mail_user);

        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);

        $i = 0;
        foreach($emails as $email)
        {
            $mailer->Body = $html;

            $mailer->addAddress($email);
            #$mailer->addAddress('eamonn@databizsolutions.ie');
            if ($i % 10 == 0)
                $mailer->addBCC('eamonn@databizsolutions.ie');
            $sent =$mailer->send();

            $mail_status = (!$sent) ? $mailer->ErrorInfo : 'No error';
            if(!$sent)
                $unsuccessful_recipients[ $email ] = $mail_status;
            else
                $successful_recipients[] = $email;

            #$log_text.= $now->format('H:i:s') . ' :: FROM ' . $this->mailer->From . '; TO ' . $email . '; STATUS ' . $status . PHP_EOL;
            $mailer->clearAllRecipients();
            $i++;
        }

        #file_put_contents($log_file_path, $log_text, FILE_APPEND);
        #return true;
    }
    catch(Exception $e)
    {
        #$this->error = $e->getMessage();
        \JCT\Helper::show($e);
        return false;
    }

    $status[] = count($successful_recipients) . ' Emails sent.';


    if(count($unsuccessful_recipients) > 0)
        throw new Exception('Email failed to send to the following addresses: ' . implode(', ', $unsuccessful_recipients));


















# texting

    /*$conn = ConnManager::get('DB');
    $q=$conn->prepare("	SELECT Username, DisplayName, Neon FROM authorisation WHERE ID = :id ");
    $q->execute(array('id'=>$_POST['ac_switch']));
    $r=$q->fetch(PDO::FETCH_ASSOC);
    $r=stripslashes_array($r);

    $_SESSION['aka'] = array();
    $_SESSION['aka']['ID'] = $_POST['ac_switch'];
    $_SESSION['aka']['Username'] = $r['Username'];
    $_SESSION['aka']['DisplayName'] = $r['DisplayName'];
    $_SESSION['aka']['Tbl'] = ascii_encrypt($r['Username']);
    $_SESSION['aka']['Password'] = $r['Neon'];

    $host = 'api.neonsolutions.ie';
    $port = 80;
    $message = 'Dear Parent/Guardian, please let us know if you have not received an email with details of your next Parent/Teacher meeting by 17/11/2017';

    $mobile_contacts = array_keys($mobile_contacts);
    #$mobile_contacts = ['353867345627'];
    $mobile_contacts_str = '';
    foreach($mobile_contacts as $i => $num)
    {
        $num = preg_replace("/[^0-9]/", "", $num);
        if(empty($num))
            continue;

        $tmp = '&to[' . $i . ']=' . $num;
        $mobile_contacts_str.= $tmp;
    }

    $send_str="user={$org_guid}&clipwd={$org_neon_pass}&text={$message}" . $mobile_contacts_str;
    $send_str_len = strlen($send_str);

    echo $send_str;
    die();

    $timeout = 120;
    $sock = @fsockopen("$host", $port, $errno, $errstr, $timeout);
    if(!$sock)
        throw new Exception("Unable to get Neon server status.");

    $out = sprintf("POST /sms.php");
    $out.=" HTTP/1.1\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out.= "Host: $host\r\n";
    $out.= "Content-Length: $send_str_len\r\n";
    $out.= "Connection: Close\r\n";
    $out.= "Cache-Control: no-cache\r\n\r\n";
    $out.= $send_str;
    fwrite($sock, $out);
    stream_set_blocking($sock, false);
    stream_set_timeout($sock, $timeout);
    $info = stream_get_meta_data($sock);
    $file = null;
    while (!feof($sock) && !$info['timed_out'])
    {
        $file.= fgets($sock, 4096);
        $info = stream_get_meta_data($sock);
    }

    $okresp = "OK: ";
    $errresp = "ERR: ";
    $return_str = null;
    $arr = preg_split("/\\r\\n|\\r|\\n/", $file);
    for ($i=0;$i < count($arr);$i++)
    {
        if (preg_match("/($errresp)|($okresp)/",$arr[$i]))
            $return_str = $arr[$i];
    }
    fclose($sock);


    // parse neon response

    $return_arr = explode(":", $return_str);
    if($return_arr[0] != 'OK')
        throw new Exception($return_arr[1]);

    $msg_id = trim($return_arr[1]);

    if(empty($msg_id))
        throw new Exception('No Msg. ID received. Message failed to send.');
    else
        $status[] = 'Messages sent successfully. Message ID ' . $msg_id;*/






















    foreach($status as $s)
        echo $s . "<br/>";

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