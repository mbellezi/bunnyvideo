/**
 * Módulo AMD para lidar com a interação e conclusão do Bunny Player.
 * VERSÃO COM LOGGING EXTRA
 */
define(['jquery', 'core/ajax', 'core/log', 'core/notification'], function($, Ajax, Log, Notification) {

    // Inicializa o módulo de log para depuração
    Log.init({ level: 'debug' }); // Garante que o nível de depuração esteja definido

    Log.debug('BunnyVideo: Módulo AMD player_handler.js carregado.');

    var playerInstance = null; // Armazena a instância do Player.js
    var config = null; // Armazena a configuração passada pelo PHP
    var maxPercentReached = 0; // Rastreia a maior porcentagem assistida
    var completionSent = false; // Flag para prevenir múltiplas chamadas AJAX
    var iframeElement = null; // Referência ao elemento DOM do iframe

    /**
     * Envia a atualização do status de conclusão via AJAX.
     */
    var sendCompletion = function() {
        Log.debug('BunnyVideo: sendCompletion chamado. completionSent = ' + completionSent);
        if (completionSent) {
            Log.debug('BunnyVideo: Conclusão já enviada para cmid ' + config.cmid + '. Abortando.');
            return; // Não envia novamente
        }

        Log.info('BunnyVideo: Limite atingido (' + maxPercentReached.toFixed(2) + '% >= ' + config.completionPercent + '%). Enviando AJAX de conclusão para cmid ' + config.cmid);
        completionSent = true; // Define a flag imediatamente

        var promises = Ajax.call([{
            methodname: 'mod_bunnyvideo_mark_complete',
            args: { cmid: config.cmid },
            done: function(response) {
                Log.debug('BunnyVideo: Callback AJAX done recebido.', response);
                if (response.success) {
                    Log.info('BunnyVideo: Conclusão marcada com sucesso via AJAX para cmid ' + config.cmid);
                    // Opcionalmente, atualiza a UI ou dispara evento de conclusão JS do Moodle
                } else {
                    Log.warn('BunnyVideo: Chamada AJAX reportou falha para cmid ' + config.cmid + ': ' + (response.message || 'Sem mensagem'));
                    Notification.add(response.message || 'Erro ao marcar atividade como concluída.', { type: 'error' });
                    completionSent = false; // Permitir nova tentativa em caso de erro? Arriscado.
                }
            },
            fail: function(ex) {
                Log.error('BunnyVideo: Chamada AJAX falhou para cmid ' + config.cmid, ex);
                Notification.exception(ex);
                completionSent = false; // Permitir nova tentativa em caso de erro? Arriscado.
            }
        }]);

        // Lida com possíveis erros de promise
        if (promises && promises[0] && typeof promises[0].catch === 'function') {
            promises[0].catch(function(ex) {
                 Log.error('BunnyVideo: Promise AJAX falhou para cmid ' + config.cmid, ex);
                 Notification.exception(ex);
                 completionSent = false; // Permitir nova tentativa
            });
        } else {
             Log.debug('BunnyVideo: Ajax.call não retornou uma promise ou array de promises.');
        }
    };

    /**
     * Listener de evento para atualizações de tempo do player.
     */
    var onTimeUpdate = function() {
        // Registrar com menos frequência para evitar inundar o console? Talvez apenas a cada poucos segundos?
        // Por enquanto, registra todas as vezes para depuração.
        // Log.debug('BunnyVideo: onTimeUpdate disparado.');

        if (!playerInstance || !config || config.completionPercent <= 0) {
            // Log.debug('BunnyVideo: onTimeUpdate - Abortando (sem player, sem config ou % de conclusão é 0)');
            return;
        }
        if (completionSent) {
             // Log.debug('BunnyVideo: onTimeUpdate - Abortando (conclusão já enviada)');
             return;
        }


        try {
            // Usa um valor padrão de 0 se a API retornar NaN ou undefined
            var currentTime = playerInstance.api('currentTime') || 0;
            var duration = playerInstance.api('duration') || 0;

            // Log.debug('BunnyVideo: timeupdate - currentTime: ' + currentTime + ', duration: ' + duration);

            // O player pode não estar pronto ou a duração pode ser desconhecida (transmissões ao vivo?)
            if (duration <= 0) {
                // Log.debug('BunnyVideo: onTimeUpdate - Abortando (duração <= 0)');
                return;
            }

            var currentPercent = (currentTime / duration) * 100;

            if (currentPercent > maxPercentReached) {
                maxPercentReached = currentPercent;
                Log.debug('BunnyVideo: Porcentagem máxima assistida atualizada: ' + maxPercentReached.toFixed(2) + '% para cmid ' + config.cmid);
            }

            if (maxPercentReached >= config.completionPercent) {
                Log.debug('BunnyVideo: onTimeUpdate - Limite atingido. Chamando sendCompletion.');
                sendCompletion();
            }
        } catch (e) {
            // Captura erros se as chamadas da API Player.js falharem (ex: player destruído)
             Log.warn('BunnyVideo: Erro ao acessar a API Player.js durante timeupdate:', e);
             // Desanexar listener se o player parecer quebrado?
             if (playerInstance && typeof playerInstance.off === 'function') {
                 Log.warn('BunnyVideo: Desanexando listener timeupdate devido a erro.');
                 playerInstance.off('timeupdate', onTimeUpdate);
             }
        }
    };

    /**
      * Listener de evento para quando o player está pronto.
      */
    var onReady = function() {
         Log.info('BunnyVideo: Evento ready do Player.js recebido para cmid ' + config.cmid);
         // Anexa listeners de evento apenas quando pronto
         try {
            if (playerInstance && config.completionPercent > 0) {
                 // Verifica se os métodos da API estão disponíveis
                 if (typeof playerInstance.api('currentTime') !== 'undefined' && typeof playerInstance.api('duration') !== 'undefined') {
                     Log.debug('BunnyVideo: Player pronto - Anexando listener timeupdate para cmid ' + config.cmid);
                     playerInstance.on('timeupdate', onTimeUpdate);
                 } else {
                      Log.warn('BunnyVideo: Player pronto, mas métodos da API de tempo/duração parecem indisponíveis. Não é possível rastrear o progresso.');
                 }
            } else {
                 Log.debug('BunnyVideo: Player pronto, mas rastreamento de conclusão está desabilitado (completionPercent=' + config.completionPercent + ')');
            }
            // Você pode adicionar outros listeners aqui (play, pause, end, etc.) se necessário
            // playerInstance.on('play', function() { Log.debug('BunnyVideo: Evento play disparado.'); });
            // playerInstance.on('pause', function() { Log.debug('BunnyVideo: Evento pause disparado.'); });
            // playerInstance.on('ended', function() { Log.debug('BunnyVideo: Evento ended disparado.'); });

         } catch (e) {
            Log.error('BunnyVideo: Erro ao anexar listeners de evento Player.js em onReady:', e);
         }
    };

     /**
      * Listener de evento para erros do player.
      */
     var onError = function(e) {
         // Registra erro detalhado se possível
         var errorDetails = e ? JSON.stringify(e) : 'Sem detalhes';
         Log.error('BunnyVideo: Evento de erro Player.js recebido para cmid ' + config.cmid + '. Detalhes: ' + errorDetails, e);
         // Talvez exibir uma mensagem amigável ao usuário
         // Notification.add('Ocorreu um erro no player de vídeo.', { type: 'error' });
     };


    // Função init pública para o módulo
    return {
        init: function(cfg) {
            config = cfg; // Armazena a configuração passada pelo PHP
            Log.info('BunnyVideo: Inicializando manipulador do player. Configuração recebida:', config);

            if (!config || !config.cmid || !config.contextid) { // Verifica também contextid
                 Log.error('BunnyVideo: Falha na inicialização - Configuração ausente (cmid ou contextid).');
                 return;
            }

            // Encontra a div container adicionada em lib.php
            var playerContainerId = 'bunnyvideo-player-' + config.cmid;
            var playerWrapper = document.getElementById(playerContainerId);
            Log.debug('BunnyVideo: Procurando por container #' + playerContainerId);

            if (!playerWrapper) {
                Log.error('BunnyVideo: Div container #' + playerContainerId + ' não encontrada.');
                return;
            } else {
                 Log.debug('BunnyVideo: Div container encontrada:', playerWrapper);
            }

            // Encontra o iframe DENTRO do container
            // Isso assume que o código de incorporação colado pelo usuário contém exatamente um iframe correspondente a este src.
            iframeElement = playerWrapper.querySelector('iframe[src*="iframe.mediadelivery.net"]');

            if (!iframeElement) {
                Log.error('BunnyVideo: Não foi possível encontrar o iframe Bunny dentro do container #' + playerContainerId);
                return;
            } else {
                Log.debug('BunnyVideo: Elemento iframe encontrado:', iframeElement);
            }

            // Dá ao iframe um ID único se ele não tiver um, ajuda o Player.js a direcioná-lo de forma confiável.
            var iframeId = iframeElement.id || 'bunny_player_iframe_' + config.cmid;
            iframeElement.id = iframeId;
            Log.debug('BunnyVideo: Garantiu que o iframe tenha o ID: ' + iframeId);


            // Inicializa o Player.js
            try {
                 // Verifica se o construtor Playerjs está disponível globalmente
                 if (typeof Playerjs !== 'undefined') {
                     Log.debug('BunnyVideo: Objeto global Playerjs encontrado. Inicializando player para iframe #' + iframeId);

                     // Inicializa explicitamente o Player.js no iframe encontrado
                     playerInstance = new Playerjs({id: iframeId}); // Direciona o ID específico do iframe

                     if (playerInstance) {
                         Log.info('BunnyVideo: Instância Player.js criada para cmid ' + config.cmid);
                         // Anexa listeners de evento cruciais usando a API Player.js
                         playerInstance.on('ready', onReady);
                         playerInstance.on('error', onError);
                         // Nota: o listener timeupdate é anexado *dentro* do callback onReady
                     } else {
                         // Este caso pode acontecer se new Playerjs({id: ...}) retornar null/undefined ou lançar implicitamente
                         Log.error('BunnyVideo: Falha ao criar instância Playerjs para iframe #' + iframeId + ' (retornou null/undefined?).');
                     }
                 } else {
                      // Isso é crítico - se a biblioteca não for carregada, nada funcionará.
                      Log.error('BunnyVideo: Biblioteca Playerjs (objeto global Playerjs) não encontrada. O JS externo foi carregado corretamente?');
                      Notification.add('Falha ao carregar a biblioteca do player de vídeo.', { type: 'error'});
                 }

            } catch (e) {
                 // Captura erros durante 'new Playerjs()' ou anexação de listeners iniciais
                 Log.error('BunnyVideo: Erro crítico durante a inicialização do Playerjs ou anexação inicial de evento:', e);
                 Notification.add('Falha ao inicializar o player de vídeo.', { type: 'error'});
            }
        }
    };
});
