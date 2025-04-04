/**
 * AMD Module for handling Bunny Player interaction and completion.
 * VERSION WITH EXTRA LOGGING
 */
define(['jquery', 'core/ajax', 'core/log', 'core/notification'], function($, Ajax, Log, Notification) {

    // Initialize log module for debugging
    Log.init({ level: 'debug' }); // Ensure debug level is set

    Log.debug('BunnyVideo: player_handler.js AMD module loaded.');

    var playerInstance = null; // Holds the Player.js instance
    var config = null; // Holds config passed from PHP
    var maxPercentReached = 0; // Tracks the highest percentage watched
    var completionSent = false; // Flag to prevent multiple AJAX calls
    var iframeElement = null; // Reference to the iframe DOM element

    /**
     * Send completion status update via AJAX.
     */
    var sendCompletion = function() {
        Log.debug('BunnyVideo: sendCompletion called. completionSent = ' + completionSent);
        if (completionSent) {
            Log.debug('BunnyVideo: Completion already sent for cmid ' + config.cmid + '. Aborting.');
            return; // Don't send again
        }

        Log.info('BunnyVideo: Threshold reached (' + maxPercentReached.toFixed(2) + '% >= ' + config.completionPercent + '%). Sending completion AJAX for cmid ' + config.cmid);
        completionSent = true; // Set flag immediately

        var promises = Ajax.call([{
            methodname: 'mod_bunnyvideo_mark_complete',
            args: { cmid: config.cmid },
            done: function(response) {
                Log.debug('BunnyVideo: AJAX done callback received.', response);
                if (response.success) {
                    Log.info('BunnyVideo: Completion successfully marked via AJAX for cmid ' + config.cmid);
                    // Optionally update UI or trigger Moodle JS completion event
                } else {
                    Log.warn('BunnyVideo: AJAX call reported failure for cmid ' + config.cmid + ': ' + (response.message || 'No message'));
                    Notification.add(response.message || 'Error marking activity complete.', { type: 'error' });
                    completionSent = false; // Allow retry on error? Risky.
                }
            },
            fail: function(ex) {
                Log.error('BunnyVideo: AJAX call failed for cmid ' + config.cmid, ex);
                Notification.exception(ex);
                completionSent = false; // Allow retry on error? Risky.
            }
        }]);

        // Handle potential promise errors
        if (promises && promises[0] && typeof promises[0].catch === 'function') {
            promises[0].catch(function(ex) {
                 Log.error('BunnyVideo: AJAX promise failed for cmid ' + config.cmid, ex);
                 Notification.exception(ex);
                 completionSent = false; // Allow retry
            });
        } else {
             Log.debug('BunnyVideo: Ajax.call did not return a promise or promise array.');
        }
    };

    /**
     * Event listener for player time updates.
     */
    var onTimeUpdate = function() {
        // Log less frequently to avoid flooding console? Maybe only every few seconds?
        // For now, log every time for debugging.
        // Log.debug('BunnyVideo: onTimeUpdate fired.');

        if (!playerInstance || !config || config.completionPercent <= 0) {
            // Log.debug('BunnyVideo: onTimeUpdate - Aborting (no player, no config, or completion % is 0)');
            return;
        }
        if (completionSent) {
             // Log.debug('BunnyVideo: onTimeUpdate - Aborting (completion already sent)');
             return;
        }


        try {
            // Use a default value of 0 if API returns NaN or undefined
            var currentTime = playerInstance.api('currentTime') || 0;
            var duration = playerInstance.api('duration') || 0;

            // Log.debug('BunnyVideo: timeupdate - currentTime: ' + currentTime + ', duration: ' + duration);

            // Player might not be ready or duration might be unknown (live streams?)
            if (duration <= 0) {
                // Log.debug('BunnyVideo: onTimeUpdate - Aborting (duration <= 0)');
                return;
            }

            var currentPercent = (currentTime / duration) * 100;

            if (currentPercent > maxPercentReached) {
                maxPercentReached = currentPercent;
                Log.debug('BunnyVideo: Max percent watched updated: ' + maxPercentReached.toFixed(2) + '% for cmid ' + config.cmid);
            }

            if (maxPercentReached >= config.completionPercent) {
                Log.debug('BunnyVideo: onTimeUpdate - Threshold met. Calling sendCompletion.');
                sendCompletion();
            }
        } catch (e) {
            // Catch errors if Player.js API calls fail (e.g., player destroyed)
             Log.warn('BunnyVideo: Error accessing Player.js API during timeupdate:', e);
             // Detach listener if player seems broken?
             if (playerInstance && typeof playerInstance.off === 'function') {
                 Log.warn('BunnyVideo: Detaching timeupdate listener due to error.');
                 playerInstance.off('timeupdate', onTimeUpdate);
             }
        }
    };

    /**
      * Event listener for when the player is ready.
      */
    var onReady = function() {
         Log.info('BunnyVideo: Player.js ready event received for cmid ' + config.cmid);
         // Attach event listeners only when ready
         try {
            if (playerInstance && config.completionPercent > 0) {
                 // Check if API methods are available
                 if (typeof playerInstance.api('currentTime') !== 'undefined' && typeof playerInstance.api('duration') !== 'undefined') {
                     Log.debug('BunnyVideo: Player ready - Attaching timeupdate listener for cmid ' + config.cmid);
                     playerInstance.on('timeupdate', onTimeUpdate);
                 } else {
                      Log.warn('BunnyVideo: Player ready, but time/duration API methods seem unavailable. Cannot track progress.');
                 }
            } else {
                 Log.debug('BunnyVideo: Player ready, but completion tracking is disabled (completionPercent=' + config.completionPercent + ')');
            }
            // You can add other listeners here (play, pause, end, etc.) if needed
            // playerInstance.on('play', function() { Log.debug('BunnyVideo: Play event fired.'); });
            // playerInstance.on('pause', function() { Log.debug('BunnyVideo: Pause event fired.'); });
            // playerInstance.on('ended', function() { Log.debug('BunnyVideo: Ended event fired.'); });

         } catch (e) {
            Log.error('BunnyVideo: Error attaching Player.js event listeners in onReady:', e);
         }
    };

     /**
      * Event listener for player errors.
      */
     var onError = function(e) {
         // Log detailed error if possible
         var errorDetails = e ? JSON.stringify(e) : 'No details';
         Log.error('BunnyVideo: Player.js error event received for cmid ' + config.cmid + '. Details: ' + errorDetails, e);
         // Maybe display a user-friendly message
         // Notification.add('Video player error occurred.', { type: 'error' });
     };


    // Public init function for the module
    return {
        init: function(cfg) {
            config = cfg; // Store config passed from PHP
            Log.info('BunnyVideo: Initializing player handler. Config received:', config);

            if (!config || !config.cmid || !config.contextid) { // Also check contextid
                 Log.error('BunnyVideo: Initialization failed - Missing configuration (cmid or contextid).');
                 return;
            }

            // Find the container div added in lib.php
            var playerContainerId = 'bunnyvideo-player-' + config.cmid;
            var playerWrapper = document.getElementById(playerContainerId);
            Log.debug('BunnyVideo: Searching for container #' + playerContainerId);

            if (!playerWrapper) {
                Log.error('BunnyVideo: Container div #' + playerContainerId + ' not found.');
                return;
            } else {
                 Log.debug('BunnyVideo: Container div found:', playerWrapper);
            }

            // Find the iframe WITHIN the container
            // This assumes the embed code pasted by the user contains exactly one iframe matching this src.
            iframeElement = playerWrapper.querySelector('iframe[src*="iframe.mediadelivery.net"]');

            if (!iframeElement) {
                Log.error('BunnyVideo: Could not find Bunny iframe within container #' + playerContainerId);
                return;
            } else {
                Log.debug('BunnyVideo: Found iframe element:', iframeElement);
            }

            // Give the iframe a unique ID if it doesn't have one, helps Player.js target it reliably.
            var iframeId = iframeElement.id || 'bunny_player_iframe_' + config.cmid;
            iframeElement.id = iframeId;
            Log.debug('BunnyVideo: Ensured iframe has ID: ' + iframeId);


            // Initialize Player.js
            try {
                 // Check if Playerjs constructor is available globally
                 if (typeof Playerjs !== 'undefined') {
                     Log.debug('BunnyVideo: Playerjs global object found. Initializing player for iframe #' + iframeId);

                     // Explicitly initialize Player.js on the found iframe
                     playerInstance = new Playerjs({id: iframeId}); // Target the specific iframe ID

                     if (playerInstance) {
                         Log.info('BunnyVideo: Player.js instance created for cmid ' + config.cmid);
                         // Attach crucial event listeners using the Player.js API
                         playerInstance.on('ready', onReady);
                         playerInstance.on('error', onError);
                         // Note: timeupdate listener is attached *inside* the onReady callback
                     } else {
                         // This case might happen if new Playerjs({id: ...}) returns null/undefined or throws implicitly
                         Log.error('BunnyVideo: Failed to create Playerjs instance for iframe #' + iframeId + ' (returned null/undefined?).');
                     }
                 } else {
                      // This is critical - if the library isn't loaded, nothing will work.
                      Log.error('BunnyVideo: Playerjs library (Playerjs global object) not found. Was the external JS loaded correctly?');
                      Notification.add('Video player library failed to load.', { type: 'error'});
                 }

            } catch (e) {
                 // Catch errors during 'new Playerjs()' or attaching initial listeners
                 Log.error('BunnyVideo: Critical error during Playerjs initialization or initial event attachment:', e);
                 Notification.add('Failed to initialize video player.', { type: 'error'});
            }
        }
    };
});
