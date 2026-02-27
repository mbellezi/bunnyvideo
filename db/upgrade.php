<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for mod_bunnyvideo.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True if the upgrade was successful.
 */
function xmldb_bunnyvideo_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022702) {
        // Create the bunnyvideo_progress table.
        $table = new xmldb_table('bunnyvideo_progress');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('bunnyvideoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('completionmet', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('bunnyvideoid', XMLDB_KEY_FOREIGN, ['bunnyvideoid'], 'bunnyvideo', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('bunnyvideoid_userid', XMLDB_INDEX_UNIQUE, ['bunnyvideoid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate existing completion data: for each user who has a completion record
        // marked as COMPLETE for a bunnyvideo activity, create a bunnyvideo_progress record.
        $sql = "SELECT cmc.userid, bv.id AS bunnyvideoid, cmc.timemodified
                FROM {course_modules_completion} cmc
                JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                JOIN {bunnyvideo} bv ON bv.id = cm.instance
                WHERE cm.module = (SELECT id FROM {modules} WHERE name = 'bunnyvideo')
                  AND cmc.completionstate = 1";

        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            // Check if a progress record already exists (avoid duplicates).
            if (
                !$DB->record_exists('bunnyvideo_progress', [
                    'bunnyvideoid' => $record->bunnyvideoid,
                    'userid' => $record->userid,
                ])
            ) {
                $progress = new stdClass();
                $progress->bunnyvideoid = $record->bunnyvideoid;
                $progress->userid = $record->userid;
                $progress->completionmet = 1;
                $progress->timemodified = $record->timemodified;
                $DB->insert_record('bunnyvideo_progress', $progress);
            }
        }

        upgrade_mod_savepoint(true, 2026022702, 'bunnyvideo');
    }

    return true;
}
