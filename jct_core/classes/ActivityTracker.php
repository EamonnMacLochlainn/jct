<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 29/09/2017
 * Time: 14:17
 */

namespace JCT;


use Exception;

class ActivityTracker
{
    private $_DB;

    private $record = false;
    private $user_id;
    private $org_guid;

    private $tracked_methods = [
        'login' => [
            'login_user',
            'logout_user'
        ]
    ];

    function __construct(Database $db)
    {
        $this->_DB = $db;

        if(!isset($_SESSION['databiz']))
            return false;

        if(empty($_SESSION['databiz']['id']))
            return false;

        if(empty($_SESSION['databiz']['org']))
            return false;

        if(empty($_SESSION['databiz']['org']['guid']))
            return false;

        $this->user_id = intval($_SESSION['databiz']['id']);
        $this->org_guid = $_SESSION['databiz']['org']['guid'];
        $this->record = true;
        return true;
    }

    function record_activity($model, $method)
    {
        if(!$this->record)
            return false;

        $model = strtolower($model);
        $method = strtolower($method);

        if(!array_key_exists($model, $this->tracked_methods))
            return false;

        if(!in_array($method, $this->tracked_methods[$model]))
            return false;

        $db = $this->_DB;
        $now = new \DateTime();
        $now->modify('-' . JCT_ACTIVITY_LIMIT . ' days');
        $now_str = $now->format('Y-m-d H:i:s');

        $db->query(" DELETE FROM activity_log 
            WHERE ( id = :id AND org_guid = :org_guid AND DATE(action_datetime) < :record_limit ) ");
        $db->bind(':id', $this->user_id);
        $db->bind(':org_guid', $this->org_guid);
        $db->bind(':record_limit', $now_str);
        $db->execute();

        $db->query(" INSERT INTO activity_log 
            ( id, org_guid, action_datetime, model, method ) VALUES ( :id, :org_guid, NOW(), :model, :method ) ");
        $db->bind(':id', $this->user_id);
        $db->bind(':org_guid', $this->org_guid);
        $db->bind(':model', $model);
        $db->bind(':method', $method);
        $db->execute();

        return true;
    }
}