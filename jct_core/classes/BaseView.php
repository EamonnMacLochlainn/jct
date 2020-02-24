<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/06/2017
 * Time: 15:08
 */

namespace JCT;


class BaseView
{
    protected $model;
    public $app_param;

    public $meta_description = 'One of the largest suppliers of software to the educational sector in Ireland.';
    public $meta_robots_follow = false;

    public $screen_classes = [];
    public $view_stylesheets = [];
    public $view_scripts = [];

    public $screen_tab_title;
    public $screen_title;
    public $screen_blurb;
    public $screen_content;

    function __construct()
    {
    }
}