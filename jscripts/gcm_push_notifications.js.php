<?php
header('Content-Type: application/javascript');

define('IN_MYBB', 1);
define('NO_ONLINE', 1);
require '../global.php';
?>
var ENDPOINT = 'xmlhttp.php';
var isEnabled = false;

var gcmPushNotifications = {

	cookies: {

		set: function (cname, cvalue, exdays) {
			var d = new Date();
			d.setTime(d.getTime() + (exdays*24*60*60*1000));
			var expires = 'expires=' + d.toUTCString();
			document.cookie = '<?= $mybb->settings['cookieprefix'] ?>' + cname + '=' + cvalue + '; ' + expires + '; path=<?= $mybb->settings['cookiepath'] ?>';
		},

		get: function (cname) {
			var name = '<?= $mybb->settings['cookieprefix'] ?>' + cname + '=';
			var ca = document.cookie.split(';');
			for (var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') c = c.substring(1);
				if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
			}
			return false;
		}	

	},

	
	permissionUI: {
		
		hasMetaRefresh: function () {
			var metas = document.getElementsByTagName('meta'); 
			for (var i = 0; i < metas.length; i++) { 
				if (metas[i].getAttribute('http-equiv') == 'refresh') return true;
			} 
			return false;
		},

		show: function () {
			var referenceNode = document.getElementById('subscriptionmethod');
			if (referenceNode) {
				// Add UI to UserCP > Edit Options page
				referenceNode.insertAdjacentHTML('afterend', '<div><br><strong>Push Notifications</strong></div><button type="button" class="gcm-push-button btn btn-primary" style="display:block">Enable Push Notifciations</button>');
				var pushButton = document.querySelector('.gcm-push-button');
				pushButton.addEventListener('click', function () {
					if (isEnabled) {
						gcmPushNotifications.unsubscribe();
					} else {
						gcmPushNotifications.subscribe();
					}
				});

				fetch(ENDPOINT, {
					credentials: 'include',
					method: 'post',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: 'action=gcm_devices'
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
						var n = Object.keys(json.result.devices).length;
						for (i = 0; i < n; i++) { 
							if (typeof subscriptionId !== 'undefined' && json.result.devices[i].subid == subscriptionId) {
								pushButton.style.display = 'none';
								pushButton.insertAdjacentHTML('afterend', '<div id="did' + json.result.devices[i].deviceid + '" class="current">' + json.result.devices[i].device + ' <strong class="label label-success">current</strong> <button type="button" class="btn btn-danger btn-xs" onClick="gcmPushNotifications.unsubscribe(\'' + json.result.devices[i].deviceid + '\')">remove</button></div>');
							} else {
								pushButton.insertAdjacentHTML('afterend', '<div id="did'+json.result.devices[i].deviceid+'">'+json.result.devices[i].device+' <button type="button" class="btn btn-danger btn-xs" onClick="gcmPushNotifications.server.revoke(\'' + json.result.devices[i].subscriptionId + '\',\'' + json.result.devices[i].deviceid + '\')">remove</button></div>');
							}
						}
					} else {
						console.log('No registered devices found');
					}
				}).catch(function(e) {
					console.error('Unable to retrieve data', e);
				});

			} else {
				if (isEnabled == false && !gcmPushNotifications.cookies.get('hidePermissionUI') && gcmPushNotifications.permissionUI.hasMetaRefresh() == false) {
					// Add UI to the page bottom
					var permissionDiv = document.createElement('div');
					permissionDiv.id = 'push-notifications-permission-ui';
					permissionDiv.style.cssText = 'position:fixed;bottom:0;z-index:10;width:100%;padding:20px;background:rgba(255,255,255,0.75);box-shadow:0px -10px 10px 0px rgba(0,0,0,0.33);text-shadow: 0px 0px 10px rgba(255, 255, 255, 1), 0px 0px 10px rgba(255, 255, 255, 1), 0px 0px 10px rgba(255, 255, 255, 1), 0px 0px 10px rgba(255, 255, 255, 1);';
					permissionDiv.innerHTML = '<h4><strong>Get notifications?</strong></h4><p>Get a push notification on your device when someone replies to your subscribed thread or sends you a private message?</p><p><button class="btn btn-primary" onclick="gcmPushNotifications.subscribe()">Yes</button> <button class="btn btn-default" onclick="gcmPushNotifications.permissionUI.decline()">Not now</button>';
					document.body.appendChild(permissionDiv);					
				}
			}			

		},

		register: function (device, deviceid) {
			var referenceNode = document.getElementById('subscriptionmethod');
			if (referenceNode) {
				var pushButton = document.querySelector('.gcm-push-button');
				pushButton.style.display = 'none';
				if (document.querySelector('#did' + deviceid) == null) {
					pushButton.insertAdjacentHTML('afterend', '<div id="did' + deviceid + '" class="current">' + device + ' <strong class="label label-success">current</strong> <button type="button" class="btn btn-danger btn-xs" onClick="gcmPushNotifications.unsubscribe(\'' + deviceid + '\')">remove</button></div>');
				}		
			} else {
				var permissionDiv = document.getElementById('push-notifications-permission-ui');
				if (permissionDiv) {
					permissionDiv.remove();
				}
			}
			
		},
		
		revoke: function (deviceid) {
			gcmPushNotifications.cookies.set('hidePermissionUI', false, 0);
			var dev = document.querySelector('#did' + deviceid);
			if (dev) {
				if (dev.getAttribute('class') == 'current') {
					// Current device was removed so re-enable the subscription
					// button.
					document.querySelector('.gcm-push-button').style.display = 'block';
				}
				dev.remove();
			}
			
		},
		
		decline: function () {
			gcmPushNotifications.cookies.set('hidePermissionUI', true, 30);
			var permissionDiv = document.getElementById('push-notifications-permission-ui');
			if (permissionDiv !== null) {
				permissionDiv.innerHTML = '<p>You can always enable push notifications from your UserCP > <a href="usercp.php?action=options">Edit Options</a> under <strong>Push Notifications</strong>.</p><p><button class="btn btn-primary" onclick="document.getElementById(\'push-notifications-permission-ui\').remove();">OK</button></p>';
			}
		}

	},

	
	server: {

		register: function (subid) {
			fetch(ENDPOINT, {
				credentials: 'include',
				method: 'post',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: 'action=gcm_register&gcm_subid='+subid
			}).then(function(response) {
				if (response.status >= 200 && response.status < 300) return response.json();
				throw new Error(response.statusText);
			}).then(function(json) {
				gcmPushNotifications.permissionUI.register(json.result.device, json.result.deviceid);
				console.log('Register succeeded with json response: ', json)
			}).catch(function(error) {
				console.log('Register failed:', error)
			})

		},

		revoke: function (subid, deviceid) {
			fetch(ENDPOINT, {
				credentials: 'include',
				method: 'post',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: 'action=gcm_revoke&gcm_subid='+subid,
			}).then(function(response) {
				if (response.status >= 200 && response.status < 300) return response.json();
				throw new Error(response.statusText);
			}).then(function(json) {
				gcmPushNotifications.permissionUI.revoke(deviceid)
				console.log('Revoke succeeded with json response: ', json)
			}).catch(function(error) {
				console.log('Revoke failed:', error)
			})
		}

	},

	
	init: function () {
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
			serviceWorkerRegistration.pushManager.getSubscription().then(function(subscription) {
				if (subscription) isEnabled = true;

				// Enable any UI which subscribes / unsubscribes from  
				// push messages.
				gcmPushNotifications.permissionUI.show();
				var pushButton = document.querySelector('.gcm-push-button');
				if (pushButton) pushButton.disabled = false;

				if (!subscription) {
					// We aren't subscribed to push, so set UI  
					// to allow the user to enable push
					return;
				}

				// Keep your server in sync with the latest subscriptionId
				var endpointParts = subscription.endpoint.split('/');
				subscriptionId = endpointParts[endpointParts.length - 1];
				gcmPushNotifications.server.register(subscriptionId);
			})
			.catch(function(error) {
				console.warn('Error during getSubscription()', error);
			});
		});
	},
	
	
	subscribe: function () {
		// Disable the button so it can't be changed while  
		// we process the permission request  
		var pushButton = document.querySelector('.gcm-push-button');
		if (pushButton) pushButton.disabled = true;

		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			serviceWorkerRegistration.pushManager.subscribe({userVisibleOnly: true})
			.then(function(subscription) {
				// The subscription was successful  
				isEnabled = true;
				if (pushButton) {
					pushButton.textContent = 'Disable Push Notifciations';
					pushButton.disabled = false;
				}

				// Send the subscription.subscriptionId and   
				// subscription.endpoint to your server  
				// and save it to send a push message at a later date
				var endpointParts = subscription.endpoint.split('/');
				subscriptionId = endpointParts[endpointParts.length - 1];
				return gcmPushNotifications.server.register(subscriptionId);
			})
			.catch(function(e) {
				if (Notification.permission === 'denied') {
					// The user denied the notification permission which  
					// means we failed to subscribe and the user will need  
					// to manually change the notification permission to  
					// subscribe to push messages  
					console.warn('Permission for Notifications was denied');
					if (pushButton) pushButton.disabled = true;
				} else {
					// A problem occurred with the subscription; common reasons  
					// include network errors, and lacking gcm_sender_id and/or  
					// gcm_user_visible_only in the manifest.  
					console.error('Unable to subscribe to push.', e);
					if (pushButton) pushButton.disabled = false;
					if (pushButton) pushButton.textContent = 'Enable Push Notifciations';
				}
			});
		});
	},
	
	
	unsubscribe: function (deviceid) {
		var pushButton = document.querySelector('.gcm-push-button');
		if (pushButton) pushButton.disabled = true;

		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			// To unsubscribe from push messaging, you need get the  
			// subscription object, which you can call unsubscribe() on.  
			serviceWorkerRegistration.pushManager.getSubscription()
			.then(function(subscription) {
				// Check we have a subscription to unsubscribe  
				console.log(subscription);
				if (!subscription) {
					// No subscription object, so set the state  
					// to allow the user to subscribe to push  
					isEnabled = false;
					if (pushButton) { 
						pushButton.disabled = false;
						pushButton.textContent = 'Enable Push Notifciations';
					}
					return;
				}

				// Make a request to your server to remove  
				// the subscriptionId from your data store so you   
				// don't attempt to send them push messages anymore
				var endpointParts = subscription.endpoint.split('/');
				subscriptionId = endpointParts[endpointParts.length - 1];
				gcmPushNotifications.server.revoke(subscriptionId, deviceid);

				// We have a subscription, so call unsubscribe on it  
				subscription.unsubscribe().then(function(successful) {
					if (pushButton) pushButton.disabled = false;
					if (pushButton) pushButton.textContent = 'Enable Push Notifciations';
					isEnabled = false;
				}).catch(function(e) {
					// We failed to unsubscribe, this can lead to  
					// an unusual state, so may be best to remove   
					// the users data from your data store and   
					// inform the user that you have done so

					console.log('Unsubscription error: ', e);
					if (pushButton) {
						pushButton.disabled = false;
						pushButton.textContent = 'Enable Push Notifciations';
					}
				});
			}).catch(function(e) {
				console.error('Error thrown while unsubscribing from push messaging.', e);
			});
		});
	}
}

window.addEventListener('load', function () {
    // Check that service workers are supported, if so, progressively  
    // enhance and add push messaging support, otherwise continue without it.  
    if ('serviceWorker' in navigator) {
		navigator.serviceWorker.register('service-worker.js.php').then(gcmPushNotifications.init);
    } else {
        console.warn('Service workers are unsupported in this browser.');
    }
});