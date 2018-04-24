<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/06/2017
 * Time: 16:03
 */

namespace JCT;

/**
 * Class PermissionsRegistry
 * @package DS
 *
 * This class is intended to provide a quick look-up for the
 * Routing class (and possibly other classes) for the attributes
 * of each application without having to query the database.
 *
 * Each application must therefore be included in the registry
 * in order to be Routed.
 *
 *
 */
class SectionRegistry
{
    private $section_registry;

    function __construct()
    {
        $this->set_app_registry();
    }

    private function set_app_registry()
    {
        $site = new \stdClass();
        $feedback = new \stdClass();
        $dashboard = new \stdClass();

        // whether or not the section requires a User to be logged in
        $site->requires_login = false;
        $feedback->requires_login = false;
        $dashboard->requires_login = true;

        // positions that can access section
        $dashboard->positions = ['admin'];

        $site->is_modular = false;
        $feedback->is_modular = false;
        $dashboard->is_modular = false;

        $site->has_internal_navigation = false;
        $feedback->has_internal_navigation = true;
        $dashboard->has_internal_navigation = true;

        $site->title = null;
        $feedback->title = 'CPD Feedback';
        $dashboard->title = 'Dashboard';

        $site->section_slug = 'site';
        $feedback->section_slug = 'feedback';
        $dashboard->section_slug = 'dashboard';

        $site->home_slug = '';
        $feedback->home_slug = 'feedback/';
        $dashboard->home_slug = 'dashboard/';

        $this->section_registry = new \stdClass();
        $this->section_registry->site = $site;
        $this->section_registry->feedback = $feedback;
        $this->section_registry->dashboard = $dashboard;
    }

    public function get_section_registry($section_name = null)
    {
        if(!is_null($section_name))
        {
            if(!property_exists($this->section_registry, $section_name))
                return false;
            else
                return $this->section_registry->$section_name;
        }

        return $this->section_registry;
    }

    public function get_section_titles($ignore_site = true)
    {
        if(empty($this->section_registry))
            $this->set_app_registry();

        $section_titles = [];
        foreach($this->section_registry as $slug => $section)
        {
            if( ($slug == 'site') && ($ignore_site) )
                continue;

            $section_titles[ $slug ] = $section->title;
        }

        ksort($section_titles);
        return $section_titles;
    }
}