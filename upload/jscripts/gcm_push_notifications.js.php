<?php
header('Content-Type: application/javascript');

define("IN_MYBB", 1);
require "../global.php";
?>
var ENDPOINT = 'gcm_push_notifications.php';
var isEnabled = false;

function getCookie(cname) {
    var name = '<?= $mybb->settings['cookieprefix'] ?>' + cname + '=';
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
    }
    return "";
}

window.addEventListener('load', function () {
    // Add button to Edit Settings User CP page
    var referenceNode = document.getElementById('subscriptionmethod');
    referenceNode.insertAdjacentHTML('afterend', '<div><br><strong>GCM Push Notifications</strong></div><button type="button" class="gcm-push-button" style="display:block">Enable GCM Push Notifciations</button>');
    var pushButton = document.querySelector('.gcm-push-button');
    pushButton.addEventListener('click', function () {
        if (isEnabled) {
            unsubscribe();
        } else {
            subscribe();
        }
    });

    // Check that service workers are supported, if so, progressively  
    // enhance and add push messaging support, otherwise continue without it.  
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js.php')
            .then(initialiseState);
    } else {
        console.warn('Service workers aren\'t supported in this browser.');
    }
});

// Once the service worker is registered set the initial state  
function initialiseState() {
    // Are Notifications supported in the service worker?  
    if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
        console.warn('Notifications aren\'t supported.');
        return;
    }

    // Check the current Notification permission.  
    // If its denied, it's a permanent block until the  
    // user changes the permission  
    if (Notification.permission === 'denied') {
        console.warn('The user has blocked notifications.');
        return;
    }

    // Check if push messaging is supported  
    if (!('PushManager' in window)) {
        console.warn('Push messaging isn\'t supported.');
        return;
    }

    
    // We need the service worker registration to check for a subscription  
    navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
        // Do we already have a push message subscription?  
        serviceWorkerRegistration.pushManager.getSubscription()
            .then(function(subscription) {
                // Enable any UI which subscribes / unsubscribes from  
                // push messages.  
                var pushButton = document.querySelector('.gcm-push-button');
                pushButton.disabled = false;

                if (!subscription) {
                    // We aren't subscribed to push, so set UI  
                    // to allow the user to enable push  
                    return;
                }
                subId = subscription.subscriptionId;
                // Keep your server in sync with the latest subscriptionId
                registerSubscriptionToServer(subId);

                // Set your UI to show they have subscribed for  
                // push messages  
                pushButton.textContent = 'Disable GCM Push Notifciations';
                isEnabled = true;
            })
            .catch(function(err) {
                console.warn('Error during getSubscription()', err);
            });
    });
    
    // Retrieve a list of registered devices
    fetch(ENDPOINT, {
        credentials: 'include',
        method: 'post',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: 'devices=1'
    }).then(function(response) {
        if (response.status !== 200) {
            console.log('Looks like there was a problem. Status Code: ' + response.status);
            // Throw an error so the promise is rejected and catch() is executed
            throw new Error();
        }
        return response.json();
    }).then(function(json) {
        console.log('Parsed json:', json.result.devices);
        if (json) {
            var pushButton = document.querySelector('.gcm-push-button');
            var n = Object.keys(json.result.devices).length;
            for (i = 0; i < n; i++) { 
                if (typeof subId !== 'undefined' && json.result.devices[i].subid == subId) {
                    pushButton.style.display = 'none';
                    pushButton.insertAdjacentHTML('afterend', '<div id="did' + json.result.devices[i].deviceid + '" class="current">' + json.result.devices[i].device + ' <strong>(current)</strong> (<a href="#!" onClick="unsubscribe(\'' + json.result.devices[i].deviceid + '\')">remove</a>)</div>');
                } else {
                    pushButton.insertAdjacentHTML('afterend', '<div id="did'+json.result.devices[i].deviceid+'">'+json.result.devices[i].device+' (<a href="#!" onClick="revokeSubscriptionFromServer(\'' + json.result.devices[i].subid + '\',\'' + json.result.devices[i].deviceid + '\')">remove</a>)</div>');
                }
            }
        } else {
            console.log('No registered devices found');
        }
    }).catch(function(ex) {
        console.log('parsing failed', ex);
    }).catch(function(err) {
        console.error('Unable to retrieve data', err);
    })
    
}

