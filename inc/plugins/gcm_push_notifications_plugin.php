<?php
if (!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.");

define("GCM_FILES", serialize(
    array (
        'images/icon-192x192.png',
        'inc/gcm_push_notifications',
        'inc/plugins/gcm_push_notifications_plugin.php',
        'inc/plugins/gcm_push_notifications_plugin.log',
        'jscripts/gcm_push_notifications.js.php',
        'gcm_push_notifications.php',
        'IndexDBWrapper.js',
        'manifest.json.php',
        'service-worker.js.php'
    )
));

define("GCM_JSCRIPT_HTML", '<script type="text/javascript" src="jscripts/gcm_push_notifications.js.php"></script>');
define("GCM_MANIFEST_HTML", '<link rel="manifest" href="manifest.json.php">');



function gcm_push_notifications_plugin_info()
{
    return array (
        "name"          => "GCM Push Notifications",
        "description"   => "Push notifications to Chrome/Android/iOS",
        "website"       => "http://github.com/marcandrews/",
        "author"        => "Marc Andrews",
        "authorsite"    => "http://github.com/marcandrews/",
        "version"       => "0.1.4",
        "codename"      => "gcm_push_notifications_plugin",
        "compatibility" => "18*"
    );
}



function gcm_push_notifications_plugin_install()
{
    global $db;

    // Create table to store GCM users
    $collation = $db->build_create_table_collation();
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
    
    // Insert settings in to the database
    $query = $db->query("SELECT disporder FROM ".TABLE_PREFIX."settinggroups ORDER BY `disporder` DESC LIMIT 1");
    $disporder = $db->fetch_field($query, 'disporder')+1;
    $setting_group = array(
        'name'          =>    'gcm_push_notifications',
        'title'         =>    'GCM Push Notifications',
        'description'   =>    'Settings for GCM Push Notifications',
        'disporder'     =>    0,
        'isdefault'     =>    0
    );
    $db->insert_query('settinggroups', $setting_group);
    $gid = $db->insert_id();

    $settings = array(
        'google_sender_id' => array(
            'title'         => 'Google Sender ID',
            'description'   => "From your Google Developer Console, create a new project, and this project's ID is your Google Sender ID.",
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'google_api_key' => array(
            'title'         => 'Google API Key',
            'description'   => "From your Google Developer Console project, navigate to APIs & auth > Credentials, and then create a new key to obtain your Google API key.",
            'optionscode'   => 'text',
            'value'         => ''
        ),
    );
    
    $s_index = 0;
    foreach($settings as $name => $setting)
    {
        $s_index++;
    	$value = $setting['value'];
        $insert_settings = array(
            'name'        => $db->escape_string('gcm_push_notifications_'.$name),
            'title'       => $db->escape_string($setting['title']),
            'description' => $db->escape_string($setting['description']),
            'optionscode' => $db->escape_string($setting['optionscode']),
            'value'       => $db->escape_string($value),
            'disporder'   => $s_index,
            'gid'         => $gid,
            'isdefault'   => 0
        );
        $db->insert_query('settings', $insert_settings);
    }
    rebuild_settings();
}



function gcm_push_notifications_plugin_is_installed()
{
    global $db;

    // Check HTTPS
    if ((empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'off') || intval($_SERVER['SERVER_PORT']) !== 443) 
    {
        echo '<div class="alert">GCM Push Notifications requires MyBB to be hosted over HTTPS.</div>';
        return false;
    }    
    
    // Check files
    $files = unserialize(GCM_FILES);
    foreach ($files as $file)
    {
        if (!file_exists('../'.$file)) $missing_files[] = $file;
    }
    if (!empty($missing_files))
    {
        echo '<div class="alert">GCM Push Notifications is missing the following files:<br> '.implode('<br>', $missing_files).'</div>';
        return false;
    }
    
    // Check database
    $result = $db->simple_select('settinggroups', 'gid', "name = 'gcm_push_notifications'", array('limit' => 1));
    $group = $db->fetch_array($result);
    return !empty($group['gid']) && $db->table_exists('gcm');
}

function gcm_push_notifications_plugin_uninstall()
{
    gcm_push_notifications_plugin_deactivate();
    
    global $db;
    
    // Remove settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'gcm_push_notifications'", array('limit' => 1));
    $group = $db->fetch_array($result);
    if (!empty($group['gid']))
    {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }
    
    // Remove gcm table
    // Until the plugin leaves pre-release, the gcm table will be left, so that
    // it is easier to reinstall without losing user/device registration.
    // $db->write_query("DROP TABLE IF EXISTS `".TABLE_PREFIX."gcm`");
}



function gcm_push_notifications_plugin_activate()
{
    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets(
        'usercp_options',
        "#" . preg_quote('{$headerinclude}') . "#i",
        '{$headerinclude}' . GCM_JSCRIPT_HTML.GCM_MANIFEST_HTML
    );
}



function gcm_push_notifications_plugin_deactivate()
{
    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    while (find_replace_templatesets('usercp_options', "#" . preg_quote(GCM_JSCRIPT_HTML.GCM_MANIFEST_HTML) . "#i", ''))
    {
        find_replace_templatesets('usercp_options', "#" . preg_quote(GCM_JSCRIPT_HTML.GCM_MANIFEST_HTML) . "#i", '');
    }
}



$plugins->add_hook('datahandler_post_insert_post', 'gcm_push_notifications_push');
function gcm_push_notifications_push()
{
    global $db, $mybb, $post;

    $date = date('c');
    $log = "--- start push {$date} ---".PHP_EOL;    
    
    if (empty($mybb->settings['gcm_push_notifications_google_sender_id']) or empty($mybb->settings['gcm_push_notifications_google_api_key'])) {
        $log .= "Error: no sender ID or API key specified".PHP_EOL;
        $log .= "--- end push {$date} ---".PHP_EOL.PHP_EOL;
        @file_put_contents("inc/plugins/gcm_push_notifications_plugin.log", $log, FILE_APPEND);
        return false;
    }
    
    $sql = "SELECT s.uid, g.subid FROM mybb_threadsubscriptions s, ".TABLE_PREFIX."gcm g WHERE s.uid = g.uid AND s.uid != {$mybb->user['uid']} AND s.tid = {$post['tid']}";
    $log .= "SQL:".preg_replace('/\s+/m', ' ', $sql).PHP_EOL;
    
    $query = $db->write_query($sql);
    
    $users = array();
    while ($user = $db->fetch_array($query))
    {
        if (!empty($user['subid'])) $users[] = $user['subid'];
    }
    $log .= "Number of subscribers: ".count($users).PHP_EOL;  
    
    if (!empty($users))
    {    
        $url = 'https://gcm-http.googleapis.com/gcm/send';
        $fields = array(
            'registration_ids' => $users,
        );
        $headers = array(
            'Authorization: key='.$mybb->settings['gcm_push_notifications_google_api_key'],
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
    @file_put_contents("inc/plugins/gcm_push_notifications_plugin.log", $log, FILE_APPEND);
}