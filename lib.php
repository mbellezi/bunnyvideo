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

    // Completion: Mark as viewed (if completion is set to 'view')
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

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
    if (trim(strip_tags($intro))) {
         $content .= $OUTPUT->box($intro, 'mod_introbox'); // Caixa para a introdução
    }
    
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
    switch ($feature) {
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_SHOW_DESCRIPTION:      return true;
        case FEATURE_BACKUP_MOODLE2:        return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true; // Supports "view" completion
        case FEATURE_COMPLETION_HAS_RULES:  return true; // Supports automatic completion rules (our JS handles one)
        case FEATURE_MODEDIT_DEFAULT_COMPLETION: return COMPLETION_TRACKING_AUTOMATIC; // Default to automatic completion
        case FEATURE_GRADE_HAS_GRADE:     return false; // No grading implemented 
        case FEATURE_RATE:                 return false; // No rating
        // FEATURE_COMPLETION_MANUAL_ALLOWED is not available in this version of Moodle
        // We'll handle preventing manual completion in the mod_form.php file
        case FEATURE_MOD_PURPOSE:           return MOD_PURPOSE_CONTENT; // Describe module purpose
        default:                              return null;
    }
}

/**
 * Returns the completion state for the given activity, user and course context.
 * This is used by the completion report and other areas to check status.
 * Our JS/AJAX handles the actual marking based on percentage. Moodle handles view completion.
 * This function mostly relies on Moodle's stored completion state.
 *
 * @param object $course Course object
 * @param object $cm Course-module object
 * @param int $userid User ID
 * @param bool $type Type of comparison (normally null)
 * @return stdClass An object containing state and time fields
 */
function bunnyvideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Standard way to get completion data
    $completion = new completion_info($course);
    $state = $completion->get_data($cm, $userid);

    // If the only condition is our percentage watch (handled by JS/AJAX)
    // and Moodle hasn't registered it as complete yet via AJAX, it's incomplete.
    // However, Moodle's $completion->get_data should reflect the state updated by our AJAX call.
    // No special logic needed here usually, unless overriding Moodle's default view tracking.
    // If 'Require view' is ticked AND percentage > 0, both must be met. Moodle tracks view, JS tracks percent.

    return $state;
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


// Add other required lib functions like backup/restore placeholders if needed.
// Moodle can often auto-generate basic backup/restore handlers if structure exists.

// A função bunnyvideo_extend_navigation_completion pode ser deixada vazia por enquanto
/**
 * Add navigation links
 * @param object $node The navigation node object
 * @param string $context The context string
 */
// function bunnyvideo_extend_navigation_completion($node, $context) { } // Não é estritamente necessário agora
