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
        $output['sql'] = "SELECT s.tid, t.subject, p.pid, p.username, COUNT(DISTINCT s.tid) as unread_threads, COUNT(DISTINCT p.pid) as unread_posts FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."threadsubscriptions s, ".TABLE_PREFIX."threads t, ".TABLE_PREFIX."threadsread r, ".TABLE_PREFIX."posts p WHERE u.uid = s.uid AND s.tid = t.tid AND u.uid = r.uid AND t.tid = r.tid AND t.tid = p.tid AND p.dateline > r.dateline AND u.lastvisit < t.lastpost AND s.uid = {$mybb->user['uid']} ORDER BY p.dateline DESC";
        $output['success'] = $db->write_query($output['sql']);
        
        if ($output['success']) {
            $output['result'] = $db->fetch_array($output['success']);
        } else {
            $output['result'] = false;
        }
        print json_encode($output, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }
}
print json_encode(false);