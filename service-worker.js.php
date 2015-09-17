<?php
header('Content-Type: application/javascript');

define("IN_MYBB", 1);
define("NO_ONLINE", 1);
require "global.php";

function clean($string) {
    $string = str_replace(' ', '-', $string);
    $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
    $string = strtolower($string);
    return preg_replace('/-+/', '-', $string);
}
?>
'use strict';

var ENDPOINT = 'xmlhttp.php';

importScripts('IndexDBWrapper.js');
var KEY_VALUE_STORE_NAME = 'key-value-store', idb;
function getIdb() {
    if (!idb) {
        idb = new IndexDBWrapper(KEY_VALUE_STORE_NAME, 1, function (db) {
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
    if (self.skipWaiting) {
        self.skipWaiting();
    }
});

self.addEventListener('push', function (event) {
    console.log('Received a push message', event);
    event.waitUntil(
        fetch(ENDPOINT, {
            credentials: 'include',
            method: 'post',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: 'action=gcm_notifications'
        }).then(function (response) {
            if (response.status !== 200) {
                console.log('Looks like there was a problem. Status Code: ' + response.status);
                // Throw an error so the promise is rejected and catch() is executed
                throw new Error();
            }
            return response.json();
        }).then(function (json) {
            if (json) {
                var title = '<?= htmlspecialchars($mybb->settings['bbname'], ENT_QUOTES) ?>',
                    message,
                    urlToOpen,
                    notificationTag = '<?= clean($mybb->settings['bbname']) ?>',
                    silent = false;
                if (json.result.unread_threads > 0) {
                    notificationTag = '<?= clean($mybb->settings['bbname']) ?>_post';
                    if (json.result.unread_threads == 1) {
                        if (json.result.unread_posts == 1) {
                            message = 'New post in ' + json.result.subject + ' from ' + json.result.username + '.';
                        } else {
                            message = json.result.unread_posts + ' new posts in ' + json.result.subject;
                            silent = true;
                        }
                        urlToOpen = 'showthread.php?tid=' + json.result.tid + '&action=newpost';
                    } else {
                        message = json.result.unread_threads + ' of your threads have new posts.';
                        silent = true;
                        urlToOpen = 'search.php?action=getnew';
                    }
                    // if (!Notification.prototype.hasOwnProperty('data')) {
                        // Since Chrome doesn't support data at the moment
                        // Store the URL in IndexDB
                        getIdb().put(KEY_VALUE_STORE_NAME, notificationTag, urlToOpen);
                    // }
                    showNotification(title, message, silent, null, notificationTag);
                }

                if (json.result_pm.unread_pms > 0) {
                    notificationTag = '<?= clean($mybb->settings['bbname']) ?>_pm';
                    if (json.result_pm.unread_pms == 1) {
                        message = 'You have an unread private message from ' + json.result_pm.from + ' titled ' + json.result_pm.subject + '.';
                        urlToOpen = 'private.php?action=read&pmid=' + json.result_pm.pmid;
                    } else {
                        message = 'You have ' + json.result_pm.unread_pms + ' unread private messages.';
                        silent = true;
                        urlToOpen = 'private.php';
                    }
                    // if (!Notification.prototype.hasOwnProperty('data')) {
                        // Since Chrome doesn't support data at the moment
                        // Store the URL in IndexDB
                        getIdb().put(KEY_VALUE_STORE_NAME, notificationTag, urlToOpen);
                    // }
                    showNotification(title, message, silent, null, notificationTag);
                }
            } else {
                console.log('No new messages');
            }
            return;
        }).catch(function (err) {
            console.error('Unable to retrieve data', err);
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    console.log('On notification click: ', event);
    event.notification.close();
    event.waitUntil(
        Promise.all([
            getIdb().get(KEY_VALUE_STORE_NAME, event.notification.tag),
            clients.matchAll({ type: 'window' })
        ]).then(function (resultArray) {
            var urlToOpen = resultArray[0] || '/';
            var clientList = resultArray[1];
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.lastIndexOf('<?= $mybb->settings['bburl'] ?>') === 0 && 'navigate' in client && 'focus' in client) {
                    client.navigate(urlToOpen);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});