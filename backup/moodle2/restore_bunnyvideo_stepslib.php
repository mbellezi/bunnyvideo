<?php
defined('MOODLE_INTERNAL') || die();

// Passo básico de restauração. Geralmente, mais lógica é necessária se houver arquivos/dados relacionados.
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

        // Ajusta o formato da introdução e os links, se necessário, usando o contexto do plano de restauração
        $data->introformat = $this->apply_date_offset($data->introformat);
        $data->intro = $this->apply_content_links($data->intro);

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insere o registro no banco de dados
        $newitemid = $DB->insert_record('bunnyvideo', $data);
        $this->apply_activity_instance($newitemid); // Mapeia o ID antigo para o novo ID
    }
}
