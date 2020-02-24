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
use JCT\Connection;
use JCT\Helper;
use JCT\Localisation;
use JCT\Mailer;
use JCT\SessionManager;
use Exception;

class HomeController
{
    private $model;
    private $session_name;


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
            if(empty($_SESSION[SessionManager::SESSION_NAME]))
                $_SESSION[SessionManager::SESSION_NAME] = [];

            if(empty($args))
                throw new Exception('No arguments were detected.');

            $username = (!empty($args['username'])) ? trim(strtolower($args['username'])) : null;
            $password = (!empty($args['password'])) ? trim($args['password']) : null;
            $set_org_guid = (!empty($args['org_guid'])) ? strtolower($args['org_guid']) : 0;
            $set_org = null;
            $set_role_id = (!empty($args['role_id'])) ? intval($args['role_id']) : 0;

            if($username === null)
                throw new Exception('No Username was detected.');

            if($password === null)
                throw new Exception('No Password was detected.');


            // init session

            $session_manager = new SessionManager($this->model->get_connection());
            $this->session_name = $session_manager::SESSION_NAME;


            // check login

            $tmp = $this->model->check_user_password($username, $password);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            $person_guid = $tmp['person_guid'];




            // check user orgs

            $user_orgs = $this->model->get_user_orgs($person_guid);
            $num_user_orgs = count($user_orgs);

            if($num_user_orgs < 1)
                throw new Exception('This account is not currently registered for any Organisation.');
            elseif($num_user_orgs == 1)
            {
                if(empty($set_org_guid))
                {
                    $set_org_guid = $user_orgs[0]['guid'];
                    $set_org = $user_orgs[0];
                }
                else
                {
                    if($set_org_guid !== $user_orgs[0]['guid'])
                        return ['error'=> 'You appear to be logging in for an Organisation you are not registered to. (1)'];
                }
            }
            else
            {
                if(empty($set_org_guid))
                    return ['org_choice'=>$user_orgs];
                else
                {
                    $k = -1;
                    foreach($user_orgs as $i => $org)
                    {
                        if($set_org_guid == $org['guid'])
                        {
                            $set_org = $org;
                            $k = $i;
                        }
                    }

                    if($k == -1)
                        return ['error'=> 'You appear to be logging in for an Organisation you are not registered to. (2)'];
                }
            }

            if(empty($set_org_guid))
                throw new Exception('User logging in without a set Organisation.');


            // user roles


            $user_roles = $this->model->get_user_roles_for_org($person_guid, $set_org_guid);
            $num_user_org_roles = count($user_roles);

            if($num_user_org_roles < 1)
                throw new Exception('This account is not currently registered for any role within their set Organisation.');
            elseif($num_user_org_roles == 1)
            {
                if(empty($set_role_id))
                    $set_role_id = $user_roles[0]['id'];
                else
                {
                    if($set_role_id != $user_roles[0]['id'])
                        return ['error'=> 'You appear to be logging in for a role you are not registered to.'];
                }
            }
            else
            {
                if(empty($set_role_id))
                    return ['role_choice'=>$user_roles];
                else
                {
                    $k = null;
                    foreach($user_roles as $i => $role)
                    {
                        if($set_role_id == $role['id'])
                            $k = $i;
                    }

                    if($k == null)
                        return ['error'=> 'You appear to be logging in for a role you are not registered to.'];
                }
            }

            if(empty($set_role_id))
                throw new Exception('User logging in without a set Role.');




            // user preferences

            $prefs = $this->model->get_user_settings_in_org($person_guid, $set_org_guid);
            $prefs['locale'] = (empty($prefs['locale'])) ? 'en_GB' : $prefs['locale'];


            // set session

            $session_manager = new SessionManager($this->model->get_connection());
            $session_manager->set_session_id($session_id);

            $session_manager->clear_user_session_record($person_guid);
            $session_manager->clear_user_from_session_globals();

            $cookie_token = Helper::generate_random_string(32);
            $cookie_value = $session_manager->create_cookie_value(
                $person_guid,
                $set_org['guid'],
                $set_org['type_id'],
                $set_org['sub_type_id'],
                $set_role_id,
                $session_id,
                $cookie_token
            );

