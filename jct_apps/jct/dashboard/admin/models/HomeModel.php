<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\dashboard;


use JCT\BaseModel;
use JCT\Connection;
use JCT\Database;
use Exception;
use JCT\Helper;

class HomeModel extends BaseModel
{
    function __construct(Database $db)
    {
        parent::__construct($db);
    }

    function index()
    {
    }
}