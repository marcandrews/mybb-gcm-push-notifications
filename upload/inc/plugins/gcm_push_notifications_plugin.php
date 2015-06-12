<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

define("GOOGLE_API_KEY", "AIzaSyBQIz34fFHeAdQaxcMiASii6SVF6p3d3kQ");   

define("GCM_FILES", serialize(
    array (
        'images/icon-192x192.png',
        'inc/gcm_push_notifications',
        'inc/plugins/gcm_push_notifications_plugin.php',
        'inc/plugins/gcm_push_notifications_plugin.log',
        'jscripts/gcm_push_notifications.js',
        'gcm_push_notifications.php',
        'IndexDBWrapper.js',
        'manifest.json',
        'service-worker.js'
    )
));

function gcm_push_notifications_plugin_info()
{
    return array(
        "name"          => "GCM Push Notifications",
        "description"   => "Push notifications to Chrome/Android/iOS",
        "website"       => "http://github.com/marcandrews/",
        "author"        => "Marc Andrews",
        "authorsite"    => "http://github.com/marcandrews/",
        "version"       => "0.1",
        "guid"          => "",
        "codename"      => "gcm_push_notifications_plugin",
        "compatibility" => "*"
    );
}


function gcm_push_notifications_plugin_install()
{
    global $db;
    
    $collation = $db->build_create_table_collation();
    if (!$db->table_exists('gcm')) {
        $db->write_query(
            "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."gcm` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `uid` int(50) NOT NULL,
                `device` varchar(16) NOT NULL,
                `deviceid` varchar(32) NOT NULL,
                `subid` varchar(256) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `UNIQUE_uid` (`uid`,`deviceid`)
            ) ENGINE=MyISAM{$collation};"
        );
    }

}

function gcm_push_notifications_plugin_is_installed()
{
    global $db;
    
    // check files
    $files = unserialize(GCM_FILES);
    foreach ($files as $file) {
        if (!file_exists('../'.$file)) return false;
    }
    
    // check db
    if (!$db->table_exists("gcm")) return false;
    
    return true;
}

function gcm_push_notifications_plugin_uninstall()
{    
    $db->write_query("DROP TABLE IF EXISTS `".TABLE_PREFIX."gcm`");
}

function gcm_push_notifications_plugin_activate()
{
    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets(
        'usercp_options',
        "#" . preg_quote('{$headerinclude}') . "#i",
        '{$headerinclude}
<script type="text/javascript" src="jscripts/gcm_push_notifications.js"></script>
<link rel="manifest" href="manifest.json">'
    );
}

function gcm_push_notifications_plugin_deactivate()
{
    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets(
        'usercp_options',
        "#" . preg_quote('<script type="text/javascript" src="jscripts/gcm_push_notifications.js"></script>') . "#i",
        ''
    );
    find_replace_templatesets(
        'usercp_options',
        "#" . preg_quote('<link rel="manifest" href="manifest.json">') . "#i",
        ''
    );
}

$plugins->add_hook('datahandler_post_insert_post', 'gcm_push_notifications_push');
function gcm_push_notifications_push()
{
    global $db, $mybb, $post;
    
    $date = date('c');
    $log = "--- start push {$date} ---".PHP_EOL;
    
    $sql = "SELECT s.uid, g.subid FROM mybb_threadsubscriptions s, mybb_gcm g WHERE s.uid = g.uid AND s.uid != {$mybb->user['uid']} AND s.tid = {$post['tid']}";
    $log .= "SQL:".preg_replace('/\s+/m', ' ', $sql).PHP_EOL;
    
    $query = $db->write_query($sql);
    
    $users = array();
    while ($user = $db->fetch_array($query)) {
        if (!empty($user['subid'])) $users[] = $user['subid'];
    }
    $log .= "Number of subscribers: ".count($users).PHP_EOL;  
    
    if (!empty($users)) {    
        $url = 'https://gcm-http.googleapis.com/gcm/send';
        $fields = array(
            'registration_ids' => $users,
        );
        $headers = array(
            'Authorization: key='.GOOGLE_API_KEY,
            'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
        $result = curl_exec($ch);
        $log .= "CURL: ".$result.PHP_EOL;
        
        if ($result === FALSE) $log .= "CURL error: ". curl_error($ch).PHP_EOL;
        curl_close($ch);
    } else {
        $log .= "Users: no users found".PHP_EOL;
    }
    
    $log .= "--- end push {$date} ---".PHP_EOL.PHP_EOL;
    file_put_contents("inc/plugins/gcm_push_notifications_plugin.log", $log, FILE_APPEND);
}
