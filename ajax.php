<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX handler for Bunny Video player operations
 *
 * @package    mod_bunnyvideo
 * @copyright  2025 Marcos Bellezi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// Get and validate parameters
$action = required_param('action', PARAM_ALPHA);
$cmid = required_param('cmid', PARAM_INT);

// Debug output for troubleshooting
$response = array('success' => false, 'message' => '');
$response['debug'] = array(
    'received_action' => $action,
    'action_type' => gettype($action),
    'received_cmid' => $cmid,
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
);

// Require a valid session key
require_sesskey();

// Validate course module
$cm = get_coursemodule_from_id('bunnyvideo', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance), '*', MUST_EXIST);

// Setup proper context
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

// Check user is logged in
require_login($course, false, $cm);

// Prepare response

if ($action === 'mark_complete' || $action === 'markcomplete') {
    // Check if completion is enabled for this course and module
    $completion = new completion_info($course);
    
    if (!$completion->is_enabled($cm)) {
        $response['message'] = get_string('completionnotenabled', 'completion');
    } else {
        // Save the current state to check if it's already complete
        $current = $completion->get_data($cm, false, $USER->id);
        $was_complete = $current->completionstate == COMPLETION_COMPLETE;
        
        // Mark module as completed
        $completion->update_state($cm, COMPLETION_COMPLETE);
        $response['success'] = true;
        
        // Different message based on whether it was already complete
        if ($was_complete) {
            $response['message'] = get_string('completion-y', 'core_completion');
            $response['already_complete'] = true;
        } else {
            $response['message'] = get_string('completed', 'completion');
            $response['already_complete'] = false;
            
            // Log completion event
            try {
                $event = \core\event\course_module_completion_updated::create(array(
                    'objectid' => $cm->id,
                    'context' => $context,
                    'relateduserid' => $USER->id,
                    'other' => array(
                        'completionstate' => COMPLETION_COMPLETE
                    )
                ));
                $event->trigger();
                $response['event_logged'] = true;
            } catch (Exception $e) {
                // Don't let event logging failure prevent completion marking
                $response['event_error'] = $e->getMessage();
                $response['event_logged'] = false;
            }
        }
    }
} else {
    $response['message'] = 'Invalid action';
}

// Always return JSON
header('Content-Type: application/json');
echo json_encode($response);
