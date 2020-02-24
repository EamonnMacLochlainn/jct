<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/06/2017
 * Time: 16:59
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\ViewInterface;

class PrivacyView extends BaseView implements ViewInterface
{
    function __construct(PrivacyModel $model)
    {
        parent::__construct();
        $this->model = $model;

        $this->update_app_param();

        $this->meta_robots_follow = true;
    }

    function update_app_param()
    {
        $this->app_param = 'site';
    }

    function index()
    {
        $this->screen_title = 'Privacy & GDPR';
        $this->screen_classes[] = 'privacy';
        $this->view_stylesheets[] = 'privacy';

        $h = <<<EOS
<div class="light-panel">
<div class="inner">  

					<p>We have created this privacy statement to demonstrate our firm commitment to your privacy and to the protection of your data. By visiting www.jctregistration.ie, you are accepting and consenting to the practices described in this Privacy Statement. If you do not agree to the terms in this Statement, we kindly ask you to leave the site. Where you provide personal data to JCT Registration such data will be used only for the service you have requested.</p>

<h3>Controller of personal information</h3>
<p>Any personal information provided to or to be gathered by www.jctregistration.ie is held by JCT Support Service, Monaghan Education Centre, Armagh Road, Co. Monaghan, Ireland. Tel: +353 (47) 74008.</p>

<h3>Disclosure of data</h3>
<p>JCT Registration may use the information received to provide JCT Registration services.<br></p>
<p>JCT Registration will not disclose any of your personally identifiable information without your permission except under special circumstances, for instance if JCT Registration is required by law or a court order to do so. Any information collected by JCT Registration will be treated confidentially and we will not sell or rent your personally identifiable information to anyone.</p>

<h3>Storage of personal information</h3>
<p>JCT Registration only stores information for as long as is necessary and, where relevant, makes every effort to keep information up-to-date. Once the information is no longer required, it is securely deleted.</p>

<h3>Removal or alteration of personal data</h3>
<ul>
<li>You have the right to be given a copy of information held by us about you. We may charge a fee for this which will not exceed 6.35 Euro.</li>
<li>You have the right to access your data and to have any inaccuracies in the details we hold corrected.</li>
<li>You also have the right to have the information erased if we do not have a legitimate reason for retaining same.</li>
<li>We will accede to any such valid requests within 40 calendar days of the receipt of a valid request in writing. Please send all requests to JCT Support Service, Monaghan Education Centre, Armagh Road, Co. Monaghan, Ireland or email info@jctregistration.ie.</li>
<li>We reserve the right to request you to provide additional information in order to enable us to identify your personal data and/or to verify your identity.</li>
</ul>

<h3>Security</h3>
<p>Whenever JCT Registration handles personal information, regardless of where this occurs, JCT Registration takes every precaution to ensure that your information is treated securely using technologies such as encryption and firewalls.</p>

<h3>Website details</h3>
<p>When visiting our web pages, tracking technologies may record information about your visit automatically. This information does not identify you personally. The only information about your visit that we automatically collect and store is the IP address from which you access our website (an IP address is a number, ‘xxx.xxx.xxx.xxx’, that is automatically assigned to your computer whenever you are surfing the web).</p>

<h3>Notification of Changes</h3>
<p>If we decide to change our Privacy Policy, we will post those changes here so our users may always know what information we collect, how we use it, and under what circumstances, if any, we disclose it. We will use the information in accordance with the Privacy Policy under which the information was collected.</p>
</div>
</div>
EOS;

        $this->screen_content = $h;
    }
}