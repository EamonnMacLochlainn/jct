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
    #require_once '../ds_core/classes/Mailer.php';
    #require_once DS_PATH_CORE_VENDORS . 'phpmailer' . DS_DE . 'phpmailer.php';
    #require_once DS_PATH_CORE_VENDORS . 'phpmailer' . DS_DE . 'smtp.php';

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




    $org_db->query(" SELECT title FROM nsadmin_pm_event WHERE id = :event_id ");
    $org_db->bind(':event_id',$event_id);
    $org_db->execute();
    $event_title = $org_db->fetchSingleColumn();

    $org_db->query(" SELECT DATE_FORMAT(day_date, '%a. %D of %M, %Y') AS day_date FROM nsadmin_pm_day WHERE event_id = :event_id ");
    $org_db->bind(':event_id',$event_id);
    $org_db->execute();
    $event_dates = $org_db->fetchAllColumn();


    $org_db->query(" SELECT DISTINCT member_id FROM nsadmin_pm_reservation WHERE event_id = :event_id AND member_id > 0 ");
    $org_db->bind(':event_id', $event_id);
    $org_db->execute();
    $reserved_pupil_ids = $org_db->fetchAllColumn();

    if(empty($reserved_pupil_ids))
        throw new Exception('No Pupils have made reservations.');

    $reserved_pupil_ids_str = implode(',',$reserved_pupil_ids);

    $org_db->query(" SELECT pp.id AS ID, CONCAT_WS(' ', pp.fname, pp.lname) AS `Pupil`, gc.abbr AS Class,
    CONCAT_WS(' ', gp.fname, gp.lname) AS `Guardian`, gp.mobile AS Mobile, gp.email AS Email
    FROM person pp
    LEFT JOIN member_guardian mg ON ( pp.id = mg.id AND mg.is_default = 1 AND guardian_end IS NULL )
    LEFT JOIN member_group_class mc ON ( pp.id = mc.id AND in_group_end IS NULL )
    LEFT JOIN group_class gc ON ( mc.group_class_id = gc.id )
    LEFT JOIN person gp ON ( mg.guardian_id = gp.id )
    WHERE ( pp.is_member = 1 AND pp.id NOT IN ( {$reserved_pupil_ids_str} ) )  ");
    $org_db->execute();
    $tmp = $org_db->fetchAllAssoc();


    $mobile_nums = [];
    $non_reserved = [];
    foreach($tmp as $t)
    {
        $non_reserved[ $t['ID'] ] = $t;
        $mobile_nums[ $t['Mobile'] ] = 1;
    }

    $non_reserved = array_values($non_reserved);
    $mobile_nums = array_keys($mobile_nums);



    \JCT\Helper::show($mobile_nums);

# texting

    $host = 'api.neonsolutions.ie';
    $port = 80;
    $message = 'Dear Parent/Guardian, please note that registration for your P/T Meeting closes at 13:00 21/11/2017. Please register as soon as possible.';

    #$mobile_contacts = array_keys($mobile_contacts);
    #$mobile_nums = ['353867345627'];
    $mobile_contacts_str = '';
    foreach($mobile_nums as $i => $num)
    {
        $num = preg_replace("/[^0-9]/", "", $num);
        if(empty($num))
            continue;

        $tmp = '&to[' . $i . ']=' . $num;
        $mobile_contacts_str.= $tmp;
    }

    $send_str="user={$org_guid}&clipwd={$org_neon_pass}&text={$message}" . $mobile_contacts_str;
    $send_str_len = strlen($send_str);

    #echo $send_str;
    #die();

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
        $status[] = 'Messages sent successfully. Message ID ' . $msg_id;



# export


    /*// include class
    if(is_readable(DS_PATH_CORE_VENDORS . 'phpexcel' . DS_DE . 'PHPExcel.php'))
        require_once DS_PATH_CORE_VENDORS . 'phpexcel' . DS_DE . 'PHPExcel.php';
    else
        throw new Exception('Could not find PHPExcel class.');

    \PHPExcel_Settings::setZipClass(\PHPExcel_Settings::PCLZIP);
    $excel_obj = new \PHPExcel();

    $fields = [
        'ID', 'Pupil', 'Class', 'Guardian', 'Mobile', 'Email'
    ];

    $default_width = 20;
    $alphabet = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
    $col_widths = [];

    foreach($alphabet as $i => $char)
    {
        $col_widths[$char] = $default_width;
    }

    $col_widths['A'] = 10;
    $col_widths['B'] = 25;
    $col_widths['C'] = 10;
    $col_widths['D'] = 25;
    $col_widths['E'] = 25;
    $col_widths['F'] = 35;



    $sheet = $excel_obj->setActiveSheetIndex(0);
    $row_count = 1;
    $col_count = 1;

    $sheet->getRowDimension($row_count)->setRowHeight(30);
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue( 'A1', $event_title);

    $row_count++;

    foreach($event_dates as $event_date)
    {
        $sheet->mergeCells('A' . $row_count . ':F' . $row_count);
        $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getFont()->setBold(true);
        $sheet->setCellValue( 'A' . $row_count, $event_date);

        $row_count++;
    }

    $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getFill()->applyFromArray(array(
        'type' => \PHPExcel_Style_Fill::FILL_SOLID,
        'startcolor' => array(
            'rgb' => 'CCCCCC'
        )
    ));
    $sheet->getStyle('A' . $row_count . ':F' . $row_count)->applyFromArray(array(
        'borders' => array(
            'allborders' => array(
                'style' => \PHPExcel_Style_Border::BORDER_THIN
            )
        )
    ));

    //set column titles
    foreach($fields as $field)
    {
        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $field)->getColumnDimension($col_char)->setWidth($col_widths[$col_char]);

        $col_count++;
    }

    $row_count++;


    foreach($non_reserved as $n)
    {
        $col_count = 1;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['ID']);
        $col_count++;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['Pupil']);
        $col_count++;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['Class']);
        $col_count++;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['Guardian']);
        $col_count++;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['Mobile']);
        $col_count++;

        $col_char = \DS\Helper::get_excel_col_char($col_count);
        $sheet->setCellValue( $col_char . $row_count, $n['Email']);

        $row_count++;
    }
    $excel_obj->setActiveSheetIndex(0);

    $event_title_slug = \DS\Helper::slugify($event_title);

    $file_name = $event_title_slug . '_non_registered.xlsx';
    #$org_guid = $_SESSION['databiz']['guid'];

    $tmp = \DS\Helper::prep_org_media_dir($org_guid, 'documents', true, true);
    $target_dir = $tmp['path'];
    $target_url = $tmp['url'];

    $objWriter = new \PHPExcel_Writer_Excel2007($excel_obj);
    $objWriter->save($target_dir . $file_name);

    $url = $target_url . $file_name;
    echo $url;*/











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

    /*$status[] = count($successful_recipients) . ' Emails sent.';


    if(count($unsuccessful_recipients) > 0)
        throw new Exception('Email failed to send to the following addresses: ' . implode(', ', $unsuccessful_recipients));

    foreach($status as $s)
        echo $s . "<br/>";*/

    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    $execution_time = round($execution_time, 3);
    echo '<br/>Total Execution Time: '.$execution_time.' seconds';
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