// Manipulador de alternância de conclusão do Bunnyvideo
// Este script lida com a funcionalidade do botão de alternância de conclusão para professores/admins

document.addEventListener('DOMContentLoaded', function() {
    // Inicializa os botões de alternância de conclusão
    initCompletionToggle();
});

/**
 * Inicializa a funcionalidade de alternância de conclusão
 */
function initCompletionToggle() {
    // Encontra todos os botões de alternância de conclusão
    const toggleButtons = document.querySelectorAll('.completion-toggle-button');
    
    // Adiciona manipulador de clique a cada botão
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Obtém atributos de dados
            const cmid = this.getAttribute('data-cmid');
            const userid = this.getAttribute('data-userid');
            let newstate = this.getAttribute('data-newstate');
            
            // Garante que newstate seja explicitamente definido como 0 para marcar como incompleto
            if (newstate === '0') {
                newstate = 0;
            } else {
                newstate = 1;
            }
            
            // Desabilita o botão e mostra o estado de carregamento
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = '...';
            
            // Chama a função AJAX para alternar a conclusão
            toggleCompletion(cmid, userid, newstate, this, originalText);
        });
    });
}

/**
 * Chama o serviço web para alternar o status de conclusão
 * @param {number} cmid - ID do módulo do curso
 * @param {number} userid - ID do usuário
 * @param {number} newstate - Novo estado de conclusão (0 ou 1)
 * @param {HTMLElement} button - O elemento botão
 * @param {string} originalText - Texto original do botão
 */
function toggleCompletion(cmid, userid, newstate, button, originalText) {
    // Assegurar que os valores sejam numéricos e não strings
    const cmidInt = parseInt(cmid, 10);
    const useridInt = parseInt(userid, 10);
    const newstateInt = parseInt(newstate, 10);
    
    console.log('BunnyVideo - Alternar Conclusão - cmid:', cmidInt, 'userid:', useridInt, 'newstate:', newstateInt);
    
    // Prepara a requisição
    const request = {
        methodname: 'mod_bunnyvideo_toggle_completion',
        args: {
            cmid: cmidInt,
            userid: useridInt,
            newstate: newstateInt
        }
    };
    
    // Usa o framework AJAX do Moodle
    require(['core/ajax'], function(ajax) {
        ajax.call([request])[0].done(function(response) {
            console.log('BunnyVideo - Resposta da Alternância:', response);
            if (response.success) {
                // Sucesso - recarrega a página com parâmetro de cache-busting para garantir atualizações do menu
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
                // Erro - restaura o botão e mostra o erro
                button.disabled = false;
                button.textContent = originalText;
                
                // Mostra notificação de erro se disponível
                if (require.defined('core/notification')) {
                    require(['core/notification'], function(notification) {
                        notification.addNotification({
                            message: response.message || 'Erro ao alternar o status de conclusão',
                            type: 'error'
                        });
                    });
                } else {
                    // Fallback para alert se o módulo de notificação não estiver disponível
                    alert(response.message || 'Erro ao alternar o status de conclusão');
                }
            }
        }).fail(function(error) {
            // Falha de rede ou outra falha
            button.disabled = false;
            button.textContent = originalText;
            
            // Mostra notificação de erro se disponível
            if (require.defined('core/notification')) {
                require(['core/notification'], function(notification) {
                    notification.addNotification({
                        message: 'Erro de rede: ' + error.message,
                        type: 'error'
                    });
                });
            } else {
                // Fallback para alert se o módulo de notificação não estiver disponível
                alert('Erro de rede: ' + error.message);
            }
        });
    });
}
