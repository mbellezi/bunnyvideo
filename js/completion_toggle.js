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
            let newstate = this.getAttribute('data-newstate');
            
            // Ensure newstate is explicitly set to 0 for marking as incomplete
            if (newstate === '0') {
                newstate = 0;
            } else {
                newstate = 1;
            }
            
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
    // Assegurar que os valores sejam numéricos e não strings
    const cmidInt = parseInt(cmid, 10);
    const useridInt = parseInt(userid, 10);
    const newstateInt = parseInt(newstate, 10);
    
    console.log('BunnyVideo - Toggle Completion - cmid:', cmidInt, 'userid:', useridInt, 'newstate:', newstateInt);
    
    // Prepare the request
    const request = {
        methodname: 'mod_bunnyvideo_toggle_completion',
        args: {
            cmid: cmidInt,
            userid: useridInt,
            newstate: newstateInt
        }
    };
    
    // Use Moodle's AJAX framework
    require(['core/ajax'], function(ajax) {
        ajax.call([request])[0].done(function(response) {
            console.log('BunnyVideo - Toggle Response:', response);
            if (response.success) {
                // Success - reload the page with cache-busting parameter to ensure menu updates
                console.log('BunnyVideo - Operação bem-sucedida, recarregando página');
                
                // Forçar recarga completa da página para atualizar o menu lateral
                var reloadUrl = window.location.href;
                
                // Adicionar ou atualizar parâmetro para evitar cache
                // Criamos um parâmetro único que sinaliza ao Moodle que deve recarregar os ícones de navegação
                var timestamp = new Date().getTime();
                if (reloadUrl.indexOf('?') > -1) {
                    // Remover qualquer parâmetro completion_updated anterior
                    reloadUrl = reloadUrl.replace(/[&?]completion_updated=[^&]*/, '');
                    // Adicionar novo parâmetro
                    reloadUrl += '&completion_updated=' + timestamp + '&forcenavrefresh=1';
                } else {
                    reloadUrl += '?completion_updated=' + timestamp + '&forcenavrefresh=1';
                }
                
                // Forçar atualização dos ícones de navegação antes da recarga
                try {
                    if (typeof M !== 'undefined' && M.core && M.core.refresh_completion_icons) {
                        console.log('BunnyVideo - Tentando atualizar ícones via API');
                        M.core.refresh_completion_icons();
                    }
                } catch(e) {
                    console.error('BunnyVideo - Erro ao atualizar ícones:', e);
                }
                
                // Recarregar a página com novos parâmetros
                window.location.href = reloadUrl;
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
