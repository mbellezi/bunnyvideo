<?php
namespace mod_bunnyvideo\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/mod/bunnyvideo/lib.php'); // Include module lib

class completion_ajax extends \external_api {

    /**
     * Define parameters for the mark_complete function.
     * @return \external_function_parameters
     */
    public static function mark_complete_parameters() {
        return new \external_function_parameters(
            array(
                'cmid' => new \external_value(PARAM_INT, 'The course module ID')
            )
        );
    }

    /**
     * Mark the activity complete.
     * @param int $cmid Course module ID.
     * @return array Success status and optional message.
     */
    public static function mark_complete($cmid) {
        global $USER, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::mark_complete_parameters(), array('cmid' => $cmid));

        // Get context and check capabilities.
        $cm = get_coursemodule_from_id('bunnyvideo', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user has capability to view (implicitly required to interact)
        require_capability('mod/bunnyvideo:view', $context);

        // Get activity instance
        $bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);

        // Check if completion is enabled and uses percentage
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm) || $bunnyvideo->completionpercent <= 0) {
            // Completion not enabled or not based on percentage for this activity
             return array(
                 'success' => false,
                 'message' => get_string('error_cannotmarkcomplete', 'mod_bunnyvideo') . ' (Completion not enabled or not percentage based)'
             );
        }

        // Check current completion state
        $currentcompletion = $completion->get_data($cm, $USER->id, true); // Get current state, ignore cache

        if ($currentcompletion->completionstate == COMPLETION_COMPLETE) {
             // Already complete, nothing to do. Return success.
             \core\session\manager::write_close(); // Release session lock early
             return array('success' => true, 'message' => 'Already complete.');
        }

        // --- Mark the activity as complete ---
        // Use the Moodle completion API
        try {
             $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
             \core\session\manager::write_close(); // Release session lock early
             // Trigger completion updated event maybe? Moodle might do this automatically.
             // \completion_info::update_ Moodle >= 4.0 ?
             return array('success' => true);
        } catch (\Exception $e) {
            \core\session\manager::write_close(); // Release session lock early
             return array(
                 'success' => false,
                 'message' => get_string('error_cannotmarkcomplete', 'mod_bunnyvideo') . ' (' . $e->getMessage() . ')'
             );
        }
    }

    /**
     * Define the return value for the mark_complete function.
     * @return \external_single_structure
     */
    public static function mark_complete_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'True if the completion was marked successfully'),
                'message' => new \external_value(PARAM_TEXT, 'Optional message (e.g., error details)', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Define parameters for the toggle_completion function.
     * @return \external_function_parameters
     */
    public static function toggle_completion_parameters() {
        return new \external_function_parameters(
            array(
                'cmid' => new \external_value(PARAM_INT, 'The course module ID'),
                'userid' => new \external_value(PARAM_INT, 'The user ID to toggle completion for'),
                'newstate' => new \external_value(PARAM_INT, 'The new completion state (0=incomplete, 1=complete)')
            )
        );
    }

    /**
     * Toggle completion status for a specified user (teacher/admin only).
     * @param int $cmid Course module ID.
     * @param int $userid User ID to toggle completion for.
     * @param int $newstate New completion state (0=incomplete, 1=complete).
     * @return array Success status and optional message.
     */
    public static function toggle_completion($cmid, $userid, $newstate) {
        global $DB, $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::toggle_completion_parameters(), array(
            'cmid' => $cmid,
            'userid' => $userid,
            'newstate' => $newstate
        ));

        // Get course module, context and course.
        $cm = get_coursemodule_from_id('bunnyvideo', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        // Check capabilities to toggle completion for another user.
        require_capability('mod/bunnyvideo:managecompletion', $context);

        // Get completion info.
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

        // Map the newstate parameter to Moodle completion constants
        $completionstate = ($params['newstate'] == 1) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        
        // Estratégia explícita para marcar como incompleto
        if ($completionstate == COMPLETION_INCOMPLETE) {
            try {
                debugging('BunnyVideo - INICIANDO processo para marcar como INCOMPLETO', DEBUG_DEVELOPER);
                
                // IMPORTANTE: Precisamos GARANTIR que o registro seja removido
                // 1. Remover diretamente do banco de dados primeiro
                $deleted = $DB->delete_records('course_modules_completion', array(
                    'coursemoduleid' => $cm->id,
                    'userid' => $params['userid']
                ));
                debugging('BunnyVideo - Registros excluídos: ' . ($deleted ? 'Sim' : 'Não'), DEBUG_DEVELOPER);
                
                // 2. Tentar a API também para garantir consistência
                try {
                    $completion->update_state($cm, COMPLETION_INCOMPLETE, $params['userid']);
                    debugging('BunnyVideo - API update_state concluído', DEBUG_DEVELOPER);
                } catch (\Exception $e) {
                    debugging('BunnyVideo - API update_state falhou, mas continuando: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    // Continuar mesmo se falhar aqui - o importante é que o registro foi excluído
                }
                
                // Limpar todos os caches específicos de navegação do Moodle
                // Isso é crucial para que os ícones de conclusão sejam atualizados no menu lateral
                if (class_exists('\core\navigation\cache')) {
                    try {
                        \core\navigation\cache::clear();
                    } catch (\Exception $e) {
                        debugging('BunnyVideo - Erro ao limpar cache de navegação: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
                
                // Usar uma abordagem mais segura para limpar caches
                try {
                    // Limpar apenas caches de navegação conhecidos
                    $cachenames = [
                        ['core', 'navigation_course'], 
                        ['core', 'course_image'],
                        ['core', 'completion'],
                    ];
                    
                    foreach ($cachenames as $cachedef) {
                        try {
                            $cache = \cache::make($cachedef[0], $cachedef[1]);
                            if ($cache) {
                                $cache->purge();
                                debugging('BunnyVideo - Cache ' . $cachedef[0] . '/' . $cachedef[1] . ' limpo com sucesso', DEBUG_DEVELOPER);
                            }
                        } catch (\Exception $e) {
                            // Ignorar erros individuais de cache
                            debugging('BunnyVideo - Erro ao limpar cache ' . $cachedef[0] . '/' . $cachedef[1] . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                        }
                    }
                } catch (\Exception $e) {
                    debugging('BunnyVideo - Erro geral ao limpar caches: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
                
                // Liberar lock da sessão - importante para evitar bloqueios
                \core\session\manager::write_close();
                
                // 3. Verificar o estado final
                $check_completion = new \completion_info($course);
                $finalstate = $check_completion->get_data($cm, true, $params['userid']);
                debugging('BunnyVideo - Estado FINAL após remoção: ' . $finalstate->completionstate, DEBUG_DEVELOPER);
                
                return array(
                    'success' => true,
                    'message' => get_string('completion_updated', 'mod_bunnyvideo')
                );
            } catch (\Exception $e) {
                \core\session\manager::write_close();
                debugging('BunnyVideo - ERRO ao marcar como incompleto: ' . $e->getMessage(), DEBUG_DEVELOPER);
                return array(
                    'success' => false,
                    'message' => get_string('completion_toggle_error', 'mod_bunnyvideo') . ' (' . $e->getMessage() . ')'
                );
            }
        } else {
            // Para marcar como completo, usar o método padrão
            try {
                $completion->update_state($cm, $completionstate, $params['userid']);
                \core\session\manager::write_close(); // Release session lock early
                
                return array(
                    'success' => true,
                    'message' => get_string('completion_updated', 'mod_bunnyvideo')
                );
            } catch (\Exception $e) {
                \core\session\manager::write_close(); // Release session lock early
                return array(
                    'success' => false,
                    'message' => get_string('completion_toggle_error', 'mod_bunnyvideo') . ' (' . $e->getMessage() . ')'
                );
            }
        }
    }

    /**
     * Define the return value for the toggle_completion function.
     * @return \external_single_structure
     */
    public static function toggle_completion_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'True if the completion was toggled successfully'),
                'message' => new \external_value(PARAM_TEXT, 'Status message or error details', VALUE_OPTIONAL)
            )
        );
    }
}
