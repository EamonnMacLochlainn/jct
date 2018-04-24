<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 22/04/2018
 * Time: 20:34
 */

namespace JCT\dashboard;


use JCT\Database;
use JCT\Helper;
use Exception;

class jct_admin_HomeModel extends HomeModel
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


        $this->data['statistics'] = $this->get_statistics();

    }

    private function get_statistics()
    {
        $stats = [
            'number_of_active_schools' => $this->get_active_school_numbers(),
            'number_of_active_teachers' => $this->get_active_teacher_numbers()
        ];

        return $stats;
    }

    private function get_active_school_numbers()
    {
        $numbers = [];
        $db = $this->_DB;

        $db->query(" SELECT COUNT(*) FROM org_school WHERE is_active = 1 ");
        $db->execute();
        $numbers['Total Active Schools'] = intval($db->fetchSingleColumn());

        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND is_deis = 1 ) ");
        $db->execute();
        $numbers['DEIS Schools'] = intval($db->fetchSingleColumn());

        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND is_jcn = 1 ) ");
        $db->execute();
        $numbers['JCN Schools'] = intval($db->fetchSingleColumn());


        $numbers['By Mode of Instruction'] = [];
        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND mode_of_instruction = 'en' ) ");
        $db->execute();
        $numbers['By Mode of Instruction']['English'] = intval($db->fetchSingleColumn());

        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND mode_of_instruction = 'ga' ) ");
        $db->execute();
        $numbers['By Mode of Instruction']['Gaeilge'] = intval($db->fetchSingleColumn());



        $db->query(" SELECT id, title_en FROM prm_school_type WHERE is_active = 1 ");
        $db->execute();
        $school_types = $db->fetchAllAssoc('id');

        $numbers['By School Type'] = [];
        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND type_id = :id ) ");
        foreach($school_types as $id => $type)
        {
            $db->bind(':id', $id);
            $db->execute();
            $numbers['By School Type'][$type] = intval($db->fetchSingleColumn());
        }



        $db->query(" SELECT id, title_en FROM prm_educational_region WHERE is_active = 1 ");
        $db->execute();
        $educational_regions = $db->fetchAllAssoc('id');

        $numbers['By Educational Region'] = [];
        $db->query(" SELECT COUNT(*) FROM org_school WHERE ( is_active = 1 AND region_id = :id ) ");
        foreach($educational_regions as $id => $region)
        {
            $db->bind(':id', $id);
            $db->execute();
            $numbers['By Educational Region'][$region] = intval($db->fetchSingleColumn());
        }

        return $numbers;
    }

    private function get_active_teacher_numbers()
    {

    }
}