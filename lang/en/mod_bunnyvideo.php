<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bunny Video';
$string['modulename'] = 'Bunny Video';
$string['modulenameplural'] = 'Bunny Videos';
$string['modulename_help'] = 'Use the Bunny Video activity to embed videos from Bunny Stream. You can optionally track completion based on the percentage of the video watched.';
$string['pluginadministration'] = 'Bunny Video administration';
$string['bunnyvideo:addinstance'] = 'Add a new Bunny video';
$string['bunnyvideo:view'] = 'View Bunny video';

$string['activityname'] = 'Activity Name';
$string['embedcode'] = 'Bunny Embed Code';
$string['embedcode_help'] = 'Paste the full HTML embed code provided by Bunny Stream (usually starts with &lt;iframe...).';
$string['completionpercent'] = 'Completion percentage required';
$string['completionpercent_help'] = 'Enter the percentage (1-100) of the video the user must watch for the activity to be marked complete automatically. Leave empty or set to 0 to disable automatic completion based on watch time.';
$string['invalidembedcode'] = 'The provided embed code seems invalid or incomplete. Please paste the full code from Bunny.';
$string['videoidnotfound'] = 'Could not extract video ID from embed code.';
$string['libraryidnotfound'] = 'Could not extract library ID from embed code.';
$string['player_loading'] = 'Loading Video...';

// For AJAX function
$string['ajax_mark_complete'] = 'Mark Bunny Video as complete';
$string['ajax_mark_complete_description'] = 'AJAX service to update completion status for a Bunny Video activity based on viewing percentage.';
$string['error_cannotmarkcomplete'] = 'Could not mark activity as complete.';
$string['error_invalidcoursemodule'] = 'Invalid course module ID.';
