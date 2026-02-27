<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/config.php');

$cmid = isset($argv[1]) ? (int) $argv[1] : 0;
$userid = isset($argv[2]) ? (int) $argv[2] : 0;

if (!$cmid || !$userid) {
    echo "Usage: php test_completion.php <cmid> <userid>\n";
    exit(1);
}

// 1. Get basic info
$cm = get_coursemodule_from_id('bunnyvideo', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

echo "--- Module Info ---\n";
echo "CM ID: $cm->id\n";
echo "Instance ID: $cm->instance\n";
echo "Custom data: " . ($cm->customdata ?? 'NULL') . "\n";
echo "customcompletionrules: ";
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
