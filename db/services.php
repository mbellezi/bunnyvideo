<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_bunnyvideo_mark_complete' => array(
        'classname'     => 'mod_bunnyvideo\external\completion_ajax',
        'methodname'    => 'mark_complete',
        'description'   => 'Marks the Bunny Video activity as complete for the current user.',
        'type'          => 'write', // Indicates it modifies data
        'ajax'          => true,    // Available via AJAX
        'capabilities'  => 'mod/bunnyvideo:view', // User needs at least view capability
    )
);
