<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 06/07/2017
 * Time: 02:47
 */

namespace JCT;


use Exception;

class Filters
{
    private $_DB;
    private $_ORG_DB;
    private $_Permissions_Registry;

    private $app_param;
    private $individuals;

    private $order_by = 'lname';
    private $order = 'ASC';
    private $offset = 0;
    private $limit = 35;

    function __construct(Database $db, Database $org_db = null, $permissions_registry, $app_param)
    {
        $this->_DB = $db;
        $this->_ORG_DB = $org_db;
        $this->_Permissions_Registry = $permissions_registry;

        $this->app_param = $app_param;
    }

    // currently only allowing the fetching and filtering of ACTIVE
    // individuals, to cut down on the number of records involved over time.
    // Perhaps provide a facility to de-activate / re-activate Individuals elsewhere
    // tldr; active will not be a usable filter for now

    // all individuals are stored in the ind_details table
    // pupils are pupils by virtue of belonging to a supergroup with a type value of 1
    // staff are staff by virtue of belonging to a supergroup with a type value of 2

    // basic list of individuals should contain:
    // id, name, group / supergroup, address (short), primary user name, contact number

    // idea is to provide methods to return suitable options per filter, or for groups of filters
    // some filters will need to update their contents based on the actions
    // of other filters, but this can be done with JS, rather than fresh queries


