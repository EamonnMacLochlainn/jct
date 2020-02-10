<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/04/2016
 * Time: 12:30
 */

namespace JCT;






date_default_timezone_set('UTC');

require_once 'jct_core/Config.php';
require_once 'jct_core/classes/Helper.php';
require_once 'jct_core/classes/Autoloader.php';

if(session_status() === PHP_SESSION_NONE)
    session_start();

new Render();