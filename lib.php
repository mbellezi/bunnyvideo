<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
// Garante que a classe de evento seja carregada pelo autoloader
// require_once($CFG->dirroot . '/mod/bunnyvideo/classes/event/course_module_viewed.php'); // Não é mais necessário aqui

/**
 * @param stdClass $bunnyvideo Uma instância de bunnyvideo do banco de dados
 * @param stdClass $cm A instância do módulo do curso à qual pertence
 * @param context_module $context O contexto da instância do módulo
 * @param array $options Outras opções de exibição
 * @return string Saída HTML para a página da atividade.
 */
function bunnyvideo_view($bunnyvideo, $cm, $context, $options = null)
{
    global $CFG, $PAGE, $OUTPUT, $USER, $DB; // Adicionado global $DB

    // Obtém os dados do curso - necessários para snapshots de registro
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    try {
        // Dispara o evento course_module_viewed. Log padrão do Moodle.
        $event = \mod_bunnyvideo\event\course_module_viewed::create(array(
            'objectid' => $bunnyvideo->id,
            'context' => $context,
            'other' => array('userid' => $USER->id)
        ));

        // Adiciona snapshots de registro com tratamento de erros
        try {
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('bunnyvideo', $bunnyvideo);
            $event->trigger();
        } catch (Exception $e) {
            // Registra o erro mas continua - não permite que a falha do evento impeça a visualização
            debugging('Erro no registro do evento: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } catch (Exception $e) {
        // Apenas registra o erro mas continua carregando a página
        debugging('Erro ao criar evento: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Impedir que o Moodle marque automaticamente a atividade como concluída por visualização
    $completion = new completion_info($course);

    // Verificar se a atividade foi marcada como concluída indevidamente
    // (por exemplo, por auto-completação do Moodle ao visualizar a página)
    $completiondata = $completion->get_data($cm, false, $USER->id);
    if (
        $completiondata->completionstate == COMPLETION_COMPLETE &&
        $cm->completion == COMPLETION_TRACKING_AUTOMATIC
    ) {

        // Verifica se existe um registro REAL de conclusão feito pelo nosso AJAX
        $real_completion = $DB->get_record('course_modules_completion', array(
            'coursemoduleid' => $cm->id,
            'userid' => $USER->id
        ));

        // Se o estado é COMPLETE mas NÃO foi definido pelo nosso sistema AJAX,
        // então é uma auto-completação indevida - desfazer
        $was_set_by_our_ajax = $real_completion &&
            $real_completion->completionstate == COMPLETION_COMPLETE;

        if (!$was_set_by_our_ajax) {
            // Apagar o registro de conclusão indevido
            $DB->delete_records('course_modules_completion', array(
                'coursemoduleid' => $cm->id,
                'userid' => $USER->id
            ));

            // Recarregar os dados de conclusão para garantir que estejam limpos
            $completiondata = $completion->get_data($cm, true, $USER->id);
            debugging('BunnyVideo: Conclusão indevida removida - atividade não foi assistida');
        }
    }

    // Obter o status de conclusão atual para o usuário após a possível correção acima
    $completionstate = $completiondata->completionstate;
    $completionpercent = $bunnyvideo->completionpercent;

    // Determinar mensagem de status apropriada
    $statusmessage = '';
    if ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) {
        $statusmessage = '<div class="alert alert-success">' . get_string('completion_status_complete', 'mod_bunnyvideo') . '</div>';
    } else if ($completionpercent > 0) {
        $statusmessage = '<div class="alert alert-info">' .
            get_string('completion_status_incomplete', 'mod_bunnyvideo', $completionpercent) .
            '</div>';
    }

    // Verifica se o usuário atual pode gerenciar a conclusão (professor/admin)
    $canmanagecompletion = has_capability('mod/bunnyvideo:managecompletion', $context);

    // Adiciona botão de alternância para professores/admins
    if ($canmanagecompletion && $cm->completion != COMPLETION_TRACKING_NONE) {
        // Cria botão com ação oposta ao estado atual
        $buttonlabel = ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS)
            ? get_string('mark_incomplete', 'mod_bunnyvideo')
            : get_string('mark_complete', 'mod_bunnyvideo');

        $newstate = ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS)
            ? COMPLETION_INCOMPLETE
            : COMPLETION_COMPLETE;

        // Adiciona botão para alternar o status de conclusão
        $togglebutton = '<div class="completion-toggle-container float-right" style="margin-top: -40px; position: relative;">';
        $togglebutton .= '<button type="button" class="btn btn-sm ' .
            (($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) ? 'btn-warning' : 'btn-success') .
            ' completion-toggle-button" data-cmid="' . $cm->id . '" data-userid="' . $USER->id . '" data-newstate="' . $newstate . '">';
        $togglebutton .= $buttonlabel . '</button>';
        $togglebutton .= '</div>';

        // Adiciona o botão de alternância à mensagem de status
        if (!empty($statusmessage)) {
            // Insere o botão na mensagem de status existente
            $statusmessage = str_replace('</div>', $togglebutton . '</div>', $statusmessage);
        } else {
            // Cria um novo container de status se não houver um
            $statusmessage = '<div class="alert alert-info">' . $togglebutton . '</div>';
        }
    }

    // Prepara dados para o JavaScript
    $jsdata = [
        'cmid' => $cm->id,
        'bunnyvideoid' => $bunnyvideo->id,
        'completionPercent' => $bunnyvideo->completionpercent > 0 ? (int) $bunnyvideo->completionpercent : 0,
        'contextid' => $context->id,
    ];

    // --- Geração do HTML usando $OUTPUT (Renderer Padrão) ---
    $html = '';

    // Incorpora o script Player.js diretamente na saída HTML
    $html .= '<script src="https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js"></script>';

    // Então adiciona nosso script manipulador - isso garante a ordem correta
    $html .= '<script src="' . $CFG->wwwroot . '/mod/bunnyvideo/js/player_handler.js?v=' . time() . '"></script>';

    // Adiciona o script de alternância de conclusão se o usuário puder gerenciar a conclusão
    if ($canmanagecompletion && $cm->completion != COMPLETION_TRACKING_NONE) {
        $html .= '<script src="' . $CFG->wwwroot . '/mod/bunnyvideo/js/completion_toggle.js?v=' . time() . '"></script>';
    }

    // Inicializa somente depois que tudo estiver carregado
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Garante que tudo esteja disponível
            if (typeof BunnyVideoHandler !== "undefined") {
                console.log("BunnyVideo: Initializing handler after DOM loaded");
                BunnyVideoHandler.init(' . json_encode($jsdata) . ');
            } else {
                console.error("BunnyVideo: Handler not found even after direct embedding");
            }
        });
    </script>';

    // Formata o nome e a introdução
    $name = format_string($bunnyvideo->name, true, ['context' => $context]);
    $intro = format_module_intro('bunnyvideo', $bunnyvideo, $cm->id);

    // Verifica se o usuário tem capacidade de visualizar conteúdo confiável
    if (has_capability('moodle/site:trustcontent', $context)) {
        // Para professores e administradores, usa format_text normalmente
        $embedcode_html = format_text($bunnyvideo->embedcode, FORMAT_HTML, ['trusted' => true, 'noclean' => true, 'context' => $context]);
    } else {
        // Para estudantes, usa diretamente o código embed sem filtrar
        // Isso ignora os filtros de segurança, mas é necessário para mostrar o iframe
        $embedcode_html = $bunnyvideo->embedcode;
        debugging('BunnyVideo: Bypass Moodle format_text filtros para estudantes', DEBUG_DEVELOPER);
    }

    // Monta o conteúdo HTML usando $OUTPUT e html_writer
    $content = '';
    $content .= $OUTPUT->box_start('generalbox boxaligncenter mod_bunnyvideo_content', 'bunnyvideocontent-' . $bunnyvideo->id); // ID único para o container geral

    // Adiciona a mensagem de status de conclusão ANTES do conteúdo
    $content .= $statusmessage;

    // if (trim(strip_tags($intro))) {
    //      $content .= $OUTPUT->box($intro, 'mod_introbox'); // Caixa para a introdução
    // }

    // Adiciona um wrapper DIV com ID único para o JS encontrar o iframe facilmente
    $content .= '<div id="bunnyvideo-player-' . $bunnyvideo->id . '" class="bunnyvideo-player-wrapper">';
    $content .= $html; // Adiciona nossos scripts antes do código de incorporação
    $content .= $embedcode_html;
    $content .= '</div>';

    $content .= $OUTPUT->box_end();

    // Retorna o HTML gerado
    return $content;

    // --- FIM da Geração do HTML ---

    /* REMOVIDO O BLOCO QUE TENTAVA USAR RENDERER/TEMPLATE:
    // Renderiza a saída usando um template (opcional mas recomendado)
    // $output = $PAGE->get_renderer('mod_bunnyvideo'); // <<< LINHA REMOVIDA
    // ... lógica if/else removida ...
    */
}

