<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 11:25
 */

namespace JCT\dashboard;


use JCT\Database;
use Exception;
use JCT\Helper;

class HomeModel
{
    protected $_DB;
    protected $_Section_Registry;
    public $has_child_class = true;

    private $child_class;

    protected $user_id;
    protected $user_position;
    protected $user_org;

    public $data;

    function __construct(Database $db, $section_registry)
    {
        $this->_DB = $db;
        $this->_Section_Registry = $section_registry;

        $this->user_id = $_SESSION['jct']['id'];
        $this->user_position = $_SESSION['jct']['position'];
        $this->user_org = $_SESSION['jct']['org'];
    }

    function get_child_class()
    {
        $child_model_name = $this->user_org . '_' . $this->user_position . '_Home';
        return __NAMESPACE__ . '\\' . $child_model_name;
    }

    function index()
    {
    }







}