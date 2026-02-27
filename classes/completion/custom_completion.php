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
        $bunnyvideo = $DB->get_record('bunnyvideo', ['id' => $this->cm->instance], 'id, completionpercent', MUST_EXIST);

        // If completionpercent is not set or is 0, the rule is effectively satisfied.
        if (empty($bunnyvideo->completionpercent) || $bunnyvideo->completionpercent <= 0) {
            return COMPLETION_COMPLETE;
        }

        // Check if the student has met the completion criteria.
        // We use our own bunnyvideo_progress table (NOT course_modules_completion)
        // to avoid circular dependency: Moodle writes to course_modules_completion
        // based on the result of this method, so we can't check that table here.
        $progress = $DB->get_record('bunnyvideo_progress', [
            'bunnyvideoid' => $bunnyvideo->id,
            'userid' => $this->userid,
        ]);

        if ($progress && $progress->completionmet == 1) {
            return COMPLETION_COMPLETE;
        }

        // No progress record or not met — student hasn't watched enough.
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
