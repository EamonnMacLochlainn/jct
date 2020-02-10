<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/04/2018
 * Time: 10:28
 */


function ascii_encrypt($var) {
    $num = '7427145899';
    $var= preg_replace('/\s+/', '', $var); $var= str_replace(' ', '', $var); $var = strtoupper($var); $to_ascii='';
    $arr_var = str_split($var);
    for ($i = 0; $i < count($arr_var); $i++) {
        $to_ascii .= ord($arr_var[$i]);
    }
    $encrypted = (int)$to_ascii + (int)$num;
    return $encrypted ;
}

function ascii_decrypt($var)
{
    $num = '7427145899';
    $min_var = $var - $num;
    $arr_var = str_split($min_var, 2);

    $var = '';
    for($i=0;$i<count($arr_var);$i++) {
        $var.= chr($arr_var[$i]);
    }
    return $var;
}



function get_default_host_name(\JCT\Database $db)
{
    $db->query(" SELECT setting_value FROM databizs_core.settings WHERE setting_key = 'default_host_name' ");
    $db->execute();
    return $db->fetchSingleColumn();
}

function get_default_org_database_ddl(\JCT\Database $create_db, $status = [])
{
    try
    {
        $create_db->query(" SHOW TABLES FROM `databizs_default_org` ");
        $create_db->execute();
        $tables = $create_db->fetchAllColumn();

        $sql = '';
        foreach($tables as $table)
        {
            $tbl = 'databizs_default_org.' . $table;
            $create_db->query(" SHOW CREATE TABLE {$tbl} ");
            $create_db->execute();
            $tmp = $create_db->fetchSingleAssoc();
            $sql.= PHP_EOL . $tmp['Create Table'] . ';' . PHP_EOL;
        }

        $create_db->query(" SELECT current_version FROM databizs_core.default_ddl_version WHERE app_slug = 'default' ");
        $create_db->execute();
        $version = floatval($create_db->fetchSingleColumn());

        $status[] = 'Default version retrieved.';

        return ['sql'=>$sql, 'version'=>$version, 'status'=>$status];
    }
    catch(Exception $e)
    {
        return ['error'=>$e->getMessage(), 'status'=>$status];
    }
}



function create_org_database(\JCT\Database $create_db, $org_guid, $org_host, $org_db_name, $sql_stmt, $status = [])
{
    try
    {
        //$create_db->query(" SET SESSION sql_mode = CONCAT(@@sql_mode. ', NO_AUTO_VALUE_ON_ZERO'); ");
        $create_db->query(" SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' ");
        $create_db->execute();

        $status[] = 'SQL mode set.';

        $stmt = ' CREATE DATABASE IF NOT EXISTS ' . $org_db_name . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci; ';
        $stmt.= ' USE ' . $org_db_name . ';';
        $stmt.= $sql_stmt;

        $create_db->query($stmt);
        $create_db->execute();

        $status[] = 'Default database SQL executed.';

        // reconnect to make sure a/c has permission to use db
        $create_db = null;
        $create_db = new \JCT\Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, $org_host, 'utf8');

        $create_db->query(" GRANT ALL PRIVILEGES ON  {$org_db_name}.* TO  'databizs_create'@'localhost' ");
        $create_db->execute();

        $status[] = 'Privileges to new DB granted.';

        $user = JCT_DB_SIUD_USER;
        $sql = " GRANT SELECT, INSERT, UPDATE, DELETE ON `{$org_db_name}`.* TO '{$user}'@'{$org_host}' ";
        $create_db->query($sql);
        $create_db->execute();

        $status[] = 'Default database permissions granted.';

        return ['success'=>1,'status'=>$status];
    }
    catch(Exception $e)
    {
        $create_db = new \JCT\Database(JCT_DB_CI_USER, JCT_DB_CI_PASS, null, $org_host, 'utf8');
        $create_db->query(" DROP DATABASE $org_db_name; ");
        $create_db->execute();

        $create_db->query(" DELETE FROM databizs_core.org_apps WHERE org_guid = :org_guid ");
        $create_db->bind(':org_guid', $org_guid);
        $create_db->execute();

        return ['error'=>'Failed at creating database: ' . $e->getMessage() . ' @ ' . $e->getTraceAsString(), 'status' => $status];
    }
}


