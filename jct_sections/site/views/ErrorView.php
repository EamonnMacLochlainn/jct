<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:36
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\ViewInterface;

class ErrorView extends BaseView implements ViewInterface
{
    private $errors = [];

    function __construct(ErrorModel $model)
    {
        parent::__construct();
        $this->model = $model;

        $this->update_section_slug();

        $this->meta_robots_follow = true;
        $this->screen_classes[] = 'error';
    }

    function update_section_slug()
    {
        $this->section_slug = 'site';
    }

    function index()
    {
        $h = '<div class="error-content ' . $this->model->error_class . '">';
        $h.= '<h2>' . $this->model->error_title . '</h2>';
        $h.= '<p>' . $this->model->error_message . '</p>';
        $h.= '</div>';

        $this->screen_content = $h;
    }
}