/**
 * Função padrão do Moodle: Adiciona uma nova instância da atividade.
 * @param stdClass $bunnyvideo Objeto do formulário.
 * @param mod_bunnyvideo_mod_form $mform Definição do formulário.
 * @return int O ID da instância recém-criada.
 */
function bunnyvideo_add_instance($bunnyvideo, $mform)
{
    global $DB;

    $bunnyvideo->timecreated = time();
    $bunnyvideo->timemodified = $bunnyvideo->timecreated;

    $bunnyvideo->id = $DB->insert_record('bunnyvideo', $bunnyvideo);

    // IMPORTANTE: Forçar completionview=0 no course_modules para impedir que o Moodle
    // marque a atividade como concluída apenas por visualização.
    // A conclusão deve ser controlada EXCLUSIVAMENTE pelo nosso AJAX (porcentagem assistida).
    $cmid = $bunnyvideo->coursemodule;
    if ($cmid) {
        $DB->set_field('course_modules', 'completionview', 0, array('id' => $cmid));
    }

    return $bunnyvideo->id;
}

/**
 * Função padrão do Moodle: Atualiza uma instância existente.
 * @param stdClass $bunnyvideo Objeto do formulário.
 * @param mod_bunnyvideo_mod_form $mform Definição do formulário.
 * @return bool True se bem-sucedido.
 */
