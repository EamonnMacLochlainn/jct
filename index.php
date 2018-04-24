<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;

require_once 'jct_core/Config.php';
require_once 'jct_core/classes/Autoloader.php';


$current_cookie_params = session_get_cookie_params();
session_set_cookie_params(
    $current_cookie_params["lifetime"],
    $current_cookie_params["path"],
    $current_cookie_params["domain"],
    $current_cookie_params["secure"],
    true
);
session_start();

new Router(new SectionRegistry(), new Render( new \Browser() ) );




