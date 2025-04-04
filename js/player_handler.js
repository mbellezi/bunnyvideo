/**
 * Standalone JavaScript for handling Bunny Player interaction and completion.
 * Using plain JS without any module system to avoid conflicts with RequireJS.
 */

// Debug configuration - modify these values to control logging and debug UI
var BunnyVideoDebugConfig = {
    // Debug level: 0=none, 1=errors only, 2=important (errors+success), 3=all logs
    debugLevel: 1,
    
    // Show debug UI elements like the manual completion button
    showDebugUI: false
};

// Simple debug logging helper with levels - always show important messages
function bunnyVideoLog(msg, data, level) {
    level = level || 'info';
    var prefix = '';
    
    // Use emoji for different log levels
    if (level === 'debug') prefix = '🔍 ';
    if (level === 'info') prefix = 'ℹ️ ';
    if (level === 'warn') prefix = '⚠️ ';
    if (level === 'error') prefix = '❌ ';
    if (level === 'success') prefix = '✅ ';
    
    if (window.console && window.console.log) {
        var message = prefix + "BunnyVideo: " + msg;
        
        // Always show important messages regardless of level
        var isImportant = level === 'error' || level === 'success' || 
                          level === 'warn' || msg.indexOf('Complete') !== -1;
        
        // Determine if we should log based on debug level
        var shouldLog = false;
        
        // Level 0: No logging
        // Level 1: Errors only
        if (BunnyVideoDebugConfig.debugLevel >= 1 && level === 'error') {
            shouldLog = true;
        }
        // Level 2: Important logs (errors, warnings, success)
        else if (BunnyVideoDebugConfig.debugLevel >= 2 && isImportant) {
            shouldLog = true;
        }
        // Level 3: All logs
        else if (BunnyVideoDebugConfig.debugLevel >= 3) {
            shouldLog = true;
        }
        
        if (shouldLog) {
            if (data !== undefined) {
                console.log(message, data);
            } else {
                console.log(message);
            }
        }
    }
}

