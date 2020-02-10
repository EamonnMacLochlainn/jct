<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:03
 */

namespace JCT\dashboard;


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
        $this->app_param = 'dashboard';
    }

    function index()
    {
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';

        $h = <<<EOS
        here

EOS;

        $this->screen_content = $h;
    }
}