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
}
