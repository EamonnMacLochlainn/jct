<?php


namespace JCT;


/**
 * Create new 'org' database for school
 * Update databizs_authorisation' DB
 * Import/Update 'op' DB for school
 *
 * Retrieve Pupil ext_ids from ORG DB (i.e. current members), with associated guardian ext_ids, emails, f/lnames
 * Retrieve Class ext_ids from ORG DB
 *
 * Retrieve Class ext_ids from OP DB
 * Retrieve all charge IDs and parameters (type, M/F, class default amts, ) from OP DB
 * Create groups on ORG DB that are not present from OP charge data
 *
 * Retrieve all fee amounts and member IDs from OP DB
 * Retrieve relevant member data for each fee ( ext_ids, f/lnames )
 * Create members on ORG DB that are not present from OP fee data
 * Insert all fee'd members into groups historically appropriate to their fees
 *
 * Create matching Charges on ORG DB
 * Create matching Fees on ORG DB
 *
 * Retrieve all payment transactions, with associated User parameters ( ext_ids, emails, f/lnames ) for fees
 * Match transaction OP users with ORG users, creating ORG accounts as necessary
 *
 * Create matching transactions on ORG DB
 */


use Exception;
use DateTime;

class org_op_import
{
    private $org_guid;

    private $_DB_core;
    private $_DB_org;
    private $_DB_auth;
    private $_DB_op;


    private $county_id = 2;
    private $country_id = 372;
    private $country_code = 'IE';

    private $org_members_by_ext_id = [];
    private $org_group_supers_by_ext_id = []; // ext_id => id pairs
    private $org_group_classes_by_ext_id = []; // ext_id => id pairs
    private $op_group_supers_by_ext_id = [];
    private $op_group_classes_by_ext_id = [];

    private $op_charges_by_id = [];
    private $op_charge_id_to_org_charge_id = [];
    private $op_fees_by_member_ext_id = [];
    private $op_transactions = []; // id => payments per member ext_id

    private $op_payment_id_to_org_line_item_id = [];
    private $org_line_item_id_refunded_by_op_payment_id = [];

    private $org_line_item_id_ai_value;
    private $org_transaction_id_ai_value;
    private $org_fee_id_ai_value;
    private $org_charge_history_ai_value;
    private $org_fee_group_amount_ai_value;
    private $org_fee_family_amount_ai_value;
    private $org_charge_id_ai_value;
    private $org_member_group_class_ai_value;
    private $org_member_guardian_ai_value;
    private $org_staff_role_ai_value;
    private $org_person_id_ai_value;
    private $org_group_class_id_ai_value;
    private $org_group_super_id_ai_value;

    private $core_user_id_ai_value;
    private $core_user_org_ai_value;


    function __construct($org_guid, Database $core, Database $org, Database $auth, Database $op)
    {
        try
        {
            $this->org_guid = $org_guid;

            $this->_DB_core = $core;
            $this->_DB_org = $org;
            $this->_DB_auth = $auth;
            $this->_DB_op = $op;

            $tmp = $this->import();
            if(isset($tmp['error']))
                throw new Exception($tmp['error']);

            echo 'done';
        }
        catch(Exception $e)
        {
            echo $e->getMessage();
        }
    }

