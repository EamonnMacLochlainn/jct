<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/04/2018
 * Time: 20:34
 */

namespace JCT\dashboard;


use JCT\Database;

class sch_admin_HomeModel extends HomeModel
{
    function __construct(Database $db, $section_registry)
    {
        parent::__construct($db, $section_registry);
    }

    function index()
    {
        $db = $this->_DB;
        $db->query(" SELECT CONCAT_WS(' ', fname, lname) AS full_name, salute_name FROM person WHERE id = :id ");
        $db->bind(':id', $this->user_id);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        if(!empty($tmp['salute_name']))
            $this->data['salute_name'] = $tmp['salute_name'];
        elseif(!empty($tmp['full_name']))
            $this->data['salute_name'] = $tmp['full_name'];
        else
            $this->data['salute_name'] = null;
    }
}