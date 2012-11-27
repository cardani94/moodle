<?php

require_once($CFG->libdir . '/portfoliolib.php');

class portfolio_plugin_download extends portfolio_plugin_pull_base {

    protected $exportconfig;

    public static function get_name() {
        return get_string('pluginname', 'portfolio_download');
    }

    public static function allows_multiple_instances() {
        return false;
    }

    public function expected_time($callertime) {
        return PORTFOLIO_TIME_LOW;
    }

    public function prepare_package() {

        $files = $this->exporter->get_tempfiles();

        // Add html wrapper to file contents if file format is html.
        $fileformat = $this->exporter->get('formatclass');
        if (($fileformat === PORTFOLIO_FORMAT_RICHHTML) || ($fileformat === PORTFOLIO_FORMAT_PLAINHTML)) {
            $files = $this->add_html_wrapper($files);
        }

        if (count($files) == 1) {
            $this->set('file', array_shift($files));
        } else {
            $this->set('file', $this->exporter->zip_tempfiles());  // this will throw a file_exception which the exporter catches separately.
        }
    }

    public function steal_control($stage) {
        if ($stage == PORTFOLIO_STAGE_FINISHED) {
            global $CFG;
            return $CFG->wwwroot . '/portfolio/download/file.php?id=' . $this->get('exporter')->get('id');
        }
    }

    public function send_package() {}

    public function verify_file_request_params($params) {
        // for download plugin the only thing we need to verify is that
        // the logged in user is the same as the exporting user
        global $USER;
        if ($USER->id  != $this->user->id) {
            return false;
        }
        return true;
    }

    public function get_interactive_continue_url() {
        return false;
    }

    /**
     * Add html wrapper to files content.
     *
     * @param array $files
     * @return array files with html content
     */
    public function add_html_wrapper($files) {
        // If no files passed then return.
        if (empty($files) || !is_array($files)) {
            return $files;
        }

        foreach ($files as $filename => $storedfile) {
            $filepath = $storedfile->get_filepath();
            if (($filepath != '/') || (preg_match('/\.html$/i', $filename) != 1)) {
                // If filename doesn't ends with html then don't add anything.
                continue;
            }
            // Get contents of file
            $content = $storedfile->get_content();

            // Create head markup with title
            $htmlmeta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            $head = html_writer::tag('head', "\n".html_writer::tag('title', '')."\n".$htmlmeta."\n");
            // Wrap existing content in body.
            $bodycontent = html_writer::tag('body', "\n".text_to_html($content, null, false)."\n");
            // Wrap head and body in html tag and return
            $content = "<!DOCTYPE html>\n".html_writer::tag('html', "\n".$head."\n".$bodycontent."\n");

            // Write the contents back to the file
            $fs = get_file_storage();
            $filerecord = array(
                'contextid' => $storedfile->get_contextid(),
                'component' => $storedfile->get_component(),
                'filearea'  => $storedfile->get_filearea(),
                'itemid'    => $storedfile->get_itemid(),
                'filepath'  => $storedfile->get_filepath(),
                'filename'  => $storedfile->get_filename()
            );
            // Delete old file, so new file can be written at same place, also, update files array.
            $storedfile->delete();
            $files[$filename] = $fs->create_file_from_string($filerecord, $content);
        }
        return $files;
    }
}

