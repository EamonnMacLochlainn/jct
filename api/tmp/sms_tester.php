<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/02/2018
 * Time: 10:52
 */


require_once '../../ds_core/Config.php';
require_once '../../ds_core/classes/Helper.php';

$d = new DateTime();

$results = '&nbsp;';
$send_str = '';




// NEON error codes
#3  - message too long
#4  - something is amiss with the account, either balance, an unverified email or the account is deleted or inactive
#6  - an invalid / badly formatted number
#7  - a number you are not allowed to send to (only relevant if you have a blacklist set up)

// TESTAC
//

if(!empty($_POST))
{
    try
    {
        if(empty($_POST['account_name']))
            throw new Exception('No account name detected.');

        if(empty($_POST['account_password']))
            throw new Exception('No account password detected.');

        if(empty($_POST['numbers']))
            throw new Exception('No numbers detected.');

        if(empty($_POST['action']))
            throw new Exception('No action value detected.');



        $action = trim($_POST['action']);
        $user = trim($_POST['account_name']);
        $clipwd = trim($_POST['account_password']);


        // parse numbers
        $numbers_raw = explode(',',$_POST['numbers']);
        $numbers_stmt = '';
        $numbers = [];
        $index = 0;
        foreach($numbers_raw as $num)
        {
            $num = preg_replace("/[^0-9]/", "", $num);
            if(empty($num))
                continue;

            switch($action)
            {
                case('Test SMS'):
                    $numbers_stmt.= '&to[' . $index . ']=' . $num;
                    break;
                case('Balance'):
                    break;
                case('Invite'):
                case('Subscribers'):
                    $numbers_stmt.= '&mobile[' . $index . ']=' . $num;
                    break;
                default:
                    throw new Exception('No switch statement for submitted action.');
                    break;
            }

            $numbers[] = $num;
            $index++;
        }

        if( ($action != 'Balance') && (empty($numbers_stmt)) )
            throw new Exception('Numbers Statement is empty.');




        $auth_str = 'user=' . $user . '&clipwd=' . $clipwd;

        switch($action)
        {
            case('Test SMS'):

                if(empty($_POST['content']))
                    throw new Exception('No content detected.');

                $now = new DateTime();
                $text = trim($_POST['content']);

                $send_str = $auth_str . $numbers_stmt . '&text=' . urlencode($text) . '&nocadoo=1&meta={"button":{"text":"DataBiz Payments","colour":"27873D","textcolour":"FFFFFF","url":"https://databizsolutions.ie/login"}}';
                $send_str_len = strlen($send_str);

                $host = 'api.neonsolutions.ie';
                $port = 80;

                $socket = @fsockopen("$host", $port, $errno, $errstr, 120);
                if(!$socket)
                    throw new Exception("Unable to get Neon server status.");

                $out = sprintf("POST /sms.php");
                $out.= " HTTP/1.1\n";
                $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out.= "Host: $host\r\n";
                $out.= "Content-Length: $send_str_len\r\n";
                $out.= "Connection: Close\r\n";
                $out.= "Cache-Control: no-cache\r\n\r\n";
                $out.= $send_str;

                fwrite($socket, $out);
                stream_set_blocking($socket, false);
                stream_set_timeout($socket, 120);
                $info = stream_get_meta_data($socket);

                while (!feof($socket) && !$info['timed_out'])
                {
                    $results.= fgets($socket, 4096);
                    $info = stream_get_meta_data($socket);
                }

                $results = preg_split('/$\R?^/m', $results);

                /*Array
                (
                    [0] =>  HTTP/1.1 200 OK
                    [1] => Date: Fri, 23 Feb 2018 15:01:16 GMT
                    [2] => Server: Apache/2.4.10 (Debian) OpenSSL/1.0.1k
                    [3] => Content-Length: 26
                    [4] => Connection: close
                    [5] => Content-Type: text/html; charset=UTF-8
                    [6] =>
                    [7] => OK: 34023245
                    [8] => OK: 34023246
                    ...

                OR

                    [7] => ERR: [msg] (num)
                )*/

                break;


            case('Balance'):

                $send_str = "https://api.neonsolutions.ie/balance.php?" . $auth_str;
                $results = file_get_contents($send_str);

                $results = explode(':', $results);

                /*Array
                (
                    [0] => OK
                    [1] => 42.5
                    [2] => 5
                    [3] => 7.50

                OR

                    [0] => ERR
                    [1] =>  Authentication error
                )*/

                break;


            case('Invite'):

                /*$send_str = "https://nsmc.neonsolutions.ie/cadoo/invite.php?" . $auth_str . $numbers_stmt . "&override=1";
                $results = file_get_contents($send_str);*/



                $send_str = $auth_str . $numbers_stmt . "&override=1";
                $send_str_len = strlen($send_str);

                $host = 'nsmc.neonsolutions.ie';
                $port = 80;

                $socket = @fsockopen("$host", $port, $errno, $errstr, 120);
                if(!$socket)
                    throw new Exception("Unable to get Neon server status.");

                $out = sprintf("POST /cadoo/invite.php");
                $out.= " HTTP/1.1\n";
                $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out.= "Host: $host\r\n";
                $out.= "Content-Length: $send_str_len\r\n";
                $out.= "Connection: Close\r\n";
                $out.= "Cache-Control: no-cache\r\n\r\n";
                $out.= $send_str;

                fwrite($socket, $out);
                stream_set_blocking($socket, false);
                stream_set_timeout($socket, 120);
                $info = stream_get_meta_data($socket);

                while (!feof($socket) && !$info['timed_out'])
                {
                    $results.= fgets($socket, 4096);
                    $info = stream_get_meta_data($socket);
                }

                $results = preg_split('/$\R?^/m', $results);

                /*[
                    'status_code' => 200,
                    'status_txt' => 'OK',
                    'data' => [
                        'url' => 'http://bit.ly/2GFCfqW',
                        'hash' => '2GFCfqW',
                        'global_hash' => '2GDdeg1',
                        'long_url' => 'http://cadoo.neonsms.ie/download_cadoo.php?neonid=1379&mobile=',
                        'new_hash' => 1
                    ]
                ]

                OR

                ERR: Authentication error
                */

                break;


            case('Subscribers'):

                $send_str = $auth_str . $numbers_stmt;
                $send_str_len = strlen($send_str);

                $host = 'nsmc.neonsolutions.ie';
                $port = 80;

                $socket = @fsockopen("$host", $port, $errno, $errstr, 120);
                if(!$socket)
                    throw new Exception("Unable to get Neon server status.");

                $out = sprintf("POST /cadoo/check_cadoo.php");
                $out.= " HTTP/1.1\n";
                $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out.= "Host: $host\r\n";
                $out.= "Content-Length: $send_str_len\r\n";
                $out.= "Connection: Close\r\n";
                $out.= "Cache-Control: no-cache\r\n\r\n";
                $out.= $send_str;

                fwrite($socket, $out);
                stream_set_blocking($socket, false);
                stream_set_timeout($socket, 120);
                $info = stream_get_meta_data($socket);

                while (!feof($socket) && !$info['timed_out'])
                {
                    $results.= fgets($socket, 4096);
                    $info = stream_get_meta_data($socket);
                }

                $results = preg_split('/$\R?^/m', $results);

                break;
        }

    }
    catch(Exception $e)
    {
        $results = $e->getMessage();
    }
}

