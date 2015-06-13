<?php
header('Content-Type: application/javascript');

define("IN_MYBB", 1);
require "global.php"; 

function clean($string) {
    $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
    $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    $string = strtolower($string);
    return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
}
?>
'use strict';

var ENDPOINT = 'gcm_push_notifications.php';

importScripts('IndexDBWrapper.js');
var KEY_VALUE_STORE_NAME = 'key-value-store';
var idb;

// avoid opening idb until first call
function getIdb() {
    if (!idb) {
        idb = new IndexDBWrapper('key-value-store', 1, function (db) {
            db.createObjectStore(KEY_VALUE_STORE_NAME);
        });
    }
    return idb;
}

function showNotification(title, body, silent, icon, tag, data) {
    var notificationOptions = {
        body: body,
        icon: icon ? icon : 'images/icon-192x192.png',
        silent: silent ? silent : false,
        tag: tag ? tag : '<?= clean($mybb->settings['bbname']) ?>',
        data: data
    };
    if (self.registration.showNotification) {
        self.registration.showNotification(title, notificationOptions);
        return;
    } else {
        new Notification(title, notificationOptions);
    }
}

self.addEventListener('install', function (event) {
    // Perform install steps
    if (self.skipWaiting) {
        self.skipWaiting();
    }
});

self.addEventListener('push', function (event) {
    // console.log(ENDPOINT);
    console.log('Received a push message', event);

    fetch(ENDPOINT, {
        credentials: 'include',
        method: 'post',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: 'notifications=1'
    }).then(function (response) {
        if (response.status !== 200) {
            console.log('Looks like there was a problem. Status Code: ' + response.status);
            // Throw an error so the promise is rejected and catch() is executed
            throw new Error();
        }
        return response.json();
    }).then(function (json) {
        // console.log('Parsed json:', json);

        if (json) {
            var title = 'MyBB Dev Forum',
                message,
                urlToOpen,
                notificationTag = '<?= clean($mybb->settings['bbname']) ?>',
                silent = false;
            if (json.unread_t == 1) {
                if (json.unread_p == 1) {
                    message = 'New post in ' + json.lastsubject + ' from ' + json.lastposter + '.';
                    urlToOpen = 'thread-' + json.lasttid +'-newpost.html';
                } else {
                    message = json.unread_p + ' new posts in ' + json.lastsubject;
                    silent = true;
                    urlToOpen = 'thread-' + json.lasttid + '-newpost.html';
                }
            } else {
                message = json.unread_t + ' of your threads have new posts.';
                silent = true;
                urlToOpen = 'search.php?action=getnew';
            }                
            if (!Notification.prototype.hasOwnProperty('data')) {
                // Since Chrome doesn't support data at the moment
                // Store the URL in IndexDB
                getIdb().put(KEY_VALUE_STORE_NAME, notificationTag, urlToOpen);
            }                
            return showNotification(title, message, silent, null, notificationTag);
        } else {
            console.log('No new messages');
        }
    }).catch(function (ex) {
        console.log('parsing failed', ex);
    }).catch(function (err) {
        console.error('Unable to retrieve data', err);
    });
});

self.addEventListener('notificationclick', function (event) {
    console.log('On notification click: ', event);
    event.waitUntil(getIdb().get(KEY_VALUE_STORE_NAME, event.notification.tag).then(function (url) {
        // At the moment you cannot open third party URL's, a simple trick
        // is to redirect to the desired URL from a URL on your domain
        var redirectUrl = "";
        if (url) redirectUrl = url;
        return clients.openWindow(redirectUrl);
        }));
    event.notification.close();
});