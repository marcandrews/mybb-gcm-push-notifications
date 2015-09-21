<?php
if (!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.");

define("GCM_FILES", serialize(
    array (
        'images/icon-192x192.png',
        'inc/plugins/gcm_push_notifications',
        'inc/plugins/gcm_push_notifications_plugin.php',
        'jscripts/gcm_push_notifications.js.php',
        'IndexDBWrapper.js',
        'manifest.json.php',
        'service-worker.js.php'
    )
));

define("GCM_JSCRIPT_HTML", '<script type="text/javascript" src="jscripts/gcm_push_notifications.js.php"></script>');
define("GCM_MANIFEST_HTML", '<link rel="manifest" href="manifest.json.php">');



function gcm_push_notifications_plugin_info() {
    return array (
        "name"          => "GCM Push Notifications",
        "description"   => "Push notifications to Chrome for Windows, Android and Chrome OS",
        "website"       => "http://github.com/marcandrews/",
        "author"        => "Marc Andrews",
        "authorsite"    => "http://github.com/marcandrews/",
        "version"       => "0.2.2",
        "codename"      => "gcm_push_notifications_plugin",
        "compatibility" => "18*"
    );
}



function gcm_push_notifications_plugin_install() {
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
    foreach($settings as $name => $setting) {
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



function gcm_push_notifications_plugin_is_installed() {
    global $db;

    // Check HTTPS
    if ((empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'off') || intval($_SERVER['SERVER_PORT']) !== 443)  {
        echo '<div class="alert">GCM Push Notifications requires MyBB to be hosted over HTTPS.</div>';
        return false;
    }    
    
    // Check files
    $files = unserialize(GCM_FILES);
    foreach ($files as $file) {
        if (!file_exists('../'.$file)) $missing_files[] = $file;
    }
    if (!empty($missing_files)) {
        echo '<div class="alert">GCM Push Notifications is missing the following files:<br> '.implode('<br>', $missing_files).'</div>';
        return false;
    }
    
    // Check database
    $result = $db->simple_select('settinggroups', 'gid', "name = 'gcm_push_notifications'", array('limit' => 1));
    $group = $db->fetch_array($result);
    return !empty($group['gid']) && $db->table_exists('gcm');
}

function gcm_push_notifications_plugin_uninstall() {
    gcm_push_notifications_plugin_deactivate();
    
    global $db;
    
    // Remove settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'gcm_push_notifications'", array('limit' => 1));
    $group = $db->fetch_array($result);
    if (!empty($group['gid'])) {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }
    
    // Remove gcm table
    // Until the plugin leaves pre-release, the gcm table will be left, so that
    // it is easier to reinstall without losing user/device registration.
    // $db->write_query("DROP TABLE IF EXISTS `".TABLE_PREFIX."gcm`");
}



function gcm_push_notifications_plugin_activate() {

}



function gcm_push_notifications_plugin_deactivate() {

}



$plugins->add_hook('pre_output_page', 'gcm_push_notifications_global');
function gcm_push_notifications_global($page) {
	global $mybb;
	
	if ($mybb->user['uid']) {
    	return str_replace('</head>', GCM_MANIFEST_HTML.PHP_EOL.GCM_JSCRIPT_HTML.PHP_EOL.'</head>', $page);
	}
}



$plugins->add_hook('xmlhttp', 'gcm_push_notifications_xmlhttp');
function gcm_push_notifications_xmlhttp() {    
    global $db, $mybb;
    
    if ($mybb->user['uid'] and (
            ($mybb->get_input('action') == 'gcm_devices') or 
            (($mybb->get_input('action') == 'gcm_register' or $mybb->get_input('action') == 'gcm_revoke') and $mybb->get_input('gcm_subid')) or            
            ($mybb->get_input('action') == 'gcm_notifications')
    )) {
        header('Content-Type: application/json');        

        function get_device() {
            function myAutoLoader ($class_name) {
                require 'inc/plugins/gcm_push_notifications/' . str_replace('\\', '/', $class_name) . '.php';
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

        if ($mybb->get_input('action') == 'gcm_devices') {
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
        }

        if ($mybb->get_input('action') == 'gcm_register' and $mybb->get_input('gcm_subid')) {
            // Register a subscription
            $subid_esc = $db->escape_string($mybb->get_input('gcm_subid'));
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

        if ($mybb->get_input('action') == 'gcm_revoke' and $mybb->get_input('gcm_subid')) {
            // Revoke a subscription
            $subid_esc = $db->escape_string($mybb->get_input('gcm_subid'));
            $output['sql'] = "DELETE FROM ".TABLE_PREFIX."gcm WHERE uid = {$mybb->user['uid']} AND subid = '{$subid_esc}'";
            $output['success'] = $db->write_query($output['sql']);
            if ($output['success']) my_setcookie('deviceid', "");
        }

        if ($mybb->get_input('action') == 'gcm_notifications') {
            // Return a subscriber's new threads and posts
            $output['sql'] = "SELECT s.tid, t.subject, p.pid, p.username, COUNT(DISTINCT s.tid) as unread_threads, COUNT(DISTINCT p.pid) as unread_posts FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."threadsubscriptions s, ".TABLE_PREFIX."threads t, ".TABLE_PREFIX."threadsread r, ".TABLE_PREFIX."posts p WHERE u.uid = s.uid AND s.tid = t.tid AND u.uid = r.uid AND t.tid = r.tid AND t.tid = p.tid AND p.dateline > r.dateline AND t.lastpost > u.lastvisit  AND s.uid = {$mybb->user['uid']} ORDER BY p.dateline DESC";
            $output['success'] = $db->write_query($output['sql']);

            if ($output['success']) {
                $output['result'] = $db->fetch_array($output['success']);
            } else {
                $output['result'] = false;
            }

            $output['sql_pm'] = "SELECT pm.pmid, fromu.username AS `from`, pm.subject, pm.dateline, COUNT(DISTINCT pm.pmid) as unread_pms FROM ".TABLE_PREFIX."privatemessages pm, ".TABLE_PREFIX."users u, ".TABLE_PREFIX."users fromu WHERE pm.uid = u.uid AND pm.fromid = fromu.uid AND pm.readtime = 0 AND pm.dateline > u.lastvisit AND pm.uid = {$mybb->user['uid']} AND pm.toid = {$mybb->user['uid']} ORDER BY pm.dateline DESC";
            $output['success_pm'] = $db->write_query($output['sql_pm']);
            if ($output['success_pm']) {
                $output['result_pm'] = $db->fetch_array($output['success_pm']);
            } else {
                $output['result_pm'] = false;
            }
        }
        print json_encode($output, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
        exit;
    }
}



function gcm_push_notifications_push($users = array()) {
    global $mybb;
    if (!empty($users) and is_array($users)) {
        $url = 'https://android.googleapis.com/gcm/send';
        $fields = array('registration_ids' => $users);
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
        $ch_result = curl_exec($ch);
        curl_close($ch);
		
        return $ch_result;
    } else {
        return false;
    }
}



$plugins->add_hook('datahandler_post_insert_post', 'gcm_push_notifications_post');
function gcm_push_notifications_post() {
    global $db, $mybb, $post;

    $date = date('c');
    $log = "--- START push for posts {$date} ---".PHP_EOL;
	
	if (!empty($mybb->settings['gcm_push_notifications_google_sender_id']) and !empty($mybb->settings['gcm_push_notifications_google_api_key']) and !empty($post['tid'])) {
		$sql = "SELECT g.subid FROM ".TABLE_PREFIX."threadsubscriptions s, ".TABLE_PREFIX."gcm g WHERE s.uid = g.uid AND s.uid != {$mybb->user['uid']} AND s.tid = {$post['tid']}";
		$log .= "SQL:".preg_replace('/\s+/m', ' ', $sql).PHP_EOL;

		$query = $db->write_query($sql);

		$users = array();
		while ($user = $db->fetch_array($query)) {
			if (!empty($user['subid'])) $users[] = $user['subid'];
		}
		
		if (count($users)) {
			$log .= "Number of subscribers: ".count($users).PHP_EOL;

			$result = gcm_push_notifications_push($users);
			$log .= "CURL: ".$result.PHP_EOL; 
		} else {
			$log .= "WARNING: no subscribers".PHP_EOL;
			$result = false;
		}
	} else {
		$log .= "ERROR: Google sender ID, API key, or post content missing".count($users).PHP_EOL; 
		$result = false;
	}
    
	$log .= "--- END push {$date} ---".PHP_EOL.PHP_EOL;
	@file_put_contents("inc/plugins/gcm_push_notifications_plugin.log", $log, FILE_APPEND);
	
	//return $result;
}



$plugins->add_hook('datahandler_pm_insert_end', 'gcm_push_notifications_pm');
function gcm_push_notifications_pm() {
    global $db, $mybb, $pm;
    
	$date = date('c');
    $log = "--- START push for PMs {$date} ---".PHP_EOL;
   
    if (!empty($mybb->settings['gcm_push_notifications_google_sender_id']) and !empty($mybb->settings['gcm_push_notifications_google_api_key']) and !empty($pm['to'])) {
		$recipients = "'".implode("','", $pm['to'])."'";
		$sql = " SELECT g.subid FROM ".TABLE_PREFIX."users u, ".TABLE_PREFIX."gcm g WHERE u.uid = g.uid AND u.username IN ($recipients) AND g.uid != {$mybb->user['uid']}";
		$log .= "SQL:".preg_replace('/\s+/m', ' ', $sql).PHP_EOL;

		$query = $db->write_query($sql);

		$users = array();
		while ($user = $db->fetch_array($query)) {
			if (!empty($user['subid'])) $users[] = $user['subid'];
		}
		
		if (count($users)) {
			$log .= "Number of subscribers: ".count($users).PHP_EOL;

			$result = gcm_push_notifications_push($users);
			$log .= "CURL: ".$result.PHP_EOL; 
		} else {
			$log .= "WARNING: no subscribers".PHP_EOL;
			$result = false;
		}		
    } else {
		$log .= "ERROR: Google sender ID, API key, or post content missing".count($users).PHP_EOL; 
		$result = false;
	}
	
	$log .= "--- END push {$date} ---".PHP_EOL.PHP_EOL;
	@file_put_contents("inc/plugins/gcm_push_notifications_plugin.log", $log, FILE_APPEND);

	//return $result;    
}