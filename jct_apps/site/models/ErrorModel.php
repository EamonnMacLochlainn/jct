<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:04
 */

namespace JCT\site;


use JCT\Database;

class ErrorModel
{
    private $_DB;

    public $error_title;
    public $error_message;

    function __construct(Database $db)
    {
        $this->_DB = $db;
    }

    function index($args = [])
    {
        $error_type = (empty($args['error_type'])) ? 'default' : $args['error_type'];

        switch($error_type)
        {
            case('routing'):
                $this->error_title = 'Routing Error';
                $uri = (empty($args['requested_uri'])) ? '' : '(' . $args['requested_uri'] . ') ';
                $this->error_message = 'The requested address ' . $uri . 'could not be found.';
                if(!empty($args['error_message']))
                    $this->error_message.= ' (' . $args['error_message'] . ')';
                break;
            case('login'):
                $this->error_title = 'Login Error';
                $this->error_message = 'You need to log in to view this screen.';
                break;
            case('permission'):
                $this->error_title = 'Permissions Error';
                $this->error_message = 'You do not have permission to view this screen.';
                break;
            default:
                $this->error_title = ucwords($error_type) . ' Error';
                $this->error_message = (empty($args['error_message'])) ? 'An unexpected error occurred.' : $args['error_message'];
                break;
        }
    }
}