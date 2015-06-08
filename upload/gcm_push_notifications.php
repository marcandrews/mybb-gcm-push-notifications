<?php
header('Content-Type: application/json');

define("IN_MYBB", 1);
require "global.php"; 
global $db;

if ($mybb->user['uid']) {  
    if (isset($_POST['register']) and !empty($_POST['regid'])) {
        // Register a subscription
        $query = $db->write_query("INSERT INTO ".TABLE_PREFIX."gcm (uid, regid) VALUES ({$mybb->user['uid']}, '{$_POST['regid']}') ON DUPLICATE KEY UPDATE regid = '{$_POST['regid']}'");
        print json_encode($query);
        exit;
    }    
    
    if (isset($_POST['revoke'])) {
        // Revoke a subscription
        $query = $db->write_query("DELETE FROM ".TABLE_PREFIX."gcm WHERE uid = {$mybb->user['uid']}");
        print json_encode($query);
        exit;
    }
    
    // Return a subscriber's new threads and posts
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
}
print json_encode($threads, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);