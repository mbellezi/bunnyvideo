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

        // NOTE: We intentionally do NOT migrate existing completion data.
        // All existing completion records were created by the bug that this
        // upgrade fixes. The table starts empty and will only be populated
        // by legitimate AJAX calls when students actually watch videos.

        upgrade_mod_savepoint(true, 2026022702, 'bunnyvideo');
    }

    if ($oldversion < 2026022703) {
        // Clear all stale records from bunnyvideo_progress.
        // The previous upgrade (2026022702) incorrectly migrated bug-caused
        // completion records. This step clears them.
        $DB->delete_records('bunnyvideo_progress');

        upgrade_mod_savepoint(true, 2026022703, 'bunnyvideo');
    }

    return true;
}
