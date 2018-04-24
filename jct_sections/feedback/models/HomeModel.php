<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\feedback;


use JCT\Database;
use Exception;
use JCT\Helper;

class HomeModel
{
    private $_DB;
    private $_ORG_DB;
    private $_Sections_Registry;

    public $data;

    function __construct(Database $db, Database $org_db = null, $sections_registry)
    {
        $this->_DB = $db;
        $this->_ORG_DB = $org_db;
        $this->_Sections_Registry = $sections_registry;
    }

    function index()
    {
    }
}