function bunnyvideo_update_instance($bunnyvideo, $mform)
{
    global $DB;

    $bunnyvideo->timemodified = time();
    $bunnyvideo->id = $bunnyvideo->instance; // Passado em $bunnyvideo por moodleform_mod
    $cmid = $bunnyvideo->coursemodule; // Passado em $bunnyvideo por moodleform_mod

    // Busca o valor anterior de completionpercent para comparação
    $oldbunnyvideo = $DB->get_record('bunnyvideo', array('id' => $bunnyvideo->id), 'completionpercent');
    $oldpercent = $oldbunnyvideo ? (int) $oldbunnyvideo->completionpercent : 0;
    $newpercent = isset($bunnyvideo->completionpercent) ? (int) $bunnyvideo->completionpercent : 0;

    $result = $DB->update_record('bunnyvideo', $bunnyvideo);

    // IMPORTANTE: Forçar completionview=0 no course_modules para impedir que o Moodle
    // marque a atividade como concluída apenas por visualização.
    if ($cmid) {
        $DB->set_field('course_modules', 'completionview', 0, array('id' => $cmid));
    }

    // Se a porcentagem mudou, resetar todos os registros de conclusão desta atividade
    // para que o Moodle reavalie corretamente (todos começam como INCOMPLETE)
    if ($oldpercent != $newpercent && $cmid) {
        $DB->delete_records('course_modules_completion', array('coursemoduleid' => $cmid));
        debugging('BunnyVideo: Porcentagem alterada de ' . $oldpercent . '% para ' . $newpercent .
            '% - registros de conclusão resetados para cmid: ' . $cmid, DEBUG_DEVELOPER);
    }

    return $result;
}

/**
 * Função padrão do Moodle: Exclui uma instância.
 * @param int $id O ID da instância.
 * @return bool True se bem-sucedido.
 */
function bunnyvideo_delete_instance($id)
{
    global $DB;

    if (!$bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $id))) {
        return false;
    }

    // Exclusão padrão - O Moodle lida com dados de conclusão relacionados etc.
    $DB->delete_records('bunnyvideo', array('id' => $bunnyvideo->id));

    return true;
}


/**
 * Define o suporte do módulo para recursos específicos.
 * @param string $feature Constante FEATURE_xx para o recurso solicitado
 * @return mixed True se o módulo suporta o recurso, null se não sabe
 */
