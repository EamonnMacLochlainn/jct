<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 16:23
 */

namespace JCT\site;


use JCT\Database;
use Exception;
use JCT\Helper;

class ContactUsModel
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