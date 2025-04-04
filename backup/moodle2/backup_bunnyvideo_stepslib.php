<?php
defined('MOODLE_INTERNAL') || die();

class backup_bunnyvideo_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $bunnyvideo = new backup_nested_element('bunnyvideo', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'embedcode',
            'completionpercent', 'timecreated', 'timemodified'
        ));

        $bunnyvideo->set_source_table('bunnyvideo', array('id' => backup::VAR_ACTIVITYID));

        return $this->prepare_activity_structure($bunnyvideo);
    }
}
