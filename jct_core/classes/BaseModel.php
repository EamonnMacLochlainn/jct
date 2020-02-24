<?php


namespace JCT;


use Exception;

class BaseModel
{
    protected $_DB;
    public $data;

    protected $session_error = false;

    protected $person_guid;
    protected $person_role_id;

    protected $org_guid;
    protected $org_type_id;
    protected $org_sub_type_id;

    function __construct(Database $db)
    {
        $this->_DB = $db;
        $s = new SessionManager($db);
        $c = $s->get_available_user_parameters();

        $this->person_guid = $c['person_guid'];
        $this->person_role_id = intval($c['role_id']);
        $this->org_guid = $c['org_guid'];
        $this->org_type_id = intval($c['org_type_id']);
        $this->org_sub_type_id = intval($c['org_sub_type_id']);
    }

    function person_role_id()
    {
        return $this->person_role_id;
    }

    function org_guid()
    {
        return $this->org_guid;
    }

    function org_type_id()
    {
        return $this->org_type_id;
    }

    function org_sub_type_id()
    {
        return $this->org_sub_type_id;
    }
}