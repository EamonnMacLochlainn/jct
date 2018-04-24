<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:04
 */

namespace JCT\site;


use JCT\Database;
use Exception;
use JCT\Helper;

class ErrorModel
{
    private $_DB;
    private $_Permissions_Registry;

    private $default_message = 'An unexpected error occurred.';
    private $errors = [
        'default_error' => ['title'=>'Error', 'message'=>'An unexpected error occurred.'],
        'routing_error' => ['title'=>'Not Found', 'message'=>'The address {address}could not be found.'],
        'login_error' => ['title'=>'Access Denied', 'message'=>'You need to login to view this screen.'],
        'role_error' => ['title'=>'Access Denied', 'message'=>'You need a higher Role in order to view this screen.'],
        'permissions_error' => ['title'=>'Access Denied', 'message'=>'You do not have permission to view this screen.'],
        'data_error' => ['title'=>'Data Error', 'message'=>'An error was encountered while gathering data to show.'],
    ];

    public $error_class;
    public $error_title;
    public $error_message;

    function __construct(Database $db, $permissions_registry)
    {
        $this->_DB = $db;
        $this->_Permissions_Registry = $permissions_registry;
    }

    function index($args = null)
    {
        if(empty($args['error']))
        {
            $this->error_class = 'default';
            $this->error_message = $this->default_message;
        }

        $error = $args['error'];
        $type = (!empty($error['type'])) ? $error['type'] : 'default';
        $param = (!empty($error['param'])) ? '<span id="error-param">' . $error['param'] . '</span>' : null;
        $thrown_error = (!empty($error['thrown_error'])) ? '<span id="thrown-error">' . $error['thrown_error'] . '</span>' : null;

        if(!array_key_exists($type, $this->errors))
            $type = 'default';

        $this->error_class = str_replace('_', '-', $type);
        $this->error_title = $this->errors[ $type ]['title'];
        if($type == 'routing_error')
        {
            $address = (!is_null($param)) ? '\'' . trim($param) .'\' ' : null;
            $this->error_message = str_replace('{address}', $address, $this->errors[ $type ]['message']);
        }
        else
            $this->error_message = $this->errors[ $type ]['message'] . $thrown_error;
    }
}