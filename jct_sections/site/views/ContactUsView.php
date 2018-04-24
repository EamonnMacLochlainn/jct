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

class ContactUsView extends BaseView implements ViewInterface
{
    function __construct(ContactUsModel $model)
    {
        parent::__construct();
        $this->model = $model;

        $this->update_section_slug();

        $this->meta_robots_follow = true;
    }

    function update_section_slug()
    {
        $this->section_slug = 'site';
    }

    function index()
    {
        $this->screen_title = 'Contact Us';
        $this->screen_classes[] = 'contact';
        $this->view_scripts[] = 'contact_us';
        $this->view_stylesheets[] = 'contact_us';

        $h = <<<EOS
<div class="light-panel">
    <form action="" method="post">  
    
    <p>Use the form below to send us an email, and a member of the DataBiz Solutions team will get back to you as soon as possible!</p>
    
    <p class="form-note">Fields marked with an <span class="negative">*</span> are required fields.</p>
        
        <label class="name">
            <span class="label-text">Full Name:<span class="negative">*</span></span>
            <input type="text" name="name" value="" />
        </label>
        
        <label class="email">
            <span class="label-text">Email Address:<span class="negative">*</span></span>
            <input type="email" name="email" value="" />
        </label>
        
        <label class="contact-number">
            <span class="label-text">Contact Number:</span>
            <input type="tel" name="contact_number" value="" />
        </label>
        
        <label class="contact-by-phone">
            <span class="label-text">Request Callback:</span>
            <input type="checkbox" name="contact_by_phone" value="1" />
        </label>
        
        <label class="subject">
            <span class="label-text">Subject or Product:</span>
            <input type="text" name="subject" value="" />
        </label>
        
        <label class="message">
            <span class="label-text">Message:<span class="negative">*</span></span>
            <textarea name="message"></textarea>
        </label>
        
        <div class="g-recaptcha" data-sitekey="6Lf85EwUAAAAAJ9fQPMEB_LyzBgj7WiX40P4nMAc"></div>
        
        <input type="submit" class="button regular send-message" value="Send Message" data-set="Send Message">
        
    </form>  
</div>
EOS;

        $this->screen_content = $h;
    }
}