<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:03
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\Localisation;
use JCT\ViewInterface;

class HomeView extends BaseView implements ViewInterface
{
    function __construct(HomeModel $model, $permission_type)
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
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';

        $bubbles_src = JCT_URL_ASSETS . 'css/images/bubbles_bg.png';
        $login_cta = Localisation::__('LOG_IN');
        $password_cta = Localisation::__('SIGN_UP');
        $help_cta = Localisation::__('HELP');

        $h = <<<EOS
        
        <div class="home-wrap"> 
        
            <div class="bubbles"><img src="$bubbles_src" alt="" /></div>
        
            <div class="welcome">  
                <h1>Welcome to the official registration site for Continual Professional Development for the Junior Cycle for Teachers programme.</h1>
                <p>Please use the adjacent form to log in.</p>
            </div>
            
            <form action="" method="post" class="login light-panel">
            
                <fieldset>
                
                    <label class="username left-iconed-input"><i class="fa fa-user"></i>
                        <input type="text" name="username" autocomplete="on" placeholder="Username" data-role="username"/>
                    </label>
            
                    <label class="password left-iconed-input"><i class="fa fa-lock"></i> 
                        <input type="password" name="password" autocomplete="off" placeholder="Password" data-role="password" />
                    </label>
            
                    <label class="org">
                        <span class="label-text">You are registered for more than one Organisation. Please pick one:</span>
                        <select><option value="0">--</option></select>
                    </label>
            
                    <label class="role">
                        <span class="label-text">You are registered for more than one Role. Please pick one:</span>
                        <select><option value="0">--</option></select>
                    </label>
            
                </fieldset>
            
                <div class="buttonset"> 
                    <button class="positive login">$login_cta</button> 
                </div>
                
                <div class="login-links">  
                    <a href="" class="request-password">Forgotten Password?</a>
                    <a href="Help" class="help">Help</a>
                </div>
                
                
            </form>
          
        </div>

EOS;

        $this->screen_content = $h;
    }
}