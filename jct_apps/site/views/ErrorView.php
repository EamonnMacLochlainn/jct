<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:36
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\Helper;
use JCT\ViewInterface;

class ErrorView extends BaseView implements ViewInterface
{
    function __construct(ErrorModel $model)
    {
        parent::__construct();
        $this->model = $model;

        $this->update_app_param();

        $this->meta_robots_follow = true;
        $this->screen_classes[] = 'error';
    }

    function update_app_param()
    {
        $this->app_param = 'site';
    }

    function index()
    {
        $this->view_stylesheets[] = 'error';
        $h = '<div class="error-content">';
        $h.= '<h2>' . $this->model->error_title . '</h2>';
        $h.= '<p>' . $this->model->error_message . '</p>';
        $h.= '<p class="error-footer">Go <span onclick="window.history.go(-1); return false;">back</span> or return to <a href="' . JCT_URL_ROOT . '">Home</a> page.</p>';
        $h.= '</div>';

        $this->screen_content = $h;
    }
}