            $session_manager->init_session_args();
            $session_manager->session_args['person']['guid'] = $person_guid;
            $session_manager->session_args['person']['role_id'] = $set_role_id;
            $session_manager->session_args['person']['prefs'] = $prefs;
            $session_manager->session_args['org']['guid'] = $set_org['guid'];
            $session_manager->session_args['org']['type_id'] = $set_org['type_id'];
            $session_manager->session_args['org']['sub_type_id'] = $set_org['sub_type_id'];

            $session_manager->recalc_cookie_expiry_times();
            $tmp = $session_manager->insert_user_into_session_globals($cookie_value);
            if($tmp === false)
                throw new Exception('Login Cookie could not set. Login is not possible.');

            $session_manager->save_user_session_record($person_guid, $cookie_token);

            $resp = [
                'success'=>1,
                'complete'=>1
            ];

            if(!empty($args['redirect']))
            {
                $redirect = (!empty($args['redirect'])) ? base64_decode($args['redirect']) : null;
                if($redirect !== null)
                {
                    if(substr($redirect, -1) === '?')
                        $redirect = substr($redirect, 0, -1);

                    $redirect_user_id = (!empty($args['redirect_user_id'])) ? intval(base64_decode($args['redirect_user_id'])) : 0;
                    $redirect = ($redirect_user_id === $person_guid) ? $redirect : 'Dashboard';

                    $resp['redirect'] = $redirect;
                }
            }

