<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:03
 */

namespace JCT\jct\dashboard\admin;


use JCT\BaseView;
use JCT\Helper;
use JCT\Localisation;
use JCT\ViewInterface;

class HomeView extends BaseView implements ViewInterface
{
    function __construct(HomeModel $model)
    {
        parent::__construct();
        $this->model = $model;

        $this->update_app_param();

        $this->meta_robots_follow = true;
    }

    function update_app_param()
    {
        $this->app_param = 'dashboard';
    }

    function index()
    {
        $org_type_id = intval($this->model->org_type_id());

        switch($org_type_id)
        {
            case(1): // JCT
                $this->jct_admin_index();
                break;
            case(2): // Education Center
                break;
            case(3): // School
                $org_sub_type_id = intval($this->model->org_sub_type_id());
                $this->school_index();
                break;
        }
    }

    function jct_admin_index()
    {
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';

        $h = <<<EOS
        Admin index
EOS;
        $this->screen_content = $h;
    }

    function school_index()
    {
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home_school';
        $this->view_stylesheets[] = 'home_school';

        $h = <<<EOS
        School Index
EOS;
        $this->screen_content = $h;
    }
}