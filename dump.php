<?php
require_once('../../config.php');

// Permite execução apenas por admins para segurança
require_login();
if (!is_siteadmin()) {
    die('Apenas admins');
}

$cmid = optional_param('cmid', 0, PARAM_INT);
if (!$cmid && isset($_GET['cmid'])) {
    $cmid = (int) $_GET['cmid'];
}

// Find the latest bunnyvideo instance if no cmid is provided
if (!$cmid) {
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
        die("No bunnyvideo activities found.");
    }
    $cmid = $latest_cm->cmid;
}

echo "<h1>BunnyVideo Completion Dump</h1>";
echo "<h3>CMID analisado: {$cmid}</h3><pre>";

// 1. Get basic info
$cm = get_coursemodule_from_id('bunnyvideo', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

echo "--- Module Info ---\n";
echo "CM ID: {$cm->id}\n";
echo "Instance ID: {$cm->instance}\n";
echo "completion tracking level: {$cm->completion}\n";
echo "Raw Custom data: " . ($cm->customdata ?? 'NULL') . "\n";
echo "Parsed customcompletionrules:\n";
$customdata = !empty($cm->customdata) ? json_decode($cm->customdata, true) : [];
var_dump($customdata['customcompletionrules'] ?? null);

// 2. Bunnyvideo DB record
echo "\n--- DB Record ---\n";
$bv = $DB->get_record('bunnyvideo', ['id' => $cm->instance]);
var_dump(['id' => $bv->id, 'completionpercent' => $bv->completionpercent]);

// 3. Test custom_completion class reflection
echo "\n--- Checking Class API ---\n";

echo "Class exists? " . (class_exists('\mod_bunnyvideo\completion\custom_completion') ? 'Yes' : 'No') . "\n";

if (class_exists('\mod_bunnyvideo\completion\custom_completion')) {
    echo "Defined rules statically:\n";
    var_dump(\mod_bunnyvideo\completion\custom_completion::get_defined_custom_rules());

    // Pick a user (the current admin) to instantiate the class
    $modinfo = get_fast_modinfo($course);
    $cminfo = $modinfo->get_cm($cm->id);
    $customcompletion = new \mod_bunnyvideo\completion\custom_completion($cminfo, $USER->id);
    echo "Available rules for current user:\n";
    var_dump($customcompletion->get_available_custom_rules());
}

// 4. Test Moodle's Completion API evaluation (Simulating a student)
$sql = "SELECT u.id
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid = ? AND u.deleted = 0 AND u.suspended = 0
        LIMIT 1";
$userid = $DB->get_field_sql($sql, [$course->id]);

if ($userid) {
    echo "\n--- Core Completion API for student ID {$userid} ---\n";
    $completion = new completion_info($course);
    $completiondata = $completion->get_data($cm, false, $userid);
    var_dump($completiondata);
} else {
    echo "\n--- No student found in course to test completion data ---\n";
}

echo "</pre>";