function subscribe() {
    // Disable the button so it can't be changed while  
    // we process the permission request  
    var pushButton = document.querySelector('.gcm-push-button');
    pushButton.disabled = true;

    navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
        serviceWorkerRegistration.pushManager.subscribe()
            .then(function(subscription) {
                // The subscription was successful  
                isEnabled = true;
                pushButton.textContent = 'Disable GCM Push Notifciations';
                pushButton.disabled = false;

                // Send the subscription.subscriptionId and   
                // subscription.endpoint to your server  
                // and save it to send a push message at a later date   
                registerSubscriptionToServer(subscription.subscriptionId);
            })
            .catch(function(e) {
                if (Notification.permission === 'denied') {
                    // The user denied the notification permission which  
                    // means we failed to subscribe and the user will need  
                    // to manually change the notification permission to  
                    // subscribe to push messages  
                    console.warn('Permission for Notifications was denied');
                    pushButton.disabled = true;
                } else {
                    // A problem occurred with the subscription; common reasons  
                    // include network errors, and lacking gcm_sender_id and/or  
                    // gcm_user_visible_only in the manifest.  
                    console.error('Unable to subscribe to push.', e);
                    pushButton.disabled = false;
                    pushButton.textContent = 'Enable GCM Push Notifciations';
                }
            });
    });
}

function unsubscribe(deviceid) {
    var pushButton = document.querySelector('.gcm-push-button');
    pushButton.disabled = true;

    navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
        // To unsubscribe from push messaging, you need get the  
        // subscription object, which you can call unsubscribe() on.  
        serviceWorkerRegistration.pushManager.getSubscription().then(
            function(pushSubscription) {
                // Check we have a subscription to unsubscribe  
                if (!pushSubscription) {
                    // No subscription object, so set the state  
                    // to allow the user to subscribe to push  
                    isEnabled = false;
                    pushButton.disabled = false;
                    pushButton.textContent = 'Enable GCM Push Notifciations';
                    return;
                }

                // var subscriptionId = pushSubscription.subscriptionId;  
                // Make a request to your server to remove  
                // the subscriptionId from your data store so you   
                // don't attempt to send them push messages anymore
                revokeSubscriptionFromServer(pushSubscription.subscriptionId, deviceid);

                // We have a subscription, so call unsubscribe on it  
                pushSubscription.unsubscribe().then(function(successful) {
                    pushButton.disabled = false;
                    pushButton.textContent = 'Enable GCM Push Notifciations';
                    isEnabled = false;
                }).catch(function(e) {
                    // We failed to unsubscribe, this can lead to  
                    // an unusual state, so may be best to remove   
                    // the users data from your data store and   
                    // inform the user that you have done so

                    console.log('Unsubscription error: ', e);
                    pushButton.disabled = false;
                    pushButton.textContent = 'Enable GCM Push Notifciations';
                });
            }).catch(function(e) {
            console.error('Error thrown while unsubscribing from push messaging.', e);
        });
    });
}

function registerSubscriptionToServer(subid) {
    fetch(ENDPOINT, {
        credentials: 'include',
        method: 'post',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: 'register=1&subid='+subid
    }).then(function(response) {
        if (response.status >= 200 && response.status < 300) return response;
        throw new Error(response.statusText)
    }).then(function(response) {
        return response.json();
    }).then(function(json) {
        var pushButton = document.querySelector('.gcm-push-button');
        pushButton.style.display = 'none';
        if (document.querySelector('#did' + json.result.deviceid) == null) {
            console.log('#did' + json.result.deviceid);
            console.log(document.querySelector('#did' + json.result.deviceid));
            pushButton.insertAdjacentHTML('afterend', '<div id="did' + json.result.deviceid + '" class="current">' + json.result.device + ' <strong>(current)</strong> (<a href="#!" onClick="unsubscribe(\'' + json.result.deviceid + '\')">remove</a>)</div>');
        }
        console.log('Register succeeded with json response: ', json)
    }).catch(function(error) {
        console.log('Register failed:', error)
    })
}

function revokeSubscriptionFromServer(subid, deviceid) {
    fetch(ENDPOINT, {
        credentials: 'include',
        method: 'post',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: 'revoke=1&subid='+subid,
    }).then(function(response) {
        if (response.status >= 200 && response.status < 300) return response;
        throw new Error(response.statusText)
    }).then(function(response) {
        return response.json()
    }).then(function(json) {
        var dev = document.querySelector('#did' + deviceid);
        console.log(deviceid);
        console.log(dev);
        if (dev) {
            if (dev.getAttribute('class') == 'current') {
                // Current device was removed so remove it from the list and enable
                // the subscription button.
                document.querySelector('.gcm-push-button').style.display = 'block';
            }
            dev.remove();
        }
        console.log('Revoke succeeded with json response: ', json)
    }).catch(function(error) {
        console.log('Revoke failed:', error)
    })
}