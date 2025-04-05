<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/completionlib.php');
// Assegure que a classe de evento seja carregada pelo autoloader
// require_once($CFG->dirroot . '/mod/bunnyvideo/classes/event/course_module_viewed.php'); // Não é mais necessário aqui

/**
 * @param stdClass $bunnyvideo A bunnyvideo instance from the database
 * @param stdClass $cm The course module instance this belongs to
 * @param context_module $context The context of the module instance
 * @param array $options Other display options
 * @return string HTML output for the activity page.
 */
function bunnyvideo_view($bunnyvideo, $cm, $context, $options=null) {
    global $CFG, $PAGE, $OUTPUT, $USER, $DB; // Added $DB global

    // Get the course data - needed for record snapshots
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    try {
        // Trigger course_module_viewed event. Standard Moodle logging.
        $event = \mod_bunnyvideo\event\course_module_viewed::create(array(
            'objectid' => $bunnyvideo->id,
            'context' => $context,
            'other' => array('userid' => $USER->id)
        ));
        
        // Add record snapshots with error handling
        try {
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('bunnyvideo', $bunnyvideo);
            $event->trigger();
        } catch (Exception $e) {
            // Log error but continue - don't let event failure prevent viewing
            debugging('Error in event logging: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } catch (Exception $e) {
        // Just log error but continue loading the page
        debugging('Error creating event: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // ABORDAGEM RADICAL: Impedir COMPLETAMENTE qualquer tentativa de marcar a visualização
    // NÃO USAR $completion->set_module_viewed($cm) em NENHUMA circunstância
    $completion = new completion_info($course);
    
    // MODIFICAÇÃO CRÍTICA: Zerar completionstate se for baseado em visualização
    // Verificar se a atividade acabou de ser marcada como concluída no banco de dados
    // Se foi marcada como concluída por visualização, desfazer essa marcação
    $completiondata = $completion->get_data($cm, false, $USER->id);
    if ($completiondata->completionstate == COMPLETION_COMPLETE && 
        $cm->completion == COMPLETION_TRACKING_AUTOMATIC && 
        $cm->completionview == 1 && 
        empty($bunnyvideo->completionpercent)) {
        
        // Apagar o registro de conclusão por visualização 
        // diretamente, sem usar APIs que podem reativar a conclusão
        $DB->delete_records('course_modules_completion', array(
            'coursemoduleid' => $cm->id,
            'userid' => $USER->id
        ));
        
        // Recarregar os dados de conclusão para garantir que estejam limpos
        $completiondata = $completion->get_data($cm, true, $USER->id);
        debugging('BunnyVideo: Impedir conclusão automática por visualização');
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
    
    // Check if the current user can manage completion (teacher/admin)
    $canmanagecompletion = has_capability('mod/bunnyvideo:managecompletion', $context);
    
    // Add toggle button for teachers/admins
    if ($canmanagecompletion && $cm->completion != COMPLETION_TRACKING_NONE) {
        // Create button with opposite action of current state
        $buttonlabel = ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) 
            ? get_string('mark_incomplete', 'mod_bunnyvideo') 
            : get_string('mark_complete', 'mod_bunnyvideo');
        
        $newstate = ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) 
            ? COMPLETION_INCOMPLETE 
            : COMPLETION_COMPLETE;
        
        // Add button to toggle completion status
        $togglebutton = '<div class="completion-toggle-container float-right" style="margin-top: -40px; position: relative;">';
        $togglebutton .= '<button type="button" class="btn btn-sm ' . 
            (($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) ? 'btn-warning' : 'btn-success') . 
            ' completion-toggle-button" data-cmid="' . $cm->id . '" data-userid="' . $USER->id . '" data-newstate="' . $newstate . '">';
        $togglebutton .= $buttonlabel . '</button>';
        $togglebutton .= '</div>';
        
        // Add the toggle button to the status message
        if (!empty($statusmessage)) {
            // Insert the button into the existing status message
            $statusmessage = str_replace('</div>', $togglebutton . '</div>', $statusmessage);
        } else {
            // Create a new status container if there wasn't one
            $statusmessage = '<div class="alert alert-info">' . $togglebutton . '</div>';
        }
    }

    // Prepare data for JavaScript
    $jsdata = [
        'cmid' => $cm->id,
        'bunnyvideoid' => $bunnyvideo->id,
        'completionPercent' => $bunnyvideo->completionpercent > 0 ? (int)$bunnyvideo->completionpercent : 0,
        'contextid' => $context->id,
    ];

    // --- Geração do HTML usando $OUTPUT (Renderer Padrão) ---
    $html = '';
    
    // Embed the Player.js script directly in the HTML output
    $html .= '<script src="https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js"></script>';
    
    // Then add our handler script - this ensures proper order
    $html .= '<script src="'.$CFG->wwwroot.'/mod/bunnyvideo/js/player_handler.js?v='.time().'"></script>';
    
    // Add the completion toggle script if the user can manage completion
    if ($canmanagecompletion && $cm->completion != COMPLETION_TRACKING_NONE) {
        $html .= '<script src="'.$CFG->wwwroot.'/mod/bunnyvideo/js/completion_toggle.js?v='.time().'"></script>';
    }
    
    // Only initialize after everything is loaded
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Make sure everything is available
            if (typeof BunnyVideoHandler !== "undefined") {
                console.log("BunnyVideo: Initializing handler after DOM loaded");
                BunnyVideoHandler.init('.json_encode($jsdata).');
            } else {
                console.error("BunnyVideo: Handler not found even after direct embedding");
            }
        });
    </script>';
    
    // Formata o nome e a introdução
    $name = format_string($bunnyvideo->name, true, ['context' => $context]);
    $intro = format_module_intro('bunnyvideo', $bunnyvideo, $cm->id);
    
    // Formata o embed code (com cuidado - veja notas anteriores sobre segurança/confiança)
    $embedcode_html = format_text($bunnyvideo->embedcode, FORMAT_HTML, ['trusted' => true, 'noclean' => true, 'context' => $context]);

    // Monta o conteúdo HTML usando $OUTPUT e html_writer
    $content = '';
    $content .= $OUTPUT->box_start('generalbox boxaligncenter mod_bunnyvideo_content', 'bunnyvideocontent-'.$bunnyvideo->id); // ID único para o container geral

    // Adiciona a mensagem de status de conclusão ANTES do conteúdo
    $content .= $statusmessage;
    
    // if (trim(strip_tags($intro))) {
    //      $content .= $OUTPUT->box($intro, 'mod_introbox'); // Caixa para a introdução
    // }
    
    // Adiciona um wrapper DIV com ID único para o JS encontrar o iframe facilmente
    $content .= '<div id="bunnyvideo-player-' . $bunnyvideo->id . '" class="bunnyvideo-player-wrapper">';
    $content .= $html; // Add our scripts before the embed code
    $content .= $embedcode_html;
    $content .= '</div>';
    
    $content .= $OUTPUT->box_end();

    // Retorna o HTML gerado
    return $content;

    // --- FIM da Geração do HTML ---

    /* REMOVIDO O BLOCO QUE TENTAVA USAR RENDERER/TEMPLATE:
    // Render the output using a template (optional but recommended)
    // $output = $PAGE->get_renderer('mod_bunnyvideo'); // <<< LINHA REMOVIDA
    // ... lógica if/else removida ...
    */
}

/**
 * Standard Moodle function: Add a new instance of the activity.
 * @param stdClass $bunnyvideo Object from form.
 * @param mod_bunnyvideo_mod_form $mform Form definition.
 * @return int The ID of the newly created instance.
 */
function bunnyvideo_add_instance($bunnyvideo, $mform) {
    global $DB;

    $bunnyvideo->timecreated = time();
    $bunnyvideo->timemodified = $bunnyvideo->timecreated;

    // completionpercent is now handled correctly by data_postprocessing in mod_form.php
    // No need to check/unset 'completionwhenpercentreached' here anymore.

    $bunnyvideo->id = $DB->insert_record('bunnyvideo', $bunnyvideo);

    // Process completion settings saved by standard_completion_elements
    $cmid = $bunnyvideo->coursemodule; // Passed in $bunnyvideo by moodleform_mod
    
    // NOTE: Removed call to completion->update_completion_rules which isn't available
    // Moodle will automatically handle the standard completion settings
    // The completionpercent field is stored in our bunnyvideo table and used by our JS

    return $bunnyvideo->id;
}

/**
 * Standard Moodle function: Update an existing instance.
 * @param stdClass $bunnyvideo Object from form.
 * @param mod_bunnyvideo_mod_form $mform Form definition.
 * @return bool True if successful.
 */
function bunnyvideo_update_instance($bunnyvideo, $mform) {
    global $DB;

    $bunnyvideo->timemodified = time();
    $bunnyvideo->id = $bunnyvideo->instance; // Passed in $bunnyvideo by moodleform_mod

    // completionpercent is now handled correctly by data_postprocessing in mod_form.php
    // No need to check/unset 'completionwhenpercentreached' here anymore.

    $result = $DB->update_record('bunnyvideo', $bunnyvideo);

    // Process completion settings saved by standard_completion_elements
    $cmid = $bunnyvideo->coursemodule; // Passed in $bunnyvideo by moodleform_mod
    
    // NOTE: Removed call to completion->update_completion_rules which isn't available 
    // Moodle will automatically handle the standard completion settings
    // The completionpercent field is stored in our bunnyvideo table and used by our JS

    return $result;
}

/**
 * Standard Moodle function: Delete an instance.
 * @param int $id The instance ID.
 * @return bool True if successful.
 */
function bunnyvideo_delete_instance($id) {
    global $DB;

    if (! $bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $id))) {
        return false;
    }

    // Standard deletion - Moodle handles related completion data etc.
    $DB->delete_records('bunnyvideo', array('id' => $bunnyvideo->id));

    return true;
}


