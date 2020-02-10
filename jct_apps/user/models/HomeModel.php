<?php


namespace JCT\user;


use JCT\Helper;
use JCT\SessionManager;
use Exception;
use JCT\Database;

class HomeModel
{
    private $_DB;

    private $user_id;
    private $user_role_id;
    private $org_guid;

    public $data;
    public $display_name;

    function __construct(Database $db)
    {
        $this->_DB = $db;

        $this->user_id = intval($_SESSION[SessionManager::SESSION_NAME]['user']['id']);
        $this->user_role_id = intval($_SESSION[SessionManager::SESSION_NAME]['user']['role_id']);
        $this->org_guid = $_SESSION[SessionManager::SESSION_NAME]['org']['guid'];

        $db = $this->_DB;
        $db->query(" SELECT s.title AS salutation, fname, lname, salute_name 
         FROM person p 
         LEFT JOIN prm_salutation s ON ( p.salt_id = s.id ) 
         WHERE p.id = :id ");
        $db->bind(':id', $this->user_id);
        $db->execute();
        $tmp = $db->fetchSingleAssoc();

        $this->display_name = Helper::determine_displayed_name($tmp);
    }


    function index()
    {
        $this->get_salutations();
        $this->get_countries();
    }

    function get_salutations()
    {
        $db = $this->_DB;
        $db->query(" SELECT id, title FROM prm_salutation WHERE ( active = 1 ) ORDER BY weight ASC, title ASC ");
        $db->execute();
        $this->data['salutations'] = $db->fetchAllAssoc('id', true);
    }

    function get_countries()
    {
        $db = $this->_DB;
        // as this is for addresses, exclude Dual Nationality options
        $db->query(" SELECT id, title_en as title FROM prm_country WHERE ( active = 1 AND id NOT IN (999,998) ) ORDER BY weight ASC, title_en ASC ");
        $db->execute();
        $this->data['country'] = $db->fetchAllAssoc('id');
    }





    function get_user()
    {
        $db = $this->_DB;
        $db->query(" SELECT p.email, p.fname, p.lname, p.salute_name, p.salt_id,  
        p.landline, p.mobile, 
        p.add1, p.add2, p.add3, p.add4, p.city_town, p.postcode, 
        p.county_id, p.country_id, p.eircode, p.show_county 
        FROM person p 
        WHERE ( id = :id AND active = 1 ) ");
        $db->bind(':id', $this->user_id);
        $db->execute();
        return $db->fetchSingleAssoc();
    }





    function check_email_in_use($email)
    {
        $db = $this->_DB;
        $db->query(" SELECT id FROM user WHERE ( email = :email AND id != :id )");
        $db->bind(':email', $email);
        $db->bind(':id', $this->user_id);
        $db->execute();
        $tmp = intval($db->fetchSingleColumn());

        if(empty($tmp))
        {
            $db = $this->_ORG_DB;
            $db->query(" SELECT id FROM person WHERE ( email = :email AND id != :id )");
            $db->bind(':email', $email);
            $db->bind(':id', $this->user_id);
            $db->execute();
            $tmp = intval($db->fetchSingleColumn());
        }

        return ($tmp > 0);
    }

    function update_email($email)
    {
        $db = $this->_DB;
        $db->query(" SELECT email FROM user WHERE id = :id ");
        $db->bind(':id', $this->user_id);
        $db->execute();
        $existing_email = $db->fetchSingleColumn();

        try
        {
            $db->query(" UPDATE user SET email = :email WHERE id = :id  ");
            $db->bind(':email', $email);
            $db->bind(':id', $this->user_id);
            $db->execute();
            $db->query(" UPDATE person SET email = :email WHERE id = :id  ");
            $db->bind(':email', $email);
            $db->bind(':id', $this->user_id);
            $db->execute();

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->query(" UPDATE user SET email = :email WHERE id = :id  ");
            $db->bind(':email', $existing_email);
            $db->bind(':id', $this->user_id);
            $db->execute();

            $db->query(" UPDATE person SET email = :email WHERE id = :id  ");
            $db->bind(':email', $existing_email);
            $db->bind(':id', $this->user_id);
            $db->execute();

            return ['error'=>$e->getMessage()];
        }
    }

    function update_password($password)
    {
        $db = $this->_DB;

        $db->query(" UPDATE user SET pass = :pass WHERE id = :id ");
        $db->bind(':pass', $password);
        $db->bind(':id', $this->user_id);
        $db->execute();
    }

    function update_name($arr)
    {
        $db = $this->_DB;

        $db->query(" UPDATE person SET 
        salt_id = :salt_id, fname = :fname, lname = :lname, salute_name = :salute_name, indexed_lname = :indexed_lname 
        WHERE id = :id ");
        foreach($arr as $k => $v)
            $db->bind(':' . $k, $v);
        $db->bind(':id', $this->user_id);
        $db->execute();
    }

    function get_counties($country_id)
    {
        $db = $this->_DB;

        $db->query(" SELECT id, CONCAT_WS(' ', prefix, title_en) AS title FROM prm_county WHERE ( country_id = :country_id AND active = 1 ) ORDER BY weight ASC, title ASC ");
        $db->bind(':country_id', $country_id);
        $db->execute();
        return $db->fetchAllAssoc();
    }

    function get_country_code_for_country($country_id)
    {
        $db = $this->_DB;
        $db->query(" SELECT country_code FROM prm_country WHERE id = :id ");
        $db->bind(':id', $country_id);
        $db->execute();
        return $db->fetchSingleColumn();
    }

    function update_contact($arr)
    {
        try
        {
            $db = $this->_DB;
            $db->query(" SELECT id FROM user WHERE ( mobile = :mobile AND id != :id ) ");
            $db->bind(':mobile', $arr['mobile']);
            $db->bind(':id', $this->user_id);
            $db->execute();
            $tmp = intval($db->fetchSingleColumn());

            if(!empty($tmp))
                throw new Exception('Mobile number already in use.');

            $db->query(" UPDATE user SET mobile = :mobile WHERE id = :id  ");
            $db->bind(':mobile', $arr['mobile']);
            $db->bind(':id', $this->user_id);
            $db->execute();

            $db->query(" UPDATE person SET 
            country_id = :country_id, mobile = :mobile, landline = :landline, 
            add1 = :add1, add2 = :add2, add3 = :add3, add4 = :add4, 
            city_town = :city_town, postcode = :postcode, 
            county_id = :county_id, show_county = :show_county, eircode = :eircode 
            WHERE ( id = :id ) ");
            foreach($arr as $k => $v)
                $db->bind(':' . $k, $v);
            $db->bind(':id', $this->user_id);
            $db->execute();

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }
}