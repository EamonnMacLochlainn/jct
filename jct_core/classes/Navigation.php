<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/06/2017
 * Time: 15:24
 */

namespace JCT;


class Navigation
{
    private $app_param;
    private $module_param;
    private $model_param;
    private $method_param;

    private $user_is_logged_in;
    private $user_position;

    private $app_registry;
    private $app_names;

    public $global_nav_html;
    public $app_nav_html;

    function __construct($user_is_logged_in = false, $user_position = null)
    {
        $this->user_is_logged_in = $user_is_logged_in;
        $this->user_position = $user_position;
    }

    function set_focused_app_registry($app_registry)
    {
        $this->app_registry = $app_registry;
    }

    function set_app_names($app_names)
    {
        $this->app_names = $app_names;
    }

    function get_navigation($app_param, $module_param = null, $model_param, $method_param)
    {
        $this->app_param = $app_param;
        $this->module_param = $module_param;
        $this->model_param = $model_param;
        $this->method_param = $method_param;

        $this->build_global_nav_html();
        $this->build_app_nav_html();
    }

    private function build_global_nav_html()
    {
        $h = '<nav class="global-nav">';
        $h.= '<span class="global-nav-trigger"><i class="fa fa-bars"></i></span>';
        $h.= '<ul>';

        $h.= '<li class="home-link"><a href="' . JCT_URL_ROOT . '"><i class="fa fa-home icon-left"></i><span>' . Localisation::__('JCT Registration') . '</span></a></li>';

        // legacy links vs new platform
        if($this->app_param == 'site')
        {
            $h.= '<li class="support-link"><span class="global-sub-nav-trigger">Support<i class="fa fa-chevron-down"></i></span>';
            $h.= '<ul class="global-sub-nav">';
            $h.= '<li><a href="' . JCT_URL_MEDIA . 'download.php?p=databiz_rs_anydesk.exe">AnyDesk</a></li>';
            $h.= '<li><a href="' . JCT_URL_MEDIA . 'download.php?p=databiz_rs_teamviewer_11.exe">Teamviewer (Win)</a></li>';
            $h.= '<li><a href="' . JCT_URL_MEDIA . 'download.php?p=databiz_rs_teamviewer_ios.dmg">Teamviewer (iOS)</a></li>';
            $h.= '</ul>';
            $h.= '</li>';

            $h.= '<li class="ext-link"><a href="//jct.ie">JCT.ie</a></li>';
            $h.= '<li class="ext-link"><a href="' . JCT_URL_ROOT . 'Feedback/">Feedback</a></li>';
        }

        if($this->user_is_logged_in)
        {
            $h.= '<li class="app-selection-link"><a href="' . JCT_URL_ROOT . 'Dashboard/"><i class="fa fa-cubes icon-left"></i><span>' . Localisation::__('DASHBOARD') . '</span></a></li>';
            $h.= '<li><a href="' . JCT_URL_ROOT . 'logout" class="login-link"><i class="fal fa-sign-out icon-left"></i><span>' . Localisation::__('LOG_OUT') . '</span></a></li>';
        }
        else
        {
            if($this->app_param != 'site')
                $h.= '<li><a href="' . JCT_URL_ROOT . 'login" class="login-link"><i class="fal fa-sign-in icon-left"></i><span>' . Localisation::__('LOG_IN') . '</span></a></li>';
        }




        $h.= '</ul>';
        $h.= '</nav>';

        $this->global_nav_html = $h;
    }

    private function build_app_nav_html()
    {
        if(is_null($this->app_registry))
            return null;

        if(!$this->app_registry->has_internal_navigation)
            return null;

        $a = new SectionNavigation($this->app_registry, $this->module_param, $this->model_param, $this->method_param);
        $this->app_nav_html = $a->get_app_navigation();
    }

}