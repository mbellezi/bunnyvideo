<?php
namespace mod_bunnyvideo\completion;

defined('MOODLE_INTERNAL') || die();

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the BunnyVideo activity.
 *
 * This class handles the custom completion rule 'completionpercent'.
 * The actual completion is set via AJAX when the student watches enough
 * of the video. This class simply checks whether the completion record
 * exists in the database (i.e., was set by the AJAX call).
 *
 * @package    mod_bunnyvideo
 * @copyright  2025 Marcos Bellezi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion
{

    /**
     * Fetches the completion state for the 'completionpercent' custom rule.
     *
     * The actual completion marking is done by the JavaScript player handler
     * via AJAX call to ajax.php when the student watches enough of the video.
     * This method simply checks whether that AJAX call has already set the
     * completion state to COMPLETION_COMPLETE.
     *
     * If no completion record exists (e.g., for a newly created activity),
     * it returns COMPLETION_INCOMPLETE.
     *
     * @param string $rule The completion rule to check.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int
    {
        global $DB;

        $this->validate_rule($rule);

        // Get the bunnyvideo instance to check if completionpercent is configured.
        $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $this->cm->instance], 'completionpercent', MUST_EXIST);

        // If completionpercent is not set or is 0, the rule is effectively satisfied.
        if (empty($bunnyvideo->completionpercent) || $bunnyvideo->completionpercent <= 0) {
            return COMPLETION_COMPLETE;
        }

        // Check if the completion has been explicitly set by our AJAX handler.
        // We look at the course_modules_completion table directly. If there's a record
        // with completionstate = COMPLETION_COMPLETE, it means the JS handler set it
        // after the student watched enough of the video.
        $completionrecord = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $this->cm->id,
            'userid' => $this->userid,
        ]);

        if ($completionrecord && $completionrecord->completionstate == COMPLETION_COMPLETE) {
            return COMPLETION_COMPLETE;
        }

        // No completion record or not marked as complete — student hasn't watched enough.
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array Array of strings describing the custom rules.
     */
    public static function get_defined_custom_rules(): array
    {
        return ['completionpercent'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array
    {
        global $DB;

        $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $this->cm->instance], 'completionpercent');
        $percent = $bunnyvideo ? (int) $bunnyvideo->completionpercent : 0;

        return [
            'completionpercent' => get_string('completionpercentdesc', 'mod_bunnyvideo', $percent),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array
    {
        return ['completionpercent'];
    }
}