    function get_filtered_individuals($options = [], $staff = false)
    {

        $sql = " SELECT i.id, i.fname_common AS fname, i.lname_common AS lname, i.gender, family_token, 
        alert_general, alert_legal, alert_medical, 
        house_num, add_1, add_2, add_3, town_city, postcode, county_id, CONCAT_WS( ' ', co.title, co.attribute) AS county, country_id, ct.title AS country, eirode";

        if(!$staff)
        {
            $sql .= ", i.group_id, i.supergroup_id, sg.name_common AS supergroup_name, sg.abbr AS supergroup_abbr, 
            g.name_common AS group_name, g.abbr AS group_abbr, gl.ind_id AS group_leader_id, 
            i.ethnicity_id, nationality_id, n.title AS nationality, e.title AS ethnicity, 
            parental_status_id, ps.title AS parental_status, religion_id, r.title AS religion, 
            p_excursion.tbl_id AS perm_excursion, p_mobile.tbl_id AS perm_mobile, p_leave.tbl_id AS perm_leave, 
            p_social.tbl_id AS perm_social, p_website.tbl_id AS perm_website, p_photo.tbl_id AS perm_photo, 
            is_off_roll_on_register, cert_received_baptism, cert_received_birth, is_traveller, is_high_incidence_special_needs, 
            parish_id, pa.title as parish, 
            exempt_religion, exempt_religious_sacraments, exempt_gaeilge, enrolment_id";
        }

        $sql.= " FROM ind_details i 
        LEFT JOIN prm_county co ON ( i.county_id = co.id ) 
        LEFT JOIN prm_country ct ON ( i.country_id = ct.id ) ";

        if(!$staff)
        {
            $sql.= " LEFT JOIN nsadmin_pupil_parameters pp ON ( i.id = pp.id ) 
            LEFT JOIN prm_parental_status ps ON ( pp.parental_status_id = ps.id ) 
            LEFT JOIN supergroups sg ON ( i.supergroup_id = sg.id ) 
            LEFT JOIN groups g ON ( i.group_id = g.id ) 
            LEFT JOIN group_leaders gl ON ( g.id = gl.group_id ) 
            LEFT JOIN prm_nationality n ON ( i.nationality_id = n.id ) 
            LEFT JOIN prm_ethnicity e ON ( i.ethnicity_id = e.id ) 
            LEFT JOIN prm_religion r ON ( pp.religion_id = r.id ) 
            LEFT JOIN prm_parish pa ON ( pp.parish_id = pa.id ) 
            LEFT JOIN ind_permissions p_excursion ON ( i.id = p_excursion.ind_id AND p_excursion.type = 'nsadmin' AND p_excursion.perm_id = 1 ) 
            LEFT JOIN ind_permissions p_mobile ON ( i.id = p_mobile.ind_id AND p_mobile.type = 'nsadmin' AND p_mobile.perm_id = 2 ) 
            LEFT JOIN ind_permissions p_leave ON ( i.id = p_leave.ind_id AND p_leave.type = 'nsadmin' AND p_leave.perm_id = 3 ) 
            LEFT JOIN ind_permissions p_social ON ( i.id = p_social.ind_id AND p_social.type = 'org' AND p_social.perm_id = 3 ) 
            LEFT JOIN ind_permissions p_website ON ( i.id = p_website.ind_id AND p_website.type = 'org' AND p_website.perm_id = 4 ) 
            LEFT JOIN ind_permissions p_photo ON ( i.id = p_photo.ind_id AND p_social.type = 'org' AND p_photo.perm_id = 5 ) ";
        }


        $binds = [];
        $binds['active'] = 1;
        $binds['gender'] = (!isset($options['gender'])) ? -1 : $options['gender'];
        $binds['alert_general'] = (!isset($options['alert_general'])) ? -1 : $options['alert_general'];
        $binds['alert_legal'] = (!isset($options['alert_legal'])) ? -1 : $options['alert_legal'];
        $binds['alert_medical'] = (!isset($options['alert_medical'])) ? -1 : $options['alert_medical'];
        $binds['cert_received_baptism'] = (!isset($options['cert_received_baptism'])) ? -1 : $options['cert_received_baptism'];
        $binds['cert_received_birth'] = (!isset($options['cert_received_birth'])) ? -1 : $options['cert_received_birth'];
        $binds['perm_excursion'] = (!isset($options['perm_excursion'])) ? -1 : $options['perm_excursion'];
        $binds['perm_mobile'] = (!isset($options['perm_mobile'])) ? -1 : $options['perm_mobile'];
        $binds['perm_leave'] = (!isset($options['perm_leave'])) ? -1 : $options['perm_leave'];
        $binds['perm_social'] = (!isset($options['perm_social'])) ? -1 : $options['perm_social'];
        $binds['perm_website'] = (!isset($options['perm_website'])) ? -1 : $options['perm_website'];
        $binds['perm_photo'] = (!isset($options['perm_photo'])) ? -1 : $options['perm_photo'];
        $binds['is_off_roll_on_register'] = (!isset($options['is_off_roll_on_register'])) ? -1 : $options['is_off_roll_on_register'];
        $binds['is_traveller'] = (!isset($options['is_traveller'])) ? -1 : $options['is_traveller'];
        $binds['is_high_incidence_special_needs'] = (!isset($options['is_high_incidence_special_needs'])) ? -1 : $options['is_high_incidence_special_needs'];

        $binds['group_id'] = (!isset($options['group_id'])) ? -1 : $options['group_id'];
        $binds['supergroup_id'] = (!isset($options['supergroup_id'])) ? -1 : $options['supergroup_id'];
        $binds['family_token'] = (!isset($options['family_token'])) ? -1 : $options['family_token'];
        $binds['parental_status_id'] = (!isset($options['parental_status_id'])) ? -1 : $options['parental_status_id'];
        $binds['religion_id'] = (!isset($options['religion_id'])) ? -1 : $options['religion_id'];
        $binds['parish_id'] = (!isset($options['parish_id'])) ? -1 : $options['parish_id'];
        $binds['nationality_id'] = (!isset($options['nationality_id'])) ? -1 : $options['nationality_id'];
        $binds['ethnicity_id'] = (!isset($options['ethnicity_id'])) ? -1 : $options['ethnicity_id'];
        $binds['exempt_religion'] = (!isset($options['exempt_religion'])) ? -1 : $options['exempt_religion'];
        $binds['exempt_religious_sacraments'] = (!isset($options['exempt_religious_sacraments'])) ? -1 : $options['exempt_religious_sacraments'];
        $binds['exempt_gaeilge'] = (!isset($options['exempt_gaeilge'])) ? -1 : $options['exempt_gaeilge'];

        $binds['id'] = (!isset($options['id'])) ? -1 : $options['id'];
        $binds['dpin'] = (!isset($options['dpin'])) ? -1 : $options['dpin'];
        $binds['enrolment_id'] = (!isset($options['enrolment_id'])) ? -1 : $options['enrolment_id'];
        $binds['pps_num'] = (!isset($options['pps_num'])) ? -1 : $options['pps_num'];

        $where_str = "";
        foreach($binds as $key => $value)
        {
            if($value == -1)
                continue;

            if($key != 'active')
                $where_str.= " AND ";

            if(is_numeric($value))
                $where_str.= $key . " = " . $value;
            else
            {
                if($key != 'family_token')
                    $where_str.= "UPPER(" . $key . ") LIKE UPPER('%" . $value . "%')";
                else
                    $where_str.= $key . " = '" . $value . "'";
            }
        }
        $sql.= "WHERE ( " . $where_str . " ) ";

        $sql.= implode(" AND ", $binds);

        $order_by = (empty($options['order_by'])) ? 'lname' : $options['order_by'];
        $order = (empty($options['order'])) ? 'ASC' : $options['order'];
        $offset = (empty($options['offset'])) ? 0 : $options['offset'];
        $limit = (empty($options['limit'])) ? 35 : $options['limit'];

        $sql.= " ORDER BY " . $order_by . " " . $order;
        $sql.= " LIMIT " . $offset . "," . $limit . ";";

        $this->_ORG_DB->query($sql);
        $this->_ORG_DB->execute();
        $this->individuals = $this->_ORG_DB->fetchAllAssoc();
    }

