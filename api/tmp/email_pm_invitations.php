<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */


exit();
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
    $event_id = 4;
    $family_size = 0;
    $day_details = [];
    $use_databiz_mail = false;




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




    // check roll number

    $org_guid = strtoupper($org_guid);
    $db = $_DB;

    $db->query(" SELECT id, org_name, blurb, host_name, db_name, active, mailer_params FROM org_details WHERE guid = :guid ");
    $db->bind(':guid', $org_guid);
    $db->execute();
    $tmp = $db->fetchSingleAssoc();

    if(empty($tmp))
        throw new Exception('Unrecognised organisation GUID');

    if(empty($tmp['org_name']))
        throw new Exception('No name defined for this Organisation.');

    if(intval($tmp['active']) < 1)
        throw new Exception('Inactive organisation GUID');

    if(empty($tmp['host_name']))
        throw new Exception('Organisation host not found');

    if(empty($tmp['db_name']))
        throw new Exception('Organisation database name not found');

    $org_db_host = $tmp['host_name'];
    $org_db_name = $tmp['db_name'];


    $org_name = $tmp['org_name'];
    $org_blurb = $tmp['blurb'];

    $status[] = 'Organisation GUID set';

    if(empty($tmp['mailer_params']))
    {
        if($use_databiz_mail)
        {
            $db->query(" SELECT mailer_params FROM org_details WHERE guid = 'DATABIZ' ");
            $db->bind(':guid', $org_guid);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();
        }
        else
            throw new Exception('No Mailer details retrieved for this Organisation.');
    }

    $mailer_settings = json_decode($tmp['mailer_params'], true);

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
        $_ORG_DB = new \JCT\Database(JCT_DB_SIUD_USER, JCT_DB_SIUD_PASS, $org_db_name, $org_db_host, 'UTF8');
        if(!empty($_ORG_DB->db_error))
            throw new Exception($_ORG_DB->db_error);
    }
    catch(Exception $e)
    {
        throw new Exception('Error in connecting to School\'s database: ' . $e->getMessage());
    }

    #\DS\Helper::show($org_db);
    $status[] = 'Organisation database connection set';



    $db = $_ORG_DB;

    // get event title

    $db->query(" SELECT title FROM pm_event WHERE id = :event_id ");
    $db->bind(':event_id', $event_id);
    $event_title = $db->fetchSingleColumn();

    if(empty($event_title))
        throw new Exception('No Event title was found matching the supplied ID (' . $event_id . ')');

    $status[] = 'Event Title set';


    // get event days

    $db->query(" SELECT id, venue, slot_mins, DATE_FORMAT(day_date, '%d-%m-%Y') AS `date`  
        FROM pm_day 
        WHERE ( event_id = :event_id ) 
        ORDER BY day_date ASC ");
    $db->bind(':event_id', $event_id);
    $db->execute();
    $tmp = $db->fetchAllAssoc();

    if(empty($tmp))
        throw new Exception('No days were found matching the supplied event ID (' . $event_id . ')');

    $day_details = [];
    $event_dates = [];
    foreach($tmp as $i => $t)
    {
        $event_dates[] = $t['date'];
        $day_details[ $t['id'] ] = [
            'date' => $t['date'],
            'venue' => $t['venue'],
            'slot_mins' => $t['slot_mins'],
            'slots' => []
        ];
    }

    $num_dates = count($event_dates);
    if($num_dates === 1)
        $dates_str = $event_dates[0];
    elseif($num_dates === 2)
        $dates_str = implode(' and ', $event_dates);
    else
    {
        $last_date = array_pop($event_dates);
        $dates_str = implode(', ', $event_dates);
        $dates_str.= ', and ' . $last_date;
    }

    $status[] = 'Event Days set';


    // get event slots

    $db->query(" SELECT id, TIME_FORMAT(slot_start, '%H:%i') AS `start`,  TIME_FORMAT(slot_end, '%H:%i') AS `end` 
            FROM pm_slot 
            WHERE ( day_id = :day_id AND event_id = :event_id ) 
            ORDER BY slot_start ASC ");
    foreach($day_details as $day_id => $d)
    {
        $db->bind(':event_id', $event_id);
        $db->bind(':day_id', $day_id);
        $db->execute();
        $tmp = $db->fetchAllAssoc('id');

        if(empty($tmp))
            throw new Exception('No time slots were found matching the supplied IDs (Day: ' . $day_id . ', Event: ' . $event_id . ')');

        $day_details[$day_id]['slots'] = $tmp;
    }

    $status[] = 'Event Slots set';






    // get involved pupil ids w/o reservations

    $db->query(" SELECT DISTINCT pairing_id FROM pm_pairing_staff WHERE ( event_id = {$event_id} AND discarded = 0 AND invalid = 0 ) ");
    $db->execute();
    $pairing_ids = $db->fetchAllColumn();

    if(empty($pairing_ids))
        throw new Exception('No Pupil meetings have been set up for this Event yet.');

    $db->query(" SELECT pairing_id FROM pm_reservation WHERE ( event_id = {$event_id} ) ");
    $db->execute();
    $reserved_pairing_ids = $db->fetchAllColumn();
    $reserved_pairing_ids = (empty($reserved_pairing_ids)) ? [] : $reserved_pairing_ids;

    $non_reserved_pairing_ids = array_diff($pairing_ids, $reserved_pairing_ids);
    $non_reserved_pairing_ids_str = implode(',', $non_reserved_pairing_ids);


    $pairing_ids_str = implode(',',$pairing_ids);
    $db->query(" SELECT DISTINCT member_id FROM pm_pairing WHERE ( event_id = {$event_id} AND id IN ({$non_reserved_pairing_ids_str}) ) ");
    $db->execute();
    $pupil_ids = $db->fetchAllColumn();


    $selection_ids = [];
    $selection_ids_by_family = [];
    $family_n = 0;
    foreach($pupil_ids as $pupil_id)
    {
        if(in_array($pupil_id, $selection_ids))
            continue;

        $db->query(" SELECT sibling_id FROM member_sibling WHERE ( id = {$pupil_id} AND sibling_end IS NULL ) ");
        $db->execute();
        $sib_ids = $db->fetchAllColumn();
        $sib_ids = (empty($sib_ids)) ? [] : $sib_ids;
        $sib_ids[] = $pupil_id;

        if($family_size > 0)
        {
            $num_sibs = count($sib_ids);
            if($family_size === 3)
            {
                if($num_sibs < $family_size)
                    continue;
            }
            else
            {
                if($num_sibs !== $family_size)
                    continue;
            }
        }

        $relevant_sib_ids = array_intersect($sib_ids, $pupil_ids);

        foreach($relevant_sib_ids as $s)
            $selection_ids[] = $s;

        $selection_ids_by_family[$family_n] = $relevant_sib_ids;
        $family_n++;
    }

    if(empty($selection_ids))
        throw new Exception('No Pupils were found for this Event matching this Pupil selection.');





    // get event pairings

    $guardian_meetings = [];
    $no_email = [];
    $n = 0;
    foreach($selection_ids_by_family as $i => $sibling_ids)
    {
        foreach($sibling_ids as $sib_id)
        {
            $db->query(" SELECT p.email 
            FROM member_guardian g 
            LEFT JOIN person p on g.guardian_id = p.id 
            WHERE ( g.id = {$sib_id} AND guardian_end IS NULL AND p.email IS NOT NULL ) ORDER BY g.is_default DESC ");
            $db->execute();
            $guardian_email = $db->fetchSingleColumn();

            if(empty($guardian_email))
                $no_email[$n] = [];

            $db->query(" SELECT CONCAT_WS(' ', fname, lname) AS pupil_name FROM person WHERE ( id = {$sib_id} ) ");
            $db->execute();
            $pupil_name = $db->fetchSingleColumn();

            $db->query(" SELECT id FROM pm_pairing WHERE ( member_id = {$sib_id} AND id IN ({$non_reserved_pairing_ids_str}) ) ");
            $db->execute();
            $sib_pairing_ids = $db->fetchAllColumn();

            foreach($sib_pairing_ids as $pairing_id)
            {
                $db->query(" SELECT staff_id FROM pm_pairing_staff WHERE ( pairing_id = {$pairing_id} ) ");
                $db->execute();
                $staff_ids = $db->fetchAllColumn();
                $staff_ids_str = implode(',',$staff_ids);

                $db->query(" SELECT p.id, fname, lname, salute_name, s.title AS salutation FROM person p LEFT JOIN prm_salutation s ON ( p.salt_id = s.id ) WHERE p.id IN ( {$staff_ids_str} ) ");
                $db->execute();
                $raw_staff = $db->fetchAllAssoc();

                $staff = [];
                foreach($raw_staff as $i => $s)
                    $staff[ $s['id'] ] = \JCT\Helper::determine_displayed_name($s);

                $res = [
                    'pupil_name' => $pupil_name,
                    'staff' => array_values($staff)
                ];

                if(!empty($guardian_email))
                {
                    if(!isset($guardian_meetings[ $guardian_email ]))
                        $guardian_meetings[ $guardian_email ] = [];

                    $guardian_meetings[ $guardian_email ][] = $res;
                }
                else
                    $no_email[$n][] = $res;
            }
        }

        $n++;
    }


    #\DS\Helper::show($selection_ids_by_family);
    #\DS\Helper::show($guardian_meetings);
    #\DS\Helper::show($no_email);

    #exit;





    $email_template = <<<EOS
<p style="margin: 0 0 0.5rem">Dear Parent / Guardian,</p>
<p style="margin: 0 0 0.5rem;"><b>Individual Educational Planning (IEP)</b> meetings will be held during the following days:</p>
<p style="margin: 0 0 0.5rem; font-weight: bold">Tuesday, 11th December to Friday, 14th December</p>
<p style="margin: 0 0 0.5rem">We are using our in-school database provider - <b>DataBiz Solutions</b> - to facilitate online booking of Parent / Teacher meetings for parents. 
We ask that you make use of this facility (instructions below) at the earliest opportunity in order to reserve a convenient time-slot for your own meeting(s). The website address is:</p>
<p style="margin: 0 0 1rem; font-weight: bold; text-align: center"><a href="https://databizsolutions.ie/">https://databizsolutions.ie</a></p>

<p style="margin:0">The following meetings are planned:</p>
<ul class="margin: 0 0 1rem;">[RESERVATIONS]</ul>

<p style="margin: 0 0 0">There are two steps to follow:</p>
<ol style="margin: 0 0 1rem">
    <li>Register with the DataBiz Solutions website to receive a password</li>
    <li>Log in and reserve your time-slot</li>
</ol>

<p style="margin: 0 0 0; font-weight: bold;">To register with the DataBiz Solutions application:</p>
<ol style="margin: 0 0 1rem">
    <li>Follow the <a href="https://databizsolutions.ie/">link above</a> to visit the DataBiz Solutions website</li>
    <li>Click on the 'DataBiz Apps' option at the top of the screen</li>
    <li>When presented with the login form, click the 'Sign Up' option</li>
    <li>Enter your email address (i.e. this email address) and mobile number in the pop-up screen and click 'Send Request'</li>
    <li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder)</li>
</ol>

<p style="margin: 0 0 0; font-weight: bold;">To log in and reserve your time-slot:</p>
<ol style="margin: 0 0 1rem">
    <li>Return to the login form and enter your email address and the new password that you have just received in your email and click 'Log In'</li>
    <li>Click on the Parent/Teacher meeting link provided in the 'Notifications' panel</li>
    <li>Follow the instructions on-screen to reserve your preferred meeting time</li>
</ol>

EOS;
    if(!empty($mail_from))
        $email_template.= '<p style="margin: 0;">Please contact <a href="mailto:' . $mail_from . '">' . $mail_from . '</a> if you have any concerns regarding the above.</p>';




    #$guardian_meetings = array_slice($guardian_meetings, 0, 4);


    $emails_content = [];
    foreach($guardian_meetings as $email => $meetings)
    {
        $h = $email_template;

        $reservation_items = '';
        foreach($meetings as $i => $r)
        {
            $staff = $r['staff'];
            $num_staff = count($staff);
            if($num_staff === 1)
                $staff_str = $staff[0];
            elseif($num_staff === 2)
                $staff_str = implode(' and ', $r['staff']);
            else
            {
                $last_staff = array_pop($staff);
                $staff_str = implode(', ', $staff);
                $staff_str.= ', and ' . $last_staff;
            }

            $str = '<li>A meeting with ' . $staff_str . ' regarding ' . $r['pupil_name'] . '.</li>';
            $reservation_items.= $str;
        }

        $h = str_replace('[RESERVATIONS]', $reservation_items, $h);
        $emails_content[ $email ] = $h;
    }

    $root_path = JCT_PATH_APPS . 'assets' . JCT_DE . 'templates' . JCT_DE . 'email' . JCT_DE;
    $html = file_get_contents($root_path . 'html' . JCT_DE . 'en_default.html');
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


    $icon = null;

    $icon = '<table width="80" align="left" border="0" cellpadding="0" cellspacing="0"><tr>';
    $icon.= '<td height="80" style="padding: 0 10px 10px 0;">';
    $icon.= '<img src="https://databizsolutions.ie/ds_media/' . strtoupper($org_guid) . '/assets/crest.png" width="80" height="80" border="0" alt="logo" />';
    $icon.= '</td>';
    $icon.= '</tr></table>';

    $html = strtr($html,
        [
            '$_SUBJECT'=>$event_title,
            '$_ORG_CREST'=>$icon,
            '$_ORG_NAME'=>$org_name,
            '$_ORG_BLURB'=>$org_blurb,
            '$_TITLE'=>$event_title,
            '$_FOOTER'=>$html_footer
        ]
    );


    $mailer = new \PHPMailer\PHPMailer\PHPMailer();
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
        $mailer->Subject = $event_title;
        $mailer->AddReplyTo($mail_user);

        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);

        $i = 0;
        foreach($emails_content as $email => $content)
        {
            $tmp = $html;
            $body = str_replace('$_BODY_HTML', $content, $tmp);

            $mailer->Body = $body;

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

            $log_text.= $now->format('H:i:s') . ' :: FROM ' . $mail_from . '; TO ' . $email . '; STATUS ' . $mail_status . PHP_EOL;
            $mailer->clearAllRecipients();
            $i++;
        }

        file_put_contents('email_status.txt', $log_text, FILE_APPEND);
    }
    catch(\PHPMailer\PHPMailer\Exception $e)
    {
        \JCT\Helper::show($e);
        return false;
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