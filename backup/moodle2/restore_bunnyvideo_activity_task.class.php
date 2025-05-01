<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bunnyvideo/backup/moodle2/restore_bunnyvideo_stepslib.php');

class restore_bunnyvideo_activity_task extends restore_activity_task {

    protected function define_my_settings() { }

    protected function define_my_steps() {
        $this->add_step(new restore_bunnyvideo_activity_structure_step('bunnyvideo_structure', 'bunnyvideo.xml'));
    }

    static public function decode_content_links($content) {
         return $content; // Marcador de posição
    }

    public static function define_decode_contents() {
        return array();
    }

    public static function define_decode_rules() {
        return array();
    }    

    static public function decode_content_links_caller($function) {
        return self::decode_content_links($function);
    }
}
