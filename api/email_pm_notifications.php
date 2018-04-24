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
    $org_neon_pass = 'claudine';
    $event_id = 1;
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

    $org_db->query(" SELECT title FROM nsadmin_pm_event WHERE id = :event_id ");
    $org_db->bind(':event_id', $event_id);
    $event_title = $org_db->fetchSingleColumn();

    if(empty($event_title))
        throw new Exception('No Event title was found matching the supplied ID (' . $event_id . ')');

    $status[] = 'Event Title set';


    // get event days

    $org_db->query(" SELECT id, venue, slot_mins, DATE_FORMAT(day_date, '%d-%m-%Y') AS `date`  
        FROM nsadmin_pm_day 
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
            FROM nsadmin_pm_slot 
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




    // get class leader ids

    $org_db->query(" SELECT DISTINCT id FROM group_leader WHERE leader_end IS NULL ");
    $org_db->execute();
    $group_leader_ids = $org_db->fetchAllColumn();

    if(empty($group_leader_ids))
        throw new Exception('No Leader IDs were retrieved');

    $status[] = 'Leader IDs set';




    // get event reservations

    $org_db->query(" SELECT r.id AS reservation_id, day_id, slot_id, 
        member_id, CONCAT_WS(' ', p_mem.fname, p_mem.lname) AS pupil_name, 
        staff_id, CONCAT_WS(' ', p_st.fname, p_st.lname) AS staff_name, p_st.salute_name AS staff_salute_name, s_st.title AS staff_salt,  
        support_staff_id, CONCAT_WS(' ', p_sst.fname, p_sst.lname) AS support_staff_name, p_sst.salute_name AS support_staff_salute_name, s_sst.title AS support_staff_salt, 
        guardian_id 
        FROM nsadmin_pm_reservation r 
        LEFT JOIN person p_mem ON ( r.member_id = p_mem.id ) 
        LEFT JOIN person p_st ON ( r.staff_id = p_st.id ) 
        LEFT JOIN prm_salutation s_st ON ( p_st.salt_id = s_st.id ) 
        LEFT JOIN person p_sst ON ( r.support_staff_id = p_sst.id ) 
        LEFT JOIN prm_salutation s_sst ON ( p_sst.salt_id = s_sst.id ) 
        LEFT JOIN member_guardian mg ON ( r.member_id = mg.id AND mg.is_default = 1 ) 
        WHERE ( event_id = :event_id AND is_break = 0 AND created_by != 1 ) 
        ORDER BY day_id ASC, slot_id ASC ");
    $org_db->bind(':event_id', $event_id);
    $org_db->execute();
    $raw_reservations = $org_db->fetchAllAssoc('reservation_id');

    if(empty($raw_reservations))
        throw new Exception('No Reservations were retrieved');

    $status[] = count($raw_reservations) . ' Reservations retrieved';




    // parse reservation details,
    // gather email details

    $email_reservations = []; #email_address => [ reservation ids ]
    $mobile_contacts = [];
    $reservations_with_no_email = [];
    $reservations_with_no_mobile = [];

    $org_db->query(" SELECT email, mobile FROM person WHERE id = :guardian_id ");
    foreach($raw_reservations as $reservation_id => $r)
    {
        # match day id

        if(!isset($day_details[ $r['day_id'] ]))
            throw new Exception('Reservation (ID ' . $reservation_id . ') gathered with invalid Day ID: ' . json_encode($r));

        $day = $day_details[ $r['day_id'] ];
        $slots = $day['slots'];

        # match slot id

        if(!isset($slots[ $r['slot_id'] ]))
            throw new Exception('Reservation (ID ' . $reservation_id . ') gathered with invalid Slot ID: ' . json_encode($r));

        $slot = $slots[ $r['slot_id'] ];



        # determine group leader vs. support staff

        $staff_1_is_group_leader = (in_array($r['staff_id'], $group_leader_ids));
        $staff_2_is_group_leader = false;
        $have_staff_2 = false;
        if(intval($r['support_staff_id']) > 0)
        {
            $have_staff_2 = true;
            $staff_2_is_group_leader = (in_array($r['support_staff_id'], $group_leader_ids));
        }

        if( (!$staff_1_is_group_leader) && (!$staff_2_is_group_leader) )
            throw new Exception('Reservation (ID ' . $reservation_id . ') gathered with no Group Leader assigned: ' . json_encode($r));

        $staff_1 = '';
        $staff_2 = '';
        if($staff_1_is_group_leader)
        {
            if(!empty($r['staff_salute_name']))
                $staff_1 = $r['staff_salute_name'];
            else
            {
                $salt = (!empty($r['staff_salt'])) ? $r['staff_salt'] . ' ' : '';
                $staff_1 = $salt . $r['staff_name'];
            }

            if($have_staff_2)
            {
                if(!empty($r['support_staff_salute_name']))
                    $staff_2 = $r['support_staff_salute_name'];
                else
                {
                    $salt = (!empty($r['support_staff_salt'])) ? $r['support_staff_salt'] . ' ' : '';
                    $staff_2 = $salt . $r['support_staff_name'];
                }
            }
        }
        else
        {
            if(!empty($r['support_staff_salute_name']))
                $staff_1 = $r['support_staff_salute_name'];
            else
            {
                $salt = (!empty($r['support_staff_salt'])) ? $r['support_staff_salt'] . ' ' : '';
                $staff_1 = $salt . $r['support_staff_name'];
            }

            if(!empty($r['staff_salute_name']))
                $staff_2 = $r['staff_salute_name'];
            else
            {
                $salt = (!empty($r['staff_salt'])) ? $r['staff_salt'] . ' ' : '';
                $staff_2 = $salt . $r['staff_name'];
            }
        }



        # get guardian email

        $org_db->bind(':guardian_id', $r['guardian_id']);
        $org_db->execute();
        $contact = $org_db->fetchSingleAssoc();

        $email = $contact['email'];
        $mobile = $contact['mobile'];


        # set reservation details

        $res = [
            'id' => $reservation_id,
            'pupil_name' => $r['pupil_name'],
            'date' => $day['date'],
            'venue' => $day['venue'],
            'slot_mins' => $day['slot_mins'],
            'slot_start' => $slot['start'],
            'slot_end' => $slot['end'],
            'staff_1' => $staff_1,
            'staff_2' => $staff_2
        ];

        #$email = 'eamonn@databizsolutions.ie';

        if(!empty($email))
        {
            if(!isset($email_reservations[ $email ]))
                $email_reservations[ $email ] = [];

            $email_reservations[ $email ][] = $res;
        }
        else
            $reservations_with_no_email[] = $res;

        if(!empty($mobile))
            $mobile_contacts[ $mobile ] = 1;
        else
            $reservations_with_no_mobile[] = $res;

    }

    $status[] = 'Reservations parsed';


    if(empty($email_reservations))
        throw new Exception('No email addresses found to send to.');


    if(empty($mobile_contacts))
        throw new Exception('No mobile numbers found to send to.');






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
    $_SESSION['aka']['Password'] = $r['Neon'];*/

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

    /*$timeout = 120;
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















    /*$emails_content = [];
    $email_template = <<<EOS
<p style="margin: 0 0 0.5rem">Dear Parents/Guardians,</p>
<p style="margin: 0;">This is an automated email, sent to you to confirm that, for the event</p>
<h3 style="margin: 0;">[EVENT_TITLE]</h3>
<p style="margin: 0;">the following [RESERVATIONS_STR] been made for you by School administrators:</p>
<ul>[RESERVATIONS]</ul>
EOS;
    if(!empty($mail_from))
        $email_template.= '<p style="margin: 0;">Please contact <a href="mailto:' . $mail_from . '">' . $mail_from . '</a> if you have any concerns regarding the above.</p>';

    foreach($email_reservations as $email => $reservations)
    {
        #\DS\Helper::show(count($reservations));
        $h = $email_template;
        $h = str_replace('[EVENT_TITLE]', $event_title, $h);

        $res_str = (count($reservations) > 1) ? 'reservations have' : 'reservation has';
        $h = str_replace('[RESERVATIONS_STR]', $res_str, $h);

        $reservation_items = '';
        foreach($reservations as $r)
        {
            $date_str = DateTime::createFromFormat('d-m-Y', $r['date'])->format('l jS \of F Y');
            $str = '<li>A meeting with ' . $r['staff_1'];
            if(!empty($r['staff_2']))
                $str.= ', and ' . $r['staff_2'] . ',';
            $str.= ' regarding ' . $r['pupil_name'] . ' on ' . $date_str . ' in ' . $r['venue'] . ' at ' . $r['slot_start'] . '. ';
            $str.= 'This meeting is scheduled to last ' . $r['slot_mins'] . ' minutes.</li>';
            $reservation_items.= $str;
        }

        $h = str_replace('[RESERVATIONS]', $reservation_items, $h);
        $emails_content[ $email ] = $h;
    }


    #\DS\Helper::show(count($emails_content));
    #\DS\Helper::show($emails_content);
    #die();



    $root_path = DS_PATH_APPS . 'assets' . DS_DE . 'templates' . DS_DE . 'email' . DS_DE;
    $html = file_get_contents($root_path . 'html' . DS_DE . 'en_default.html');
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
    $icon.= '<img src="http://databizsolutions.ie/databiz/ds_media/19374W/assets/crest.png" width="80" height="80" border="0" alt="logo" />';
    $icon.= '</td>';
    $icon.= '</tr></table>';

    $html = strtr($html,
        [
            '$_SUBJECT'=>$event_title,
            '$_ORG_ICON'=>$icon,
            '$_ORG_NAME'=>$org_name,
            '$_ORG_BLURB'=>$org_blurb,
            '$_TITLE'=>$event_title,
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
        \DS\Helper::show($e);
        return false;
    }
    catch(phpmailerException $e)
    {
        #$this->error = $e->getMessage();
        \DS\Helper::show($e);
        return false;
    }*/

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