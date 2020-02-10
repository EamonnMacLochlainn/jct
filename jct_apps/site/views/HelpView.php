<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 28/09/2017
 * Time: 10:30
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\ViewInterface;

class HelpView extends BaseView implements ViewInterface
{
    function __construct(HelpModel $model, $permission_type)
    {
        parent::__construct();
        $this->model = $model;
        $this->permission_type = $permission_type;

        $this->update_app_param();

        $this->meta_robots_follow = true;
    }

    function update_app_param()
    {
        $this->app_param = 'site';
    }

    function index()
    {
        $this->screen_title = 'Help';
        $this->view_stylesheets[] = 'help';
        $this->view_scripts[] = 'help';

        $h = '<div class="inner-wrap">';
        $h.= <<<EOS
        
        <div class="light-panel clearfix">
        
            <div class="preamble">
                <p style="text-align: left">Your email address and/or contact number will have been registered with DataBiz Solutions 
                by your School in preparation for setting up your account.</p>
                <p style="text-align: left">Please request a Password by clicking on 'Sign Up', and provide your email address and contact number. 
                If either are recognised, a password will be emailed to you (you can change that password to something more memorable later).</p>
                <!--<p style="text-align: left">Should your details not be recognised, you should contact your School to have them register you.</p>-->
            </div>
            
            <dl class="faqs">
                <dt>My email / contact number is not recognised<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    <p>Make sure that the contact details you are providing are the same as those you originally provided to your School. 
                    Check with your School to see what details they have on record for you - you may have changed your email address recently, for example.</p>
                    <!--<p>They can correct any information we have on record for you, or add you as a user.</p>-->
                </dd>
                <dt>My email address and Password are correct, but I can't log in<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    In almost every case, this is due to one of two factors:
                    <br>
                    <ul>
                        <li>You are trying to login from a place of work, where a security firewall is preventing your login details from being submitted.</li>
                        <li>You are trying to login using an older device, or an out-of-date browser. In the case of the latter, please note that we will 
                        not be extending support to such software in the future. 
                        You can (and should!) <a target="_blank" href="http://browsehappy.com/">update your browser</a> as soon as possible.</li>
                    </ul>
                </dd>
                <!--<dt>I never provided my School with my email address or mobile number<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    Your School's secretary / administrator will be able to take your contact details and add you as a user, at which point you can 
                    use our login form to request a password.
                </dd>-->
                <dt>I never received my email<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    <p>While we receive the contact details for all Guardians recorded by your School for each child, by default we only initially send emails to those 
                        signified as a child's primary contact person. Remaining guardians can be included simply by logging in, thereby activating their account.</p>
                    <p>If you have already activated your account, but are still not receiving any emails from our system, try the following steps:</p>
                    <ol>
                        <li>Check your Spam / Trash folder - if the emails are there, make sure to mark them as 'not spam' to avoid the same happening in the future.</li>
                        <li>Check the spam settings for your email client (e.g. Outlook, GMail, etc.). Make sure that emails it thinks are spam are not automatically deleted!</li>
                        <li>Check that the email address you use to log in is correctly spelled.</li>
                    </ol>
                </dd>
                <dt>I've forgotten my Password<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    That's not a problem - just use our login form to request a new one.
                </dd>
                <dt>Who else knows my Password?<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    No one. Your password is stored in a one-way encrypted format, so even DataBiz Solutions employees cannot know what it is. 
                    So, if you forget your password, just request a new one.
                </dd>
                <dt>How can I change my Password?<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    Once you log in, click on the 'User' link (top right of your screen) to update your password.
                </dd>
                <dt>Can I change my email?<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    Once you log in, click on the 'User' link (top right of your screen) to update your email address.
                </dd>
                <dt>What is my contact information used for?<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    <p>DataBiz Solutions has only two purposes for your contact information:</p>
                    <ul>
                        <li>To match you with your User Account.</li>
                        <li>To allow your School to contact you with appropriate email or SMS notifications.</li>
                    </ul>
                    We do not use your information for any form of advertising, and we do not share your information. 
                    Your details and those of your family members are only available to us, your School, and you.
                </dd>
                <dt>Who can see my family's information?<i class="fa fa-chevron-down faq-expand" aria-hidden="true"></i></dt>
                <dd>
                    <ol>
                        <li>You, and any other persons identified by your School as Guardians for your children.</li>
                        <li>Your School's staff members, should the School administrators have given them access and permission.</li>
                        <li>Appropriate DataBiz Solutions employees.</li>
                    </ol>
                </dd>
            </dl>
            
            <div class="contact">
                <p>Still need help?</p>
                <a class="regular" href="contact-us">Contact Us</a>
            </div>
            
        </div>
EOS;

        $h.= '</div>';

        $this->screen_content = $h;


    }
}