/**
 * Define module support for specific features.
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function bunnyvideo_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:         return true;
        case FEATURE_SHOW_DESCRIPTION:       return true;
        case FEATURE_GRADE_HAS_GRADE:        return false; // No grading
        case FEATURE_GROUPS:                 return false; // No grouping support
        case FEATURE_GROUPINGS:              return false; // No grouping support
        case FEATURE_MOD_INTRO:              return true;  // Basic intro field
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false; // Desativado para impedir marcação automática ao visualizar
        case FEATURE_COMPLETION_HAS_RULES:   return true;  // We have custom completion rules
        case FEATURE_MODEDIT_DEFAULT_COMPLETION: return true; // Default to completion tracked
        case FEATURE_COMMENT:                return false; // No comments
        case FEATURE_RATE:                   return false; // No rating
        case FEATURE_MOD_PURPOSE:            return MOD_PURPOSE_CONTENT; // Describe module purpose
        default:                              return null;
    }
}

/**
 * Defines all the completion rules for this module.
 * Returns an array of the names of the custom completion rules (other than viewing/grade)
 * This function is needed for Moodle 4.x to properly recognize the rules.
 *
 * @return array Array of strings defining the rules
 */
function bunnyvideo_get_completion_rules() {
    return ['completionpercent'];
}

