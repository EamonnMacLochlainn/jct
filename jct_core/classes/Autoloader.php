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
     * Class names use the following pattern:
     * [namespace]/[model_title] // core classes
     * [namespace]/[app_slug]/[model_title] // accessible to all classes
     * [namespace]/[org_section_slug]/[app_slug]/[user_module_slug]/[model_title] // modulated classes
     */


    $app_slug = null;
    $org_section = null;
    $user_module = null;

    $model_title = null;
    $view_title = null;

    $class_parts = explode('\\', $class);
    if(count($class_parts) > 1)
        array_shift($class_parts);

    $class_title = array_pop($class_parts);

    if(empty($class_parts))
        $dir_path = JCT_PATH_CORE . 'classes' . JCT_DE;
    else
    {
        $x = str_replace(['Model','View','Controller'], '', $class_title);
        $model_title = $x . 'Model';
        $view_title = $x . 'View';

        $dir_path = JCT_PATH_APPS . implode(JCT_DE, $class_parts) . JCT_DE;
        if($class_title == $model_title)
            $class_directory = 'models';
        else
            $class_directory = ($class_title == $view_title) ? 'views' : 'controllers';

        $dir_path.= $class_directory . JCT_DE;
    }

    $dir_iterator = new \RecursiveDirectoryIterator($dir_path, \RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file)
    {
        $file_name = $file->getFilename();
        if(strtoupper($class_title . '.php') == strtoupper($file_name))
        {
            require_once $file;
            return true;
        }
    }

    return false;
});