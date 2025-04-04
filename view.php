<?php
require_once('../../config.php'); // Moodle config
require_once('lib.php');       // Include your library file

$id = required_param('id', PARAM_INT); // Course Module ID

// Get course module and course record
if (!$cm = get_coursemodule_from_id('bunnyvideo', $id)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}
if (!$bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance))) {
    print_error('invalidcoursemodule'); // Or a more specific error
}

// Require user to be logged in and enrolled (or guest access if allowed)
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/bunnyvideo:view', $context);

// --- Set up the page ---
$PAGE->set_url('/mod/bunnyvideo/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($bunnyvideo->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// --- Output the page ---
echo $OUTPUT->header();

// Call the view function from lib.php to render the content
echo bunnyvideo_view($bunnyvideo, $cm, $context);

echo $OUTPUT->footer();
