<?php
header('Content-Type: application/json');

function get_device() {
    function myAutoLoader ($class_name) {
        require 'inc/gcm_push_notifications/' . str_replace('\\', '/', $class_name) . '.php';
    }
    spl_autoload_register('myAutoLoader');
    
    //  DeviceDetector
    $dd = new DeviceDetector\DeviceDetector($_SERVER['HTTP_USER_AGENT']);
    $dd->parse();    
    if ($dd->getModel()) {
        return $dd->getModel();
    } else {
        return sprintf('%s %1.1f for %s', $dd->getClient()['name'], $dd->getClient()['version'], $dd->getOs()['name']);
    }
}

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
require "global.php"; 
global $db;

if ($mybb->user['uid']) {
    if ($mybb->get_input('devices')) {
        // Get a user's registered devices
        $sql = "SELECT * FROM ".TABLE_PREFIX."gcm WHERE uid = {$mybb->user['uid']}";
        $query = $db->write_query($sql);
        $output['success'] = $query;
        if ($db->num_rows($query)) {
            while ($device = $db->fetch_array($query)) {
                $devices[] = $device;
            }
        } else {
            $devices = false;
        }
        $output['sql'] = $sql;
        $output['result']['devices'] = $devices;
        print json_encode($output, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }
    
    
    if ($mybb->get_input('register') and $mybb->get_input('subid')) {
        // Register a subscription
        $subid_esc = $db->escape_string($mybb->get_input('subid'));
        $device = get_device();
        $device_esc = $db->escape_string($device);
        if ($mybb->cookies['deviceid']) {
            $deviceid = $mybb->cookies['deviceid'];
        } else {
            $deviceid = md5($mybb->user['uid'].$device.uniqid(rand(), true));
        }
        $deviceid_esc = $db->escape_string($deviceid);
        $sql = "INSERT INTO ".TABLE_PREFIX."gcm (uid, device, deviceid, subid) VALUES ({$mybb->user['uid']}, '{$device_esc}', '{$deviceid_esc}', '{$subid_esc}') ON DUPLICATE KEY UPDATE subid = '{$subid_esc}'";
        $output['success'] = $db->write_query($sql);
        $output['sql'] = $sql;
        if ($output['success']) my_setcookie('deviceid', $deviceid);
        $output['result']['device'] = $device;
        $output['result']['deviceid'] = $deviceid;
        print json_encode($output, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }    
    
    if ($mybb->get_input('revoke') and $mybb->get_input('subid')) {
        // Revoke a subscription
        $subid_esc = $db->escape_string($mybb->get_input('subid'));
        $output['sql'] = "DELETE FROM ".TABLE_PREFIX."gcm WHERE uid = {$mybb->user['uid']} AND subid = '{$subid_esc}'";
        $output['success'] = $db->write_query($output['sql']);
        if ($output['success']) my_setcookie('deviceid', "");
        print json_encode($output, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }
    
    if ($mybb->get_input('notifications')) {
        // Return a subscriber's new threads and posts
        // this SQL needs to be optimized
        $query = $db->write_query("
            SELECT      (SELECT COUNT(*) FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."threadsread r, ".TABLE_PREFIX."threads t WHERE u.uid = r.uid AND r.tid = t.tid AND r.dateline < t.lastpost AND u.lastvisit < t.lastpost AND u.uid = 1) AS unread_t,
                        (SELECT COUNT(*) FROM ".TABLE_PREFIX."posts p WHERE p.tid = t.tid and p.dateline > r.dateline) AS unread_p,
                        s.tid lasttid, t.subject lastsubject, t.lastposter, r.dateline lastread, t.lastpost
            FROM        (".TABLE_PREFIX."users u, ".TABLE_PREFIX."threadsubscriptions s, ".TABLE_PREFIX."threads t)
            LEFT JOIN   ".TABLE_PREFIX."threadsread r ON s.tid = r.tid AND s.uid = r.uid
            WHERE       u.uid = s.uid AND
                        s.tid = t.tid AND
                        t.visible = 1 AND
                        r.dateline < t.lastpost AND
                        u.lastvisit < t.lastpost AND
                        u.uid = {$mybb->user['uid']}
            ORDER BY    t.lastpost DESC
            LIMIT       1
        ");

        if ($db->num_rows($query)) {
            $threads = $db->fetch_array($query);
        } else {
            $threads = false;
        }
        print json_encode($threads, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }
}
print json_encode(false);