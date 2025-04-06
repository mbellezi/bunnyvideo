<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bunnyvideo/backup/moodle2/backup_bunnyvideo_stepslib.php');

class backup_bunnyvideo_activity_task extends backup_activity_task {
    protected function define_my_settings() { }

    protected function define_my_steps() {
        $this->add_step(new backup_bunnyvideo_activity_structure_step('bunnyvideo_structure', 'bunnyvideo.xml'));
    }

    static public function encode_content_links($content) {
        return $content; // Marcador de posição
    }
}
