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
 * Completion lib advanced test case.
 *
 * This file contains the advanced test suite for completion lib.
 *
 * @package    core_completion
 * @category   phpunit
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/completionlib.php');

class completionlib_advanced_testcase extends advanced_testcase {

    function test_get_activities() {
        global $DB;
        $this->resetAfterTest(true);

        // Create a course with mixed auto completion data.
        $course = $this->getDataGenerator()->create_course();
        $completionauto = array('completion' => COMPLETION_TRACKING_AUTOMATIC);
        $completionmanual = array('completion' => COMPLETION_TRACKING_MANUAL);
        $completionnone = array('completion' => COMPLETION_TRACKING_NONE);
        $forum = $this->getDataGenerator()->create_module('forum', array('course' => $course->id), $completionauto);
        $page = $this->getDataGenerator()->create_module('page', array('course' => $course->id), $completionauto);
        $data = $this->getDataGenerator()->create_module('data', array('course' => $course->id), $completionmanual);

        $forum2 = $this->getDataGenerator()->create_module('forum', array('course' => $course->id), $completionnone);
        $page2 = $this->getDataGenerator()->create_module('page', array('course' => $course->id), $completionnone);
        $data2 = $this->getDataGenerator()->create_module('data', array('course' => $course->id), $completionnone);

        // Create data in another course to make sure it's not considered.
        $course2 = $this->getDataGenerator()->create_course();
        $c2forum = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id), $completionauto);
        $c2page = $this->getDataGenerator()->create_module('page', array('course' => $course2->id), $completionmanual);
        $c2data = $this->getDataGenerator()->create_module('data', array('course' => $course2->id), $completionnone);

        $c = new completion_info($course);
        $activities = $c->get_activities();
        $this->assertEquals(3, count($activities));
        $this->assertTrue(isset($activities[$forum->cmid]));
        $this->assertEquals($activities[$forum->cmid]->name, $forum->name);
        $this->assertTrue(isset($activities[$page->cmid]));
        $this->assertEquals($activities[$page->cmid]->name, $page->name);
        $this->assertTrue(isset($activities[$data->cmid]));
        $this->assertEquals($activities[$data->cmid]->name, $data->name);

        $this->assertFalse(isset($activities[$forum2->cmid]));
        $this->assertFalse(isset($activities[$page2->cmid]));
        $this->assertFalse(isset($activities[$data2->cmid]));
    }

    function test_has_activities() {
        global $DB;
        $this->resetAfterTest(true);

        // Create a course with mixed auto completion data.
        $course = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $completionauto = array('completion' => COMPLETION_TRACKING_AUTOMATIC);
        $completionnone = array('completion' => COMPLETION_TRACKING_NONE);
        $c1forum = $this->getDataGenerator()->create_module('forum', array('course' => $course->id), $completionauto);
        $c2forum = $this->getDataGenerator()->create_module('forum', array('course' => $course2->id), $completionnone);

        $c1 = new completion_info($course);
        $c2 = new completion_info($course2);

        $this->assertTrue($c1->has_activities());
        $this->assertFalse($c2->has_activities());
    }

    /**
     * Test course module completion update event.
     */
    public function test_course_module_completion_updated_event() {
        global $DB, $CFG;

        $this->resetAfterTest();

        // Create a course with mixed auto completion data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        // Get manual enrolment plugin and enrol user.
        require_once($CFG->dirroot.'/enrol/manual/locallib.php');
        $manplugin = enrol_get_plugin('manual');
        $maninstance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $manplugin->enrol_user($maninstance, $user->id, $studentrole->id);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));

        $completionauto = array('completion' => COMPLETION_TRACKING_AUTOMATIC);
        $forum = $this->getDataGenerator()->create_module('forum', array('course' => $course->id), $completionauto);

        $c = new completion_info($course);
        $activities = $c->get_activities();
        $this->assertEquals(1, count($activities));
        $this->assertTrue(isset($activities[$forum->cmid]));
        $this->assertEquals($activities[$forum->cmid]->name, $forum->name);

        $current = $c->get_data($activities[$forum->cmid], false, $user->id);
        $current->completionstate = COMPLETION_COMPLETE;
        $current->timemodified = time();
        $sink = $this->redirectEvents();
        $c->internal_set_data($activities[$forum->cmid], $current);
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\core\event\course_module_completion_updated', $event);
        $this->assertEquals($forum->cmid, $event->get_record_snapshot('course_modules_completion', $event->objectid)->coursemoduleid);
        $this->assertEquals($current, $event->get_record_snapshot('course_modules_completion', $event->objectid));
        $this->assertEquals(context_module::instance($forum->id), $event->get_context());
    }
}
