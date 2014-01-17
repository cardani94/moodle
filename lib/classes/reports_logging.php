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
 * trait core_reports_logging
 *
 * Groups a list of methods, that most reports will find useful.
 *
 * @package    core
 * @copyright  2014 Ankit Agarwal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait core_reports_logging {
    protected $readers;

    /**
     * Get a list of enabled reader objects
     *
     * @return \core\log\reader[] list of reader objects is returend as an array.
     */
    public function get_readers() {
        if (!isset($this->readers)) {
            $manager = get_log_manager();
            $this->readers = $manager->get_readers();
        }
        return $this->readers;
    }

    /**
     * Returns the name of currently selected reader.
     *
     * @param bool $returndefault Return
     *
     * @return bool|mixed Name of currently selected reader, default if $returndefault set to true, false otherwise.
     */
    public function get_selected_reader($returndefault = true) {
        $readers = $this->get_readers();
        $reader = optional_param('reader', '', PARAM_COMPONENT);
        if (!empty($readers[$reader])) {
            return $reader;
        }
        if ($returndefault && !empty($readers)) {
            return key($readers);
        }
        return false;
    }

    /**
     * Get a reader object corresponding to the passed reader name.
     *
     * @param string $reader reader name.
     *
     * @return bool|\core\log\reader reader object if found, false otherwise.
     */
    public function get_reader_object($reader) {
        $readers = $this->get_readers();
        if (!empty($readers[$reader])) {
            return $readers[$reader];
        }
        return false;
    }

    /**
     * Print a dropdown with list of readers.
     *
     * @param bool $return weather to return the select element or print.
     *
     * @return single_select returns the single_select object if return is set to true, nothing otherwise.
     */
    public function print_readers_select($return = false) {
        global $PAGE, $OUTPUT;
        $options = array();
        $reader = $this->get_selected_reader();
        $readers = $this->get_readers();
        if (empty($readers)) {
            echo 'No readers found'; // TODO
            return;
        }
        foreach ($readers as $k => $v) {
            $options[$k] = $v->get_name();
        }
        $select = new single_select($PAGE->url, 'reader', $options, $reader, null);
        $select->set_label('Select log'); // TODO
        if ($return) {
            return $select;
        }
        echo $OUTPUT->render($select);
    }
}