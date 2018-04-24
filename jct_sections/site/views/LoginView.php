<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/05/2016
 * Time: 13:41
 */

namespace JCT\site;


use JCT\GlobalView;
use JCT\Helper;
use JCT\Localisation;
use JCT\Render;

use JCT\BaseView;
use JCT\ViewInterface;

class LoginView extends BaseView implements ViewInterface
{
    function __construct(LoginModel $model)
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
        $this->screen_title = '';
        $this->view_stylesheets[] = 'login';
        $this->view_scripts[] = 'login';

        $crest_cra = $this->model->data['crest_src'];
        $login_cta = Localisation::__('LOG_IN');
        $password_cta = Localisation::__('REQUEST_A_PASSWORD');
        $help_cta = Localisation::__('HELP');


        $h = <<<EOS
<form action="" method="post" class="login light-panel" />
    <!--<input type="hidden" name="org_id" value=""/>-->
    
    <div class="icon-ctn">
        <img src="$crest_cra"/>
    </div>

    <fieldset>
    
        <label class="email left-iconed-input"><i class="fa fa-user"></i>
            <input type="text" name="email" autocomplete="on" placeholder="Email" data-role="email"/>
        </label>

        <label class="password left-iconed-input"><i class="fa fa-lock"></i> 
            <input type="password" name="password" autocomplete="off" placeholder="Password" data-role="password" />
        </label>

    </fieldset>

    <div class="buttonset"> 
        <button class="positive login">$login_cta</button> 
    </div>

    <div class="new-password"> 
        <button class="positive password-request">$password_cta</button> 
        <button class="regular login-help-trigger">$help_cta</button>
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