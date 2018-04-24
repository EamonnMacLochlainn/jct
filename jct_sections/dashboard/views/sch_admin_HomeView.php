<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/04/2018
 * Time: 20:59
 */

namespace JCT\dashboard;


use JCT\Localisation;

class sch_admin_HomeView extends HomeView
{
    function __construct(HomeModel $model)
    {
        parent::__construct($model);
    }

    function index()
    {
        $this->screen_title = Localisation::__('Dashboard');
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';

        $h = '';
        if($this->model->data['salute_name'] !== null)
            $h.= '<p class="user-welcome">' . Localisation::__('Welcome') . $this->model->data['salute_name'] . '</p>';

        $this->screen_content = $h;
    }
}