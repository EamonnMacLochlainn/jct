<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/11/2017
 * Time: 17:19
 */


#exit();
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
    $event_id = 2;

    $confirmation_day_id = 4;

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

    $org_db->query(" SELECT title FROM pm_event WHERE id = :event_id ");
    $org_db->bind(':event_id', $event_id);
    $event_title = $org_db->fetchSingleColumn();

    if(empty($event_title))
        throw new Exception('No Event title was found matching the supplied ID (' . $event_id . ')');

    $status[] = 'Event Title set';


    // get event days

    $org_db->query(" SELECT id, venue, slot_mins, DATE_FORMAT(day_date, '%d-%m-%Y') AS `date`  
        FROM pm_day 
        WHERE ( event_id = :event_id ) 
        ORDER BY day_date ASC ");
    $org_db->bind(':event_id', $event_id);
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();

    if(empty($tmp))
        throw new Exception('No days were found matching the supplied event ID (' . $event_id . ')');

    $day_details = [];
    foreach($tmp as $i => $t)
    {
        $day_details[ $t['id'] ] = [
            'date' => $t['date'],
            'venue' => $t['venue'],
            'slot_mins' => $t['slot_mins'],
            'slots' => []
        ];
    }

    $status[] = 'Event Days set';


    // get event slots

    $org_db->query(" SELECT id, TIME_FORMAT(slot_start, '%H:%i') AS `start`,  TIME_FORMAT(slot_end, '%H:%i') AS `end` 
            FROM pm_slot 
            WHERE ( day_id = :day_id AND event_id = :event_id ) 
            ORDER BY slot_start ASC ");
    foreach($day_details as $day_id => $d)
    {
        $org_db->bind(':event_id', $event_id);
        $org_db->bind(':day_id', $day_id);
        $org_db->execute();
        $tmp = $org_db->fetchAllAssoc('id');

        if(empty($tmp))
            throw new Exception('No time slots were found matching the supplied IDs (Day: ' . $day_id . ', Event: ' . $event_id . ')');

        $day_details[$day_id]['slots'] = $tmp;
    }

    $status[] = 'Event Slots set';





    // get event reservations

    $org_db->query(" SELECT r.id AS reservation_id, day_id, slot_id, pairing_id, member_id 
        FROM pm_reservation r 
        LEFT JOIN pm_pairing p ON ( r.pairing_id = p.id ) 
        WHERE ( r.event_id = :event_id AND r.day_id = :day_id ) 
        ORDER BY day_id ASC, slot_id ASC ");
    $org_db->bind(':event_id', $event_id);
    $org_db->bind(':day_id', $confirmation_day_id);
    $org_db->execute();
    $raw_reservations = $org_db->fetchAllAssoc('reservation_id');

    if(empty($raw_reservations))
        throw new Exception('No Reservations were retrieved');

    $status[] = count($raw_reservations) . ' Reservations retrieved';




    // get person details per reservation
    // sort reservations by guardian

    $guardian_reservations = [];
    foreach($raw_reservations as $res_id => $r)
    {

        # match day id

        if(!isset($day_details[ $r['day_id'] ]))
            throw new Exception('Reservation (ID ' . $res_id . ') gathered with invalid Day ID: ' . json_encode($r));

        $day = $day_details[ $r['day_id'] ];
        $slots = $day['slots'];

        # match slot id

        if(!isset($slots[ $r['slot_id'] ]))
            throw new Exception('Reservation (ID ' . $res_id . ') gathered with invalid Slot ID: ' . json_encode($r));

        $slot = $slots[ $r['slot_id'] ];



        $org_db->query(" SELECT staff_id FROM pm_pairing_staff WHERE ( pairing_id = :pairing_id ) ");
        $org_db->bind(':pairing_id', $r['pairing_id']);
        $org_db->execute();
        $staff_ids = $org_db->fetchAllColumn();

        if(!in_array($staff_id_causing_replacement, $staff_ids))
            continue;

        $staff_ids_str = implode(',',$staff_ids);

        $org_db->query(" SELECT p.id, fname, lname, salute_name, s.title AS salutation FROM person p LEFT JOIN prm_salutation s ON ( p.salt_id = s.id ) WHERE p.id IN ( {$staff_ids_str} ) ");
        $org_db->execute();
        $raw_staff = $org_db->fetchAllAssoc();

        $staff = [];
        foreach($raw_staff as $i => $s)
            $staff[ $s['id'] ] = \JCT\Helper::determine_displayed_name($s);


        $org_db->query(" SELECT CONCAT_WS(' ', fname, lname) AS pupil_name FROM person WHERE ( id = :id ) ");
        $org_db->bind(':id', $r['member_id']);
        $org_db->execute();
        $pupil_name = $org_db->fetchSingleColumn();




        $res = [
            'id' => $res_id,
            'pupil_name' => $pupil_name,
            'date' => $confirmation_day_id,
            'venue' => $day['venue'],
            'slot_mins' => $day['slot_mins'],
            'slot_start' => $slot['start'],
            'slot_end' => $slot['end'],
            'staff' => $staff
        ];




        # get guardian email

        $org_db->query(" SELECT guardian_id FROM member_guardian WHERE ( id = :id AND guardian_end IS NULL ) 
        ORDER BY (is_default = 1) DESC ");
        $org_db->bind(':id', $r['member_id']);
        $org_db->execute();
        $guardian_ids = $org_db->fetchAllColumn();

        $guardian_ids_str = implode(',',$guardian_ids);
        $org_db->query(" SELECT email FROM person WHERE ( id IN ({$guardian_ids_str}) AND email IS NOT NULL ) LIMIT 0,1 ");
        $org_db->execute();
        $email = $org_db->fetchSingleColumn();

        if(!isset($guardian_reservations[ $email ]))
            $guardian_reservations[ $email ] = [];

        $guardian_reservations[ $email ][ $res_id ] = $res;
    }


    $guardian_reservations = array_slice($guardian_reservations, 0, 2);


    \JCT\Helper::show($guardian_reservations);
    exit();






    /*<<<EOS
<h3 style="margin: 0;">$event_title</h3>
<p style="margin: 0 0 0.5rem">A Thuismitheoirí, a chairde,</p>
<p style="margin: 0 0 1rem;">This is an automated email, sent to you to confirm your time for meeting with Teacher.</p>
<p style="margin: 0;">the following [RESERVATIONS_STR] been made for you by School administrators:</p>
<ul>[RESERVATIONS]</ul>
<p style="text-align: center; font-weight: bold; margin-bottom:2rem;">Go raibh míle maith agat.</p>
<p><b>Mura féidir leat freastal ar an gcruinniú agus má tá duine eile in ann freastal i d’áit iarrtar ort anmúinteoir / oifig
a chur ar an eolas faoi seo le do thoil.</b><br/>
If you, the parent/guardian, is unable to attend the meeting at the arranged time and wish for someone else to represent you, we ask you let the office or Múinteoir know in advance.</p>
<p><b>Mura féidir leat freastal ar an gcruinniú in aon chor, déan cinnte de go gcuireann tú an múinteoir ar an eolas faoi.  Déanfar iarracht cruinniú eile a shocrú am eicint eile ach ní féidir seo a ghealúint. Is fearr freastal ar an lá.</b><br/>
If you are unable to attend on the day, please inform the teacher beforehand. The teacher will try to facilitate you on another day but this cannot be guaranteed. We strongly recommend making time for your parent/teacher meeting on the allocated time and day.</p>
<p><b>Iarrtar oraibh gan aon pháiste a thabhairt chun na scoile ag na cruinnithe.</b><br/>
Children may not be brought to the school or the meetings.</p>
EOS;*/


    /*
    <p><b>Mura féidir leat freastal ar an gcruinniú agus má tá duine eile in ann freastal i d’áit iarrtar ort anmúinteoir / oifig
    a chur ar an eolas faoi seo le do thoil.</b><br/>
    If you, the parent/guardian, is unable to attend the meeting at the arranged time and wish for someone else to represent you, we ask you let the office or Múinteoir know in advance.</p>
    <p><b>Mura féidir leat freastal ar an gcruinniú in aon chor, déan cinnte de go gcuireann tú an múinteoir ar an eolas faoi.  Déanfar iarracht cruinniú eile a shocrú am eicint eile ach ní féidir seo a ghealúint. Is fearr freastal ar an lá.</b><br/>
    If you are unable to attend on the day, please inform the teacher beforehand. The teacher will try to facilitate you on another day but this cannot be guaranteed. We strongly recommend making time for your parent/teacher meeting on the allocated time and day.</p>

    */

    $email_template = <<<EOS
<p style="margin: 0 0 0.5rem">Dear Parents / Guardians,</p>
<p style="margin: 0 0 1rem;">This is an automated email, sent to you to confirm your reserved [TIME_STR] for the following event:</p>
<h3 style="margin: 0;">$event_title</h3>
<p style="margin: 0;">The following [RESERVATIONS_STR] been made for you by School administrators:</p>
<ul>[RESERVATIONS]</ul>
<p style="text-align: center; font-weight: bold; margin-bottom:2rem;">Go raibh míle maith agat.</p>
EOS;


    $emails_content = [];
    foreach($guardian_reservations as $email => $reservations)
    {
        $h = $email_template;
        $time_str = (count($reservations) > 1) ? 'times' : 'time';
        $res_str = (count($reservations) > 1) ? 'reservations have' : 'reservation has';
        $h = str_replace('[TIME_STR]', $time_str, $h);
        $h = str_replace('[RESERVATIONS_STR]', $res_str, $h);


        $reservation_items = '';
        foreach($reservations as $res_id => $r)
        {
            $date_str = DateTime::createFromFormat('d-m-Y', $r['date'])->format('l jS \of F Y');
            $staff_str = implode(', and ', $r['staff']);

            $str = '<li>A meeting with ' . $staff_str;
            $str.= ' regarding ' . $r['pupil_name'] . ' on <b>' . $date_str . '</b> in ' . $r['venue'] . ' at ' . $r['slot_start'] . '. ';
            $str.= 'This meeting is scheduled to last ' . $r['slot_mins'] . ' minutes.</li>';
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

        /*$attachment_root = '../../ds_media/' . $org_guid . '/documents/attachments/';
        if(!is_dir)
            throw new Exception('Cannot find dir (' . $attachment_root . ').');

        $attachment_path = $attachment_root . 'pt_meeting_sen_notification_2018.pdf';
        if(!file_exists($attachment_path))
            throw new Exception('Cannot find attachment (' . $attachment_path . ').');*/

        $i = 0;
        foreach($emails_content as $email => $content)
        {
            $tmp = $html;
            $body = str_replace('$_BODY_HTML', $content, $tmp);

            $mailer->Body = $body;

            #$mailer->addAddress($email);
            $mailer->addAddress('eamonn@databizsolutions.ie');
            #$mailer->addAttachment($attachment_path,'PT Meeting SEN Notification',  $encoding = 'base64', $type = 'application/pdf');
            /*if ($i % 10 == 0)
                $mailer->addBCC('eamonn@databizsolutions.ie');*/
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
        #return true;
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