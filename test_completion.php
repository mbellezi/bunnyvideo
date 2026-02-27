<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/config.php');

global $DB;

// Find the latest bunnyvideo instance
$sql = "SELECT cm.id AS cmid, c.id AS courseid, bv.id AS bvid
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {course} c ON c.id = cm.course
        JOIN {bunnyvideo} bv ON bv.id = cm.instance
        WHERE m.name = 'bunnyvideo'
        ORDER BY cm.id DESC
        LIMIT 1";

$latest_cm = $DB->get_record_sql($sql);

if (!$latest_cm) {
    echo "No bunnyvideo activities found.\n";
    exit(1);
}

$cmid = $latest_cm->cmid;

// Find a student enrolled in that course
$sql = "SELECT u.id
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid = ? AND u.deleted = 0 AND u.suspended = 0
        LIMIT 1";

$userid = $DB->get_field_sql($sql, [$latest_cm->courseid]);

if (!$userid) {
    echo "No students found in course $latest_cm->courseid.\n";
    exit(1);
}

echo "Using CMID: $cmid, UserID: $userid\n";

// 1. Get basic info
$cm = get_coursemodule_from_id('bunnyvideo', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

echo "--- Module Info ---\n";
echo "CM ID: $cm->id\n";
echo "Instance ID: $cm->instance\n";
echo "completion: {$cm->completion}\n";
echo "Custom data: " . ($cm->customdata ?? 'NULL') . "\n";
echo "customcompletionrules:\n";
$customdata = customdata_get($cm);
var_dump($customdata['customcompletionrules'] ?? null);

// 2. Test the completion API
$completion = new completion_info($course);
$completiondata = $completion->get_data($cm, false, $userid);
echo "\n--- Raw core completion data ---\n";
var_dump($completiondata);

// 3. Test custom_completion class
echo "\n--- Custom Completion Class ---\n";
$customcompletion = new \mod_bunnyvideo\completion\custom_completion($cm, $userid);

echo "Defined rules:\n";
var_dump(\mod_bunnyvideo\completion\custom_completion::get_defined_custom_rules());

echo "Available rules:\n";
var_dump($customcompletion->get_available_custom_rules());

echo "Overall state:\n";
var_dump($customcompletion->get_overall_completion_state());

// 4. Checking what's in progress table
$progress = $DB->get_record('bunnyvideo_progress', [
    'bunnyvideoid' => $cm->instance,
    'userid' => $userid,
]);
echo "\n--- Progress Table ---\n";
var_dump($progress);

// 5. Explicitly call our legacy callback to see what it returns
echo "\n--- Legacy Callback ---\n";
require_once(__DIR__ . '/lib.php');
if (function_exists('bunnyvideo_get_completion_state')) {
    $result = bunnyvideo_get_completion_state($course, $cm, $userid, COMPLETION_AND);
    echo "bunnyvideo_get_completion_state returned: ";
    var_dump($result);
} else {
    echo "bunnyvideo_get_completion_state DOES NOT EXIST!\n";
}