function bunnyvideo_supports($feature)
{
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false; // Sem notas
        case FEATURE_GROUPS:
            return false; // Sem suporte a grupos
        case FEATURE_GROUPINGS:
            return false; // Sem suporte a agrupamentos
        case FEATURE_MOD_INTRO:
            return true;  // Campo de introdução básico
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false; // Desativado para impedir marcação automática ao visualizar
        case FEATURE_COMPLETION_HAS_RULES:
            return true;  // Temos regras de conclusão personalizadas
        case FEATURE_MODEDIT_DEFAULT_COMPLETION:
            return true; // Padrão para conclusão rastreada
        case FEATURE_COMMENT:
            return false; // Sem comentários
        case FEATURE_RATE:
            return false; // Sem avaliação
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT; // Descreve o propósito do módulo
        default:
            return null;
    }
}

/**
 * Define todas as regras de conclusão para este módulo.
 * Retorna um array com os nomes das regras de conclusão personalizadas (além de visualização/nota)
 * Esta função é necessária para o Moodle 4.x reconhecer corretamente as regras.
 *
 * @return array Array de strings definindo as regras
 */
function bunnyvideo_get_completion_rules()
{
    return ['completionpercent'];
}

/**
 * Adiciona a regra de conclusão personalizada aos elementos do formulário.
 *
 * @param object $mform O objeto do formulário
 */
function bunnyvideo_add_completion_rules($mform)
{
    $mform->addElement('text', 'completionpercent', get_string('completionpercent', 'bunnyvideo'), ['size' => 3]);
    $mform->setType('completionpercent', PARAM_INT);
    $mform->addHelpButton('completionpercent', 'completionpercent', 'bunnyvideo');
    $mform->setDefault('completionpercent', 0);
    $mform->addRule('completionpercent', null, 'numeric', null, 'client');
    $mform->addRule('completionpercent', get_string('err_numeric', 'form'), 'numeric', null, 'server');
    $mform->setAdvanced('completionpercent');

    return ['completionpercent'];
}

/**
 * Retorna uma descrição das regras de conclusão personalizadas.
 *
 * @param array $rules As regras conforme retornadas pela função completion_rules deste módulo
 * @param object $cm A instância do módulo do curso
 * @return array Array de descrições de string das regras
 */
function bunnyvideo_completion_rule_description($rules)
{
    $descriptions = [];

    if (!empty($rules['completionpercent'])) {
        if ($rules['completionpercent'] > 0) {
            $descriptions[] = get_string('completionpercenthelp', 'bunnyvideo', $rules['completionpercent']);
        }
    }

    return $descriptions;
}

/**
 * Retorna o estado de conclusão para a atividade, usuário e contexto do curso fornecidos.
 * Usado pelo relatório de conclusão e outras áreas para verificar o status.
 * Nosso JS/AJAX lida com a marcação real com base na porcentagem. O Moodle lida com a conclusão por visualização.
 *
 * @param object $course Objeto do curso
 * @param object $cm Objeto do módulo do curso
 * @param int $userid ID do usuário
 * @param bool $type Tipo de comparação (normalmente nulo)
 * @return stdClass|bool Um objeto com os campos 'state' e 'time', ou booleano para regras simples
 */
function bunnyvideo_get_completion_state($course, $cm, $userid, $type)
{
    global $DB;

    // Obtém o estado de conclusão atual do Moodle
    $completion = new completion_info($course);
    $current = $completion->get_data($cm, false, $userid);

    // Para marcação manual ou solicitação de estado geral (tipo nulo)
    if (empty($type) || $type == 'completionpercent') {
        // Busca o registro bunnyvideo para verificação de porcentagem
        $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'completionpercent');

        // Se for conclusão manual, devemos honrar o estado atual
        // Isso permite que as substituições do administrador funcionem na interface de relatórios
        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            return $current;
        }

        // Se estivermos usando conclusão automática com regra de porcentagem
        if (
            $cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
            !empty($bunnyvideo->completionpercent) &&
            $bunnyvideo->completionpercent > 0
        ) {

            // Verifica se existe um registro REAL de conclusão na tabela course_modules_completion
            // Isso evita que atividades novas apareçam como completadas para alunos existentes
            $existing = $DB->get_record('course_modules_completion', array(
                'coursemoduleid' => $cm->id,
                'userid' => $userid
            ));

            if ($existing && $existing->completionstate == COMPLETION_COMPLETE) {
                // Existe um registro real de conclusão (definido pelo AJAX do player)
                return $current;
            }

            // Sem registro de conclusão real - retorna explicitamente INCOMPLETE
            $result = new stdClass();
            $result->completionstate = COMPLETION_INCOMPLETE;
            $result->timemodified = 0;
            return $result;
        }

        // CORREÇÃO ADICIONAL: Força o estado incompleto se a única razão para a conclusão
        // seria a conclusão por visualização (completionview) que desabilitamos
        if (
            $current->completionstate == COMPLETION_COMPLETE &&
            $cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
            $cm->completionview == 1 &&
            (empty($bunnyvideo->completionpercent) || $bunnyvideo->completionpercent <= 0)
        ) {

            $state = new stdClass();
            $state->completionstate = COMPLETION_INCOMPLETE;
            $state->timemodified = 0;
            debugging('BunnyVideo: Forcing INCOMPLETE for improper view completion.');
            return $state;
        }
    }

    // Retorna o estado atual para todos os outros casos
    return $current;
}

