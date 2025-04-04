<?php
defined('MOODLE_INTERNAL') || die();

// Basic restore step. Usually more logic is needed if there are related files/data.
class restore_bunnyvideo_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('bunnyvideo', '/activity/bunnyvideo');
        return $this->prepare_activity_structure($paths);
    }

    protected function process_bunnyvideo($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Adjust intro format and links if necessary using restore plan context
        $data->introformat = $this->apply_date_offset($data->introformat);
        $data->intro = $this->apply_content_links($data->intro);

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the database record
        $newitemid = $DB->insert_record('bunnyvideo', $data);
        $this->apply_activity_instance($newitemid); // Map old id to new id
    }
}