            return $resp;
        }
        catch(Exception $e)
        {
            unset($_SESSION['databiz']);
            return ['error'=>$e->getMessage()];
        }
    }




    /*function request_password($args)
    {
        try
        {
            if(empty($args['country_code']))
                throw new Exception('No Country selected.');
            $country_code = strtoupper(trim($args['country_code']));

            if(empty($args['mobile']))
                throw new Exception('No mobile number detected.');

            if(empty($args['email']))
                throw new Exception('No Email detected.');
            $email = trim(strtolower($args['email']));

            if(!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new Exception('Invalid Email detected.');



            $mobile = Localisation::parse_contact_number($country_code, $args['mobile']);
            if(is_array($mobile))
                throw new Exception($mobile['error'] . ' Check that you have the correct origin country selected for this number.');

            $tmp = $this->model->get_user_for_contact_params($mobile, $email);
            if(isset($tmp['error']))
            {
                if($tmp['error'] == 'no_user')
                    throw new Exception('<p>No User was found matching the supplied contact details.</p>
                    <p>See our <a href="Help">Help section</a> for more details.</p>');
                else
                    throw new Exception($tmp['error']);
            }

            if(count($tmp['user']) > 1)
                throw new Exception('<p>An account has been found matching the supplied email address, while another account matches the 
supplied mobile.</p>
<p>This is likely due to the mixing of Guardian\'s contact information for their children by your Organisation.</p>
<p>In this case try registering again, using either your email with the appropriate alternative mobile, or your mobile with the appropriate alternative email.</p>', 2);

            $user = $tmp['user'][0];

            if(
                ( ($user['mobile'] == $mobile) || (empty($user['mobile'])) ) &&
                ( ($user['email'] == $email) || (empty($user['email'])) )
            )
            {
                // email is not different, mobile is not different
                $user_id = $user['id'];
            }
            else
            {
                if( (!empty($user['mobile'])) && ($user['mobile'] !== $mobile) )
                {
                    // mobile exists, but does not match submitted (email matches)

                    $split = explode(' ', $user['mobile']);
                    $int_code = array_shift($split);
                    $mob_code = array_shift($split);
                    $last_four = array_pop($split);

                    $str = '0' . $mob_code . ' ';
                    foreach($split as $seg)
                    {
                        $len = strlen($seg);
                        for($x = 0; $x < $len; $x++)
                            $str.= '*';
                        $str.= ' ';
                    }
                    $str.= ' **' . substr($last_four, -2);//

                    $provided_str = 'mobile number';
                    $matched_str = 'email address';
                    $orig_arg = htmlspecialchars($args['email']);
                }
                else
                {
                    // email exists, but does not match submitted (mobile exists)

                    $first_three = substr($user['email'], 0, 3);
                    $seg = substr($user['email'], 3, -5);
                    $last_five = substr($user['email'], -5);

                    $str = $first_three;
                    $len = strlen($seg);
                    for($x = 0; $x < $len; $x++)
                        $str.= '*';
                    $str.= $last_five;

                    $provided_str = 'email address';
                    $matched_str = 'mobile number';
                    $orig_arg = htmlspecialchars($args['mobile']);
                }

                throw new Exception('<p style="margin-bottom:3em">There is a different ' . $provided_str . ' already associated 
                    with this ' . $matched_str . ':<br/><span style="margin-top: 0.5em;display: block;font-weight: bold;font-size: 0.9em;color: #86b7e8;">' . $str . '</span></p>
                    If you have access to the ' . $provided_str . ' above,<br/>please sign up again using:
                    <ul style="text-align: left;font-size: 0.9em;color: #86b7e8;"><li>' . $orig_arg . '</li>
                    <li>' . $str . '</li></ul> 
                    <p style="margin-top: 3rem;font-size: 0.8em;">If you don\'t recognise or have access to that ' . $provided_str . ', or still have difficulty signing in, 
                    please <a href="mailto:info@databizsolutions.ie" style="outline: none;">contact Databiz Solutions</a>.</p>', 2);
            }


            $password_str = Helper::generate_random_string();
            $password_hash = Helper::hash_password($password_str);




            $c = new Connection();
            $db_name = JCT_PREFIX . '_org_databiz';
            $c->set_org_connection('localhost', $db_name);
            $databiz_db = $c->get_connection($instance_name = JCT_DB_SIUD_USER . '@localhost:' . $db_name);

            $mailer = new Mailer($this->model->get_connection(), $databiz_db, 'DATABIZ');
            if(!empty($mailer->error))
                throw new Exception($mailer->error);

            $source_account = $this->model->get_email_account_by_username('no-reply@databizsolutions.ie', $databiz_db);
            $mailer->set_source_account($source_account);

            $mailer->subject = 'Your DataBiz Online Password';
            $mailer->set_document_header($this->build_header());
            $mailer->set_document_footer('default');
            $mailer->set_document_structure();

            $recipients = [];
            $recipients[] = [
                'email' => $email,
                'document_body' => '<p>A new password to access DataBiz Solutions online applications has been requested for your account.</p>
<p>As a result a new random password has been generated and sent here to the email address on record for you. The password is as follows:</p>
<p>' . $password_str . '</p>
<p>Please note that the password is case sensitive, and you should avoid copy-pasting any blank spaces before or after it.</p>
<p>Follow <a href="https://databizsolutions.ie/login">this link</a> to log in.</p>
<p>If you did not request, or do not need a password to access the Databiz Solutions website, it is likely that someone has registered your email address 
by mistake. Please <a href="mailto:support@databizsolutions.ie?subject=[ErU.' . $user_id . ']">let us know</a>.</p>'
            ];

            $send_statuses = $mailer->send($recipients);
            if($send_statuses === false)
                throw new Exception('Email(s) failed to send: ' . $mailer->error);

            $tmp = $this->model->update_user_password($user_id, $password_hash, $email, $mobile);
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            if($e->getCode() == 2)
                return ['conflict'=>$e->getMessage()];

            return ['error'=>$e->getMessage()];
        }
    }

    private function build_header()
    {
        $crest = null;
        $contact_colspan = 1;
        if(is_readable(JCT_PATH_MEDIA . 'DATABIZ' . JCT_DE . 'assets' . JCT_DE . 'crest.png'))
        {
            $crest = '<td width="100px" valign="top" align="center" style="padding: 20px 0 20px 20px;"><img alt="" src="' . JCT_URL_MEDIA . 'DATABIZ/assets/crest.png' . '" width="100%" height="auto" /></td>';
            $contact_colspan = 2;
        }

        $org_name = '<h1 style="letter-spacing: -1px;color: #fff;margin: 0;line-height: 1em;">DataBiz Solutions</h1>';
        $org_blurb = '<p class="org-blurb"  style="color: #fff;margin: 0;">IT Solutions for Irish Schools</p>';

        $contact = '<p class="contact" style="text-align: center;margin: 0;padding: 0.5em 1em;background: #306e32;color: #fff;font-size: 0.8rem;">';
        $contact.= '<span style="display: inline-block;padding: 0 0.5em;"><a style="color: inherit;text-decoration: none;" href="mailto:' . JCT_EMAIL_QUERY . '">' . JCT_EMAIL_QUERY . '</a></span>';
        $contact.= '<span style="display: inline-block;padding: 0 0.5em;">' . JCT_PHONE_QUERY . '</span>';
        $contact.= '</p>';

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
                        $contact
                    </td>
                </tr>
            </table>
EOS;
        return $h;
    }

    function relog_user($args)
    {
        if(session_status() === PHP_SESSION_NONE)
            session_start();

        $session_name = SessionManager::SESSION_NAME;

        session_regenerate_id();
        $session_id = session_id();

        try
        {
            if(empty($_SESSION[$session_name]))
                $_SESSION[$session_name] = [];

            if(empty($args))
                throw new Exception('No arguments were detected.');


            $email = (!empty($args['email'])) ? trim(strtolower($args['email'])) : null;
            $password = (!empty($args['password'])) ? trim($args['password']) : null;
            $set_org_guid = (!empty($args['org_guid'])) ? trim(strtoupper($args['org_guid'])) : null;
            $set_role_id = (!empty($args['role_id'])) ? intval($args['role_id']) : 0;


            if(!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new Exception('Invalid email detected.');

            if(empty($password))
                throw new Exception('No Password was detected.');

            if($set_org_guid === null)
                throw new Exception('No set Organisation GUID detected.');

            if($set_role_id === 0)
                throw new Exception('No set Organisation Role detected.');



            // check login

            $tmp = $this->model->check_user_password($email, $password);
            if(isset($tmp['error']))
            {
                $tmp = $this->set_grace_logins();
                return ['error'=>$tmp['response']];
            }

            $user_id = $tmp['user_id'];




            $set_org = $this->model->get_org_by_guid($set_org_guid);


            // establish org db connection

            $tmp = $this->model->set_org_connection($set_org['id']);
            if(is_array($tmp))
            {
                if(isset($tmp['error']))
                    throw new Exception($tmp['error']);
                else
                    throw new Exception('Unidentified error in setting Org connection.');
            }


            // user roles

            $user_org_roles = $this->model->get_roles_of_user($user_id, $set_org['guid']); # [id,id,...]

            if( (empty($user_org_roles)) || (!in_array($set_role_id, $user_org_roles)) )
                throw new Exception('Invalid Role ID.');




            // set session

            $session_manager = new SessionManager($this->model->get_connection());
            $session_manager->set_session_id($session_id);

            $session_manager->clear_user_session_record($user_id);
            $session_manager->clear_user_from_session_globals();

            $cookie_token = $session_manager->create_cookie_token();
            $cookie_value = $session_manager->create_cookie_value(
                $user_id,
                $set_org['guid'],
                $set_role_id,
                $session_id, $cookie_token);

            $session_manager->init_session_args();
            $session_manager->session_args['user']['id'] = $user_id;
            $session_manager->session_args['user']['role_id'] = $set_role_id;
            $session_manager->session_args['user']['prefs'] = [ 'locale' => 'en_GB' ];

            $session_manager->session_args['org']['id'] = $set_org['org_id'];
            $session_manager->session_args['org']['guid'] = $set_org['guid'];
            $session_manager->session_args['org']['name'] = $set_org['org_name'];
            $session_manager->session_args['org']['type_id'] = $set_org['type_id'];
            $session_manager->session_args['org']['sub_type_id'] = $set_org['sub_type_id'];

            $session_manager->recalc_cookie_expiry_times();
            $session_manager->insert_user_into_session_globals($cookie_value);
            $session_manager->save_user_session_record($user_id, $cookie_token);

            return ['success'=>1, 'id'=>$session_id];
        }
        catch(Exception $e)
        {
            unset($_SESSION['databiz']);
            return ['error'=>$e->getMessage()];
        }
    }*/
}