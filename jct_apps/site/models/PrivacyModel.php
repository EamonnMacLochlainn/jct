<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 16:23
 */

namespace JCT\site;


use JCT\Database;

class PrivacyModel
{
    private $_DB;
    private $_ORG_DB;

    public $data;

    function __construct(Database $db, Database $org_db = null)
    {
        $this->_DB = $db;
        $this->_ORG_DB = $org_db;
    }

    function index()
    {
    }
}