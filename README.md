# GCM Push Notifications for MyBB
This plugin for MyBB 1.8x pushes notifications via Google Cloud Messenging to Chrome after a post is made to a user's subscribed thread.

## Requirements
- MyBB hosted over HTTPS
- Modern version of Chrome (tested on Windows and Android, but all platforms should work)

## Installation instructions

 1. Obtain your Google Sender ID and API key
    1. Go to [Google Developer Console](https://console.developers.google.com/) and create a new project
    2. Near the top of the screen your Google Sender ID is your Project ID
    3. Under APIs & auth > Credentials, create a new key
    4. Your API key is your Google API key

 1. [Download ZIP](https://github.com/marcandrews/gcm-push-notifications-for-mybb/archive/master.zip) and extract the contents of the upload folder to your forum's root

 2. Update manifest.json
   - add your 2:`name`, 3:`short_name` and 11:`gcm_sender_id` (from 1.ii)

 3. Update service-worker.js
   - add your-forum-name to 24,58:`tag`
   - add Your Forum Name to 62,66,72:`title`

 4. Update gcm_push_notifications_plugin.php
   - define your Google API Key at 9:`GOOGLE_API_KEY` (from 1.iv)

 5. Make sure gcm_push_notifications_plugin.log is writable

 6. In your theme's User Control Panel Templates > usercp_options template, directly after `{$headerinclude}`, add: 
    ```
    <script type="text/javascript" src="jscripts/gcm_push_notifications.js">
    </script><link rel="manifest" href="manifest.json">
    ```

 7. Activate the plugin from your MyBB Admin CP

 8. Enable GCM Push Notifications from your User CP > Edit Options (under Default Thread Subscription Mode)

## Screenshots
### Android
<img src=assets/i/android.png width=360 height=640 />
### Desktop
<img src=assets/i/desktop.png />
