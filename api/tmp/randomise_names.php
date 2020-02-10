<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/01/2018
 * Time: 22:18
 */

namespace JCT;

require_once '../../ds_core/Config.php';
require_once '../../ds_core/classes/Database.php';
require_once '../../ds_core/classes/Helper.php';

use Exception;
use JCT\Database;


#die('safety on');

try
{
    $names_raw = file('../sample_data' . JCT_DE . 'random_names.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $names = [];
    foreach($names_raw as $name)
    {
        $n = explode(' ', $name);
        $names[] = [
            'fname' => $n[0],
            'lname' => $n[1]
        ];
    }

    $database = JCT_PREFIX . '_org_11111a';
    $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, $database, JCT_DB_CI_HOST, 'utf8');

    $db->query(" SELECT id, fname, lname, salute_name, email, mobile, landline FROM person WHERE 1 ORDER BY id ASC ");
    $db->execute();
    $persons = $db->fetchAllAssoc('id');

    $db->query(" SELECT sibling_id FROM member_sibling WHERE ( id = :id ) ");

    $n = 0;
    $used_mobiles = [];
    $lnames_for_ids = [];
    foreach($persons as $id => $p)
    {
        $db->bind(':id', $id);
        $db->execute();
        $sibling_ids = $db->fetchAllColumn();

        if(!isset($names[$n]))
            $n = 0;

        $lname = (array_key_exists($id, $lnames_for_ids)) ? $lnames_for_ids[$id] : $names[$n]['lname'];
        $lnames_for_ids[ $id ] = $lname;
        if(!empty($sibling_ids))
            foreach($sibling_ids as $sib_id)
                $lnames_for_ids[ $sib_id ] = $lname;

        $fname = $names[$n]['fname'];
        $indexed_lname =  Helper::lname_as_index($lname);

        $persons[ $id ]['fname'] = $fname;
        $persons[ $id ]['lname'] = $lname;
        $persons[ $id ]['indexed_lname'] = $indexed_lname;
        $persons[ $id ]['salute_name'] = $fname;

        $x = 10000 + $n;
        $a = substr((string)$x, 0, 1);
        $b = substr((string)$x, 1, strlen($x));
        $ph = '+020 91' . $a . ' ' . $b;

        if(in_array($ph, $used_mobiles))
            $ph = null;

        if(!empty($p['landline']))
            $persons[$id]['landline'] = $ph;
        else
            $persons[$id]['landline'] = null;

        if(!empty($p['mobile']))
            $persons[$id]['mobile'] = $ph;
        else
            $persons[$id]['mobile'] = null;

        $used_mobiles[] = $ph;

        if(!empty($p['email']))
            $persons[$id]['email'] = 'user_' . $id . '@example.com';
        else
            $persons[$id]['email'] = null;

        $n++;
    }

    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE person 
        SET salt_id = 0, fname = :fname, lname = :lname, indexed_lname = :indexed_lname, salute_name = :salute_name, 
        landline = :landline, mobile = :mobile, email = :email 
        WHERE id = :id  ");
        foreach($persons as $id => $p)
        {
            $salute_name = (empty($p['salute_name'])) ? null : $p['salute_name'];
            $landline = (empty($p['landline'])) ? null : $p['landline'];
            $mobile = (empty($p['mobile'])) ? null : $p['mobile'];
            $email = (empty($p['email'])) ? null : $p['email'];

            $db->bind(':id', $id);

            $db->bind('fname', $p['fname']);
            $db->bind('lname', $p['lname']);
            $db->bind('indexed_lname', $p['indexed_lname']);
            $db->bind('salute_name', $salute_name);
            $db->bind('landline', $landline);
            $db->bind('mobile', $mobile);
            $db->bind('email', $email);
            $db->execute();
        }

        $db->commit();
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage() . ' @ person line ' . $e->getLine());
    }


    $db = new Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, 'databizs_core', JCT_DB_CI_HOST, 'utf8');
    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE user SET mobile = :mobile, email = :email WHERE id = :id ");
        foreach($persons as $id => $p)
        {
            $mobile = (empty($p['mobile'])) ? null : $p['mobile'];
            $email = (empty($p['email'])) ? null : $p['email'];

            $db->bind(':id', $id);
            $db->bind('mobile', $mobile);
            $db->bind('email', $email);
            $db->execute();
        }

        $db->commit();
        echo 'done';
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage() . ' @ user line ' . $e->getLine());
    }
}
catch(Exception $e)
{
    echo $e->getMessage() . ' @ ' . $e->getLine();
}