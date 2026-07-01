<?php
namespace mod_bunnyvideo\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Syncs gradebook entries from BunnyVideo completion progress after plugin upgrade.
 *
 * @package    mod_bunnyvideo
 */
class sync_completion_grades extends \core\task\adhoc_task
{
    /**
     * Execute the adhoc task.
     */
    public function execute()
    {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/bunnyvideo/lib.php');

        $sql = "SELECT b.*, cm.idnumber AS cmidnumber
                  FROM {bunnyvideo} b
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = b.id";

        $bunnyvideos = $DB->get_recordset_sql($sql, ['modname' => 'bunnyvideo']);
        foreach ($bunnyvideos as $bunnyvideo) {
            bunnyvideo_grade_item_update($bunnyvideo);
            bunnyvideo_update_grades($bunnyvideo, 0, false);
        }
        $bunnyvideos->close();
    }
}
