// Bunnyvideo completion toggle handler
// This script handles the completion toggle button functionality for teachers/admins

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the completion toggle buttons
    initCompletionToggle();
});

/**
 * Initialize the completion toggle functionality
 */
function initCompletionToggle() {
    // Find all completion toggle buttons
    const toggleButtons = document.querySelectorAll('.completion-toggle-button');
    
    // Add click handler to each button
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get data attributes
            const cmid = this.getAttribute('data-cmid');
            const userid = this.getAttribute('data-userid');
            const newstate = this.getAttribute('data-newstate');
            
            // Disable button and show loading state
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = '...';
            
            // Call the AJAX function to toggle completion
            toggleCompletion(cmid, userid, newstate, this, originalText);
        });
    });
}

/**
 * Call the web service to toggle completion status
 * @param {number} cmid - Course module ID
 * @param {number} userid - User ID
 * @param {number} newstate - New completion state (0 or 1)
 * @param {HTMLElement} button - The button element
 * @param {string} originalText - Original button text
 */
function toggleCompletion(cmid, userid, newstate, button, originalText) {
    // Prepare the request
    const request = {
        methodname: 'mod_bunnyvideo_toggle_completion',
        args: {
            cmid: parseInt(cmid),
            userid: parseInt(userid),
            newstate: parseInt(newstate)
        }
    };
    
    // Use Moodle's AJAX framework
    require(['core/ajax'], function(ajax) {
        ajax.call([request])[0].done(function(response) {
            if (response.success) {
                // Success - reload the page to show updated status
                window.location.reload();
            } else {
                // Error - restore button and show error
                button.disabled = false;
                button.textContent = originalText;
                
                // Show error notification if available
                if (require.defined('core/notification')) {
                    require(['core/notification'], function(notification) {
                        notification.addNotification({
                            message: response.message || 'Error toggling completion status',
                            type: 'error'
                        });
                    });
                } else {
                    // Fallback to alert if notification module not available
                    alert(response.message || 'Error toggling completion status');
                }
            }
        }).fail(function(error) {
            // Network or other failure
            button.disabled = false;
            button.textContent = originalText;
            
            // Show error notification if available
            if (require.defined('core/notification')) {
                require(['core/notification'], function(notification) {
                    notification.addNotification({
                        message: 'Network error: ' + error.message,
                        type: 'error'
                    });
                });
            } else {
                // Fallback to alert if notification module not available
                alert('Network error: ' + error.message);
            }
        });
    });
}
