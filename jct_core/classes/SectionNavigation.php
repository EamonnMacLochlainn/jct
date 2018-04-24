<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/06/2017
 * Time: 15:31
 */

namespace JCT;


class SectionNavigation
{
    private $section;
    private $section_slug;
    private $module_param;
    private $model_param;
    private $method_param;

    private $user_position;

    // app modules:
    private $nav_slug;

    function __construct($this_section_registry, $module_param = null, $model_param, $method_param)
    {
        $this->section = $this_section_registry;
        $this->section_slug = $this->section->section_slug;
        $this->module_param = $module_param;
        $this->model_param = $model_param;
        $this->method_param = $method_param;

        $this->nav_slug = $this->build_nav_slug();
    }

    private function build_nav_slug()
    {
        $tmp = [];
        if(!empty($this->section_slug))
            $tmp[] = strtolower($this->section_slug);
        if(!empty($this->module_param))
            $tmp[] = strtolower($this->module_param);
        if(!empty($this->model_param))
            $tmp[] = strtolower($this->model_param);
        if(!empty($this->method_param))
            $tmp[] = strtolower($this->method_param);

        return implode(':', $tmp);
    }

    function get_app_navigation()
    {
        // show nothing if application has no internal navigation
        if(!$this->section->has_internal_navigation)
            return null;

        // show nothing if user is not logged in
        if(isset($_SESSION['jct']))
        {
            if(empty($_SESSION['jct']['position']))
                return null;

            $this->user_position = $_SESSION['jct']['position'];
        }

        // show nothing if nav_slug not set
        if($this->nav_slug === null)
            return null;

        // show nothing if no method exists for this application's navigation
        $nav = $this->get_navigation();
        if(empty($nav))
            return null;


        // build nav
        $ctn = '<nav class="app-nav-ctn ' . $this->section_slug . '-nav">';

        // home link
        $ctn.= '<div class="section-nav-home-link"><a href="' . JCT_URL_ROOT . '"><i class="fa fa-home icon-left"></i><span>DataBiz Solutions</span></a></div>';

        // sidebar
        $ctn.= '<div class="section-nav-sidebar">';
        $ctn.= '<span class="fa fa-bars section-nav-trigger"></span>';
        $ctn.= $this->get_section_sidebar();
        $ctn.= '</div>';

        // nav window
        $ctn.= '<div class="section-nav-window">';
        // title
        $ctn.= '<a class="section-nav-app-home" href="' . JCT_URL_ROOT . $this->section->home_slug . '">' . $this->section->title . '</a>';
        // nav
        $ctn.= $nav;
        $ctn.= '</div>';

        $ctn.= '</nav>';

        return $ctn;
    }

    private function get_section_sidebar()
    {
        $func_call = 'get_' . $this->section_slug . '_sidebar';
        if(!method_exists($this, $func_call))
            return null;
        else
            return $this->$func_call();
    }

    private function get_navigation()
    {
        $func_call = 'get_' . $this->section_slug . '_navs';
        if(!method_exists($this, $func_call))
            return null;
        else
            return $this->$func_call();
    }

    private function get_feedback_navs()
    {
        $app_root_url = JCT_URL_ROOT . $this->section_slug . '/';

        // breadcrumbs
        $h = '<ul class="section-nav-breadcrumbs">';
        $h.= '<li data-get="feedback:home" class="visible"><span>Home</span></li>';
        $h.= '</ul>';


        // sysadmin:home
        $h.= '<ul class="section-nav" data-layer="feedback:home">';
        $h.= '<li><span><a href="' . $app_root_url . '">Home</a></span></li>';
        $h.= '</ul>';

        return $h;
    }
}