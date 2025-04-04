<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vídeo Bunny';
$string['modulename'] = 'Vídeo Bunny';
$string['modulenameplural'] = 'Vídeos Bunny';
$string['modulename_help'] = 'Use a atividade Vídeo Bunny para incorporar vídeos do Bunny Stream. Você pode opcionalmente rastrear a conclusão com base na porcentagem do vídeo assistido.';
$string['pluginadministration'] = 'Administração do Vídeo Bunny';
$string['bunnyvideo:addinstance'] = 'Adicionar um novo vídeo Bunny';
$string['bunnyvideo:view'] = 'Ver vídeo Bunny';

$string['activityname'] = 'Nome da Atividade';
$string['embedcode'] = 'Código de Incorporação (Embed) Bunny';
$string['embedcode_help'] = 'Cole o código HTML completo de incorporação fornecido pelo Bunny Stream (geralmente começa com &lt;iframe...).';
$string['completionpercent'] = 'Percentual para conclusão';
$string['completionpercent_help'] = 'Digite a porcentagem (1-100) do vídeo que o usuário deve assistir para que a atividade seja marcada como concluída automaticamente. Deixe em branco ou defina como 0 para desativar a conclusão automática baseada no tempo assistido.';
$string['invalidembedcode'] = 'O código de incorporação fornecido parece inválido ou incompleto. Por favor, cole o código completo do Bunny.';
$string['videoidnotfound'] = 'Não foi possível extrair o ID do vídeo do código de incorporação.';
$string['libraryidnotfound'] = 'Não foi possível extrair o ID da biblioteca do código de incorporação.';
$string['player_loading'] = 'Carregando Vídeo...';

// For AJAX function
$string['ajax_mark_complete'] = 'Marcar Vídeo Bunny como concluído';
$string['ajax_mark_complete_description'] = 'Serviço AJAX para atualizar o status de conclusão de uma atividade Vídeo Bunny com base na porcentagem de visualização.';
$string['error_cannotmarkcomplete'] = 'Não foi possível marcar a atividade como concluída.';
$string['error_invalidcoursemodule'] = 'ID de módulo de curso inválido.';
