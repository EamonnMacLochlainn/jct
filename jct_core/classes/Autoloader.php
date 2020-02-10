<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;

//use DS\AppRegistry;

spl_autoload_register(function($class)
{
    /**
     * Paths use the following pattern:
     * [app_slug][module_slug][destination_slug]
     */

    $class_name = null;
    $module_name = null;
    $app_namespace = null;

    $class_parts = explode('\\', $class);
    if(count($class_parts) < 4)
    {
        $class_name = array_pop($class_parts);
        $app_namespace = (!empty($class_parts)) ? array_pop($class_parts) : null;
    }
    else
    {
        $class_name = array_pop($class_parts);
        $module_name = array_pop($class_parts);
        $app_namespace = (!empty($class_parts)) ? array_pop($class_parts) : null;
    }

    if($app_namespace == __NAMESPACE__)
        $app_namespace = null;

    if(is_null($app_namespace)) // looking for a core class
    {
        $app_path = JCT_PATH_CORE;
        $class_directory = 'classes';
    }
    else // looking for an app class
    {
        $app_path = JCT_PATH_APPS . strtolower($app_namespace) . JCT_DE;

        // flesh out where to find the class
        //
        $class_directory = 'models';

        if(substr($class_name, -4) == 'View')
            $class_directory = 'views';
        elseif(substr($class_name, -10) == 'Controller')
            $class_directory = 'controllers';

        if(!is_null($module_name))
            $class_directory = $module_name . JCT_DE . $class_directory;
    }

    $app_path.= $class_directory . JCT_DE;
    if(!is_dir($app_path))
        return false;

    $dir_iterator = new \RecursiveDirectoryIterator($app_path, \RecursiveDirectoryIterator::SKIP_DOTS);
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