/**
 * Adds the custom completion rule to the form elements.
 *
 * @param object $mform The form object
 */
function bunnyvideo_add_completion_rules($mform) {
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
 * Returns a description of the custom completion rules.
 *
 * @param array $rules The rules as returned from this module's completion_rules function
 * @param object $cm The course module instance
 * @return array Array of string descriptions of rules
 */
function bunnyvideo_completion_rule_description($rules) {
    $descriptions = [];
    
    if (!empty($rules['completionpercent'])) {
        if ($rules['completionpercent'] > 0) {
            $descriptions[] = get_string('completionpercenthelp', 'bunnyvideo', $rules['completionpercent']);
        }
    }
    
    return $descriptions;
}

/**
 * Returns the completion state for the given activity, user and course context.
 * This is used by the completion report and other areas to check status.
 * Our JS/AJAX handles the actual marking based on percentage. Moodle handles view completion.
 *
 * @param object $course Course object
 * @param object $cm Course-module object
 * @param int $userid User ID
 * @param bool $type Type of comparison (normally null)
 * @return stdClass|bool An object with 'state' and 'time' fields, or boolean for simple rules
 */
function bunnyvideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get the current completion state from Moodle
    $completion = new completion_info($course);
    $current = $completion->get_data($cm, false, $userid);

    // For manual marking or overall state request (null type)
    if (empty($type) || $type == 'completionpercent') {
        // Fetch the bunnyvideo record for percentage checking
        $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'completionpercent');
        
        // If this is manual completion, we should honor the current state
        // This allows admin overrides to work in the reporting interface
        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            return $current;
        }
        
        // If we're using automatic completion with percentage rule
        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && 
            !empty($bunnyvideo->completionpercent) && 
            $bunnyvideo->completionpercent > 0) {
            
            // For a null type request (main completion state check), return the current state
            // This ensures the report reflects the true state marked by our JavaScript
            return $current;
        }
        
        // CORREÇÃO ADICIONAL: Force incomplete state if the only reason for completion
        // would be view completion (completionview) which we've disabled
        if ($current->completionstate == COMPLETION_COMPLETE && 
            $cm->completion == COMPLETION_TRACKING_AUTOMATIC && 
            $cm->completionview == 1 && 
            (empty($bunnyvideo->completionpercent) || $bunnyvideo->completionpercent <= 0)) {
            
            $state = new stdClass();
            $state->completionstate = COMPLETION_INCOMPLETE;
            $state->timemodified = 0;
            debugging('BunnyVideo: Forcing INCOMPLETE for improper view completion.');
            return $state;
        }
    }
    
    // Return the current state for all other cases
    return $current;
}