    private function get_individuals()
    {
    }
/**


FILTERS
 *
 *
 * preset
Gender => preset M/F (MED)
Medical alert => preset Y/N (ADV)
Legal alert	 => preset Y/N (ADV)
General alert => preset Y/N (ADV)
Baptismal cert received	 => preset Y/N (ADV) (nsadmin)
Birth cert received	=> preset Y/N (ADV) (nsadmin)
Mobile phone permitted => preset Y/N (ADV) (nsadmin)
Excursions permitted => preset Y/N (ADV) (nsadmin)
Leave Class permitted => preset Y/N (ADV) (nsadmin)
Social photo permitted => preset Y/N (ADV)
Website photo permitted => preset Y/N (ADV)
Store photo permitted => preset Y/N (ADV)
On/Off Roll => preset Y/N (ADV) (nsadmin)
Is traveller => preset Y/N (ADV) (nsadmin)
high/low incidence needs => preset Y/N (ADV) (nsadmin)
 *
 *
 * pre-filled
Individual => depends on Individuals (MIN)
Group => depends on Individuals (MIN)
Supergroup => depends on Individuals (MIN)
Group Leader => depends on Groups (MIN)
Guardian => depends on Individuals (ADV)
Family Members of => is Individuals (ADV)
Status of parents => depends on Individuals (ADV)
Religion => depends on Individuals (ADV) (nsadmin)
Parish => depends on Individuals (ADV) (nsadmin)
Nationality => depends on Individuals (ADV) (nsadmin)
Ethnic Background => depends on Individuals (ADV) (nsadmin)
exempt religious instruction => depends on Individuals (ADV) (nsadmin)
exempt religious sacraments => depends on Individuals (ADV) (nsadmin)
exempt irish => depends on Individuals (ADV) (nsadmin)
 *
 *
 * input only
ID => not populated (MED)
DPIN => not populated (MED)
Registration No => not populated (ADV) (nsadmin)
PPS Number => not populated (ADV) (nsadmin)
 *
 * formatted likes
Contact Number => not populated (ADV)
 *
 * concat likes
Whose name contains => not populated (MED)
Whose address contains => not populated (MED)
 *
 * betweens
IDs from and to => not populated (ADV)
Born from and to => not populated (ADV)
Started from and to => not populated (ADV) (nsadmin)
Finished from and to => not populated (ADV) (nsadmin)
Enrolled between => not populated (ADV)





 * nsadmin only
Status before joining => depends on Individuals (not implemented) (ADV) (nsadmin)
Medical Contact => depends on Individuals (not implemented) (ADV)
Medical Condition => depends on Individuals (not implemented) (ADV)
Absent from and to => depends on Individuals (not implemented) (ADV)
Arrived late => depends on Individuals (not implemented) (ADV)
Left early => depends on Individuals (not implemented) (ADV)
All families is/not supported => preset Y/N (not implemented) (ADV)
General Fee paid => preset Y/N (not implemented) (ADV)
Book Rental Fee paid => preset Y/N (not implemented) (ADV)



 */
}