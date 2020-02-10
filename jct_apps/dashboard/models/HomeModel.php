<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\dashboard;


use JCT\Connection;
use JCT\Database;
use Exception;
use JCT\Helper;

class HomeModel
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