/**
 * Callback function when the completion settings form is processed.
 * Used to reset completion status if settings change significantly.
 *
 * @param object $data Data returned from the form. Contains standard completion fields like $data->completion...
 * @param object $cm The course module object.
 * @param int $completion Current completion setting state (before save).
 * @param bool $enabled Whether completion is enabled at all (before save).
 * @return bool True if there are changes that require completion reset.
 */
function bunnyvideo_cm_completion_settings_changed($data, $cm, $completion, $enabled) {
    global $DB;

    // Get the previously saved settings for comparison
    $oldbunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'completionpercent', MUST_EXIST);
    $oldpercent = $oldbunnyvideo->completionpercent;

    // Get the new percentage value from the submitted data
    $newpercent = isset($data->completionpercent) ? (int)$data->completionpercent : 0;
    // Force 0 if completion is disabled overall in the submitted data
    if (isset($data->completion) && $data->completion == COMPLETION_TRACKING_NONE) {
         $newpercent = 0;
    }

    // Check if the percentage requirement was added, removed, or changed value *while being active*
    $reset = false;
    if (($oldpercent > 0 && $newpercent == 0) || ($oldpercent == 0 && $newpercent > 0) || ($oldpercent > 0 && $newpercent > 0 && $oldpercent != $newpercent)) {
        // The percentage requirement itself changed significantly
        $reset = true;
    }

    // Also consider if other completion rules changed (e.g., 'require view' added/removed)
    // Moodle's standard function handles resetting based on enabling/disabling completion overall.
    // We return true if *our* specific rule (percentage) changed in a meaningful way.
    return $reset;
}

/**
 * Callback function when the completion settings form is processed.
 * Used to reset completion status if settings change significantly.
 *
 * @param stdClass $cm The course module object (new state)
 * @param stdClass $oldcm The previous course module object (old state)
 * @param stdClass $data Form data submitted (new bunnyvideo settings)
 * @param stdClass $olddata The previous bunnyvideo instance data (fetched)
 * @return bool Always true
 */
