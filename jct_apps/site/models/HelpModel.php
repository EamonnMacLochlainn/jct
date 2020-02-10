<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 28/09/2017
 * Time: 10:31
 */

namespace JCT\site;


use JCT\Database;
use Exception;
use JCT\Helper;

class HelpModel
{
    private $_DB;

    public $data;

    function __construct(Database $db)
    {
        $this->_DB = $db;
    }

    function index()
    {
    }
}