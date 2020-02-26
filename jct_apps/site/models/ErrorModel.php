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

    public $data;

    function __construct(Database $db)
    {
        $this->_DB = $db;
    }

    function index($args = [])
    {
        $type = (!empty($args['error_type'])) ? $args['error_type'] : 'unknown';
        $this->error_title = ucwords($type) . ' Error';

        $requested_uri = (!empty($args['requested_uri'])) ? base64_decode($args['requested_uri']) : ' this URL';
        $this->error_message = 'While attempting to access \'' . $requested_uri . '\'<br/>';
        $this->error_message.= (!empty($args['error_msg'])) ? ' (' . base64_decode($args['error_msg']) . ')' : ' (unspecified error occurred)';
    }
}