    private function import()
    {
        try
        {
            // Retrieve Pupil ext_ids from ORG DB (i.e. current pupils), with associated guardian ext_ids, emails, f/lnames

            $tmp = $this->get_org_pupils();
            if (isset($tmp['error']))
                throw new Exception('While getting ORG members: ' . $tmp['error']);

            $this->org_members_by_ext_id = $tmp;


            // Retrieve Class ext_ids from ORG DB

            $tmp = $this->get_org_group_supers();
            if (isset($tmp['error']))
                throw new Exception('While getting ORG Super Groups: ' . $tmp['error']);

            $this->org_group_supers_by_ext_id = $tmp;

            $tmp = $this->get_org_group_classes();
            if (isset($tmp['error']))
                throw new Exception('While getting ORG Class Groups: ' . $tmp['error']);

            $this->org_group_classes_by_ext_id = $tmp;


            // Retrieve Class ext_ids from OP DB

            $tmp = $this->get_op_group_supers();
            if (isset($tmp['error']))
                throw new Exception('While getting OP Super Groups: ' . $tmp['error']);

            $this->op_group_supers_by_ext_id = $tmp;

            $tmp = $this->get_op_group_classes();
            if (isset($tmp['error']))
                throw new Exception('While getting OP Class Groups: ' . $tmp['error']);

            $this->op_group_classes_by_ext_id = $tmp;





            // Retrieve all charge IDs and parameters (type, M/F, class default amts, ) from OP DB

            $tmp = $this->get_op_charges();
            if (isset($tmp['error']))
                throw new Exception('While getting OP charges: ' . $tmp['error']);

            $this->op_charges_by_id = $tmp;


            // Create groups on ORG DB that are not present from OP charge data

            $affected_op_class_ext_ids = [];
            foreach ($this->op_charges_by_id as $id => $c)
            {
                if (empty($c['amounts_per_member_by_group_class_ext_id']))
                    continue;

                $x = array_keys($c['amounts_per_member_by_group_class_ext_id']);
                $affected_op_class_ext_ids = array_merge($affected_op_class_ext_ids, $x);
            }
            $affected_op_class_ext_ids = array_unique($affected_op_class_ext_ids);

            $org_class_ext_ids = array_keys($this->org_group_classes_by_ext_id);
            $classes_to_create_by_ext_id = array_diff($affected_op_class_ext_ids, $org_class_ext_ids);

            if(!empty($classes_to_create_by_ext_id))
            {
                $tmp = $this->create_org_groups($classes_to_create_by_ext_id);
                if (isset($tmp['error']))
                    throw new Exception('While setting ORG groups: ' . $tmp['error']);
            }
            $affected_op_class_ext_ids = null;
            $classes_to_create_by_ext_id = null;



            // Retrieve all fee amounts and pupil IDs from OP DB
            // Retrieve relevant Pupil data for each fee ( ext_ids, f/lnames )

            $tmp = $this->get_op_fees();
            if(isset($tmp['error']))
                throw new Exception('While getting OP fees: ' . $tmp['error']);

            $this->op_fees_by_member_ext_id = $tmp;

            // Create members on ORG DB that are not present from OP fee data

            $affected_op_member_ext_ids = array_keys($this->op_fees_by_member_ext_id);
            $org_member_ext_ids = array_keys($this->org_members_by_ext_id);

            $members_to_create_by_ext_id = array_diff($affected_op_member_ext_ids, $org_member_ext_ids);

            if(!empty($members_to_create_by_ext_id))
            {
                $tmp = $this->create_org_members($members_to_create_by_ext_id);
                if (isset($tmp['error']))
                    throw new Exception('While setting ORG members: ' . $tmp['error']);
            }

            // Insert all fee'd members into groups historically appropriate to their fees

            $tmp = $this->update_org_member_group_associations();
            if (isset($tmp['error']))
                throw new Exception('While setting ORG members into groups for their Fees: ' . $tmp['error']);







            // Create matching Charges on ORG DB

            $tmp = $this->create_org_charges();
            if(isset($tmp['error']))
                throw new Exception('While setting ORG charges: ' . $tmp['error']);

            // Create matching Fees on ORG DB

            $tmp = $this->create_org_fees();
            if(isset($tmp['error']))
                throw new Exception('While setting ORG fees: ' . $tmp['error']);










            // Retrieve all payment transactions, with associated User parameters ( ext_ids, emails, f/lnames ) for fees

            $tmp = $this->get_op_transactions();
            if(isset($tmp['error']))
                throw new Exception('While getting OP transactions: ' . $tmp['error']);

            if(empty($tmp))
                return ['success'=>1]; // complete at this stage as no payments need to be processed

            $this->op_transactions = $tmp;


            // Match transaction OP users with ORG users, creating ORG accounts as necessary

            $tmp = $this->set_org_users_for_transactions();
            if(isset($tmp['error']))
                throw new Exception('While setting ORG users for transactions: ' . $tmp['error']);





            // By now, all groups and members are imported, all charges and fees are imported, and all relevant guardians are imported


            // Create matching transactions on ORG DB

            $payment_transactions = [];
            $refund_transactions = [];

            foreach($this->op_transactions as $i => $t)
            {
                $trx_amt = floatval($t['amount']);
                $trx_method = ($trx_amt < 0) ? 'refund' : $t['method'];

                if($trx_method === 'refund')
                    $refund_transactions[] = $t;
                else
                    $payment_transactions[] = $t;
            }

            $db = $this->_DB_org;

            $db->query(" SELECT MAX(id) FROM op_transaction_line_item WHERE 1 = 1 ");
            $db->execute();
            $this->org_line_item_id_ai_value = intval($db->fetchSingleColumn()) + 1;

            $db->query(" SELECT MAX(id) FROM op_transaction WHERE 1 = 1 ");
            $db->execute();
            $this->org_transaction_id_ai_value = intval($db->fetchSingleColumn()) + 1;



            $tmp = $this->set_org_payments($payment_transactions);
            if(isset($tmp['error']))
                throw new Exception('While setting ORG payments: ' . $tmp['error']);

            $tmp = $this->set_org_refunds($refund_transactions);
            if(isset($tmp['error']))
                throw new Exception('While setting ORG refunds: ' . $tmp['error']);

            return ['success'=>1];
        }
        catch(Exception $e)
        {

            $db = $this->_DB_org;

            if($this->org_line_item_id_ai_value)
            {
                $db->query(" DELETE FROM op_transaction_line_item WHERE ( id >= {$this->org_line_item_id_ai_value} ); 
                ALTER TABLE op_transaction_line_item auto_increment = {$this->org_group_class_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_transaction_id_ai_value)
            {
                $db->query(" DELETE FROM op_transaction WHERE ( id >= {$this->org_transaction_id_ai_value} ); 
                ALTER TABLE op_transaction auto_increment = {$this->org_transaction_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_fee_id_ai_value)
            {
                $db->query(" DELETE FROM op_fee WHERE ( id >= {$this->org_fee_id_ai_value} ); 
                ALTER TABLE op_fee auto_increment = {$this->org_fee_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_charge_history_ai_value)
            {
                $db->query(" DELETE FROM op_charge_history WHERE ( tbl_id >= {$this->org_charge_history_ai_value} ); 
                ALTER TABLE op_fee auto_increment = {$this->org_charge_history_ai_value}; ");
                $db->execute();
            }
            if($this->org_fee_group_amount_ai_value)
            {
                $db->query(" DELETE FROM op_fee_group_amount WHERE ( tbl_id >= {$this->org_fee_group_amount_ai_value} ); 
                ALTER TABLE op_fee_group_amount auto_increment = {$this->org_fee_group_amount_ai_value}; ");
                $db->execute();
            }
            if($this->org_fee_family_amount_ai_value)
            {
                $db->query(" DELETE FROM op_fee_family_amount WHERE ( tbl_id >= {$this->org_fee_family_amount_ai_value} ); 
                ALTER TABLE op_fee_family_amount auto_increment = {$this->org_fee_family_amount_ai_value}; ");
                $db->execute();
            }
            if($this->org_charge_id_ai_value)
            {
                $db->query(" DELETE FROM op_charge WHERE ( id >= {$this->org_charge_id_ai_value} ); 
                ALTER TABLE op_charge auto_increment = {$this->org_charge_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_member_group_class_ai_value)
            {
                $db->query(" DELETE FROM member_group_class WHERE ( tbl_id >= {$this->org_member_group_class_ai_value} ); 
                ALTER TABLE member_group_class auto_increment = {$this->org_member_group_class_ai_value}; ");
                $db->execute();
            }
            if($this->org_member_guardian_ai_value)
            {
                $db->query(" DELETE FROM member_guardian WHERE ( tbl_id >= {$this->org_member_guardian_ai_value} ); 
                ALTER TABLE member_guardian auto_increment = {$this->org_member_guardian_ai_value}; ");
                $db->execute();
            }
            if($this->org_staff_role_ai_value)
            {
                $db->query(" DELETE FROM staff_role WHERE ( tbl_id >= {$this->org_staff_role_ai_value} ); 
                ALTER TABLE staff_role auto_increment = {$this->org_staff_role_ai_value}; ");
                $db->execute();
            }
            if($this->org_person_id_ai_value)
            {
                $db->query(" DELETE FROM person WHERE ( id >= {$this->org_person_id_ai_value} ); 
                ALTER TABLE person auto_increment = {$this->org_person_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_group_class_id_ai_value)
            {
                $db->query(" DELETE FROM group_class WHERE ( id >= {$this->org_group_class_id_ai_value} ); 
                ALTER TABLE group_class auto_increment = {$this->org_group_class_id_ai_value}; ");
                $db->execute();
            }
            if($this->org_group_super_id_ai_value)
            {
                $db->query(" DELETE FROM group_super WHERE ( id >= {$this->org_group_super_id_ai_value} ); 
                ALTER TABLE group_super auto_increment = {$this->org_group_super_id_ai_value}; ");
                $db->execute();
            }

            $db = $this->_DB_core;

            if($this->core_user_org_ai_value)
            {
                $db->query(" DELETE FROM user_org WHERE ( tbl_id >= {$this->core_user_org_ai_value} ); 
                ALTER TABLE user_org auto_increment = {$this->core_user_org_ai_value}; ");
                $db->execute();
            }
            if($this->core_user_id_ai_value)
            {
                $db->query(" DELETE FROM user WHERE ( id >= {$this->core_user_id_ai_value} ); 
                ALTER TABLE user auto_increment = {$this->core_user_id_ai_value}; ");
                $db->execute();
            }

            return ['error'=>$e->getMessage()];
        }
    }




    private function get_org_pupils()
    {
        $db = $this->_DB_org;
        try
        {
            $db->query(" SELECT p.id, p.ext_id, mgc.group_class_id  
            FROM person p 
            LEFT JOIN member_group_class mgc on ( p.id = mgc.id AND in_group_end IS NULL ) 
            WHERE ( is_member = 1 ) ");
            $db->execute();
            $current_members_by_ext_id = $db->fetchAllAssoc('ext_id');

            if(empty($current_members_by_ext_id))
                throw new Exception('There are no existing member records on ORG DB.');

            $db->query(" SELECT mg.guardian_id, p.ext_id, p.email, p.mobile, CONCAT_WS(' ', p.fname, p.lname) as name  
                FROM member_guardian mg 
                LEFT JOIN person p on mg.guardian_id = p.id 
                WHERE mg.id = :id 
                GROUP BY mg.guardian_id 
                ORDER BY is_default DESC ");
            foreach($current_members_by_ext_id as $ext_id => $m)
            {
                $db->bind(':id', $m['id']);
                $db->execute();
                $current_members_by_ext_id[ $ext_id ]['guardians'] = $db->fetchAllAssoc('ext_id');
            }

            return $current_members_by_ext_id;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }



    private function get_org_group_supers()
    {
        $db = $this->_DB_org;
        $db->query(" SELECT id, ext_id FROM group_super WHERE 1=1 ");
        $db->execute();
        $org_group_supers_by_ext_id = $db->fetchAllAssoc('ext_id', true);

        if(empty($org_group_supers_by_ext_id))
            return ['error'=>'There are no Super groups defined on the ORG DB.'];

        return $org_group_supers_by_ext_id;
    }

    private function get_org_group_classes()
    {
        $db = $this->_DB_org;
        $db->query(" SELECT id, ext_id FROM group_class WHERE 1=1 ");
        $db->execute();
        $org_group_classes_by_ext_id = $db->fetchAllAssoc('ext_id', true);

        if(empty($org_group_classes_by_ext_id))
            return ['error'=>'There are no Class groups defined on the ORG DB.'];

        return $org_group_classes_by_ext_id;
    }

    private function get_op_group_supers()
    {
        $db = $this->_DB_op;

        $db->query(" SELECT SuperGroupID as id, SuperGroupExtID as ext_id, SuperGroupDesc as title, SuperGroupCreated as active_begin, 
        IF(bool_SuperGroupIsActive = 1, NULL, SuperGroupModified) as active_end 
        FROM supergroups 
        WHERE 1 = 1 ");
        $db->execute();
        $op_group_supers_by_ext_id = $db->fetchAllAssoc('ext_id');

        if(empty($op_group_supers_by_ext_id))
            return ['error'=>'There are no Super groups defined on the OP DB.'];

        return $op_group_supers_by_ext_id;
    }

    private function get_op_group_classes()
    {
        $db = $this->_DB_op;

        $db->query(" SELECT GroupID as id, GroupExtID as ext_id, GroupDesc as title, GroupAbbr as abbr, 
        GroupCreated as active_begin, SuperGroupExtID as group_super_ext_id, IF(bool_GroupIsActive = 1, NULL, GroupModified) as active_end     
        FROM `groups` g 
        LEFT JOIN supergroups sg on g.GroupSuperGroupID = sg.SuperGroupID 
        WHERE 1 = 1 ");
        $db->execute();
        $op_group_classes_by_ext_id = $db->fetchAllAssoc('ext_id');

        if(empty($op_group_classes_by_ext_id))
            return ['error'=>'There are no Class groups defined on the OP DB.'];

        return $op_group_classes_by_ext_id;
    }

    private function create_org_groups($classes_to_create_by_ext_id)
    {
        $db = $this->_DB_org;

        $db->query(" SELECT MAX(id) FROM group_super WHERE 1 = 1 ");
        $db->execute();
        $this->org_group_super_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(id) FROM group_class WHERE 1 = 1 ");
        $db->execute();
        $this->org_group_class_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $now = new DateTime();
        $now_str = $now->format('Y-m-d');

        $db->beginTransaction();
        try
        {
            foreach($this->op_group_classes_by_ext_id as $gc_ext_id => $gc)
            {
                if(!in_array($gc_ext_id, $classes_to_create_by_ext_id))
                    continue;

                $group_super_ext_id = $gc['group_super_ext_id'];

                if(!array_key_exists($group_super_ext_id, $this->org_group_supers_by_ext_id))
                {
                    $band = ($group_super_ext_id % 2) ? (($group_super_ext_id + 1) / 2) : ($group_super_ext_id / 2);
                    $sg = $this->op_group_supers_by_ext_id[$group_super_ext_id];
                    $abbr = substr($sg['title'], 0, 7);
                    $active_end = (empty($sg['active_end'])) ? $now_str : $sg['active_end'];

                    $db->query(" INSERT INTO group_super 
                    ( ext_id, title, title_eng_gae_variant, abbr, 
                    active_begin, active_end, current, academic_band, academic_order, 
                    weight, updated, updated_by ) VALUES 
                    ( {$group_super_ext_id}, :title, NULL, '{$abbr}', 
                    :active_begin, :active_end, 0, {$band}, 9, 
                    99, NOW(), -1 ) ");
                    $db->bind(':title', $sg['title']);
                    $db->bind(':active_begin', $sg['active_begin']);
                    $db->bind(':active_end', $active_end);
                    $db->execute();

                    $this->org_group_supers_by_ext_id[ $group_super_ext_id ] = $db->lastInsertId();
                }

                $group_super_org_id = $this->org_group_supers_by_ext_id[ $group_super_ext_id ];

                $abbr = substr($gc['title'], 0, 7);
                $active_end = (empty($gc['active_end'])) ? $now_str : $gc['active_end'];

                $db->query(" INSERT INTO group_class 
                ( ext_id, title, title_eng_gae_variant, abbr, 
                 group_super_id, gender, type_id, 
                 active_begin, active_end, updated, updated_by ) VALUES 
                ( {$gc_ext_id}, :title, NULL, '{$abbr}', 
                 {$group_super_org_id}, 'A', 1, 
                 :active_begin, :active_end, NOW(), -1 ) ");
                $db->bind(':title', $gc['title']);
                $db->bind(':active_begin', $gc['active_begin']);
                $db->bind(':active_end', $active_end);
                $db->execute();

                $this->org_group_classes_by_ext_id[ $gc_ext_id ] = $db->lastInsertId();
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }




    private function get_op_charges()
    {
        $db = $this->_DB_op;

        $db->query(" SELECT ChargeID as id, ChargeDesc as title, ChargeDueFrom as due_from, ChargeDueTo as due_to, ChargeTypeID as type_id,  
            ChargeStatusID as status_id, ChargeStopped as stopped, bool_ChargeIsMandatory as mandatory, bool_ChargeIsFixedAmt as fixed, 
            ChargeYear as year_starting, ChargeStoppedDate as closed, ChargeCreated as created    
            FROM charges  
            WHERE 1 ");
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        if(empty($tmp))
            return ['error'=>'There are no Charges to be found on OP database.'];

        /*
         * OP Charge Types
            1 = 1 Fee per Individual
            2 = Individual Fees based on number of Payment Group Members
            3 = Individual Fees, less flat discount based on number of Payment Group Members
            4 = Individual Fees, less percentile discount based on number of Payment Group Members

         * ORG Charge Types
            1 = Each individual charged independently
            2 = Each individual charged according to their family size
            3 = One fee per family
            4 = Itemised Charge (not currently used)
        */

        $charges = [];
        foreach($tmp as $c)
        {
            $id = $c['id'];
            $status_id = $c['status_id'];
            $stopped = ($c['stopped'] == 1);

            unset($c['status_id']);
            unset($c['stopped']);

            if($stopped)
                $status = 'closed';
            else
                $status = ($status_id == 1) ? 'draft' : 'open';

            $c['status'] = $status;

            $op_type_id = $c['type_id'];
            $c['type_id'] = ($op_type_id == 1) ? 1 : 2;

            $c['amounts_per_member_by_group_class_ext_id'] = [];
            $c['amounts_per_member_by_family_size'] =[];

            // there is no direct correlation between the OP types 3 & 4 (discounted fees based on family size) and the ORG types,
            // therefore we just take the max default amount for those charges (the group default) and don't apply the discount to it -
            // the fees will still be recorded as-is anyway

            if(intval($op_type_id) !== 2)
            {
                $db->query(" SELECT g.GroupExtID, DefaultDue 
                FROM charges_groups cg 
                LEFT JOIN `groups` g on cg.GroupID = g.GroupID 
                WHERE ( ChargeID = {$id} ) ");
                $db->execute();
                $c['amounts_per_member_by_group_class_ext_id'] = $db->fetchAllAssoc('GroupExtID', true);
            }
            else
            {
                foreach(range(1,6) as $size)
                {
                    $sql = " SELECT PaymentGroupDefaultFee" . $size . "Member FROM charges_paymentgroups WHERE ( ChargeID = {$id} ) ";
                    $db->query($sql);
                    $db->execute();
                    $c['amounts_per_member_by_family_size'][$size] = $db->fetchSingleColumn();
                }
            }

            $charges[ $id ] = $c;
        }

        return $charges;
    }

    private function get_op_fees()
    {

        $db = $this->_DB_op;
        try
        {
            $db->query(" SELECT ChargeID as charge_id, 
            InitialDue as amount_initial, TotalDue as amount_due, TotalPaid as amount_paid, 
            IF(FeeCancelled = 1, FeeCancelledDate, NULL) as stopped, FeeCreated as created, 
            PaymentGroupID as paymentgroup_id, g.GroupExtID as group_class_ext_id, g.GroupSuperGroupExtID as group_super_ext_id, 
            ci.IndividualID as id, i.IndividualExtID as ext_id, i.IndividualFName as fname, i.IndividualLName as lname
            FROM charges_individuals ci 
            LEFT JOIN individuals i on ci.IndividualID = i.IndividualID 
            LEFT JOIN `groups` g on ci.GroupID = g.GroupID 
            LEFT JOIN supergroups sg on g.GroupSuperGroupID = sg.SuperGroupID 
            WHERE 1=1 ");
            $db->execute();
            $tmp = $db->fetchAllAssoc();

            if(empty($tmp))
                throw new Exception('No Fees were found on OP DB.');

            $fees_by_member_ext_id = [];
            foreach($tmp as $t)
            {
                $ext_id = $t['ext_id'];
                $op_id = $t['id'];

                if(!isset($fees_by_member_ext_id[ $ext_id ]))
                    $fees_by_member_ext_id[ $ext_id ] = [
                        'op_id' => $op_id,
                        'fname' => $t['fname'],
                        'lname' => $t['lname'],
                        'fees' => [],
                        'groups' => []
                    ];

                unset($t['ext_id']);
                unset($t['id']);
                unset($t['fname']);
                unset($t['lname']);

                $group_ext_id = $t['group_class_ext_id'];
                $created = DateTime::createFromFormat('Y-m-d H:i:s', $t['created']);
                $created->modify('-1 day');

                if(!array_key_exists($group_ext_id, $fees_by_member_ext_id[ $ext_id ]['groups']))
                {
                    $fees_by_member_ext_id[ $ext_id ]['groups'][$group_ext_id] = [
                        'from' => $created->format('Y-m-d'),
                        'to' => $created->format('Y-m-d')
                    ];
                }
                else
                {
                    $prev_from = DateTime::createFromFormat('Y-m-d', $fees_by_member_ext_id[ $ext_id ]['groups'][$group_ext_id]['from']);
                    $prev_to = DateTime::createFromFormat('Y-m-d', $fees_by_member_ext_id[ $ext_id ]['groups'][$group_ext_id]['to']);

                    if($created < $prev_from)
                        $fees_by_member_ext_id[ $ext_id ]['groups'][$group_ext_id]['from'] = $created->format('Y-m-d');

                    if($created > $prev_to)
                        $fees_by_member_ext_id[ $ext_id ]['groups'][$group_ext_id]['to'] = $created->format('Y-m-d');
                }

                $fees_by_member_ext_id[ $ext_id ]['fees'][] = $t;
            }

            return $fees_by_member_ext_id;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    private function create_org_members($members_to_create_by_ext_id)
    {
        $db = $this->_DB_core;

        $db->query(" SELECT MAX(id) FROM user WHERE 1 = 1 ");
        $db->execute();
        $this->core_user_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM user_org WHERE 1 = 1 ");
        $db->execute();
        $this->core_user_org_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db = $this->_DB_org;

        $db->query(" SELECT MAX(id) FROM person WHERE 1 = 1 ");
        $db->execute();
        $this->org_person_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM member_group_class WHERE 1 = 1 ");
        $db->execute();
        $this->org_member_group_class_ai_value = intval($db->fetchSingleColumn()) + 1;

        $new_members = [];
        $db = $this->_DB_core;
        $db->beginTransaction();
        try
        {
            $db->query(" SELECT county_id, country_id, country_code FROM org_details WHERE guid = '{$this->org_guid}' ");
            $db->execute();
            $x = $db->fetchSingleAssoc();
            $this->county_id = $x['county_id'];
            $this->country_id = $x['country_id'];
            $this->country_code = $x['country_code'];

            foreach($members_to_create_by_ext_id as $ext_id)
            {
                if( ($this->op_fees_by_member_ext_id[$ext_id]['fname'] == 'Test') &&
                    ($this->op_fees_by_member_ext_id[$ext_id]['lname'] == 'Pupil') )
                    continue;

                $db->query(" INSERT INTO user 
                ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES 
                ( 0, NULL, NULL, NULL, NULL, NOW(), -1 ) ");
                $db->execute();
                $id = $db->lastInsertId();

                $db->query(" INSERT INTO user_org 
                ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                ( {$id}, '{$this->org_guid}', 7, '{$ext_id}', NULL, 0, NOW(), -1 ) ");
                $db->execute();

                $new_members[$ext_id] = [
                    'fname' => $this->op_fees_by_member_ext_id[$ext_id]['fname'],
                    'lname' => $this->op_fees_by_member_ext_id[$ext_id]['lname'],
                    'id' => $id
                ];
            }

            $db->commit();
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }

        $db = $this->_DB_org;
        $db->beginTransaction();
        try
        {
            $db->query(" INSERT INTO person  
            ( id, ext_id, fname, lname, indexed_lname, salute_name, salt_id, 
             landline, mobile, email, landline_alt, mobile_alt, email_alt, 
             add1, add2, add3, add4, city_town, postcode, eircode, county_id, country_id, show_county, 
             is_staff, is_guardian, is_member, 
             active, created, deactivated, updated, updated_by ) VALUES 
            ( :id, :ext_id, :fname, :lname, :indexed_lname, NULL, 0, 
             NULL, NULL, NULL, NULL, NULL, NULL, 
             NULL, NULL, NULL, NULL, NULL, 0, NULL, {$this->county_id}, {$this->country_id}, 1, 
             0, 0, 1, 
             0, NOW(), NOW(), NOW(), -1 )");
            foreach($new_members as $ext_id => $m)
            {
                $db->bind(':id', $m['id']);
                $db->bind(':ext_id', $ext_id);
                $db->bind(':fname', $m['fname']);
                $db->bind(':lname', $m['lname']);
                $db->bind(':indexed_lname', Helper::lname_as_index($m['lname']));
                $db->execute();

                $this->org_members_by_ext_id[ $ext_id ] = [
                    'id' => $m['id'],
                    'group_class_id' => 0,
                    'guardians' => []
                ];
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function update_org_member_group_associations()
    {
        $db = $this->_DB_org;
        $db->beginTransaction();
        try
        {
            foreach($this->op_fees_by_member_ext_id as $ext_id => $m)
            {
                if( ($m['fname'] == 'Test') &&
                    ($m['lname'] == 'Pupil') )
                    continue;

                $id = $this->org_members_by_ext_id[$ext_id]['id'];

                foreach($m['groups'] as $grp_ext_id => $dates)
                {
                    $db->query(" SELECT gc.id, gs.id as group_super_id  
                    FROM group_class gc 
                    LEFT JOIN group_super gs on gc.group_super_id = gs.id 
                    WHERE ( gc.ext_id = {$grp_ext_id} ) ");
                    $db->execute();
                    $tmp = $db->fetchSingleAssoc();
                    $group_id = $tmp['id'];
                    $group_super_id = $tmp['group_super_id'];

                    $db->query(" SELECT tbl_id FROM member_group_class WHERE ( id = {$id} AND group_class_id = {$group_id} ) ");
                    $db->execute();
                    $tmp = $db->fetchSingleColumn();

                    if(!empty($tmp))
                        continue; // not bothering with dates here

                    $db->query(" INSERT INTO member_group_class 
                    ( id, group_class_id, group_super_id, in_group_begin, in_group_end, updated, updated_by ) VALUES 
                    ( {$id}, {$group_id}, {$group_super_id}, :in_group_begin, :in_group_end, NOW(), -1 ) ");
                    $db->bind(':in_group_begin', $dates['from']);
                    $db->bind(':in_group_end', $dates['to']);
                    $db->execute();
                }

                $this->op_fees_by_member_ext_id[$ext_id]['id'] = $id;
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }




    private function create_org_charges()
    {
        $db = $this->_DB_org;

        $db->query(" SELECT MAX(id) FROM op_charge WHERE 1 ");
        $db->execute();
        $this->org_charge_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM op_fee_group_amount WHERE 1 ");
        $db->execute();
        $this->org_fee_group_amount_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM op_fee_family_amount WHERE 1 ");
        $db->execute();
        $this->org_fee_family_amount_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->beginTransaction();
        try
        {
            foreach($this->op_charges_by_id as $id => $c)
            {
                if( (empty($c['amounts_per_member_by_group_class_ext_id'])) &&
                    (empty($c['amounts_per_member_by_family_size'])) )
                    continue;

                $title = $c['title'];

                $db->query(" SELECT COUNT(*) FROM op_charge WHERE ( UPPER(title) = UPPER(:title) ) ");
                $db->bind(':title', $title);
                $db->execute();
                $int = intval($db->fetchSingleColumn());

                if($int > 0)
                    $title.= Helper::generate_random_string(5);

                $this->op_charges_by_id[ $id ]['title'] = $title;
                $token = 'dsch_' . Helper::generate_random_string(20);

                $db->query(" INSERT INTO op_charge 
                ( token, replaces, title, description, 
                 type_id, applies_to_custom_group, category_id, 
                 year_starting, due_from, due_to, 
                 mandatory, amount_fixed, charge_status, 
                 affected_groups_saved, settings_saved, amounts_saved, cap_amount, disregard_unaffected_members, 
                 closed, created, replaced_by, updated, updated_by ) VALUES 
                ( :token, NULL, :title, NULL, 
                 :type_id, 0, 1, 
                 :year_starting, :due_from, :due_to, 
                 :mandatory, :amount_fixed, :charge_status, 
                 1, 1, 1, 0, 1, 
                 :closed, :created, NULL, NOW(), -1 )");
                $db->bind(':token', $token);
                $db->bind(':title', $title);
                $db->bind(':type_id', $c['type_id']);
                $db->bind(':year_starting', $c['year_starting']);
                $db->bind(':due_from', $c['due_from']);
                $db->bind(':due_to', $c['due_to']);
                $db->bind(':mandatory', $c['mandatory']);
                $db->bind(':amount_fixed', $c['fixed']);
                $db->bind(':charge_status', $c['status']);
                $db->bind(':closed', $c['closed']);
                $db->bind(':created', $c['created']);
                $db->execute();
                $org_charge_id = $db->lastInsertId();

                if(!empty($c['amounts_per_member_by_group_class_ext_id']))
                {
                    $db->query(" INSERT INTO op_fee_group_amount 
                    ( charge_id, group_id, amount ) VALUES  
                    ( {$org_charge_id}, :group_id, :amount ) ");
                    foreach($c['amounts_per_member_by_group_class_ext_id'] as $ext_id => $amt)
                    {
                        if(empty($this->org_group_classes_by_ext_id[ $ext_id ]))
                            throw new Exception('Charge applies to unrecognised Group ID (' . $ext_id . ')');

                        $db->bind(':group_id', $this->org_group_classes_by_ext_id[ $ext_id ]);
                        $db->bind(':amount', $amt);
                        $db->execute();
                    }
                }

                if(!empty($c['amounts_per_member_by_family_size']))
                {
                    $db->query(" INSERT INTO op_fee_family_amount 
                    ( charge_id, member_1, member_2, member_3, member_4, member_5, member_6 ) VALUES  
                    ( {$org_charge_id}, :member_1, :member_2, :member_3, :member_4, :member_5, :member_6 ) ");
                    foreach($c['amounts_per_member_by_family_size'] as $size => $amt)
                    {
                        $col = ':member_' . $size;
                        $db->bind($col, $amt);
                    }
                    $db->execute();
                }

                $this->op_charge_id_to_org_charge_id[ $c['id'] ] = $org_charge_id;
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function create_org_fees()
    {
        $db = $this->_DB_org;

        $db->query(" SELECT MAX(id) FROM op_fee WHERE 1 = 1 ");
        $db->execute();
        $this->org_fee_id_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM op_charge_history WHERE 1 = 1 ");
        $db->execute();
        $this->org_charge_history_ai_value = intval($db->fetchSingleColumn()) + 1;


        $db->beginTransaction();
        $focus = [];
        try
        {
            $charges = [];
            foreach($this->op_fees_by_member_ext_id as $ext_id => $m)
            {
                $focus = $m;
                if( ($m['fname'] == 'Test') &&
                    ($m['lname'] == 'Pupil') )
                    continue;

                foreach($m['fees'] as $i => $f)
                {
                    $op_charge_id = $f['charge_id'];
                    $org_charge_id = $this->op_charge_id_to_org_charge_id[ $op_charge_id ];
                    $charges[$org_charge_id] = $f['created'];

                    $org_grp_id = $this->org_group_classes_by_ext_id[$f['group_class_ext_id']];
                    $org_sg_id = $this->org_group_supers_by_ext_id[$f['group_super_ext_id']];

                    $db->query(" INSERT INTO op_fee 
                    ( member_id, group_class_id, group_super_id, charge_id, 
                     amount_initial, amount_due, amount_paid, 
                     stopped, created, processing_fee_level, 
                     notification_email_sent, notification_text_sent, reminder_email_sent, reminder_text_sent, 
                     updated, updated_by ) VALUES  
                    ( :member_id, :group_class_id, :group_super_id, :charge_id, 
                     :amount_initial, :amount_due, :amount_paid, 
                     :stopped, :created, 1, 
                     NULL, NULL, NULL, NULL, 
                     NOW(), -1 ) ");
                    $db->bind(':member_id', $m['id']);
                    $db->bind(':group_class_id', $org_grp_id);
                    $db->bind(':group_super_id', $org_sg_id);
                    $db->bind(':charge_id', $org_charge_id);
                    $db->bind(':amount_initial', $f['amount_initial']);
                    $db->bind(':amount_due', $f['amount_due']);
                    $db->bind(':amount_paid', $f['amount_paid']);
                    $db->bind(':stopped', (empty($f['stopped'])) ? NULL : $f['stopped']);
                    $db->bind(':created', $f['created']);
                    $db->execute();

                    $this->op_fees_by_member_ext_id[ $ext_id ]['fees'][$i]['id'] = $db->lastInsertId();
                    $this->op_fees_by_member_ext_id[ $ext_id ]['fees'][$i]['group_class_id'] = $org_grp_id;
                    $this->op_fees_by_member_ext_id[ $ext_id ]['fees'][$i]['group_super_id'] = $org_sg_id;
                    $this->op_fees_by_member_ext_id[ $ext_id ]['fees'][$i]['org_charge_id'] = $org_charge_id;
                }
            }

            foreach($charges as $charge_id => $created)
            {
                $db->query(" INSERT INTO op_charge_history 
                ( id, charge_status, updated, updated_by ) VALUES 
                ( :charge_id, 'imported_from_op', NOW(), 1 ) ");
                $db->bind(':charge_id', $charge_id);
                $db->execute();

                $db->query(" INSERT INTO op_charge_history 
                ( id, charge_status, updated, updated_by ) VALUES 
                ( :charge_id, 'initial_fees_set', :created, 1 ) ");
                $db->bind(':charge_id', $charge_id);
                $db->bind(':created', $created);
                $db->execute();

                $db->query(" INSERT INTO op_charge_history 
                ( id, charge_status, updated, updated_by ) VALUES 
                ( :charge_id, 'made_open', :created, 1 ) ");
                $db->bind(':charge_id', $charge_id);
                $db->bind(':created', $created);
                $db->execute();
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            Helper::show($focus);
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }




    private function get_op_transactions()
    {
        $transactions = [];
        $db = $this->_DB_op;
        try
        {
            $db->query(" SELECT TransactionID as id, TransactionMethod as method, 
            TransactionDescription as trx_comment, TransactionShowDescription as trx_comment_is_public, 
            TransactionToken as ext_token, TransactionCreated as created, TransactionTotalAmt as amount, 
            TransactionUserID as user_id 
            FROM transactions t 
            WHERE 1=1 
            ORDER BY TransactionCreated ASC ");
            $db->execute();
            $transactions = $db->fetchAllAssoc('id');

            if(empty($transactions))
                throw new Exception('There are no Transaction on record on OP DB.');

            $db->query(" SELECT TblID as payment_id, Refunded as refunded_by_id, ChargeID as charge_id, TransactionAmt as amount, 
            i.IndividualExtID as ext_id
            FROM transaction_charges tc 
            LEFT JOIN individuals i on tc.IndividualID = i.IndividualID 
            WHERE ( TransactionID = :trx_id ) ");
            foreach($transactions as $id => $trx)
            {
                $db->bind(':trx_id', $id);
                $db->execute();
                $tmp = $db->fetchAllAssoc();

                $per_member = [];
                foreach($tmp as $t)
                {
                    $ext_id = $t['ext_id'];
                    unset($t['ext_id']);

                    if(empty($ext_id))
                        throw new Exception('Payment found for member with no Ext. ID: ' . $t['payment_id']);

                    if(!isset($per_member[ $ext_id ]))
                        $per_member[ $ext_id ] = [];

                    $per_member[ $ext_id ][] = $t;
                }

                if(empty($per_member))
                {
                    unset($transactions[$id]);
                    continue;
                }

                $transactions[ $id ]['payments'] = $per_member;
            }
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }

        if(empty($transactions))
            return $transactions;

        $db = $this->_DB_auth;

        $db->query(" SELECT UserEmail, UserMobile, UserFName, UserLName, UserPass, UserExtID FROM users WHERE ( UserID = :id ) ");
        foreach($transactions as $i => $t)
        {
            $db->bind(':id', $t['user_id']);
            $db->execute();
            $tmp = $db->fetchSingleAssoc();

            $transactions[$i]['user_email'] = $tmp['UserEmail'];
            $transactions[$i]['user_mobile'] = $tmp['UserMobile'];
            $transactions[$i]['user_fname'] = $tmp['UserFName'];
            $transactions[$i]['user_lname'] = $tmp['UserLName'];
            $transactions[$i]['user_pass'] = $tmp['UserPass'];
            $transactions[$i]['user_op_ext_id'] = $tmp['UserExtID'];
        }

        return $transactions;
    }

    private function set_org_users_for_transactions()
    {
        $db = $this->_DB_org;

        $db->query(" SELECT MAX(tbl_id) FROM member_guardian WHERE 1 = 1 ");
        $db->execute();
        $this->org_member_guardian_ai_value = intval($db->fetchSingleColumn()) + 1;

        $db->query(" SELECT MAX(tbl_id) FROM staff_role WHERE 1 = 1 ");
        $db->execute();
        $this->org_staff_role_ai_value = intval($db->fetchSingleColumn()) + 1;


        $db->query(" SELECT email FROM person WHERE is_staff = 1 ");
        $db->execute();
        $staff_emails = $db->fetchAllColumn();

        $db = $this->_DB_auth;
        $db->query(" SELECT u.UserEmail, u.UserMobile, u.UserPass   
        FROM users_orgs uo 
        LEFT JOIN users u on uo.UserID = u.UserID
        WHERE ( 
            uo.UserRank = 11 AND 
            OrgGUID = '{$this->org_guid}' AND 
            UserEmail IS NOT NULL AND 
            u.UserID > 3
        ) 
        LIMIT 0,1 ");
        $db->execute();
        $admin = $db->fetchSingleAssoc();

        if(empty($admin))
            return ['error'=>'No Administrative User account found in OP DB.'];

        $admin_email = $admin['UserEmail'];
        $admin_mobile = $admin['UserEmail'];
        $admin_pass = $admin['UserPass'];

        try
        {
            foreach($this->op_transactions as $i => $t)
            {
                $user_id = 0;
                $email = $t['user_email'];
                $mobile = $t['user_mobile'];

                if(strpos($email, '@databizsolutions.ie') !== false)
                {
                    $email = $admin_email;
                    $mobile = $admin_mobile;
                    $t['user_pass'] = $admin_pass;
                }

                // where user cannot be accurately identified, get a current user for paymentgroup with email and mobile

                if( (empty($email)) && (empty($mobile)) )
                {
                    $ward_ext_ids = array_keys($t['payments']);
                    $sample_ind_ext_id = $ward_ext_ids[0];

                    $db = $this->_DB_op;

                    $db->query(" SELECT IndividualPaymentGroupID FROM individuals WHERE ( IndividualExtID = {$sample_ind_ext_id} ) ");
                    $db->execute();
                    $pg_id = $db->fetchSingleColumn();

                    $db->query(" SELECT UserID FROM paymentgroup_users WHERE ( PaymentGroupID = {$pg_id} ) ");
                    $db->execute();
                    $user_ids = $db->fetchAllColumn();

                    if(!empty($user_ids))
                    {
                        $user_ids_str = implode(',',$user_ids);

                        $db = $this->_DB_auth;
                        $db->query(" SELECT UserID, UserEmail as email, UserMobile as mobile   
                        FROM users 
                        WHERE ( 
                            UserID IN ({$user_ids_str}) AND 
                            ( (UserEmail IS NOT NULL) OR (UserMobile IS NOT NULL) ) 
                        ) 
                        ORDER BY ( (UserEmail IS NOT NULL) AND (UserMobile IS NOT NULL) ) DESC, UserEmail IS NOT NULL DESC ");
                        $db->execute();
                        $us = $db->fetchAllAssoc();

                        foreach($us as $u)
                        {
                            if( (!empty($u['email'])) || (!empty($u['mobile'])) )
                            {
                                $email = $u['email'];
                                $mobile = $u['mobile'];
                                $this->op_transactions[$i]['user_id'] = $u['UserID'];
                                break;
                            }
                        }
                    }
                }


                // where user still cannot be accurately identified, set to stand-in a/c

                $stand_in_user = false;
                if( (empty($email)) && (empty($mobile)) )
                {
                    $email = ($t['method'] == 'card') ? Helper::generate_random_string(5) . '@databizsolutions.ie' : $admin_email;
                    $stand_in_user = true;
                }



                $db = $this->_DB_core;

                // get id corresponding to email / mobile from core DB

                if(!$stand_in_user)
                {
                    if( (!empty($email)) && (!empty($mobile)) )
                    {
                        $db->query(" SELECT id FROM user WHERE ( email = :email AND mobile = :mobile ) ");
                        $db->bind(':email', $email);
                        $db->bind(':mobile', $mobile);
                        $db->execute();
                        $id = intval($db->fetchSingleColumn());

                        if(!empty($id))
                            $user_id = $id;
                        else
                        {
                            $db->query(" SELECT id FROM user WHERE ( mobile = :mobile ) ");
                            $db->bind(':mobile', $mobile);
                            $db->execute();
                            $id = intval($db->fetchSingleColumn());

                            if(!empty($id))
                                $user_id = $id;
                            else
                            {
                                $db->query(" SELECT id FROM user WHERE ( email = :email ) ");
                                $db->bind(':email', $email);
                                $db->execute();
                                $id = intval($db->fetchSingleColumn());

                                if(!empty($id))
                                    $user_id = $id;
                            }
                        }
                    }
                    else
                    {
                        if(!empty($mobile))
                        {
                            $db->query(" SELECT id FROM user WHERE ( mobile = :mobile ) ");
                            $db->bind(':mobile', $mobile);
                            $db->execute();
                            $id = intval($db->fetchSingleColumn());

                            if(!empty($id))
                                $user_id = $id;
                        }

                        if(!empty($email))
                        {
                            $db->query(" SELECT id FROM user WHERE ( email = :email ) ");
                            $db->bind(':email', $email);
                            $db->execute();
                            $id = intval($db->fetchSingleColumn());

                            if(!empty($id))
                                $user_id = $id;
                        }
                    }

                    // where a user has been identified, fill in any empty email/mobile data

                    if($user_id > 0)
                    {
                        if(empty($mobile))
                        {
                            $db->query(" SELECT mobile FROM user WHERE ( id = {$user_id} ) ");
                            $db->execute();
                            $mobile = $db->fetchSingleColumn();
                        }

                        if(empty($email))
                        {
                            $db->query(" SELECT email FROM user WHERE ( id = {$user_id} ) ");
                            $db->execute();
                            $email = $db->fetchSingleColumn();
                        }
                    }
                }

                // where an OP user has NOT been matched with ORG equivalent, create user a/c

                if(!$user_id)
                {
                    $db->query(" INSERT INTO user 
                    ( active, session_id, email, mobile, pass, updated, updated_by ) VALUES 
                    ( 0, NULL, :email, :mobile, :pass, NOW(), -1 ) ");
                    $db->bind(':email', (empty($email)) ? NULL : $email);
                    $db->bind(':mobile', (empty($mobile)) ? NULL : $mobile);
                    $db->bind(':pass', (empty($t['user_pass'])) ? NULL : $t['user_pass']);
                    $db->execute();
                    $user_id = $db->lastInsertId();

                    $db->query(" INSERT INTO user_org 
                    ( id, guid, role_id, ext_id, token, active, updated, updated_by ) VALUES 
                    ( {$user_id}, '{$this->org_guid}', 8, :ext_id, NULL, 0, NOW(), -1 ) ");
                    $db->bind(':ext_id', (empty($t['user_op_ext_id'])) ? NULL : $t['user_op_ext_id']);
                    $db->execute();
                }


                $db = $this->_DB_org;

                $db->query(" SELECT id FROM person WHERE ( id = {$user_id} ) ");
                $db->execute();
                $x = $db->fetchSingleColumn();

                $is_staff = $is_guardian = $is_member = 0;
                if(empty($x))
                {
                    if( ($t['method'] != 'card') || ($email == $admin_email) || (in_array($email, $staff_emails)) )
                        $is_staff = 1;
                    else
                        $is_guardian = 1;

                    $db->query(" INSERT INTO person 
                    ( id, ext_id, fname, lname, indexed_lname, salute_name, salt_id, 
                     landline, mobile, email, landline_alt, mobile_alt, email_alt, 
                     add1, add2, add3, add4, city_town, postcode, eircode, county_id, country_id, show_county, 
                     is_staff, is_guardian, is_member, 
                     active, created, deactivated, updated, updated_by ) VALUES 
                    ( {$user_id}, :ext_id, :fname, :lname, :indexed_lname, NULL, 0, 
                     NULL, :mobile, :email, NULL, NULL, NULL, 
                     NULL, NULL, NULL, NULL, NULL, 0, NULL, {$this->county_id}, {$this->country_id}, 1, 
                     {$is_staff}, {$is_guardian}, 0, 
                     0, NOW(), NOW(), NOW(), -1 ) ");
                    $db->bind(':ext_id', (empty($t['user_op_ext_id'])) ? NULL : $t['user_op_ext_id']);
                    $db->bind(':fname', (empty($t['user_fname'])) ? NULL : $t['user_fname']);
                    $db->bind(':lname', (empty($t['user_lname'])) ? NULL : $t['user_lname']);
                    $db->bind(':indexed_lname', (empty($t['user_lname'])) ? NULL : Helper::lname_as_index($t['user_lname']));
                    $db->bind(':mobile', (empty($mobile)) ? NULL : $mobile);
                    $db->bind(':email', (empty($email)) ? NULL : $email);
                    $db->execute();


                    if($is_staff)
                    {
                        $db->query(" INSERT INTO staff_role 
                        ( id, role_id, role_begin, role_end, updated, updated_by ) VALUES 
                        ( {$user_id}, 3, NOW(), NOW(), NOW(), -1 ) ");
                        $db->execute();
                    }
                }

                if($is_guardian)
                {
                    // assign as guardian, if not already assigned

                    $ward_ext_ids = array_keys($t['payments']);
                    $ward_ext_ids_str = implode(',',$ward_ext_ids);

                    $db->query(" SELECT id FROM person WHERE ( is_member = 1 AND ext_id IN ({$ward_ext_ids_str}) ) ");
                    $db->execute();
                    $ward_ids = $db->fetchAllColumn();

                    foreach($ward_ids as $ward_id)
                    {
                        $db->query(" SELECT tbl_id FROM member_guardian WHERE ( id = :id AND guardian_id = {$user_id} ) ");
                        $db->bind(':id', $ward_id);
                        $db->execute();
                        $x = $db->fetchSingleColumn();

                        if(!empty($x))
                            continue;

                        $db->query(" INSERT INTO member_guardian 
                        ( id, guardian_id, guardian_begin, guardian_end, is_default, 
                         include_in_email, include_in_text, include_in_letter, 
                         updated, updated_by ) VALUES  
                        ( {$ward_id}, {$user_id}, NOW(), NOW(), 0, 
                         0, 0, 0, 
                         NOW(), -1 )");
                        $db->execute();
                    }
                }


                // store and return

                $this->op_transactions[$i]['org_user_id'] = $user_id;
                $this->op_transactions[$i]['user_email'] = $email;
                $this->op_transactions[$i]['user_mobile'] = $mobile;
            }

            return ['success'=>1];
        }
        catch(Exception $e)
        {
            Helper::show($db);
            return ['error'=>$e->getMessage()];
        }
    }




    private function set_org_payments($transactions)
    {
        $db = $this->_DB_core;

        $db->query(" SELECT MIN(fee_level) FROM op_processing_fee_level_org WHERE ( (fee_level > 1) AND org_guid = '{$this->org_guid}' ) ");
        $db->execute();
        $fee_level = intval($db->fetchSingleColumn());

        if(empty($fee_level))
            return ['error'=>'Fee levels have not been set for this Organisation.'];

        $db = $this->_DB_org;
        $db->beginTransaction();
        try
        {
            foreach($transactions as $i => $t)
            {
                $trx_amt = floatval($t['amount']);
                $trx_method = ($trx_amt < 0) ? 'refund' : $t['method'];

                if($trx_method == 'refund')
                    continue;

                $trx_comment = ($trx_method == 'card') ? null : $t['trx_comment']; // nullify comments intended for Stripe

                $db->query(" INSERT INTO op_transaction 
                ( ext_token, fee_level, method, refund_method, amount, trx_comment, trx_comment_is_public, user_id, created ) VALUES 
                ( :ext_token, {$fee_level}, '{$trx_method}', :refund_method, {$trx_amt}, :trx_comment, :trx_comment_is_public, :user_id, :created ) ");
                $db->bind(':ext_token', $t['ext_token']);
                $db->bind(':refund_method', null);
                $db->bind(':trx_comment', $trx_comment);
                $db->bind(':trx_comment_is_public', $t['trx_comment_is_public']);
                $db->bind(':user_id', $t['org_user_id']);
                $db->bind(':created', $t['created']);
                $db->execute();
                $trx_id = $db->lastInsertId();

                foreach($t['payments'] as $m_ext_id => $payments)
                {
                    $m_id = $this->org_members_by_ext_id[$m_ext_id]['id'];

                    foreach($payments as $p)
                    {
                        $p_amt = floatval($p['amount']);

                        if(empty($this->op_charge_id_to_org_charge_id[ $p['charge_id'] ]))
                            throw new Exception('Payment charge ID not mapped (' . $p['payment_id'] . ':' . $p['charge_id'] . ').');

                        $charge_id = $this->op_charge_id_to_org_charge_id[ $p['charge_id'] ];

                        $db->query(" SELECT id FROM op_fee WHERE ( member_id = {$m_id} AND charge_id = {$charge_id} ) ");
                        $db->execute();
                        $fee_id = $db->fetchSingleColumn();

                        if(empty($fee_id))
                            throw new Exception('Unrecognised Fee ID for payment: ' . $p['payment_id']);

                        $db->query(" INSERT INTO op_transaction_line_item 
                        ( refunded_by_id, transaction_id, deposit_id, fee_id, member_id, charge_id, amount ) VALUES 
                        ( 0, :transaction_id, 0, :fee_id, :member_id, :charge_id, :amount ) ");
                        $db->bind(':transaction_id', $trx_id);
                        $db->bind(':fee_id', $fee_id);
                        $db->bind(':member_id', $m_id);
                        $db->bind(':charge_id', $charge_id);
                        $db->bind(':amount', $p_amt);
                        $db->execute();
                        $payment_id = $db->lastInsertId();

                        $this->op_payment_id_to_org_line_item_id[ $p['payment_id'] ] = $payment_id;

                        if(!empty($p['refunded_by_id']))
                            $this->org_line_item_id_refunded_by_op_payment_id[$payment_id] = $p['refunded_by_id'];
                    }
                }
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }

    private function set_org_refunds($transactions)
    {
        $db = $this->_DB_core;

        $db->query(" SELECT MIN(fee_level) FROM op_processing_fee_level_org WHERE ( (fee_level > 1) AND org_guid = '{$this->org_guid}' ) ");
        $db->execute();
        $fee_level = intval($db->fetchSingleColumn());

        if(empty($fee_level))
            return ['error'=>'Fee levels have not been set for this Organisation.'];

        $db = $this->_DB_org;
        $db->beginTransaction();
        try
        {
            foreach($transactions as $i => $t)
            {
                $trx_amt = floatval($t['amount']);
                $trx_method = ($trx_amt < 0) ? 'refund' : $t['method'];

                if($trx_method !== 'refund')
                    continue;

                $db->query(" INSERT INTO op_transaction 
                ( ext_token, fee_level, method, refund_method, amount, trx_comment, trx_comment_is_public, user_id, created ) VALUES 
                ( :ext_token, {$fee_level}, '{$trx_method}', :refund_method, {$trx_amt}, :trx_comment, :trx_comment_is_public, :user_id, :created ) ");
                $db->bind(':ext_token', $t['ext_token']);
                $db->bind(':refund_method', null);
                $db->bind(':trx_comment', $t['trx_comment']);
                $db->bind(':trx_comment_is_public', $t['trx_comment_is_public']);
                $db->bind(':user_id', $t['org_user_id']);
                $db->bind(':created', $t['created']);
                $db->execute();
                $trx_id = $db->lastInsertId();

                foreach($t['payments'] as $m_ext_id => $payments)
                {
                    $m_id = $this->org_members_by_ext_id[$m_ext_id]['id'];

                    $refund_method = null;
                    foreach($payments as $n => $p)
                    {
                        $p_amt = floatval($p['amount']);
                        if($p_amt > 0)
                            $p_amt = 0 - $p_amt;

                        $line_item_refunded_id = array_search($p['payment_id'], $this->org_line_item_id_refunded_by_op_payment_id);
                        if(empty($line_item_refunded_id))
                            throw new Exception('Refunded line item not found for refund: ' . $p['payment_id']);

                        if($n === 0)
                        {
                            $db->query(" SELECT ot.method 
                            FROM op_transaction_line_item li 
                            LEFT JOIN op_transaction ot on li.transaction_id = ot.id 
                            WHERE ( li.id = {$line_item_refunded_id} ) ");
                            $db->execute();
                            $refund_method = $db->fetchSingleColumn();
                        }

                        if(empty($refund_method))
                            throw new Exception('No refund method found for refund: ' . $p['payment_id']);

                        if(empty($this->op_charge_id_to_org_charge_id[ $p['charge_id'] ]))
                            throw new Exception('Payment charge ID not mapped (' . $p['payment_id'] . ':' . $p['charge_id'] . ').');

                        $charge_id = $this->op_charge_id_to_org_charge_id[ $p['charge_id'] ];

                        $db->query(" SELECT id FROM op_fee WHERE ( member_id = {$m_id} AND charge_id = {$charge_id} ) ");
                        $db->execute();
                        $fee_id = $db->fetchSingleColumn();

                        if(empty($fee_id))
                            throw new Exception('Unrecognised Fee ID for payment: ' . $p['payment_id']);

                        $db->query(" INSERT INTO op_transaction_line_item 
                        ( refunded_by_id, transaction_id, deposit_id, fee_id, member_id, charge_id, amount ) VALUES 
                        ( 0, :transaction_id, 0, :fee_id, :member_id, :charge_id, :amount ) ");
                        $db->bind(':transaction_id', $trx_id);
                        $db->bind(':fee_id', $fee_id);
                        $db->bind(':member_id', $m_id);
                        $db->bind(':charge_id', $charge_id);
                        $db->bind(':amount', $p_amt);
                        $db->execute();
                        $payment_id = $db->lastInsertId();

                        $db->query(" UPDATE op_transaction_line_item SET refunded_by_id = {$payment_id} WHERE id = {$line_item_refunded_id} ");
                        $db->execute();

                        $this->op_payment_id_to_org_line_item_id[ $p['payment_id'] ] = $payment_id;
                    }

                    $db->query(" UPDATE op_transaction SET refund_method = '{$refund_method}' WHERE ( id = {$trx_id} ) ");
                    $db->execute();
                }
            }

            $db->commit();
            return ['success'=>1];
        }
        catch(Exception $e)
        {
            $db->rollBack();
            return ['error'=>$e->getMessage()];
        }
    }
}


require_once '../../ds_core/Config.php';
require_once '../../ds_core/classes/Database.php';
require_once '../../ds_core/classes/Helper.php';



$prefix = 'databizs';
$org_guid = '17961E';
$org_guid_lwr = strtolower($org_guid);
$org_op_db_name = $prefix . '_op_' . $org_guid_lwr;

$auth = new Database(
    $prefix . '_create',
    JCT_DB_CI_PASS,
    $prefix . '_authorisation',
    JCT_DB_CI_HOST,
    'utf8'
);

if(!$auth->db_valid)
    exit('While making authorisation DB connection: ' . $auth->db_error);

$op = new Database(
    $prefix . '_create',
    JCT_DB_CI_PASS,
    $org_op_db_name,
    JCT_DB_CI_HOST,
    'utf8'
);

if(!$op->db_valid)
    exit('While making op DB connection: ' . $op->db_error);

$core = new Database(
    $prefix . '_create',
    JCT_DB_CI_PASS,
    $prefix . '_core',
    JCT_DB_CI_HOST,
    'utf8'
);

if(!$core->db_valid)
    exit('While making core DB connection: ' . $core->db_error);

$org = new Database(
    $prefix . '_create',
    JCT_DB_CI_PASS,
    $prefix . '_org_' . $org_guid_lwr,
    JCT_DB_CI_HOST,
    'utf8'
);

if(!$org->db_valid)
    exit('While making org DB connection: ' . $org->db_error);

$i = new org_op_import($org_guid, $core, $org, $auth, $op);