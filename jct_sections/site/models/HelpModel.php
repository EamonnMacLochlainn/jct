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
    private $_Permissions_Registry;

    public $data;

    function __construct(Database $db, $permissions_registry)
    {
        $this->_DB = $db;
        $this->_Permissions_Registry = $permissions_registry;
    }

    function index()
    {
    }
}