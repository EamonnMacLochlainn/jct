<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 25/06/2017
 * Time: 15:54
 */

namespace JCT;


use Exception;

class User extends Connection
{
    private $is_logged_in = false;
    private $id = 0;
    private $position = 99;
    private $role_id = 'User';

    private $org;
    private $permissions;
    private $name;

    public $user_error;

    function __construct()
    {
        parent::__construct();
        if(!$this->default_connection_ok)
            return false;

        $this->set_user_from_session();

        return true;
    }

    function reset_user()
    {
        $this->is_logged_in = false;
        $this->id = 0;
        $this->position = 99;
        $this->role_id = 'User';

        $this->org = null;
        $this->permissions = null;
        $this->name = null;
    }

    private function set_user_from_session()
    {
        try
        {
            $this->reset_user();

            if(session_status() === PHP_SESSION_NONE)
                session_start();

            $session = (!empty($_SESSION['jct'])) ? $_SESSION['jct'] : null;
            if(is_null($session))
                throw new Exception('User Session not set.');

            // get parameters from session

            $session_id = session_id();
            $id = (!empty($session['id'])) ? intval($session['id']) : null;
            if(is_null($id))
                throw new Exception('User\'s ID not set in session.');


            // check session id

            $this->default_db_connection->query(" SELECT session_id, position FROM user WHERE id = :id ");
            $this->default_db_connection->bind(':id', $id);
            $this->default_db_connection->execute();
            $tmp = $this->default_db_connection->fetchSingleAssoc();

            if($session_id != $tmp['session_id'])
                throw new Exception('Session ID does not match stored value.');


            $this->is_logged_in = true;
            $this->id = intval($id);
            $this->position = $tmp['position'];

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $this->user_error = $e->getMessage();
            return ['error'=>$e->getMessage()];
        }
    }

    function user_position()
    {
        return $this->position;
    }

    function user_salute_name()
    {
        if(is_null($this->name))
            return null;

        return $this->name['salute_name'];
    }

    function user_is_logged_in()
    {
        return $this->is_logged_in;
    }


}