function bunnyvideo_update_completion_state_settings($cm, $oldcm, $data, $olddata) {
    global $DB;

    // Fetch the old bunnyvideo instance record if needed for comparison
    $oldbunnyvideo = $DB->get_record('bunnyvideo', ['id' => $oldcm->instance], 'id, completionpercent');

    // Check if the core completion tracking method changed
    $corecompletionchanged = $cm->completion != $oldcm->completion ||
                             $cm->completionview != $oldcm->completionview;

    // Check if our custom percentage rule changed
    // Note: $data contains the submitted form data, $oldbunnyvideo the previous db value.
    $percentrulechanged = false;
    $newpercent = isset($data->completionpercent) ? (int)$data->completionpercent : 0;
    $oldpercent = $oldbunnyvideo ? (int)$oldbunnyvideo->completionpercent : 0;
    if ($newpercent != $oldpercent) {
        $percentrulechanged = true;
    }

    // If any relevant completion setting was modified, trigger a reset.
    if ($corecompletionchanged || $percentrulechanged) {
        // Mark that settings affecting completion have been updated.
        // Moodle core will handle resetting completion status for users based on this.
        mark_completion_state_updated($cm->id, time());
        debugging("BunnyVideo: Completion settings changed, triggering state update for cmid: {$cm->id}", DEBUG_DEVELOPER);
    }

    return true; // Must return true
}

/**
 * Returns description of the active completion rule for this module instance.
 * Used in reports like Activity Completion.
 *
 * @param cm_info|stdClass $cm Course-module object
 * @param bool $showdescription Whether to show the full description (not just title)
 * @return string|null Description of the rule, or null if none specifically applies beyond view
 */
function bunnyvideo_get_completion_active_rule($cm, $showdescription) {
    global $DB, $CFG;
    
    // Only provide a specific rule description if automatic completion is used
    if ($cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return null;
    }
    
    // Get the bunnyvideo instance to check the percentage
    $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $cm->instance], 'id, completionpercent');
    
    // Build an array of rule descriptions
    $rules = [];
    
    // Check if the percentage rule is enabled for this instance 
    if (!empty($bunnyvideo->completionpercent) && intval($bunnyvideo->completionpercent) > 0) {
        // Add the percentage rule description
        $rules[] = get_string('completionrulenamepercent', 'bunnyvideo', $bunnyvideo->completionpercent);
    }
    
    // Return all rules as a formatted string
    if (!empty($rules)) {
        if (count($rules) == 1) {
            return $rules[0]; // Just return the single rule
        } else {
            // Format multiple rules if needed (unlikely for our module)
            return implode(', ', $rules);
        }
    }
    
    return null; // No rules active
}

/**
 * Return completion state for any criteria (including core ones) that don't have
 * a more specific method.
 *
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm     Activity
 * @param int $userid User ID
 * @param string $type Criteria type
 * @return bool
 */
function bunnyvideo_get_completion_state_no_rule($course, $cm, $userid, $type) {
    global $DB;
    
    $bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // If completion is not enabled for this activity, just return the state
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        return COMPLETION_INCOMPLETE;
    }
    
    // Handle percentage-based completion
    if ($type == 'completionpercentprogress') {
        // We only get here if the percentage rule is active and we need to check it
        if (!empty($bunnyvideo->completionpercent)) {
            // In a real scenario, we would check user progress data from our tables
            // For now, since we only want to display the rule, we can return false
            return COMPLETION_INCOMPLETE;
        }
    }
    
    // For any other type, delegate to the parent function
    return false;
}

/**
 * Get CSS styles for bunnyvideo module
 *
 * @return array Array of CSS files to include
 */
function bunnyvideo_get_styles() {
    return [new moodle_url('/mod/bunnyvideo/styles.css')];
}

// A função bunnyvideo_extend_navigation_completion pode ser deixada vazia por enquanto
/**
 * Add navigation links
 * @param object $node The navigation node object
 * @param string $context The context string
 */
// function bunnyvideo_extend_navigation_completion($node, $context) { } // Não é estritamente necessário agora

// Add other required lib functions like backup/restore placeholders if needed.
// Moodle can often auto-generate basic backup/restore handlers if structure exists.
