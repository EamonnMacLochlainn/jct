<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:03
 */

namespace JCT\feedback;


use JCT\BaseView;
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
        $this->section_slug = 'feedback';
    }

    function index()
    {
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';


        $this->screen_content = 'feedback';
    }
}