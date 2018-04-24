<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:03
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\Helper;
use JCT\Cryptor;
use JCT\Localisation;
use JCT\ViewInterface;

class HomeView extends BaseView implements ViewInterface
{
    function __construct(HomeModel $model)
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
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';


        $username = Localisation::__('Username');
        $password = Localisation::__('Password');
        $show_password = Localisation::__('Show Password');
        $login = Localisation::__('Log In');
        $reset_password = Localisation::__('Reset Password');

        $h = <<<EOS
<section class="home-preamble">  
<h2>Welcome to <b>JCT Registration.ie</b></h2>
<p>This is the official site for Schools to register for Continual Professional Development Workshops, Electives, and Courses.</p>
<p>Use the adjacent form to log in.</p>

<h3>Need help logging in?</h3>
<p>Find answers to your questions in our <a href="help">Help Section</a>.</p>
</section>
<form id="login" action="" method="post">  
    <fieldset>  
        <label class="username">  
            <span class="label-text">$username:</span>
            <input type="text" name="username" value="" autocomplete="off" />
        </label>
        <label class="password">  
            <span class="label-text">$password:</span>
            <input type="password" name="password" value="" autocomplete="off" />
        </label>
        <label class="show-password">  
            <span class="label-text">$show_password:</span>
            <input type="checkbox" name="show_password" value="1" />
        </label>
    </fieldset>
    <div class="buttonset">  
        <button class="button negative" id="reset-password">$reset_password</button>
        <button class="button regular" id="login-btn">$login</button>
    </div>
</form> 
EOS;

        $this->screen_content = $h;
    }

    function logout()
    {
        //void
    }
}