$results = '<pre>' . print_r($results, true) . '</pre>';

// 0877761405
// 0863821397
// evelyn: 0863821397

$h = <<<EOS
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cadoo Tester</title>
    
    <script src="http://localhost/databiz/ds_apps/assets/js/main.js"></script>
    <script src="http://localhost/databiz/ds_apps/assets/js/jquery-ui-1.12.1.custom/external/jquery/jquery.js"></script>
    <script src="http://localhost/databiz/ds_apps/assets/js/jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
    
    <script src="sms_tester.js"></script>
    
</head>
<body>

    <form action="" method="post">  
    
        <fieldset class="account">  
        <legend>Account</legend>
            
            <span>databiz/dzcadoo</span><br/>
            <label class="account-name">  
                <span class="label-text" style="display: inline-block; width: 7rem;">Account Name:</span>
                <input type="text" name="account_name" value="databiz" />
            </label><br/>
            
            <label class="account-password">  
                <span class="label-text" style="display: inline-block; width: 7rem;">Password:</span>
                <input type="text" name="account_password" value="Databiz2012" />
            </label><br/>
        
            <label class="numbers">  
                <span class="label-text" style="display: block;">Numbers:</span>
                <input type="text" name="numbers" value="353867345627" style="display: block; width: 100%;" />
            </label> <br/> 
            
            <label class="content">  
                <span class="label-text" style="display: block;">Content:</span>
                <input type="text" name="content" value="" style="display: block; width: 100%;" />
            </label> <br/>   
        
        </fieldset>
        
        
        <fieldset class="actions"> 
        <legend>Actions</legend> 
        
        <input type="submit" name="action" value="Test SMS" />
        <input type="submit" name="action" value="Balance" />
        <input type="submit" name="action" value="Invite" />
        <input type="submit" name="action" value="Subscribers" />
        
        </fieldset>
        
        
        <fieldset class="results"> 
        <legend>Results</legend> 
        
            <p>$send_str</p>
            <p>$results</p>
        
        </fieldset>
    
    </form>
    
    
    
    <!--<form action="http://databizsolutions.ie/texting/access_balance.php" method="post">
    
        <input name="username" type="text" value="databiz" />
        <input name="password" type="text" value="Databiz2012" />
        <input type="submit" value="Submit" />
    
    </form>-->

</body>
</html>


EOS;

echo $h;


