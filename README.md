# GCM Push Notifications for MyBB
This plugin for MyBB pushes notifications via Google Cloud Messaging to Chrome after a post is made to a user's subscribed thread.


## Requirements
- MyBB 1.8x hosted over HTTPS
- Modern version of Chrome (tested on Windows and Android, but all platforms should work)


## Demo
 1. Make sure your Chrome browser is synced with a Google account
 2. Navigate to my [MyBB Dev Forums](https://chivegaming.org/dev/) and register
 3. Enable GCM Push Notifications from your User CP > [Edit Options](https://chivegaming.org/dev/usercp.php?action=options) (under Default Thread Subscription Mode)
    - note that this is my development forum so if I'm currently working on the plugin, the demo may not function correctly; however, I will try to always have a functioning/tested version on GitHub
 4. Post a new thread and subscribe to it or subscribe to an existing thread
 5. Open a new Chrome incognito window, and register a second account on my MyBB Dev Forum
 6. Post to your first account's subscribed thread to see notification


## Instructions

### Installation

 1. Obtain your Google Sender ID and API key
    1. Go to [Google Developer Console](https://console.developers.google.com/) and create a new project
    2. Near the top of the screen your Google Sender ID is your Project ID
    3. Under APIs & auth > Credentials, create a new key
    4. Your API key is your Google API key
 2. [Download](https://github.com/marcandrews/gcm-push-notifications-for-mybb/releases) and extract the contents of the upload folder to your MyBB forum's root
 3. From your MyBB Admin CP
    1. Install and activate the GCM Push Notifications plugin
    2. Navigate to Configuration > Settings > GCM Push Notifications (under Plugin Settings)
       1. Input your Google Sender ID (from 1.ii)
       2. Input your Google API key (from 1.iv)

### Update

If you're updating from a previous pre-release, please uninstall from your Admin CP, and then continue from installation instructions step 2.

### Uninstallation

Uninstall from you Admin CP, and then manually remove the gcm table from your database.


## Screenshots

### Android
<img src=.assets/i/android.png width=360 height=640 />
### Desktop
<img src=.assets/i/desktop.png />
