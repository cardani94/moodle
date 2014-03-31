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
 * mod_page data generator
 *
 * @package    mod_page
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Page module data generator class
 *
 * @package    mod_page
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_page_generator extends testing_module_generator {
    /** @var array Number of Page activities in course as per size. */
    public static $parampages = array(1, 50, 200, 1000, 5000, 10000);

    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        $record = (object)(array)$record;

        if (!isset($record->content)) {
            $record->content = 'Test page content';
        }
        if (!isset($record->contentformat)) {
            $record->contentformat = FORMAT_MOODLE;
        }
        if (!isset($record->display)) {
            $record->display = RESOURCELIB_DISPLAY_AUTO;
        }
        if (!isset($record->printheading)) {
            $record->printheading = 1;
        }
        if (!isset($record->printintro)) {
            $record->printintro = 0;
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Total records which will be genrated.
     *
     * @param int $size
     * @return int
     */
    public function total_records_to_create($size) {
        return self::$parampages[$size];
    }

    /**
     * Create instances of page.
     *
     * @param int $size size of records to generate.
     * @param tool_generator_course_backend $coursetoolgenerator
     * @param array $options
     * @return int number of records created
     */
    public function create_instances($size, $coursetoolgenerator, array $options = array()) {
        $number = self::$parampages[$size];
        for ($i = 0; $i < $number; $i++) {
            $record = array('course' => $coursetoolgenerator->get_course());
            $options = array_merge($options, array('section' => $coursetoolgenerator->get_target_section()));
            $this->create_instance($record, $options);
            $coursetoolgenerator->dot($i, $number);
        }
        return $number;
    }
}