// Global object for our handler - no AMD, no UMD, just plain global
window.BunnyVideoHandler = {
    // State variables
    playerInstance: null,
    config: null,
    maxPercentReached: 0,
    completionSent: false,
    progressTimer: null,
    playerReady: false,
    
    // Send completion status update via AJAX
    sendCompletion: function() {
        if (this.completionSent) {
            bunnyVideoLog('Completion already sent for cmid ' + this.config.cmid, null, 'debug');
            return;
        }
        
        bunnyVideoLog('COMPLETION THRESHOLD REACHED (' + this.maxPercentReached.toFixed(1) + '% ≥ ' + 
                this.config.completionPercent + '%)', null, 'success');
        this.completionSent = true;
        
        // Use standard fetch for AJAX call
        var ajaxUrl = M.cfg.wwwroot + '/mod/bunnyvideo/ajax.php';
        
        // Use URLSearchParams for simpler serialization compatible with PHP's $_POST
        var params = new URLSearchParams();
        params.append('action', 'mark_complete');
        params.append('cmid', this.config.cmid);
        params.append('sesskey', M.cfg.sesskey);
        
        bunnyVideoLog('Sending completion request to: ' + ajaxUrl, null, 'info');
        bunnyVideoLog('With params: ' + params.toString(), null, 'debug');
        
        // Try with XMLHttpRequest which is more compatible with Moodle
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        var self = this;
        xhr.onload = function() {
            if (xhr.status === 200) {
                bunnyVideoLog('AJAX response received, status: 200', null, 'debug');
                try {
                    var data = JSON.parse(xhr.responseText);
                    bunnyVideoLog('Parsed AJAX response:', data, 'debug');
                    
                    if (data.success) {
                        if (data.already_complete) {
                            bunnyVideoLog('Activity was already marked as complete', null, 'info');
                        } else {
                            bunnyVideoLog('Activity was just marked as complete', null, 'success');
                        }
                    } else {
                        bunnyVideoLog('AJAX call failed: ' + (data.message || 'No message'), data, 'error');
                        self.completionSent = false;
                    }
                } catch (e) {
                    bunnyVideoLog('Error parsing AJAX response:', e, 'error');
                    self.completionSent = false;
                }
            } else {
                bunnyVideoLog('AJAX request failed with status: ' + xhr.status, null, 'error');
                self.completionSent = false;
            }
        };
        
        xhr.onerror = function() {
            bunnyVideoLog('AJAX network error occurred', null, 'error');
            self.completionSent = false;
        };
        
        xhr.send(params.toString());
        
        // Also show a visual indicator
        this.showCompletionIndicator();
    },
    
    // For debugging - manually trigger completion
    debugTriggerCompletion: function() {
        bunnyVideoLog('Manually triggering completion for debugging', null, 'warn');
        this.maxPercentReached = this.config.completionPercent;
        this.sendCompletion();
    },
    
    // Process timeupdate events from the player
    onTimeUpdate: function(timingData) {
        if (!this.config) {
            bunnyVideoLog('No config available', null, 'error');
            return;
        }
        
        if (this.config.completionPercent <= 0) {
            bunnyVideoLog('Completion percentage not set or zero', null, 'debug');
            return; 
        }
        
        if (this.completionSent) {
            return;
        }
        
        try {
            bunnyVideoLog('Received timeupdate event', timingData, 'debug');
            
            // Parse the data if it's a string
            var data = typeof timingData === 'string' ? JSON.parse(timingData) : timingData;
            
            // The standard Player.js library uses seconds and duration
            var currentTime = 0;
            var duration = 0;
            
            // Handle different data formats from various Player.js implementations
            if (data.seconds !== undefined && data.duration !== undefined) {
                // Standard Player.js format
                currentTime = data.seconds || 0;
                duration = data.duration || 0;
                bunnyVideoLog('Using standard Player.js data format', null, 'debug');
            } else if (data.currentTime !== undefined && data.duration !== undefined) {
                // Alternative format some players might use
                currentTime = data.currentTime || 0;
                duration = data.duration || 0;
                bunnyVideoLog('Using alternative data format with currentTime', null, 'debug');
            } else if (typeof data === 'object') {
                // Try to intelligently find time data in the object
                bunnyVideoLog('Searching for time data in object keys', Object.keys(data), 'debug');
                for (var key in data) {
                    if (key.toLowerCase().indexOf('time') !== -1 && key.toLowerCase().indexOf('current') !== -1) {
                        currentTime = parseFloat(data[key]) || 0;
                    }
                    if (key.toLowerCase().indexOf('duration') !== -1 || key.toLowerCase().indexOf('length') !== -1) {
                        duration = parseFloat(data[key]) || 0;
                    }
                }
            }
            
            // Extra safety: ensure we have valid numbers
            currentTime = parseFloat(currentTime) || 0;
            duration = parseFloat(duration) || 0;
            
            if (duration <= 0) {
                bunnyVideoLog('Invalid duration: ' + duration, null, 'debug');
                return;
            }
            
            // Calculate percentage watched
            var percentWatched = (currentTime / duration) * 100;
            var previousMax = this.maxPercentReached;
            this.maxPercentReached = Math.max(this.maxPercentReached, percentWatched);
            
            // Only log significant changes to avoid flooding the console
            if (Math.floor(this.maxPercentReached) > Math.floor(previousMax) || 
                Math.floor(percentWatched / 5) !== Math.floor(previousMax / 5)) {
                bunnyVideoLog('PROGRESS UPDATE: ' + percentWatched.toFixed(1) + '%, max: ' + 
                        this.maxPercentReached.toFixed(1) + '%, target: ' + this.config.completionPercent + '%', 
                        null, 'info');
            }
            
            // Check if completion threshold reached
            if (this.maxPercentReached >= this.config.completionPercent) {
                bunnyVideoLog('COMPLETION THRESHOLD REACHED!', null, 'success');
                this.sendCompletion();
            }
        } catch (e) {
            bunnyVideoLog('Error processing timeupdate:', e, 'error');
        }
    },
    
    // Poll for progress if timeupdate events aren't firing
    startProgressPolling: function() {
        var self = this;
        
        if (this.progressTimer) {
            clearInterval(this.progressTimer);
        }
        
        bunnyVideoLog('Starting progress polling as backup', null, 'info');
        
        this.progressTimer = setInterval(function() {
            if (!self.playerInstance || !self.playerReady || self.completionSent) return;
            
            try {
                // Try to get current time and duration via API call
                if (typeof self.playerInstance.api === 'function') {
                    var currentTime = 0;
                    var duration = 0;
                    
                    try {
                        currentTime = self.playerInstance.api('currentTime') || 0;
                        duration = self.playerInstance.api('duration') || 0;
                    } catch (e) {
                        // Some players might not support these methods
                        return;
                    }
                    
                    if (duration > 0) {
                        var percentWatched = (currentTime / duration) * 100;
                        var previousMax = self.maxPercentReached;
                        self.maxPercentReached = Math.max(self.maxPercentReached, percentWatched);
                        
                        // Log significant changes
                        if (Math.floor(self.maxPercentReached) > Math.floor(previousMax)) {
                            bunnyVideoLog('[POLL] Progress: ' + percentWatched.toFixed(1) + '%, max: ' + 
                                    self.maxPercentReached.toFixed(1) + '%, target: ' + self.config.completionPercent + '%', 
                                    null, 'info');
                        }
                        
                        // Check completion threshold
                        if (self.maxPercentReached >= self.config.completionPercent) {
                            bunnyVideoLog('[POLL] COMPLETION THRESHOLD REACHED!', null, 'success');
                            self.sendCompletion();
                        }
                    }
                }
            } catch (e) {
                bunnyVideoLog('Error in progress polling:', e, 'error');
            }
        }, 2000); // Check every 2 seconds
    },
    
    // Show a visual completion indicator
    showCompletionIndicator: function() {
        try {
            // Create a small indicator that fades out after a few seconds
            var container = document.querySelector('[id^="bunnyvideo-player-"]');
            if (!container) {
                bunnyVideoLog('Container not found for completion indicator', null, 'warn');
                return;
            }
            
            // Only show if not already shown
            if (document.getElementById('bunny-completion-indicator')) return;
            
            bunnyVideoLog('Showing completion indicator', null, 'info');
            
            var indicator = document.createElement('div');
            indicator.id = 'bunny-completion-indicator';
            indicator.style.cssText = 'position:absolute; top:10px; right:10px; background-color:rgba(0,128,0,0.8); color:white; padding:8px 12px; border-radius:4px; font-size:14px; z-index:1000; transition:opacity 0.5s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
            indicator.innerHTML = '✓ ' + (M.str.completion ? M.str.completion.completion_y : 'Atividade Completada');
            
            container.style.position = 'relative';
            container.appendChild(indicator);
            
            // Fade out after 5 seconds
            setTimeout(function() {
                indicator.style.opacity = '0';
                // Remove after fade out
                setTimeout(function() {
                    if (indicator.parentNode) {
                        indicator.parentNode.removeChild(indicator);
                    }
                }, 500);
            }, 5000);
        } catch (e) {
            bunnyVideoLog('Error showing completion indicator:', e, 'error');
        }
    },
    
    // Initialize player and set up event handlers
    initializePlayer: function() {
        var self = this;
        
        // Log all parameters to help with debugging
        bunnyVideoLog('Initializing player with config:', this.config, 'info');
        
        if (!this.config || !this.config.cmid) {
            bunnyVideoLog('Invalid configuration', null, 'error');
            return;
        }
        
        // Try both potential container IDs
        var containerIds = [
            'bunnyvideo-player-' + this.config.bunnyvideoid,  // Try bunnyvideo ID first (from lib.php)
            'bunnyvideo-player-' + this.config.cmid           // Try course module ID as fallback
        ];
        
        bunnyVideoLog('Looking for containers with IDs:', containerIds, 'debug');
        
        var container = null;
        for (var i = 0; i < containerIds.length; i++) {
            var id = containerIds[i];
            bunnyVideoLog('Looking for container with ID: ' + id, null, 'debug');
            container = document.getElementById(id);
            if (container) {
                bunnyVideoLog('Found container: ' + id, null, 'info');
                break;
            }
        }
        
        if (!container) {
            bunnyVideoLog('Player container not found with any of these IDs: ' + containerIds.join(', '), null, 'error');
            return;
        }
        
        // Find the iframe
        var iframe = container.querySelector('iframe');
        if (!iframe) {
            bunnyVideoLog('No iframe found in container', null, 'error');
            return;
        }
        
        bunnyVideoLog('Using iframe with id: ' + iframe.id, null, 'info');
        
        // Check for any variant of Player.js library
        this.ensurePlayerJsLibraryLoaded(function() {
            self.initializePlayerInstance(iframe, container);
        });
    },
    
    // Make sure the Player.js library is loaded and ready to use
    ensurePlayerJsLibraryLoaded: function(callback) {
        var attempts = 0;
        var maxAttempts = 10;
        var self = this;
        
        function checkForPlayerJs() {
            attempts++;
            
            // Check for all potential global variables
            var playerJsExists = (
                typeof playerjs !== 'undefined' || 
                typeof PlayerJS !== 'undefined' || 
                typeof window.playerjs !== 'undefined' || 
                typeof window.PlayerJS !== 'undefined'
            );
            
            if (playerJsExists) {
                bunnyVideoLog('Player.js library found on attempt ' + attempts, null, 'success');
                callback();
                return;
            }
            
            if (attempts >= maxAttempts) {
                bunnyVideoLog('Player.js library not found after ' + maxAttempts + ' attempts, using fallback approach', null, 'warn');
                callback(); // Continue anyway, we'll use fallback in initialization
                return;
            }
            
            bunnyVideoLog('Waiting for Player.js library (attempt ' + attempts + '/' + maxAttempts + ')', null, 'debug');
            setTimeout(checkForPlayerJs, 200);
        }
        
        checkForPlayerJs();
    },
    
    // Initialize the player instance once we've verified library loading
    initializePlayerInstance: function(iframe, container) {
        // From examining the Bunny-specific library, we can see the global object is 'playerjs'
        // Try all known variants
        if (typeof playerjs !== 'undefined') {
            bunnyVideoLog('Found Bunny-specific playerjs library', null, 'success');
            
            // Check which constructor pattern is available
            if (typeof playerjs.Player === 'function') {
                bunnyVideoLog('Using playerjs.Player constructor', null, 'success');
                this.playerInstance = new playerjs.Player(iframe);
            } else if (typeof playerjs === 'function') {
                bunnyVideoLog('Using playerjs constructor directly', null, 'success');
                this.playerInstance = new playerjs(iframe);
            } else {
                bunnyVideoLog('Unknown playerjs library structure', playerjs, 'warn');
                this.setupPostMessagePlayer(iframe);
                return;
            }
        } else if (typeof PlayerJS !== 'undefined') {
            bunnyVideoLog('Found PlayerJS constructor', null, 'success');
            this.playerInstance = new PlayerJS(iframe);
        } else if (typeof window.playerjs !== 'undefined') {
            bunnyVideoLog('Found window.playerjs', null, 'success');
            
            if (typeof window.playerjs.Player === 'function') {
                this.playerInstance = new window.playerjs.Player(iframe);
            } else {
                this.playerInstance = new window.playerjs(iframe);
            }
        } else if (typeof window.PlayerJS !== 'undefined') {
            bunnyVideoLog('Found window.PlayerJS constructor', null, 'success');
            this.playerInstance = new window.PlayerJS(iframe);
        } else {
            bunnyVideoLog('Player.js library not found! Looking for alternatives...', null, 'warn');
            
            // Log all window properties containing "player" for debugging
            var playerVars = Object.keys(window).filter(function(key) {
                return key.toLowerCase().indexOf('player') !== -1; 
            });
            
            bunnyVideoLog('Available global variables containing "player":', playerVars, 'debug');
            
            // Try a different approach with the latest CDN method
            bunnyVideoLog('Attempting to use direct iframe control with postMessage API', null, 'info');
            this.setupPostMessagePlayer(iframe);
            return;
        }
        
        if (!this.playerInstance) {
            bunnyVideoLog('Failed to create player instance, using fallback', null, 'error');
            this.setupPostMessagePlayer(iframe);
            return;
        }
        
        bunnyVideoLog('Player instance created successfully, setting up event listeners', null, 'success');
        this.setupPlayerEvents();
        
        // Add debug button for testing
        this.addDebugButton(container);
    },
    
    // Set up player events once we have a valid player instance
    setupPlayerEvents: function() {
        var self = this;
        
        // When the player is ready
        this.playerInstance.on('ready', function() {
            bunnyVideoLog('Player ready event received', null, 'success');
            self.playerReady = true;
            
            // Setup various event listeners
            self.playerInstance.on('timeupdate', function(data) {
                self.onTimeUpdate(data);
            });
            
            self.playerInstance.on('play', function() {
                bunnyVideoLog('Player play event', null, 'debug');
            });
            
            self.playerInstance.on('pause', function() {
                bunnyVideoLog('Player pause event', null, 'debug');
            });
            
            self.playerInstance.on('ended', function() {
                bunnyVideoLog('Player ended event - setting 100% watched', null, 'success');
                self.maxPercentReached = 100;
                if (self.config.completionPercent > 0) {
                    self.sendCompletion();
                }
            });
            
            // Start polling as a backup
            self.startProgressPolling();
        });
        
        this.playerInstance.on('error', function(error) {
            bunnyVideoLog('Player error:', error, 'error');
        });
    },
    
    // Add a debug button for manually triggering completion (only in dev/test)
    addDebugButton: function(container) {
        try {
            // Only add debug button if debug UI is enabled
            if (!BunnyVideoDebugConfig.showDebugUI) {
                return;
            }
            
            var self = this;
            var debugButton = document.createElement('button');
            debugButton.textContent = 'DEBUG: Mark Complete';
            debugButton.style.cssText = 'position:absolute; bottom:10px; right:10px; background:#f44336; color:white; border:none; padding:5px 10px; cursor:pointer; z-index:1000; border-radius:4px; font-size:12px;';
            debugButton.onclick = function() {
                self.debugTriggerCompletion();
            };
            
            container.style.position = 'relative';
            container.appendChild(debugButton);
        } catch (e) {
            // Silently fail - just a debug tool
        }
    },
    
    // Setup player using the detected library
    setupPlayerWithLibrary: function(libraryType, iframe) {
        var self = this;
        try {
            bunnyVideoLog('Setting up player with ' + libraryType, null, 'info');
            
            // Try to create a player instance with the detected library
            if (libraryType === 'PlayerJS') {
                bunnyVideoLog('Creating a new PlayerJS instance', null, 'debug');
                this.playerInstance = new PlayerJS(iframe);
            } else if (libraryType === 'playerjs') {
                bunnyVideoLog('Creating a new playerjs.Player instance', null, 'debug');
                this.playerInstance = new playerjs.Player(iframe);
            } else if (libraryType === 'window.PlayerJS') {
                bunnyVideoLog('Creating a new window.PlayerJS instance', null, 'debug');
                this.playerInstance = new window.PlayerJS(iframe);
            } else if (libraryType === 'window.playerjs') {
                bunnyVideoLog('Creating a new window.playerjs.Player instance', null, 'debug');
                this.playerInstance = new window.playerjs.Player(iframe);
            }
            
            if (!this.playerInstance) {
                bunnyVideoLog('Failed to create player instance', null, 'error');
                this.setupPostMessagePlayer(iframe);
                return;
            }
            
            bunnyVideoLog('Player instance created successfully, setting up event listeners', null, 'success');
            
            // When the player is ready
            this.playerInstance.on('ready', function() {
                bunnyVideoLog('Player ready event received', null, 'success');
                self.playerReady = true;
                
                // Setup various event listeners
                self.playerInstance.on('timeupdate', function(data) {
                    self.onTimeUpdate(data);
                });
                
                self.playerInstance.on('play', function() {
                    bunnyVideoLog('Player play event', null, 'debug');
                });
                
                self.playerInstance.on('pause', function() {
                    bunnyVideoLog('Player pause event', null, 'debug');
                });
                
                self.playerInstance.on('ended', function() {
                    bunnyVideoLog('Player ended event - setting 100% watched', null, 'success');
                    self.maxPercentReached = 100;
                    if (self.config.completionPercent > 0) {
                        self.sendCompletion();
                    }
                });
                
                // Start polling as a backup
                self.startProgressPolling();
            });
            
            this.playerInstance.on('error', function(error) {
                bunnyVideoLog('Player error:', error, 'error');
            });
            
        } catch (e) {
            bunnyVideoLog('Error setting up player:', e, 'error');
            
            // Try fallback
            this.setupPostMessagePlayer(iframe);
        }
    },
    
    // Fallback to using postMessage API directly with iframe
    setupPostMessagePlayer: function(iframe) {
        var self = this;
        bunnyVideoLog('Using fallback postMessage approach', null, 'info');
        
        // Create a simple custom player wrapper
        var customPlayer = {
            ready: false,
            
            sendMessage: function(method, value) {
                var msg = {
                    context: 'player.js',
                    version: '1.0',
                    event: method
                };
                
                if (value !== undefined) {
                    msg.value = value;
                }
                
                iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
            },
            
            on: function(event, callback) {
                if (event === 'ready' && this.ready) {
                    setTimeout(callback, 0);
                    return;
                }
                
                window.addEventListener('message', function(e) {
                    try {
                        var data = JSON.parse(e.data);
                        if (data.event === event) {
                            if (event === 'ready') {
                                customPlayer.ready = true;
                            }
                            callback(data.value);
                        }
                    } catch (err) {
                        // Not a valid JSON message or not from our player
                    }
                });
                
                // Listen for all events from iframe
                this.sendMessage('addEventListener', event);
            },
            
            api: function(method, value) {
                this.sendMessage(method, value);
            }
        };
        
        this.playerInstance = customPlayer;
        self.playerReady = false;
        
        // Set up event handlers similar to standard Player.js
        customPlayer.on('ready', function() {
            bunnyVideoLog('Custom player ready event received', null, 'success');
            self.playerReady = true;
            
            // Listen for timeupdate events
            customPlayer.on('timeupdate', function(data) {
                self.onTimeUpdate(data);
            });
            
            // Other events
            customPlayer.on('play', function() {
                bunnyVideoLog('Custom player play event', null, 'debug');
            });
            
            customPlayer.on('pause', function() {
                bunnyVideoLog('Custom player pause event', null, 'debug');
            });
            
            customPlayer.on('ended', function() {
                bunnyVideoLog('Custom player ended event - setting 100% watched', null, 'success');
                self.maxPercentReached = 100;
                if (self.config.completionPercent > 0) {
                    self.sendCompletion();
                }
            });
            
            // Start polling as a backup
            self.startProgressPolling();
        });
        
        customPlayer.on('error', function(error) {
            bunnyVideoLog('Custom player error:', error, 'error');
        });
    },
    
    // Main initialization function called from PHP
    init: function(cfg) {
        this.config = cfg;
        bunnyVideoLog('Initializing BunnyVideoHandler with config:', cfg, 'info');
        
        if (!this.config || !this.config.cmid) {
            bunnyVideoLog('Invalid configuration', null, 'error');
            return;
        }
        
        var self = this;
        // Initialize player after DOM is ready
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(function() { self.initializePlayer(); }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() { self.initializePlayer(); }, 100);
            });
        }
    }
};

// Also define this for backward compatibility
window.BunnyVideoInit = function(config) {
    bunnyVideoLog('BunnyVideoInit called, delegating to BunnyVideoHandler', null, 'info');
    window.BunnyVideoHandler.init(config);
};
