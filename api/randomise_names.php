<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/01/2018
 * Time: 22:18
 */

namespace JCT;

require_once '../ds_core/Config.php';
require_once '../ds_core/classes/Database.php';
require_once '../ds_core/classes/Helper.php';

use Exception;
use JCT\Database;


die('safety on');

try
{
    $names_raw = file('sample_data' . JCT_DE . 'random_names.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $names = [];
    foreach($names_raw as $name)
    {
        $n = explode(' ', $name);
        $names[] = [
            'fname' => $n[0],
            'lname' => $n[1]
        ];
    }

    $db = new Database('root', 'pass', 'databizs_org_19374w', 'localhost', 'utf8');

    $db->query(" SELECT id, fname, lname, salute_name, email, mobile, landline FROM person WHERE 1 ORDER BY id ASC ");
    $db->execute();
    $persons = $db->fetchAllAssoc('id');

    $db->query(" SELECT sibling_id FROM member_sibling WHERE id = :id ");
    foreach($persons as $id => $p)
    {
        $persons[ $id ]['done'] = 0;

        $db->bind(':id', $id);
        $db->execute();
        $tmp = $db->fetchAllColumn();

        if(count($tmp))
            $persons[ $id ]['siblings'] = $tmp;
        else
            $persons[ $id ]['siblings'] = [];

        if(!empty($p['landline']))
            $persons[$id]['landline'] = substr_replace($p['landline'], '+020 91', 0, 7);

        if(!empty($p['mobile']))
            $persons[$id]['mobile'] = substr_replace($p['mobile'], '+020 91', 0, 7);
    }

    $n = 0;
    $used_fnames = [];
    foreach($persons as $id => $p)
    {
        if($p['done'])
            continue;

        if(!isset($names[$n]))
            $n = 0;

        $fname = $names[$n]['fname'];
        $lname = $names[$n]['lname'];
        $indexed_lname =  Helper::lname_as_index($lname);

        $persons[ $id ]['fname'] = $fname;
        $persons[ $id ]['lname'] = $lname;
        $persons[ $id ]['indexed_lname'] = $indexed_lname;

        if(!array_key_exists($fname, $used_fnames))
            $used_fnames[$fname] = 1;
        else
            $used_fnames[$fname]++;

        if(!empty($p['email']))
            $persons[ $id ]['email'] = strtolower($fname) . $used_fnames[$fname] . '@example.com';

        if(!empty($p['salute_name']))
        {
            $salt = explode(' ', $p['salute_name'])[0];
            $persons[$id]['salute_name'] = $salt . ' ' . $lname;
        }

        if(!empty($p['siblings']))
        {
            foreach($p['siblings'] as $sib_id)
            {
                $n++;

                if(!isset($names[$n]))
                    $n = 0;

                $fname = $names[$n]['fname'];
                $persons[ $sib_id ]['fname'] = $fname;
                $persons[ $sib_id ]['lname'] = $lname;
                $persons[ $sib_id ]['indexed_lname'] = $indexed_lname;

                if(!array_key_exists($fname, $used_fnames))
                    $used_fnames[$fname] = 1;
                else
                    $used_fnames[$fname]++;

                if(!empty($p['email']))
                    $persons[ $id ]['email'] = strtolower($fname) . $used_fnames[$fname] . '@example.com';

                if(!empty($p['salute_name']))
                {
                    $salt = explode(' ', $p['salute_name'])[0];
                    $persons[$id]['salute_name'] = $salt . ' ' . $lname;
                }

                $persons[ $sib_id ]['done'] = 1;
            }
        }

        $persons[ $id ]['done'] = 1;

        $n++;
    }


    Helper::show($persons);

    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE person 
        SET fname = :fname, lname = :lname, indexed_lname = :indexed_lname, salute_name = :salute_name, landline = :landline, mobile = :mobile, email = :email 
        WHERE id = :id ");
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
            $db->bind('salute_name', $p['salute_name']);
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
        throw new Exception($e->getMessage() . ' @ ' . $e->getLine());
    }


    $db = new Database('root', 'pass', 'databizs_core', 'localhost', 'utf8');
    $db->beginTransaction();
    try
    {
        $db->query(" UPDATE user 
        SET mobile = :mobile, email = :email 
        WHERE id = :id ");
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
    }
    catch(Exception $e)
    {
        $db->rollBack();
        throw new Exception($e->getMessage() . ' @ ' . $e->getLine());
    }



}
catch(Exception $e)
{
    echo $e->getMessage() . ' @ ' . $e->getLine();
}