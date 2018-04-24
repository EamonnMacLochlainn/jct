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
    public $section_slug;

    public $meta_description = 'One of the largest suppliers of software to the educational sector in Ireland.';
    public $meta_robots_follow = false;

    public $view_stylesheets = [];
    public $screen_classes = [
        'jct'
    ];
    public $view_scripts = [];

    public $screen_tab_title;
    public $screen_title;
    public $screen_blurb;
    public $screen_content;

    function __construct()
    {
    }
}