/**
 * Função de callback quando o formulário de configurações de conclusão é processado.
 * Usado para redefinir o status de conclusão se as configurações mudarem significativamente.
 *
 * @param object $data Dados retornados do formulário. Contém campos de conclusão padrão como $data->completion...
 * @param object $cm O objeto do módulo do curso.
 * @param int $completion Estado atual da configuração de conclusão (antes de salvar).
 * @param bool $enabled Se a conclusão está habilitada (antes de salvar).
 * @return bool True se houver alterações que exigem redefinição da conclusão.
 */
function bunnyvideo_cm_completion_settings_changed($data, $cm, $completion, $enabled)
{
    global $DB;

    // Obtém as configurações salvas anteriormente para comparação
    $oldbunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'completionpercent', MUST_EXIST);
    $oldpercent = $oldbunnyvideo->completionpercent;

    // Obtém o novo valor percentual dos dados enviados
    $newpercent = isset($data->completionpercent) ? (int) $data->completionpercent : 0;
    // Força 0 se a conclusão estiver desabilitada nos dados enviados
    if (isset($data->completion) && $data->completion == COMPLETION_TRACKING_NONE) {
        $newpercent = 0;
    }

    // Verifica se o requisito de porcentagem foi adicionado, removido ou teve o valor alterado *enquanto estava ativo*
    $reset = false;
    if (($oldpercent > 0 && $newpercent == 0) || ($oldpercent == 0 && $newpercent > 0) || ($oldpercent > 0 && $newpercent > 0 && $oldpercent != $newpercent)) {
        // O próprio requisito de porcentagem mudou significativamente
        $reset = true;
    }

    // Considera também se outras regras de conclusão mudaram (ex: 'exigir visualização' adicionado/removido)
    // A função padrão do Moodle lida com a redefinição com base na habilitação/desabilitação geral da conclusão.
    // Retornamos true se *nossa* regra específica (porcentagem) mudou de forma significativa.
    return $reset;
}

// REMOVIDA: bunnyvideo_update_completion_state_settings()
// Essa função chamava mark_completion_state_updated() que não é uma função padrão do Moodle.
// A lógica de reset de conclusão ao alterar configurações agora é tratada diretamente
// em bunnyvideo_update_instance().

/**
 * Retorna a descrição da regra de conclusão ativa para esta instância do módulo.
 * Usado em relatórios como Conclusão de Atividade.
 *
 * @param cm_info|stdClass $cm Objeto do módulo do curso
 * @param bool $showdescription Se deve mostrar a descrição completa (não apenas o título)
 * @return string|null Descrição da regra, ou nulo se nenhuma se aplica especificamente além da visualização
 */
function bunnyvideo_get_completion_active_rule($cm, $showdescription)
{
    global $DB, $CFG;

    // Fornece uma descrição de regra específica apenas se a conclusão automática for usada
    if ($cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return null;
    }

    // Obtém a instância bunnyvideo para verificar a porcentagem
    $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'id, completionpercent');

    // Constrói um array de descrições de regras
    $rules = [];

    // Verifica se a regra de porcentagem está habilitada para esta instância 
    if (!empty($bunnyvideo->completionpercent) && intval($bunnyvideo->completionpercent) > 0) {
        // Adiciona a descrição da regra de porcentagem
        $rules[] = get_string('completionrulenamepercent', 'bunnyvideo', $bunnyvideo->completionpercent);
    }

    // Retorna todas as regras como uma string formatada
    if (!empty($rules)) {
        if (count($rules) == 1) {
            return $rules[0]; // Apenas retorna a regra única
        } else {
            // Formata múltiplas regras se necessário (improvável para nosso módulo)
            return implode(', ', $rules);
        }
    }

    return null; // Nenhuma regra ativa
}

