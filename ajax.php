<?php
// Este arquivo é parte do Moodle - http://moodle.org/
//
// O Moodle é software livre: você pode redistribuí-lo e/ou modificá-lo
// sob os termos da Licença Pública Geral GNU conforme publicada pela
// Free Software Foundation, seja a versão 3 da Licença, ou
// (a seu critério) qualquer versão posterior.
//
// O Moodle é distribuído na esperança de que seja útil,
// mas SEM QUALQUER GARANTIA; sem mesmo a garantia implícita de
// COMERCIALIZAÇÃO ou ADEQUAÇÃO A UM DETERMINADO FIM. Consulte a
// Licença Pública Geral GNU para obter mais detalhes.
//
// Você deve ter recebido uma cópia da Licença Pública Geral GNU
// junto com o Moodle. Caso contrário, consulte <http://www.gnu.org/licenses/>.

/**
 * Manipulador AJAX para operações do player de vídeo Bunny
 *
 * @package    mod_bunnyvideo
 * @copyright  2025 Marcos Bellezi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 ou posterior
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Obtém e valida os parâmetros
$action = required_param('action', PARAM_ALPHA);
$cmid = required_param('cmid', PARAM_INT);

// Saída de depuração para solução de problemas
$response = array('success' => false, 'message' => '');
$response['debug'] = array(
    'received_action' => $action,
    'action_type' => gettype($action),
    'received_cmid' => $cmid,
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
);

// Requer uma chave de sessão válida
require_sesskey();

// Valida o módulo do curso
$cm = get_coursemodule_from_id('bunnyvideo', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);

// Configura o contexto apropriado
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

// Verifica se o usuário está logado
require_login($course, false, $cm);

// Prepara a resposta

if ($action === 'mark_complete' || $action === 'markcomplete') {
    // Verifica se a conclusão está habilitada para este curso e módulo
    $completion = new completion_info($course);

    if (!$completion->is_enabled($cm)) {
        $response['message'] = get_string('completionnotenabled', 'completion');
    } else {
        // Salva o estado atual para verificar se já está concluído
        $current = $completion->get_data($cm, false, $USER->id);
        $was_complete = $current->completionstate == COMPLETION_COMPLETE;

        // Registra o progresso na tabela bunnyvideo_progress ANTES de chamar update_state.
        // Isso é necessário porque update_state() chama get_state() da classe custom_completion,
        // que verifica bunnyvideo_progress para determinar se o aluno completou.
        $progressrecord = $DB->get_record('bunnyvideo_progress', [
            'bunnyvideoid' => $bunnyvideo->id,
            'userid' => $USER->id,
        ]);

        if ($progressrecord) {
            if ($progressrecord->completionmet != 1) {
                $progressrecord->completionmet = 1;
                $progressrecord->timemodified = time();
                $DB->update_record('bunnyvideo_progress', $progressrecord);
            }
        } else {
            $progress = new stdClass();
            $progress->bunnyvideoid = $bunnyvideo->id;
            $progress->userid = $USER->id;
            $progress->completionmet = 1;
            $progress->timemodified = time();
            $DB->insert_record('bunnyvideo_progress', $progress);
        }

        // Agora chama update_state() — o Moodle chamará get_state() que encontrará
        // o registro em bunnyvideo_progress e retornará COMPLETION_COMPLETE.
        $completion->update_state($cm, COMPLETION_COMPLETE);
        $response['success'] = true;

        // Mensagem diferente com base em se já estava concluído
        if ($was_complete) {
            $response['message'] = get_string('completion-y', 'core_completion');
            $response['already_complete'] = true;
        } else {
            $response['message'] = get_string('completed', 'completion');
            $response['already_complete'] = false;
            $response['event_logged'] = false;
            $response['note'] = 'Event logging skipped to avoid badge observer errors';
        }
    }
} else {
    $response['message'] = 'Invalid action';
}

// Sempre retorna JSON
header('Content-Type: application/json');
echo json_encode($response);
