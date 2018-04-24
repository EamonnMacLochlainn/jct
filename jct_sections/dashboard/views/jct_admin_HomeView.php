<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/04/2018
 * Time: 20:59
 */

namespace JCT\dashboard;


use JCT\Helper;
use JCT\Localisation;

class jct_admin_HomeView extends HomeView
{
    function __construct(HomeModel $model)
    {
        parent::__construct($model);
    }

    function index()
    {
        $this->screen_title = Localisation::__('Dashboard');
        $this->screen_classes[] = 'home-page';
        $this->view_scripts[] = 'jct_admin';
        $this->view_stylesheets[] = 'home';
        $this->view_stylesheets[] = 'jct_admin';

        $h = '';
        if($this->model->data['salute_name'] !== null)
            $h.= '<p class="user-welcome">Welcome ' . $this->model->data['salute_name'] . '</p>';

        $h.= $this->school_numbers_widget();

        $this->screen_content = $h;
    }

    private function school_numbers_widget()
    {
        $h = '<div class="numbers-widget widget">';
        $h.= '<div class="widget-title"><h4>School Numbers</h4><i class="fal fa-calculator"></i></div>';

        $h.= '<div class="widget-inner">';

        foreach($this->model->data['statistics']['number_of_active_schools'] as $key => $values)
        {
            $h.= '<div class="numbers-widget-block widget-inner-block">';
            if(!is_array($values))
                $h.= '<p class="widget-key-with-value">' . $key . '<span>' . $values . '</span></p>';
            else
            {
                $h.= '<p class="widget-sub-title">' . $key . '</p>';
                foreach($values as $k => $v)
                    $h.= '<p class="widget-key-with-value">' . $k . '<span>' . $v . '</span></p>';
            }
            $h.= '</div>';
        }

        $h.= '</div>';

        $h.= '</div>';
        return $h;
    }
}