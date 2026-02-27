<?php
namespace mod_bunnyvideo\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/bunnyvideo/lib.php'); // Inclui a lib do módulo

class completion_ajax extends \external_api
{

    /**
     * Define os parâmetros para a função mark_complete.
     * @return \external_function_parameters
     */
    public static function mark_complete_parameters()
    {
        return new \external_function_parameters(
            array(
                'cmid' => new \external_value(PARAM_INT, 'O ID do módulo do curso')
            )
        );
    }

    /**
     * Marca a atividade como concluída.
     * @param int $cmid ID do módulo do curso.
     * @return array Status de sucesso e mensagem opcional.
     */
    public static function mark_complete($cmid)
    {
        global $USER, $DB;

        // Valida os parâmetros.
        $params = self::validate_parameters(self::mark_complete_parameters(), array('cmid' => $cmid));

        // Obtém o contexto e verifica as capacidades.
        $cm = get_coursemodule_from_id('bunnyvideo', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Verifica se o usuário tem capacidade de visualizar (implicitamente necessário para interagir)
        require_capability('mod/bunnyvideo:view', $context);

        // Obtém a instância da atividade
        $bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);

        // Verifica se a conclusão está habilitada e usa porcentagem
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm) || $bunnyvideo->completionpercent <= 0) {
            // Conclusão não habilitada ou não baseada em porcentagem para esta atividade
            return array(
                'success' => false,
                'message' => get_string('error_cannotmarkcomplete', 'mod_bunnyvideo') . ' (Conclusão não habilitada ou não baseada em porcentagem)'
            );
        }

        // Verifica o estado atual da conclusão
        $currentcompletion = $completion->get_data($cm, $USER->id, true); // Obtém o estado atual, ignora o cache

        if ($currentcompletion->completionstate == COMPLETION_COMPLETE) {
            // Já concluído, nada a fazer. Retorna sucesso.
            \core\session\manager::write_close(); // Libera o bloqueio da sessão mais cedo
            return array('success' => true, 'message' => 'Já concluído.');
        }

        // --- Registra o progresso na tabela bunnyvideo_progress ---
        // Isso é necessário ANTES de chamar update_state() porque
        // update_state() chama get_state() que verifica bunnyvideo_progress.
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
            $progress = new \stdClass();
            $progress->bunnyvideoid = $bunnyvideo->id;
            $progress->userid = $USER->id;
            $progress->completionmet = 1;
            $progress->timemodified = time();
            $DB->insert_record('bunnyvideo_progress', $progress);
        }

        // --- Marca a atividade como concluída ---
        // Agora update_state() chamará get_state() que encontrará o registro
        // em bunnyvideo_progress e retornará COMPLETION_COMPLETE.
        try {
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            \core\session\manager::write_close();
            return array('success' => true);
        } catch (\Exception $e) {
            \core\session\manager::write_close();
            return array(
                'success' => false,
                'message' => get_string('error_cannotmarkcomplete', 'mod_bunnyvideo') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    /**
     * Define o valor de retorno para a função mark_complete.
     * @return \external_single_structure
     */
    public static function mark_complete_returns()
    {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Verdadeiro se a conclusão foi marcada com sucesso'),
                'message' => new \external_value(PARAM_TEXT, 'Mensagem opcional (ex: detalhes do erro)', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Define os parâmetros para a função toggle_completion.
     * @return \external_function_parameters
     */
    public static function toggle_completion_parameters()
    {
        return new \external_function_parameters(
            array(
                'cmid' => new \external_value(PARAM_INT, 'O ID do módulo do curso'),
                'userid' => new \external_value(PARAM_INT, 'O ID do usuário para alternar a conclusão'),
                'newstate' => new \external_value(PARAM_INT, 'O novo estado de conclusão (0=incompleto, 1=completo)')
            )
        );
    }

    /**
     * Alterna o status de conclusão para um usuário especificado (somente professor/admin).
     * @param int $cmid ID do módulo do curso.
     * @param int $userid ID do usuário para alternar a conclusão.
     * @param int $newstate Novo estado de conclusão (0=incompleto, 1=completo).
     * @return array Status de sucesso e mensagem opcional.
     */
    public static function toggle_completion($cmid, $userid, $newstate)
    {
        global $DB, $CFG;

        // Valida os parâmetros.
        $params = self::validate_parameters(self::toggle_completion_parameters(), array(
            'cmid' => $cmid,
            'userid' => $userid,
            'newstate' => $newstate
        ));

        // Obtém o módulo do curso, contexto e curso.
        $cm = get_coursemodule_from_id('bunnyvideo', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        // Verifica as capacidades para alternar a conclusão de outro usuário.
        require_capability('mod/bunnyvideo:managecompletion', $context);

        // Obtém informações de conclusão.
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm)) {
            return array(
                'success' => false,
                'message' => get_string('completionnotenabled', 'completion')
            );
        }

        // Debug logs para verificar o que está sendo enviado
        debugging('BunnyVideo - Toggle solicitado - cmid: ' . $params['cmid'] .
            ', userid: ' . $params['userid'] .
            ', newstate: ' . $params['newstate'], DEBUG_DEVELOPER);

        // Verificar o estado atual de conclusão
        $currentdata = $completion->get_data($cm, true, $params['userid']); // Forçar recarregamento
        debugging('BunnyVideo - Estado atual: ' . $currentdata->completionstate .
            ' - Tentando alterar para: ' . $params['newstate'], DEBUG_DEVELOPER);
        // Mapeia o parâmetro newstate para as constantes de conclusão do Moodle
        $completionstate = ($newstate == 1) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

        try {
            // Atualiza o estado de conclusão usando a API do Moodle
            $completion->update_state($cm, $completionstate, $userid, true);

            // Limpa o cache de conclusão
            $cache = \cache::make('core', 'completion');
            $cache->purge();

            // Opcionalmente, limpa o cache de navegação se necessário
            // $cache = \cache::make('core', 'navigation_course');
            // $cache->purge();

            \core\session\manager::write_close();
            return array(
                'success' => true,
                'message' => get_string('completion_updated', 'mod_bunnyvideo')
            );
        } catch (\Exception $e) {
            \core\session\manager::write_close();
            return array(
                'success' => false,
                'message' => get_string('completion_toggle_error', 'mod_bunnyvideo') . ' (' . $e->getMessage() . ')'
            );
        }

    }

    /**
     * Define o valor de retorno para a função toggle_completion.
     * @return \external_single_structure
     */
    public static function toggle_completion_returns()
    {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Verdadeiro se a conclusão foi alternada com sucesso'),
                'message' => new \external_value(PARAM_TEXT, 'Mensagem de status ou detalhes do erro', VALUE_OPTIONAL)
            )
        );
    }
}
