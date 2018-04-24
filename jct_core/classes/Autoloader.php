<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;

spl_autoload_register(function($class)
{
    /**
     * Paths use the following pattern:
     * [namespace][app_namespace][module_name][class_name]
     */

    $class_name = null;
    $module_name = null;
    $section_namespace = null;

    $class_parts = explode('\\', $class);
    if(count($class_parts) < 4)
    {
        $class_name = array_pop($class_parts);
        $section_namespace = (!empty($class_parts)) ? array_pop($class_parts) : null;
    }
    else
    {
        $class_name = array_pop($class_parts);
        $module_name = array_pop($class_parts);
        $section_namespace = (!empty($class_parts)) ? array_pop($class_parts) : null;
    }

    if($section_namespace == __NAMESPACE__)
        $section_namespace = null;

    if(is_null($section_namespace)) // looking for a core class
    {
        $section_path = JCT_PATH_CORE;
        $class_directory = 'classes';
    }
    else // looking for a section class
    {
        $section_path = JCT_PATH_SECTIONS . strtolower($section_namespace) . JCT_DE;

        // flesh out where to find the class
        //
        $class_directory = 'models';
        if(stripos($class_name, 'Controller') !== false)
            $class_directory = 'controllers';
        else if(stripos($class_name, 'View') !== false)
            $class_directory = 'views';

        if(!is_null($module_name))
            $class_directory = $module_name . JCT_DE . $class_directory;
    }

    $section_path.= $class_directory . JCT_DE;
    if(!is_dir($section_path))
        return false;

    $dir_iterator = new \RecursiveDirectoryIterator($section_path, \RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::CHILD_FIRST);

    foreach($iterator as $file)
    {
        $file_name = $file->getFilename();
        if(strtoupper($class_name . '.php') == strtoupper($file_name))
        {
            require_once $file;
            return true;
        }
    }

    return false;
});