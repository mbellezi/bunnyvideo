<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bunny Video';
$string['modulename'] = 'Bunny Video';
$string['modulenameplural'] = 'Bunny Videos';
$string['modulename_help'] = 'Use the Bunny Video activity to embed videos from Bunny Stream. You can optionally track completion based on the percentage of the video watched.';
$string['pluginadministration'] = 'Bunny Video administration';
$string['bunnyvideo:addinstance'] = 'Add a new Bunny video';
$string['bunnyvideo:view'] = 'View Bunny video';
$string['bunnyvideo:managecompletion'] = 'Manage completion status for Bunny video activities';

$string['activityname'] = 'Activity Name';
$string['embedcode'] = 'Bunny Embed Code';
$string['embedcode_help'] = 'Paste the full HTML embed code provided by Bunny Stream (usually starts with &lt;iframe...).';
$string['completionpercent'] = 'Require watch percentage';
$string['completionpercent_help'] = 'If enabled, the activity will be marked complete when the user watches the specified percentage of the video.';
$string['completionrulenamepercent'] = 'Watch {$a}% of video';
$string['invalidembedcode'] = 'The provided embed code seems invalid or incomplete. Please paste the full code from Bunny.';
$string['videoidnotfound'] = 'Could not extract video ID from embed code.';
$string['libraryidnotfound'] = 'Could not extract library ID from embed code.';
$string['player_loading'] = 'Loading Video...';

// For AJAX function
$string['ajax_mark_complete'] = 'Mark Bunny Video as complete';
$string['ajax_mark_complete_description'] = 'AJAX service to update completion status for a Bunny Video activity based on viewing percentage.';
$string['error_cannotmarkcomplete'] = 'Could not mark activity as complete.';
$string['error_invalidcoursemodule'] = 'Invalid course module ID.';
$string['completionpercenthelp'] = 'User must watch at least {$a}% of the video';
$string['completionpercentdesc'] = 'Student must watch at least {$a}% of the video';
$string['completion_status_complete'] = 'Activity completed ✓';
$string['completion_status_incomplete'] = 'To complete this activity, you need to watch at least {$a}% of the video.';
$string['toggle_completion'] = 'Toggle completion status';
$string['mark_complete'] = 'Mark as complete';
$string['mark_incomplete'] = 'Mark as incomplete';
$string['completion_updated'] = 'Completion status updated';
$string['completion_toggle_success'] = 'Completion status successfully updated';
$string['completion_toggle_error'] = 'Error updating completion status';
