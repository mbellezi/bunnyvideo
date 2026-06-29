/**
 * JavaScript independente para lidar com a interação e conclusão do Bunny Player.
 * Usando JS puro sem nenhum sistema de módulos para evitar conflitos com o RequireJS.
 */

// Configuração de depuração - modifique esses valores para controlar o registro e a UI de depuração
var BunnyVideoDebugConfig = {
    // Nível de depuração: 0=nenhum, 1=apenas erros, 2=importante (erros+sucesso), 3=todos os logs
    debugLevel: 1,
    
    // Mostrar elementos da UI de depuração, como o botão de conclusão manual
    showDebugUI: false,
    
    // Resolução para rastreamento de tempo (em segundos) - valores menores são mais precisos, mas consomem mais memória
    timeTrackingResolution: 1,

    // Mantém o código de autosave periódico disponível, mas desligado para reduzir carga.
    enablePeriodicPositionSaving: false,
    positionSaveIntervalMs: 60000
};

// Auxiliar de registro de depuração simples com níveis - sempre mostra mensagens importantes
function bunnyVideoLog(msg, data, level) {
    level = level || 'info';
    var prefix = '';
    
    // Usa emoji para diferentes níveis de log
    if (level === 'debug') prefix = '🔍 ';
    if (level === 'info') prefix = 'ℹ️ ';
    if (level === 'warn') prefix = '⚠️ ';
    if (level === 'error') prefix = '❌ ';
    if (level === 'success') prefix = '✅ ';
    
    if (window.console && window.console.log) {
        var message = prefix + "BunnyVideo: " + msg;
        
        // Sempre mostra mensagens importantes, independentemente do nível
        var isImportant = level === 'error' || level === 'success' || 
                          level === 'warn' || msg.indexOf('Complete') !== -1;
        
        // Determina se devemos registrar com base no nível de depuração
        var shouldLog = false;
        
        // Nível 0: Sem registro
        // Nível 1: Apenas erros
        if (BunnyVideoDebugConfig.debugLevel >= 1 && level === 'error') {
            shouldLog = true;
        }
        // Nível 2: Logs importantes (erros, avisos, sucesso)
        else if (BunnyVideoDebugConfig.debugLevel >= 2 && isImportant) {
            shouldLog = true;
        }
        // Nível 3: Todos os logs
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

// Objeto global para nosso manipulador - sem AMD, sem UMD, apenas global puro
window.BunnyVideoHandler = {
    // Variáveis de estado
    playerInstance: null,
    config: null,
    maxPercentReached: 0,
    completionSent: false,
    progressTimer: null,
    positionSaveTimer: null,
    playerReady: false,
    currentPosition: 0,
    currentDuration: 0,
    lastSavedPosition: null,
    resumeAttempted: false,
    positionEventsAttached: false,
    
    // Variáveis de rastreamento de tempo
    watchedSegments: [], // Array de intervalos de tempo assistidos [início, fim]
    lastPosition: null,  // Última posição para rastrear a visualização contínua
    lastUpdateTime: null, // Timestamp da última atualização para lidar com abas inativas
    actualTimeWatched: 0, // Total de segundos realmente assistidos (contabilizando saltos)
    
    // Envia atualização do status de conclusão via AJAX
    sendCompletion: function() {
        if (this.completionSent) {
            bunnyVideoLog('Conclusão já enviada para cmid ' + this.config.cmid, null, 'debug');
            return;
        }
        
        bunnyVideoLog('LIMITE DE CONCLUSÃO ATINGIDO (' + this.maxPercentReached.toFixed(1) + '% ≥ ' + 
                this.config.completionPercent + '%)', null, 'success');
        this.completionSent = true;
        this.savePlaybackPosition(false);
        
        // Usa fetch padrão para chamada AJAX
        var ajaxUrl = M.cfg.wwwroot + '/mod/bunnyvideo/ajax.php';
        
        // Usa URLSearchParams para serialização mais simples compatível com $_POST do PHP
        var params = new URLSearchParams();
        params.append('action', 'mark_complete');
        params.append('cmid', this.config.cmid);
        params.append('sesskey', M.cfg.sesskey);
        
        bunnyVideoLog('Enviando requisição de conclusão para: ' + ajaxUrl, null, 'info');
        bunnyVideoLog('Com parâmetros: ' + params.toString(), null, 'debug');
        
        // Tenta com XMLHttpRequest que é mais compatível com o Moodle
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        var self = this;
        xhr.onload = function() {
            if (xhr.status === 200) {
                bunnyVideoLog('Resposta AJAX recebida, status: 200', null, 'debug');
                try {
                    var data = JSON.parse(xhr.responseText);
                    bunnyVideoLog('Resposta AJAX parseada:', data, 'debug');
                    
                    if (data.success) {
                        if (data.already_complete) {
                            bunnyVideoLog('Atividade já estava marcada como concluída', null, 'info');
                        } else {
                            bunnyVideoLog('Atividade acabou de ser marcada como concluída', null, 'success');
                        }
                    } else {
                        bunnyVideoLog('Chamada AJAX falhou: ' + (data.message || 'Sem mensagem'), data, 'error');
                        self.completionSent = false;
                    }
                } catch (e) {
                    bunnyVideoLog('Erro ao parsear resposta AJAX:', e, 'error');
                    self.completionSent = false;
                }
            } else {
                bunnyVideoLog('Requisição AJAX falhou com status: ' + xhr.status, null, 'error');
                self.completionSent = false;
            }
        };
        
        xhr.onerror = function() {
            bunnyVideoLog('Erro de rede AJAX ocorrido', null, 'error');
            self.completionSent = false;
        };
        
        xhr.send(params.toString());
        
        // Também mostra um indicador visual
        this.showCompletionIndicator();
    },

    // Extrai posição e duração dos formatos emitidos pelo Bunny Player.
    extractTimingData: function(timingData) {
        if (typeof timingData === 'string') {
            timingData = JSON.parse(timingData);
        }

        var currentTime;
        var duration;

        if (timingData && typeof timingData.seconds !== 'undefined' && typeof timingData.duration !== 'undefined') {
            currentTime = parseFloat(timingData.seconds);
            duration = parseFloat(timingData.duration);
        } else if (timingData && typeof timingData.currentTime !== 'undefined' && typeof timingData.duration !== 'undefined') {
            currentTime = parseFloat(timingData.currentTime);
            duration = parseFloat(timingData.duration);
        } else if (timingData) {
            for (var key in timingData) {
                if (!Object.prototype.hasOwnProperty.call(timingData, key)) {
                    continue;
                }

                var value = timingData[key];
                if (typeof value === 'number' || (typeof value === 'string' && !isNaN(parseFloat(value)))) {
                    if (key.toLowerCase().indexOf('time') !== -1 && key.toLowerCase().indexOf('current') !== -1) {
                        currentTime = parseFloat(value);
                    } else if (key.toLowerCase().indexOf('duration') !== -1) {
                        duration = parseFloat(value);
                    }
                }
            }
        }

        return {
            currentTime: currentTime,
            duration: duration
        };
    },

    // Mantém a posição atual separada do cálculo de conclusão.
    updateCurrentPlaybackPosition: function(currentTime, duration) {
        if (!isNaN(currentTime) && currentTime >= 0) {
            this.currentPosition = currentTime;
        }

        if (!isNaN(duration) && duration > 0) {
            this.currentDuration = duration;
        }
    },

    // Salva a posição atual sem alterar completionmet.
    savePlaybackPosition: function(force) {
        if (!this.config || !this.config.cmid) {
            return;
        }

        var position = Math.max(0, Math.round(parseFloat(this.currentPosition) || 0));
        if (!force && this.lastSavedPosition !== null && Math.abs(position - this.lastSavedPosition) < 1) {
            return;
        }

        var ajaxUrl = M.cfg.wwwroot + '/mod/bunnyvideo/ajax.php';
        var params = new URLSearchParams();
        params.append('action', 'save_position');
        params.append('cmid', this.config.cmid);
        params.append('position', position);
        params.append('sesskey', M.cfg.sesskey);

        if (force && navigator.sendBeacon) {
            try {
                var beaconData = window.Blob
                    ? new Blob([params.toString()], {type: 'application/x-www-form-urlencoded'})
                    : params;

                if (navigator.sendBeacon(ajaxUrl, beaconData)) {
                    this.lastSavedPosition = position;
                    bunnyVideoLog('Posição enviada ao sair da página: ' + this.formatTime(position), null, 'debug');
                    return;
                }
            } catch (e) {
                bunnyVideoLog('sendBeacon falhou ao salvar posição, tentando XHR', e, 'debug');
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, !force);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        var self = this;
        xhr.onload = function() {
            if (xhr.status === 200) {
                self.lastSavedPosition = position;
                bunnyVideoLog('Posição salva: ' + self.formatTime(position), null, 'debug');
            } else {
                bunnyVideoLog('Falha ao salvar posição, status: ' + xhr.status, null, 'warn');
            }
        };
        xhr.onerror = function() {
            bunnyVideoLog('Erro de rede ao salvar posição', null, 'warn');
        };

        try {
            xhr.send(params.toString());
            if (force) {
                this.lastSavedPosition = position;
            }
        } catch (e) {
            bunnyVideoLog('Erro ao enviar posição do vídeo:', e, 'warn');
        }
    },

    // Inicia o salvamento periódico e os eventos de saída da página.
    startPositionSaving: function() {
        var self = this;

        if (this.positionSaveTimer) {
            clearInterval(this.positionSaveTimer);
        }

        if (BunnyVideoDebugConfig.enablePeriodicPositionSaving) {
            this.positionSaveTimer = setInterval(function() {
                self.savePlaybackPosition(false);
            }, BunnyVideoDebugConfig.positionSaveIntervalMs);
        }

        if (!this.positionEventsAttached) {
            var flushPosition = function() {
                self.savePlaybackPosition(true);
            };

            window.addEventListener('pagehide', flushPosition);
            window.addEventListener('beforeunload', flushPosition);
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    flushPosition();
                }
            });

            this.positionEventsAttached = true;
        }
    },

    // Retoma o vídeo da posição salva para este usuário e esta atividade.
    resumePlaybackPosition: function() {
        if (this.resumeAttempted || !this.config) {
            return;
        }

        var position = parseFloat(this.config.lastPosition || 0);
        if (isNaN(position) || position <= 0) {
            this.resumeAttempted = true;
            return;
        }

        if (this.currentDuration > 0 && position >= this.currentDuration) {
            position = Math.max(0, this.currentDuration - 2);
        }

        this.resumeAttempted = true;
        this.currentPosition = position;
        this.lastPosition = null;
        bunnyVideoLog('Retomando vídeo em ' + this.formatTime(position), null, 'info');
        this.seekToPosition(position);
    },

    // Tenta as formas conhecidas de seek suportadas pelas variações da API Player.js.
    seekToPosition: function(position) {
        var self = this;
        var attempts = 0;

        var trySeek = function() {
            attempts++;

            try {
                if (self.playerInstance && typeof self.playerInstance.setCurrentTime === 'function') {
                    self.playerInstance.setCurrentTime(position);
                    return;
                }

                if (self.playerInstance && typeof self.playerInstance.api === 'function') {
                    self.playerInstance.api('seek', position);
                    self.playerInstance.api('setCurrentTime', position);
                    self.playerInstance.api('currentTime', position);
                    return;
                }

                if (self.playerInstance && typeof self.playerInstance.sendMessage === 'function') {
                    self.playerInstance.sendMessage('setCurrentTime', position);
                    return;
                }
            } catch (e) {
                bunnyVideoLog('Tentativa de retomar posição falhou:', e, 'warn');
            }

            if (attempts < 3) {
                setTimeout(trySeek, 500);
            }
        };

        setTimeout(trySeek, 300);
    },
    
    // Para depuração - dispara manualmente a conclusão
    debugTriggerCompletion: function() {
        bunnyVideoLog('Disparando conclusão manualmente para depuração', null, 'warn');
        this.maxPercentReached = this.config.completionPercent;
        this.sendCompletion();
    },
    
    // Processa eventos timeupdate do player
    onTimeUpdate: function(timingData) {
        if (!this.config) {
            bunnyVideoLog('Sem configuração disponível', null, 'error');
            return;
        }

        try {
            bunnyVideoLog('Evento timeupdate recebido', timingData, 'debug');
            var timing = this.extractTimingData(timingData);
            var currentTime = parseFloat(timing.currentTime);
            var duration = parseFloat(timing.duration);

            this.updateCurrentPlaybackPosition(currentTime, duration);

            if (this.config.completionPercent <= 0) {
                bunnyVideoLog('Porcentagem de conclusão não definida ou zero', null, 'debug');
                return;
            }

            if (this.completionSent) {
                return;
            }

            // Se tivermos ambos os valores de tempo, podemos atualizar o progresso
            if (!isNaN(currentTime) && !isNaN(duration) && duration > 0) {
                var currentTimeRounded = Math.round(currentTime / BunnyVideoDebugConfig.timeTrackingResolution) * BunnyVideoDebugConfig.timeTrackingResolution;
                var percentWatched = (currentTime / duration) * 100;
                
                // Ainda rastreia maxPercentReached para depuração, mas não usa para conclusão
                if (percentWatched > this.maxPercentReached) {
                    this.maxPercentReached = percentWatched;
                    
                    bunnyVideoLog('Posição atual: ' + percentWatched.toFixed(1) + '%, máx: ' + 
                            this.maxPercentReached.toFixed(1) + '%, alvo: ' + this.config.completionPercent + '%', 
                            null, 'info');
                }
                
                // Rastreia o tempo real assistido com segmentos
                this.updateWatchedTime(currentTimeRounded);
                
                // Calcula a porcentagem do tempo real assistido
                var percentOfActualTimeWatched = (this.actualTimeWatched / duration) * 100;
                
                // Registra informações do tempo real assistido
                bunnyVideoLog('Tempo real assistido: ' + this.formatTime(this.actualTimeWatched) + 
                       ' (' + percentOfActualTimeWatched.toFixed(1) + '% de ' + this.formatTime(duration) + ')', 
                       null, BunnyVideoDebugConfig.debugLevel >= 3 ? 'debug' : 'info');
                
                // Arredonda a porcentagem calculada APENAS se a meta for 100% para evitar problemas de precisão
                var comparisonPercent = (this.config.completionPercent === 100) ? Math.round(percentOfActualTimeWatched) : percentOfActualTimeWatched;

                // Usa APENAS o tempo real assistido para a conclusão, não maxPercentReached
                // Compara usando o valor potencialmente arredondado
                if (comparisonPercent >= this.config.completionPercent) {
                    bunnyVideoLog('LIMITE DE CONCLUSÃO ATINGIDO com base no tempo real assistido!', null, 'success');
                    this.sendCompletion();
                }
            }
        } catch (e) {
            bunnyVideoLog('Erro ao processar timeupdate:', e, 'error');
        }
    },
    
    // Formata o tempo em segundos para o formato MM:SS
    formatTime: function(seconds) {
        if (isNaN(seconds)) return "00:00";
        seconds = Math.floor(seconds);
        var minutes = Math.floor(seconds / 60);
        seconds = seconds % 60;
        return (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
    },
    
    // Atualiza o tempo rastreado quando a posição do player muda
    updateWatchedTime: function(currentTime) {
        var now = Date.now();
        
        // Inicializa a última posição se esta for a primeira atualização
        if (this.lastPosition === null) {
            this.lastPosition = currentTime;
            this.lastUpdateTime = now;
            return;
        }
        
        // Verifica se a aba ficou inativa por muito tempo (mais de 2 segundos sem atualizações)
        var timeSinceLastUpdate = now - this.lastUpdateTime;
        if (timeSinceLastUpdate > 2000) {
            bunnyVideoLog('A aba pode ter ficado inativa, pulando o rastreamento de tempo para este intervalo', null, 'debug');
            this.lastPosition = currentTime;
            this.lastUpdateTime = now;
            return;
        }
        
        // Se a posição avançou um pouco, conta como visualização contínua
        var positionDelta = Math.abs(currentTime - this.lastPosition);
        if (positionDelta <= 2 * BunnyVideoDebugConfig.timeTrackingResolution) {
            // Pequeno movimento para frente (reprodução normal)
            this.actualTimeWatched += positionDelta;
            this.lastPosition = currentTime;
            this.lastUpdateTime = now;
            
            // Adiciona aos segmentos assistidos (mescla com existente se possível)
            this.addWatchedSegment(currentTime - positionDelta, currentTime);
        } else {
            // Salto maior - usuário provavelmente pulou
            bunnyVideoLog('Salto de posição detectado: ' + this.formatTime(this.lastPosition) + 
                   ' → ' + this.formatTime(currentTime), null, 'debug');
            this.lastPosition = currentTime;
            this.lastUpdateTime = now;
        }
    },
    
    // Adiciona um segmento assistido e mescla segmentos sobrepostos
    addWatchedSegment: function(start, end) {
        if (start >= end) return;
        
        // Arredonda para a resolução configurada
        start = Math.floor(start / BunnyVideoDebugConfig.timeTrackingResolution) * BunnyVideoDebugConfig.timeTrackingResolution;
        end = Math.ceil(end / BunnyVideoDebugConfig.timeTrackingResolution) * BunnyVideoDebugConfig.timeTrackingResolution;
        
        // Verifica se este segmento se sobrepõe a algum segmento existente
        var merged = false;
        for (var i = 0; i < this.watchedSegments.length; i++) {
            var segment = this.watchedSegments[i];
            
            // Verifica sobreposição
            if (start <= segment[1] + BunnyVideoDebugConfig.timeTrackingResolution && 
                end >= segment[0] - BunnyVideoDebugConfig.timeTrackingResolution) {
                // Mescla segmentos
                segment[0] = Math.min(segment[0], start);
                segment[1] = Math.max(segment[1], end);
                merged = true;
                break;
            }
        }
        
        // Se não houver sobreposição, adiciona como novo segmento
        if (!merged) {
            this.watchedSegments.push([start, end]);
        }
        
        // Mescla quaisquer segmentos que agora se sobrepõem após a atualização
        this.mergeOverlappingSegments();
        
        // Recalcula o tempo total assistido
        this.recalculateTimeWatched();
    },
    
    // Mescla quaisquer segmentos sobrepostos
    mergeOverlappingSegments: function() {
        if (this.watchedSegments.length <= 1) return;
        
        // Ordena segmentos por tempo de início
        this.watchedSegments.sort(function(a, b) {
            return a[0] - b[0];
        });
        
        // Mescla segmentos sobrepostos
        var merged = [];
        var current = this.watchedSegments[0];
        
        for (var i = 1; i < this.watchedSegments.length; i++) {
            var next = this.watchedSegments[i];
            
            // Se o atual se sobrepõe ao próximo, mescla-os
            if (current[1] + BunnyVideoDebugConfig.timeTrackingResolution >= next[0]) {
                current[1] = Math.max(current[1], next[1]);
            } else {
                // Sem sobreposição, adiciona o atual à lista mesclada e move para o próximo
                merged.push(current);
                current = next;
            }
        }
        
        // Adiciona o último segmento
        merged.push(current);
        this.watchedSegments = merged;
    },
    
    // Recalcula o tempo total assistido de segmentos
    recalculateTimeWatched: function() {
        var total = 0;
        for (var i = 0; i < this.watchedSegments.length; i++) {
            var segment = this.watchedSegments[i];
            total += (segment[1] - segment[0]);
        }
        this.actualTimeWatched = total;
    },
    
    // Polling para progresso se os eventos timeupdate não estiverem disparando
    startProgressPolling: function() {
        var self = this;
        
        if (this.progressTimer) {
            clearInterval(this.progressTimer);
        }
        
        bunnyVideoLog('Iniciando polling de progresso como backup', null, 'info');
        
        this.progressTimer = setInterval(function() {
            if (!self.playerInstance || !self.playerReady) return;
            
            try {
                // Tenta obter o tempo atual e a duração via chamada de API
                if (typeof self.playerInstance.api === 'function') {
                    var currentTime = 0;
                    var duration = 0;
                    
                    try {
                        currentTime = self.playerInstance.api('currentTime') || 0;
                        duration = self.playerInstance.api('duration') || 0;
                    } catch (e) {
                        // Alguns players podem não suportar esses métodos
                        return;
                    }

                    self.updateCurrentPlaybackPosition(currentTime, duration);
                    
                    if (duration > 0) {
                        var percentWatched = (currentTime / duration) * 100;
                        var previousMax = self.maxPercentReached;
                        self.maxPercentReached = Math.max(self.maxPercentReached, percentWatched);
                        
                        // Registra mudanças significativas
                        if (Math.floor(self.maxPercentReached) > Math.floor(previousMax)) {
                            bunnyVideoLog('[POLL] Progresso: ' + percentWatched.toFixed(1) + '%, máx: ' + 
                                    self.maxPercentReached.toFixed(1) + '%, alvo: ' + self.config.completionPercent + '%', 
                                    null, 'info');
                        }
                        
                        // Verifica o limite de conclusão
                        if (self.config.completionPercent > 0 && !self.completionSent && self.maxPercentReached >= self.config.completionPercent) {
                            bunnyVideoLog('[POLL] LIMITE DE CONCLUSÃO ATINGIDO!', null, 'success');
                            self.sendCompletion();
                        }
                    }
                }
            } catch (e) {
                bunnyVideoLog('Erro no polling de progresso:', e, 'error');
            }
        }, 2000); // Verifica a cada 2 segundos
    },
    
    // Mostra um indicador visual de conclusão
    showCompletionIndicator: function() {
        try {
            // Cria um pequeno indicador que desaparece após alguns segundos
            var container = document.querySelector('[id^="bunnyvideo-player-"]');
            if (!container) {
                bunnyVideoLog('Container não encontrado para indicador de conclusão', null, 'warn');
                return;
            }
            
            // Mostra apenas se ainda não estiver sendo mostrado
            if (document.getElementById('bunny-completion-indicator')) return;
            
            bunnyVideoLog('Mostrando indicador de conclusão', null, 'info');
            
            var indicator = document.createElement('div');
            indicator.id = 'bunny-completion-indicator';
            indicator.style.cssText = 'position:absolute; top:10px; right:10px; background-color:rgba(0,128,0,0.8); color:white; padding:8px 12px; border-radius:4px; font-size:14px; z-index:1000; transition:opacity 0.5s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
            
            // Verificar se as strings do Moodle estão disponíveis e usar fallback apropriado
            var completionText = 'Atividade Completada';
            try {
                if (typeof M !== 'undefined' && M.str && M.str.completion && M.str.completion.completion_y) {
                    completionText = M.str.completion.completion_y;
                }
                // Não usar M.util.get_string pois está retornando o placeholder
            } catch (e) {
                bunnyVideoLog('Erro ao obter string de conclusão:', e, 'warn');
            }
            
            indicator.innerHTML = '✓ ' + completionText;
            
            container.style.position = 'relative';
            container.appendChild(indicator);
            
            // Desaparece após 5 segundos
            setTimeout(function() {
                indicator.style.opacity = '0';
                // Remove após o desaparecimento
                setTimeout(function() {
                    if (indicator.parentNode) {
                        indicator.parentNode.removeChild(indicator);
                    }
                }, 500);
            }, 5000);
        } catch (e) {
            bunnyVideoLog('Erro ao mostrar indicador de conclusão:', e, 'error');
        }
    },
    
    // Inicializa o player e configura os manipuladores de evento
    initializePlayer: function() {
        var self = this;
        
        // Registra todos os parâmetros para ajudar na depuração
        bunnyVideoLog('Inicializando player com config:', this.config, 'info');
        
        if (!this.config || !this.config.cmid) {
            bunnyVideoLog('Configuração inválida', null, 'error');
            return;
        }
        
        // Tenta ambos os IDs de container potenciais
        var containerIds = [
            'bunnyvideo-player-' + this.config.bunnyvideoid,  // Tenta o ID do bunnyvideo primeiro (de lib.php)
            'bunnyvideo-player-' + this.config.cmid           // Tenta o ID do módulo do curso como fallback
        ];
        
        bunnyVideoLog('Procurando por containers com IDs:', containerIds, 'debug');
        
        var container = null;
        for (var i = 0; i < containerIds.length; i++) {
            var id = containerIds[i];
            bunnyVideoLog('Procurando por container com ID: ' + id, null, 'debug');
            container = document.getElementById(id);
            if (container) {
                bunnyVideoLog('Container encontrado: ' + id, null, 'info');
                break;
            }
        }
        
        if (!container) {
            bunnyVideoLog('Container do player não encontrado com nenhum desses IDs: ' + containerIds.join(', '), null, 'error');
            return;
        }
        
        // Encontra o iframe
        var iframe = container.querySelector('iframe');
        if (!iframe) {
            bunnyVideoLog('Nenhum iframe encontrado no container', null, 'error');
            return;
        }
        
        bunnyVideoLog('Usando iframe com id: ' + iframe.id, null, 'info');
        
        // Verifica qualquer variante da biblioteca Player.js
        this.ensurePlayerJsLibraryLoaded(function() {
            self.initializePlayerInstance(iframe, container);
        });
    },
    
    // Garante que a biblioteca Player.js seja carregada e esteja pronta para uso
    ensurePlayerJsLibraryLoaded: function(callback) {
        var attempts = 0;
        var maxAttempts = 10;
        var self = this;
        
        function checkForPlayerJs() {
            attempts++;
            
            // Verifica todas as variáveis globais potenciais
            var playerJsExists = (
                typeof playerjs !== 'undefined' || 
                typeof PlayerJS !== 'undefined' || 
                typeof window.playerjs !== 'undefined' || 
                typeof window.PlayerJS !== 'undefined'
            );
            
            if (playerJsExists) {
                bunnyVideoLog('Biblioteca Player.js encontrada na tentativa ' + attempts, null, 'success');
                callback();
                return;
            }
            
            if (attempts >= maxAttempts) {
                bunnyVideoLog('Biblioteca Player.js não encontrada após ' + maxAttempts + ' tentativas, usando abordagem de fallback', null, 'warn');
                callback(); // Continua mesmo assim, usaremos fallback na inicialização
                return;
            }
            
            bunnyVideoLog('Aguardando a biblioteca Player.js (tentativa ' + attempts + '/' + maxAttempts + ')', null, 'debug');
            setTimeout(checkForPlayerJs, 200);
        }
        
        checkForPlayerJs();
    },
    
    // Inicializa a instância do player assim que verificamos o carregamento da biblioteca
    initializePlayerInstance: function(iframe, container) {
        // Ao examinar a biblioteca específica do Bunny, podemos ver que o objeto global é 'playerjs'
        // Tenta todas as variantes conhecidas
        if (typeof playerjs !== 'undefined') {
            bunnyVideoLog('Biblioteca playerjs específica do Bunny encontrada', null, 'success');
            
            // Verifica qual padrão de construtor está disponível
            if (typeof playerjs.Player === 'function') {
                bunnyVideoLog('Usando construtor playerjs.Player', null, 'success');
                this.playerInstance = new playerjs.Player(iframe);
            } else if (typeof playerjs === 'function') {
                bunnyVideoLog('Usando construtor playerjs diretamente', null, 'success');
                this.playerInstance = new playerjs(iframe);
            } else {
                bunnyVideoLog('Estrutura de biblioteca playerjs desconhecida', playerjs, 'warn');
                this.setupPostMessagePlayer(iframe);
                return;
            }
        } else if (typeof PlayerJS !== 'undefined') {
            bunnyVideoLog('Construtor PlayerJS encontrado', null, 'success');
            this.playerInstance = new PlayerJS(iframe);
        } else if (typeof window.playerjs !== 'undefined') {
            bunnyVideoLog('window.playerjs encontrado', null, 'success');
            
            if (typeof window.playerjs.Player === 'function') {
                this.playerInstance = new window.playerjs.Player(iframe);
            } else {
                this.playerInstance = new window.playerjs(iframe);
            }
        } else if (typeof window.PlayerJS !== 'undefined') {
            bunnyVideoLog('Construtor window.PlayerJS encontrado', null, 'success');
            this.playerInstance = new window.PlayerJS(iframe);
        } else {
            bunnyVideoLog('Biblioteca Player.js não encontrada! Procurando alternativas...', null, 'warn');
            
            // Registra todas as propriedades da janela contendo "player" para depuração
            var playerVars = Object.keys(window).filter(function(key) {
                return key.toLowerCase().indexOf('player') !== -1; 
            });
            
            bunnyVideoLog('Variáveis globais disponíveis contendo "player":', playerVars, 'debug');
            
            // Tenta uma abordagem diferente com o método CDN mais recente
            bunnyVideoLog('Tentando usar controle direto do iframe com a API postMessage', null, 'info');
            this.setupPostMessagePlayer(iframe);
            return;
        }
        
        if (!this.playerInstance) {
            bunnyVideoLog('Falha ao criar instância do player, usando fallback', null, 'error');
            this.setupPostMessagePlayer(iframe);
            return;
        }
        
        bunnyVideoLog('Instância do player criada com sucesso, configurando listeners de evento', null, 'success');
        this.setupPlayerEvents();
        
        // Adiciona botão de depuração para teste
        this.addDebugButton(container);
    },
    
    // Configura eventos do player assim que tivermos uma instância de player válida
    setupPlayerEvents: function() {
        var self = this;
        
        // Quando o player estiver pronto
        this.playerInstance.on('ready', function() {
            bunnyVideoLog('Evento ready do player recebido', null, 'success');
            self.playerReady = true;
            self.startPositionSaving();
            self.resumePlaybackPosition();
            
            // Configura vários listeners de evento
            self.playerInstance.on('timeupdate', function(data) {
                self.onTimeUpdate(data);
            });
            
            self.playerInstance.on('play', function() {
                bunnyVideoLog('Evento play do player', null, 'debug');
            });
            
            self.playerInstance.on('pause', function() {
                bunnyVideoLog('Evento pause do player', null, 'debug');
            });
            
            self.playerInstance.on('ended', function() {
                // bunnyVideoLog('Evento ended do player', null, 'success');
                // self.maxPercentReached = 100;
                // if (self.config.completionPercent > 0) {
                //     self.sendCompletion();
                // }
            });
            
            // Inicia polling como backup
            self.startProgressPolling();
        });
        
        this.playerInstance.on('error', function(error) {
            bunnyVideoLog('Erro do player:', error, 'error');
        });
    },
    
    // Adiciona um botão de depuração para disparar manualmente a conclusão (apenas em dev/teste)
    addDebugButton: function(container) {
        try {
            // Adiciona botão de depuração apenas se a UI de depuração estiver habilitada
            if (!BunnyVideoDebugConfig.showDebugUI) {
                return;
            }
            
            var self = this;
            var debugButton = document.createElement('button');
            debugButton.textContent = 'DEBUG: Marcar Concluído';
            debugButton.style.cssText = 'position:absolute; bottom:10px; right:10px; background:#f44336; color:white; border:none; padding:5px 10px; cursor:pointer; z-index:1000; border-radius:4px; font-size:12px;';
            debugButton.onclick = function() {
                self.debugTriggerCompletion();
            };
            
            container.style.position = 'relative';
            container.appendChild(debugButton);
        } catch (e) {
            // Falha silenciosamente - apenas uma ferramenta de depuração
        }
    },
    
    // Configura o player usando a biblioteca detectada
    setupPlayerWithLibrary: function(libraryType, iframe) {
        var self = this;
        try {
            bunnyVideoLog('Configurando player com ' + libraryType, null, 'info');
            
            // Tenta criar uma instância de player com a biblioteca detectada
            if (libraryType === 'PlayerJS') {
                bunnyVideoLog('Criando uma nova instância PlayerJS', null, 'debug');
                this.playerInstance = new PlayerJS(iframe);
            } else if (libraryType === 'playerjs') {
                bunnyVideoLog('Criando um novo construtor playerjs.Player', null, 'debug');
                this.playerInstance = new playerjs.Player(iframe);
            } else if (libraryType === 'window.PlayerJS') {
                bunnyVideoLog('Criando uma nova instância window.PlayerJS', null, 'debug');
                this.playerInstance = new window.PlayerJS(iframe);
            } else if (libraryType === 'window.playerjs') {
                bunnyVideoLog('Criando uma nova instância window.playerjs.Player', null, 'debug');
                this.playerInstance = new window.playerjs.Player(iframe);
            }
            
            if (!this.playerInstance) {
                bunnyVideoLog('Falha ao criar instância do player', null, 'error');
                this.setupPostMessagePlayer(iframe);
                return;
            }
            
            bunnyVideoLog('Instância do player criada com sucesso, configurando listeners de evento', null, 'success');
            
            // Quando o player estiver pronto
            this.playerInstance.on('ready', function() {
                bunnyVideoLog('Evento ready do player recebido', null, 'success');
                self.playerReady = true;
                self.startPositionSaving();
                self.resumePlaybackPosition();
                
                // Configura vários listeners de evento
                self.playerInstance.on('timeupdate', function(data) {
                    self.onTimeUpdate(data);
                });
                
                self.playerInstance.on('play', function() {
                    bunnyVideoLog('Evento play do player', null, 'debug');
                });
                
                self.playerInstance.on('pause', function() {
                    bunnyVideoLog('Evento pause do player', null, 'debug');
                });
                
                self.playerInstance.on('ended', function() {
                    bunnyVideoLog('Evento ended do player - definindo 100% assistido', null, 'success');
                    self.maxPercentReached = 100;
                    if (self.config.completionPercent > 0) {
                        self.sendCompletion();
                    }
                });
                
                // Inicia polling como backup
                self.startProgressPolling();
            });
            
            this.playerInstance.on('error', function(error) {
                bunnyVideoLog('Erro do player:', error, 'error');
            });
            
        } catch (e) {
            bunnyVideoLog('Erro ao configurar player:', e, 'error');
            
            // Tenta fallback
            this.setupPostMessagePlayer(iframe);
        }
    },
    
    // Fallback para usar a API postMessage diretamente com iframe
    setupPostMessagePlayer: function(iframe) {
        var self = this;
        bunnyVideoLog('Usando abordagem de fallback postMessage', null, 'info');
        
        // Cria um wrapper de player personalizado simples
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
                        // Mensagem JSON inválida ou não do nosso player
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
        
        // Configura manipuladores de evento semelhantes ao Player.js padrão
        customPlayer.on('ready', function() {
            bunnyVideoLog('Evento ready do player personalizado recebido', null, 'success');
            self.playerReady = true;
            self.startPositionSaving();
            self.resumePlaybackPosition();
            
            // Configura vários listeners de evento
            customPlayer.on('timeupdate', function(data) {
                self.onTimeUpdate(data);
            });
            
            customPlayer.on('play', function() {
                bunnyVideoLog('Evento play do player personalizado', null, 'debug');
            });
            
            customPlayer.on('pause', function() {
                bunnyVideoLog('Evento pause do player personalizado', null, 'debug');
            });
            
            customPlayer.on('ended', function() {
                bunnyVideoLog('Evento ended do player personalizado - definindo 100% assistido', null, 'success');
                self.maxPercentReached = 100;
                if (self.config.completionPercent > 0) {
                    self.sendCompletion();
                }
            });
            
            // Inicia polling como backup
            self.startProgressPolling();
        });
        
        customPlayer.on('error', function(error) {
            bunnyVideoLog('Erro do player personalizado:', error, 'error');
        });
    },
    
    // Função principal de inicialização chamada do PHP
    init: function(cfg) {
        this.config = cfg;
        bunnyVideoLog('Inicializando BunnyVideoHandler com config:', cfg, 'info');
        
        if (!this.config || !this.config.cmid) {
            bunnyVideoLog('Configuração inválida', null, 'error');
            return;
        }
        
        var self = this;
        // Inicializa o player após o DOM estar pronto
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(function() { self.initializePlayer(); }, 100);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() { self.initializePlayer(); }, 100);
            });
        }
    }
};

// Também define isso para compatibilidade com versões anteriores
window.BunnyVideoInit = function(config) {
    bunnyVideoLog('BunnyVideoInit chamado, delegando para BunnyVideoHandler', null, 'info');
    window.BunnyVideoHandler.init(config);
};