function update_org_database_values(\JCT\Database $db, $org_guid, $host, $db_name, $default_ddl_version)
{
    $user_id = -1;
    $db->query(" UPDATE databizs_core.org_details SET host_name = '{$host}', db_name = '{$db_name}', db_version = {$default_ddl_version}, 
            updated = NOW(), updated_by = {$user_id} 
            WHERE guid = '{$org_guid}' ");
    $db->execute();
}


function subscribe_org_to_app(\JCT\Database $db, $org_guid, $app_slug, $is_default, $version)
{
    try
    {
        $db->query(" SELECT subscription_id FROM databizs_core.org_apps WHERE ( org_guid = '{$org_guid}' AND app_slug = '{$app_slug}' AND sub_ended IS NULL ) ");
        $db->execute();
        $existing_subscription = $db->fetchSingleColumn();

        if(!empty($existing_subscription))
            throw new Exception('Subscription already exists.');

        $user_id = -1;
        $db->query(" INSERT INTO databizs_core.org_apps 
            ( default_sub, app_slug, app_version, org_guid, sub_began, sub_ended, org_settings, active, updated, updated_by ) VALUES 
            ( {$is_default}, '{$app_slug}', {$version}, '{$org_guid}', NOW(), NULL, NULL, 1, NOW(), {$user_id} ) ");
        $db->execute();

        return ['success'=>1];
    }
    catch(Exception $e)
    {
        return ['error'=>$e->getMessage()];
    }
}

function insert_default_parameter_data(\JCT\Database $create_db, $db_name)
{
    // add default parameter data
    // not sure if this will work without manual sorting, to allow for Foreign Keys...
    try
    {
        $create_db->query(" SHOW TABLES FROM `databizs_default_org` LIKE 'prm_%' ");
        $create_db->execute();
        $prm_tables = $create_db->fetchAllColumn();
        foreach($prm_tables as $table)
        {
            $create_db->query(" INSERT INTO {$db_name}.{$table} SELECT * FROM databizs_default_org.{$table} ");
            $create_db->execute();
        }

        return ['success'=>1];
    }
    catch(Exception $e)
    {
        return ['error'=>$e->getMessage()];
    }
}


function get_app_table_names(\JCT\Database $create_db, $app_slug)
{
    $db_name = 'databizs_default_' . $app_slug;
    $create_db->query(" SHOW TABLES FROM {$db_name} ");
    $create_db->execute();
    return $create_db->fetchAllColumn();
}

function get_app_tables_ddl(\JCT\Database $create_db, $app_slug, $tables)
{
    $sql = '';
    foreach($tables as $table)
    {
        $tbl = 'databizs_default_' . $app_slug . '.' . $table;
        $create_db->query(" SHOW CREATE TABLE {$tbl} ");
        $create_db->execute();
        $tmp = $create_db->fetchSingleAssoc();
        $sql.= PHP_EOL . $tmp['Create Table'] . ';' . PHP_EOL;
    }

    $create_db->query(" SELECT current_version FROM databizs_core.default_ddl_version WHERE app_slug = '{$app_slug}' ");
    $create_db->execute();
    $version = floatval($create_db->fetchSingleColumn());

    return ['sql'=>$sql, 'version'=>$version];
}

function create_app_tables(\JCT\Database $create_db, $sql_stmt, $status = [])
{
    $create_db->beginTransaction();
    try
    {
        $create_db->query(" SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' ");
        $create_db->execute();

        $status[] = 'App tables SQL mode set.';

        $create_db->query($sql_stmt);
        $create_db->execute();

        $status[] = 'App tables SQL executed.';
        $create_db->commit();

        return ['success'=>1,'status'=>$status];
    }
    catch(Exception $e)
    {
        $create_db->rollBack();
        return ['error'=>'Failed at creating database: ' . $e->getMessage() . ' @ ' . $e->getTraceAsString()];
    }
}


function update_org_to_app(\JCT\Database $db, $org_guid, $app_slug, $sub_began_date)
{
    $user_id = -1;
    $db->query(" INSERT INTO databizs_core.org_apps 
            ( default_sub, app_slug, app_version, org_guid, sub_began, sub_ended, org_settings, active, updated, updated_by ) VALUES 
            ( 0, '{$app_slug}', '1.0', '{$org_guid}', '{$sub_began_date}', NULL, NULL, 1, NOW(), {$user_id} ) ");
    $db->execute();
}

try
{
    require_once '../ds_core/Config.php';
    require_once '../ds_core/classes/Database.php';
    require_once '../ds_core/classes/Helper.php';
    require_once '../ds_core/classes/Cryptor.php';
    require_once 'TextingCryptor.php';

    $db = new \JCT\Database('root', 'pass', null, 'localhost', 'utf8');
    if($db->db_error)
        throw new Exception($db->db_error);

    $db->query(" SELECT Username, Neon FROM databizs_core.authorisation WHERE 1 ");
    $db->execute();
    $tmp = $db->fetchAllAssoc();

    $needed = ['18494E','19000E','20237T'];
    $neon_users = [];
    $unrecognised = [];
    foreach($tmp as $i => $t)
    {
        $org_guid = strtoupper($t['Username']);
        $db->query(" SELECT id FROM databizs_core.org_details WHERE guid = :guid ");
        $db->bind(':guid', $org_guid);
        $db->execute();
        $id = intval($db->fetchSingleColumn());

        if( (!$id) || (!in_array($org_guid,$needed)) )
            continue;


        /*$tmp = get_default_host_name($db);
        if(empty($tmp))
            throw new Exception('Default database Hostname could not be established.');

        $default_host_name = $tmp;
        $org_host = $default_host_name;

        $tmp = get_default_org_database_ddl($db);
        if(empty($tmp['sql']))
            throw new Exception($tmp['error']);
        $default_sql_stmt = $tmp['sql'];
        $default_ddl_version = $tmp['version'];

        $org_db_name = 'databizs_org_' . strtolower($org_guid);

        $tmp = create_org_database($db, $org_guid, $org_host, $org_db_name, $default_sql_stmt, []);
        if(isset($tmp['error']))
            throw new Exception($tmp['error']);

        update_org_database_values($db, $org_guid, $org_host, $org_db_name, $default_ddl_version);

        $tmp = subscribe_org_to_app($db, $org_guid, 'manager', 1, $default_ddl_version);
        if(isset($tmp['error']))
            throw new Exception($tmp['error']);

        $tmp = insert_default_parameter_data($db, $org_db_name);
        if(isset($tmp['error']))
            throw new Exception($tmp['error']);






        // check if app-specific tables already exist
        $app_slug = 'messaging';
        $have_app_tables = false;

        $app_tables = get_app_table_names($db, $app_slug);
        if(empty($app_tables))
            throw new Exception('Required table names could not be determined.');



        // if tables are not found, create them
        $tmp = get_app_tables_ddl($db, $app_slug, $app_tables);
        if(empty($tmp['sql']))
            throw new Exception('App tables SQL could not be generated.');
        $app_tables_sql_stmt = $tmp['sql'];
        $app_tables_ddl_version = $tmp['version'];

        $tmp = create_app_tables($db, $app_tables_sql_stmt, []);
        if(isset($tmp['error']))
            throw new Exception($tmp['error']);

        $tmp = subscribe_org_to_app($db, $org_guid, $app_slug, 0, $app_tables_ddl_version);
        if(isset($tmp['error']))
            throw new Exception($tmp['error']);*/


        $password = TextingCryptor::Decrypt($t['Neon']);
        $password = \JCT\Cryptor::Encrypt($password);

        $db->query(" INSERT INTO databizs_core.messaging_org_operator 
          ( guid, operator, operator_begin, operator_end, username, password, updated, updated_by ) VALUES 
          ( :guid, :operator, NOW(), NULL, :username, :password, NOW(), -1 )  ");
        $db->bind(':guid', $org_guid);
        $db->bind(':operator', 'neon');
        $db->bind(':username', \JCT\Cryptor::Encrypt($t['Username']));
        $db->bind(':password', $password);
        $db->execute();

        $neon_users[] = [
            'guid' => $org_guid,
            'username' => $t['Username'],
            'password' => TextingCryptor::Decrypt($t['Neon'])
        ];
    }


    // 18494E
    // ScoilMuin15

    // 19000E

    // 20237T

    \JCT\Helper::show($unrecognised);
    \JCT\Helper::show($neon_users);
}
catch(Exception $e)
{
    \JCT\Helper::show($e->getTrace());
    echo $e->getMessage() . ' ' . $e->getLine();
}