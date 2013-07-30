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

namespace core\event;

/**
 * Event when course module completion is updated.
 *
 * @package    core
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_completion_updated extends \core\event\base {

    /**
     * Initialise required event data properties.
     */
    protected function init() {
        $this->data['objecttable'] = 'course_modules_completion';
        $this->data['crud'] = 'u';
        // TODO: MDL-37658 set level.
        $this->data['level'] = 50;
    }

    /**
     * Returns localised event name.
     *
     * @return \lang_string
     */
    public static function get_name() {
        return new \lang_string('eventcoursemodulecompletionupdated', 'core_completion');
    }

    /**
     * Returns localised description of what happened.
     *
     * @return \lang_string
     */
    public function get_description() {
        $a = $this->userid;
        return new \lang_string('eventcoursemodulecompletionupdateddesc', 'core_completion', $a);
    }

    /**
     * Return name of the legacy event, which is replaced by this event.
     *
     * @return string legacy event name
     */
    protected function get_legacy_eventname() {
        return 'activity_completion_changed';
    }

    /**
     * Return course module completion legacy event data.
     *
     * @return \stdClass completion data.
     */
    protected function get_legacy_eventdata() {
        return $this->get_record_snapshot('course_modules_completion', $this->objectid);
    }
}
