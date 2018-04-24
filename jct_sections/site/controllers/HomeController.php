<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/05/2016
 * Time: 13:41
 */

namespace JCT\site;


use DateTime;
use DateTimeZone;
use JCT\Database;
use JCT\Helper;
use JCT\Localisation;
use JCT\Mailer;
use JCT\User;
use Exception;

class HomeController
{
    private $model;


    /**
     * Login attempt constraints
     */
    const max_login_attempts = 3;
    const login_timeout_mins = 1;


    function __construct(HomeModel $model)
    {
        $this->model = $model;
    }

    function login_user($args)
    {
        if(session_status() === PHP_SESSION_NONE)
            session_start();

        session_regenerate_id();
        $session_id = session_id();

        try
        {
            if(empty($_SESSION['jct']))
                $_SESSION['jct'] = [];

            if(empty($args))
                throw new Exception('No arguments were detected.');

            if(empty($args['username']))
                throw new Exception('No Username was detected.');

            if(empty($args['password']))
                throw new Exception('No Password was detected.');

            $username = trim($args['username']);
            $password = trim($args['password']);
            $username = filter_var($username, FILTER_SANITIZE_STRING);


            // check time out

            $this_attempt = new DateTime();
            $this_attempt->setTimezone(new DateTimeZone(JCT_DEFAULT_TIMEZONE));

            $time_out_until = (!empty($_SESSION['jct']['timeout_until'])) ? DateTime::createFromFormat('U', $_SESSION['jct']['timeout_until']) : null;
            $grace_logins_remaining = (!empty($_SESSION['jct']['grace_logins_remaining'])) ? intval($_SESSION['jct']['grace_logins_remaining']) : $this::max_login_attempts;


            if(!is_null($time_out_until))
            {
                $time_out_until->setTimezone(new DateTimeZone(JCT_DEFAULT_TIMEZONE));

                if($time_out_until > $this_attempt)
                {
                    $time_diff = $time_out_until->diff($this_attempt);
                    $time_diff_str = str_pad($time_diff->i, 2, 0, STR_PAD_LEFT) . ':' . str_pad($time_diff->s, 2, 0, STR_PAD_LEFT);
                    throw new Exception('<p>You have been timed out, due to too many failed login attempts.</p><p>Time remaining (mm:ss): ' . $time_diff_str . '</p>');
                }
            }


            // check login

            $tmp = $this->model->check_user_password($username, $password);

            if(isset($tmp['error']))
            {
                $grace_logins_remaining = $grace_logins_remaining - 1;
                $grace_logins_remaining = ($grace_logins_remaining < 0) ? 0 : $grace_logins_remaining;

                $_SESSION['jct']['grace_logins_remaining'] = $grace_logins_remaining;

                // if no attempts remaining, time out
                if($grace_logins_remaining == 0)
                {
                    $time_out_until = clone $this_attempt;
                    $time_out_until->modify('+' . $this::login_timeout_mins . ' minutes');
                    $_SESSION['jct']['timeout_until'] = $time_out_until->format('U');
                    throw new Exception('<p>You have been timed out, due to too many failed login attempts.</p><p>Time remaining: ' . $this::login_timeout_mins . ' minutes</p>');
                }

                throw new Exception('Invalid Login. You have ' . $grace_logins_remaining . ' attempts remaining.');
            }

            $_SESSION['jct']['timeout_until'] = null;
            $_SESSION['jct']['grace_logins_remaining'] = $this::max_login_attempts;

            $user_id = $tmp['id'];
            $position = $tmp['position'];
            $org = $tmp['org'];

            // set session

            $tmp = $this->model->save_user_session_id($user_id, $session_id);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $_SESSION['jct'] = [
                'id' => $user_id,
                'position' => $position,
                'org' => $org
            ];

            return [ 'success'=>1 ];
        }
        catch(Exception $e)
        {
            //unset($_SESSION['jct']);
            return ['error'=>$e->getMessage()];
        }
    }

    function reset_password($args)
    {
        try
        {
            if(empty($args['username']))
                throw new Exception('No Username detected.');

            $username = trim($args['username']);
            $username = filter_var($username, FILTER_SANITIZE_STRING);

            $tmp = $this->model->get_id_email_for_username($username);
            if(empty($tmp))
                throw new Exception('No User account was found for this Username.');

            $user_id = $tmp['id'];
            $email = $tmp['email'];

            $new_password = Helper::generate_random_string(10);
            $new_password_hashed = Helper::hash_password($new_password);

            $mailer = new Mailer($this->model->get_connection(), 'resend_password');
            if($mailer->error !== null)
                throw new Exception($mailer->error);

            $tmp = $mailer->set_recipients([$email]);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $recipients = $mailer->get_recipients();
            $templates = $mailer->fetch_templates('default');




            $en_subject = 'JCT Registration.ie Password Reset';
            $en_title = 'JCT Registration.ie Password Reset';
            $mailer->subject = $en_subject;

            $ga_subject = $en_subject;
            $ga_title = $en_title;

            $en_body_html = '<p>Dear $_SALUTE_NAME,</p>';
            $en_body_html.= '<p>A password reset was requested for your account on JCT Registration.ie. This action automatically resets your password, and emails you a copy. It is not otherwise divulged. You should see your new (randomised) password below:</p>';
            $en_body_html.= '<p style="font-weight: bold">' . $new_password . '</p>';
            $en_body_html.= '<p>Once you have logged in to JCT Registration.ie using the above password, you can keep it or reset it to something more memorable.</p>';

            $en_body_text = 'Dear $_SALUTE_NAME,' . PHP_EOL;
            $en_body_text.= 'A password reset was requested for your account on JCT Registration.ie. This action automatically resets your password, and emails you a copy. It is not otherwise divulged. You should see your new (randomised) password below:' . PHP_EOL;
            $en_body_text.= $new_password . PHP_EOL;
            $en_body_text.= 'Once you have logged in to JCT Registration.ie using the above password, you can keep it or reset it to something more memorable.' . PHP_EOL;

            $ga_body_html = $en_body_html;
            $ga_body_text = $en_body_text;

            foreach($recipients as $i => $r)
            {
                $body_html = ($r['mode_of_communication'] === 'en') ? $en_body_html : $ga_body_html;
                $body_text = ($r['mode_of_communication'] === 'en') ? $en_body_text : $ga_body_text;
                $title = ($r['mode_of_communication'] === 'en') ? $en_title : $ga_title;
                $subject = ($r['mode_of_communication'] === 'en') ? $en_subject : $ga_subject;

                $template = ($r['mode_of_email_content'] === 'html') ? $templates['html'] : $templates['text'];
                $template = ($r['mode_of_email_content'] === 'html') ? str_replace('$_BODY_HTML', $body_html, $template) : str_replace('$_BODY_TEXT', $body_text, $template);

                $template = strtr($template,
                    [
                        '$_SALUTE_NAME'=>$r['salute_name'],
                        '$_SUBJECT'=>$subject,
                        '$_LOGO_SRC'=>JCT_LOGO_SRC,
                        '$_TITLE'=>$title
                    ]
                );

                $recipients[$i]['content'] = $template;
            }



            $mailer->send_as_group = false;
            $mailer->send($recipients);

            if(array_key_exists($email, $mailer->successful_recipients))
            {
                $tmp = $this->model->reset_user_password($user_id, $new_password_hashed);
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);

                return ['success'=>1];
            }
            else
            {
                $error = $mailer->unsuccessful_recipients[$email]['status'];
                throw new Exception('The email was not successfully sent (' . $error . '). Please refresh and try again.');
            }
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}