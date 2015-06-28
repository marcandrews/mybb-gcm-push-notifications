<?php
header('Content-Type: application/json');

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
require "global.php"; 
?>
{
  "name": "<?= $mybb->settings['bbname'] ?>",
  "short_name": "<?= $mybb->settings['bbname'] ?>",
  "icons": [{
        "src": "images/icon-192x192.png",
        "sizes": "192x192",
        "type": "image/png"
      }],
  "start_url": "/dev",
  "display": "standalone",
  "gcm_sender_id": "<?= $mybb->settings['gcm_push_notifications_google_sender_id'] ?>",
  "gcm_user_visible_only": true
}