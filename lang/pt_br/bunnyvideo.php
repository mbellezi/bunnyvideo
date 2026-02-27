<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vídeo Bunny';
$string['modulename'] = 'Vídeo Bunny';
$string['modulenameplural'] = 'Vídeos Bunny';
$string['modulename_help'] = 'Use a atividade Vídeo Bunny para incorporar vídeos do Bunny Stream. Você pode opcionalmente rastrear a conclusão com base na porcentagem do vídeo assistido.';
$string['pluginadministration'] = 'Administração do Vídeo Bunny';
$string['bunnyvideo:addinstance'] = 'Adicionar um novo vídeo Bunny';
$string['bunnyvideo:view'] = 'Ver vídeo Bunny';
$string['bunnyvideo:managecompletion'] = 'Gerenciar status de conclusão para atividades de vídeo Bunny';

$string['activityname'] = 'Nome da Atividade';
$string['embedcode'] = 'Código de Incorporação (Embed) Bunny';
$string['embedcode_help'] = 'Cole o código HTML completo de incorporação fornecido pelo Bunny Stream (geralmente começa com &lt;iframe...).';
$string['completionpercent'] = 'Exigir porcentagem assistida';
$string['completionpercent_help'] = 'Se habilitado, a atividade será marcada como concluída quando o usuário assistir a porcentagem especificada do vídeo.';
$string['completionrulenamepercent'] = 'Assistir {$a}% do vídeo';
$string['configframesecurity'] = 'Segurança do Frame do Player Bunny.net';
$string['configframesecurity_desc'] = 'Configure os domínios permitidos para o player incorporado.';
$string['invalidembedcode'] = 'O código de incorporação fornecido parece inválido ou incompleto. Por favor, cole o código completo do Bunny.';
$string['videoidnotfound'] = 'Não foi possível extrair o ID do vídeo do código de incorporação.';
$string['libraryidnotfound'] = 'Não foi possível extrair o ID da biblioteca do código de incorporação.';
$string['player_loading'] = 'Carregando Vídeo...';

// For AJAX function
$string['ajax_mark_complete'] = 'Marcar Vídeo Bunny como concluído';
$string['ajax_mark_complete_description'] = 'Serviço AJAX para atualizar o status de conclusão de uma atividade Vídeo Bunny com base na porcentagem de visualização.';
$string['error_cannotmarkcomplete'] = 'Não foi possível marcar a atividade como concluída.';
$string['error_invalidcoursemodule'] = 'ID de módulo de curso inválido.';
$string['completionpercenthelp'] = 'O usuário deve assistir pelo menos {$a}% do vídeo';
$string['completionpercentdesc'] = 'O aluno deve assistir pelo menos {$a}% do vídeo';
$string['completion_status_complete'] = 'Atividade concluída ✓';
$string['completion_status_incomplete'] = 'Para concluir esta atividade, você precisa assistir pelo menos {$a}% do vídeo.';
$string['toggle_completion'] = 'Alternar status de conclusão';
$string['mark_complete'] = 'Marcar como concluída';
$string['mark_incomplete'] = 'Marcar como não concluída';
$string['completion_updated'] = 'Status de conclusão atualizado';
$string['completion_toggle_success'] = 'Status de conclusão atualizado com sucesso';
$string['completion_toggle_error'] = 'Erro ao atualizar status de conclusão';