/**
 * Retorna o estado de conclusão para qualquer critério (incluindo os do núcleo) que não tenha
 * um método mais específico.
 *
 * @param stdClass $course Curso
 * @param cm_info|stdClass $cm     Atividade
 * @param int $userid ID do usuário
 * @param string $type Tipo de critério
 * @return bool
 */
function bunnyvideo_get_completion_state_no_rule($course, $cm, $userid, $type)
{
    global $DB;

    $bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);

    // Se a conclusão não estiver habilitada para esta atividade, apenas retorna o estado
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        return COMPLETION_INCOMPLETE;
    }

    // Lida com a conclusão baseada em porcentagem
    if ($type == 'completionpercentprogress') {
        // Só chegamos aqui se a regra de porcentagem estiver ativa e precisarmos verificá-la
        if (!empty($bunnyvideo->completionpercent)) {
            // Em um cenário real, verificaríamos os dados de progresso do usuário em nossas tabelas
            // Por enquanto, como só queremos exibir a regra, podemos retornar false
            return COMPLETION_INCOMPLETE;
        }
    }

    // Para qualquer outro tipo, delega para a função pai
    return false;
}

/**
 * Obtém estilos CSS para o módulo bunnyvideo
 *
 * @return array Array de arquivos CSS para incluir
 */
function bunnyvideo_get_styles()
{
    return [new moodle_url('/mod/bunnyvideo/styles.css')];
}

/**
 * Retorna informações sobre este módulo para as informações do módulo do curso.
 * Usado pelo Moodle para exibir o módulo nas listagens do curso.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null Informações para exibir para a instância do módulo.
 */
function bunnyvideo_get_coursemodule_info($coursemodule)
{
    global $DB;

    // Obtém informações básicas do banco de dados
    if (
        !$bunnyvideo = $DB->get_record(
            'bunnyvideo',
            ['id' => $coursemodule->instance],
            'id, name, intro, introformat'
        )
    ) {
        return null;
    }

    // Inicializa o objeto de resultado
    $info = new cached_cm_info();
    $info->name = $bunnyvideo->name;

    // Define o conteúdo se a introdução existir e showdescription estiver habilitado
    if ($coursemodule->showdescription && $bunnyvideo->intro) {
        // Converte a introdução para html
        $info->content = format_module_intro('bunnyvideo', $bunnyvideo, $coursemodule->id, false);
    }

    // IMPORTANTE: Forçar visibilidade para todos os usuários
    // Isso corrige problemas com o módulo não aparecendo para alunos
    $info->visible = true;
    $info->visibleoncoursepage = true;

    // Configurações personalizadas adicionais
    $info->customdata = [
        'visible_to_all' => true,
    ];

    return $info;
}

/**
 * Estende a navegação do curso com o módulo BunnyVideo
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 */
function bunnyvideo_extend_navigation_course($parentnode, $course, $context)
{
    // Esta função garante que o módulo apareça na navegação do curso
    global $DB;

    // Garante que módulos de atividade BunnyVideo apareçam para alunos
    if (has_capability('mod/bunnyvideo:view', $context)) {
        // Normalmente não precisamos fazer nada aqui, mas incluir a função
        // sinaliza para o Moodle que o módulo deve ser considerado na navegação
    }
}

/**
 * Estende a navegação com as configurações do bunnyvideo
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $videonode
 */
function bunnyvideo_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $videonode)
{
    global $PAGE;

    // Nada especial para fazer aqui, esta função é principalmente um placeholder
    // para garantir integração completa com a navegação do Moodle
}

// A função bunnyvideo_extend_navigation_completion pode ser deixada vazia por enquanto
/**
 * Adiciona links de navegação
 * @param object $node O objeto do nó de navegação
 * @param string $context A string de contexto
 */
// function bunnyvideo_extend_navigation_completion($node, $context) { } // Não é estritamente necessário agora

// Adiciona outras funções lib necessárias como placeholders de backup/restauração se necessário.
// O Moodle pode frequentemente autogerar manipuladores básicos de backup/restauração se a estrutura existir.
