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
 * Self enrol external PHPunit tests
 *
 * @package    enrol_self
 * @copyright  2012 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.5
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/self/externallib.php');

class enrol_self_external_testcase extends externallib_advanced_testcase {

    /**
     * Test self enrol user
     */
    public function test_enrol_user() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Check if self enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('self');
        $this->assertNotEmpty($selfplugin);

        // Get student role and teacher role for testing.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->assertNotEmpty($teacherrole);

        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Create courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = self::getDataGenerator()->create_course();

        // Check self enrol without password.
        $id = $selfplugin->add_instance($course1, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course1->id);

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course2,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $teacherrole->id,
                    'password' => 'test'));
        enrol_self_external::enrol_user($course2->id, 'test');

        // Create cohort and add member.
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user->id);
        $id = $selfplugin->add_instance($course3,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint5' => $cohort->id));
        enrol_self_external::enrol_user($course3->id);

        // Create group and make sure user can enrol with group password.
        $groupdata = array();
        $groupdata['courseid'] = $course4->id;
        $groupdata['name'] = 'Group Test 1';
        $groupdata['description'] = 'Group Test 1 description';
        $groupdata['descriptionformat'] = FORMAT_MOODLE;
        $groupdata['enrolmentkey'] = 'groupkey';
        $group = self::getDataGenerator()->create_group($groupdata);

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course4,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint1' => 1,
                    'password' => 'correctkey'));
        enrol_self_external::enrol_user($course4->id, 'groupkey');

        // Check we retrieve the good total number of enrolled users.
        require_once($CFG->dirroot . '/enrol/externallib.php');
        $enrolledusers1 = core_enrol_external::get_enrolled_users($course1->id);
        $this->assertEquals(1, count($enrolledusers1));
        $enrolledusers2 = core_enrol_external::get_enrolled_users($course2->id);
        $this->assertEquals(1, count($enrolledusers2));
        $enrolledusers3 = core_enrol_external::get_enrolled_users($course3->id);
        $this->assertEquals(1, count($enrolledusers3));
        $enrolledusers4 = core_enrol_external::get_enrolled_users($course4->id);
        $this->assertEquals(1, count($enrolledusers4));
    }

    /**
     * Test guest user should not self enrol.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_guest_user() {
        global $DB;
        $this->resetAfterTest(true);
        // Set guest user as current user.
        $guestuser = guest_user();
        $this->setUser($guestuser);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling existing enrolled user.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrolled_user() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course->id);
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling user in self enrolled course whose start date is in future.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrol_notstarted() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();
        $starttime = time() + 60; // Make start time in future.
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'enrolstartdate' => $starttime));
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling user in self enrolled course whose end date is in past.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrol_finshed() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();
        // Make start time in future.
        $endtime = time() - 60;
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'enrolenddate' => $endtime));
        enrol_self_external::enrol_user($course->id);
    }


    /**
     * Test enroling user in self enrolled course with wrong password key
     * @expectedException moodle_exception
     */
    public function test_enrol_user_wrong_key() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();
        // Check self enrol with password.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'password' => 'correctkey'));
        enrol_self_external::enrol_user($course->id, 'wrongkey');
    }

    /**
     * Test enroling user in self enrolled course where user is not a member of cohort
     * @expectedException moodle_exception
     */
    public function test_enrol_user_no_cohort_member() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = $this->getDataGenerator()->create_course();

        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort();

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint5' => $cohort->id));
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling user in self enrolled course with wrong group password key
     * @expectedException moodle_exception
     */
    public function test_enrol_user_wrong_group_key() {
        global $DB;
        $this->resetAfterTest(true);
        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $course = self::getDataGenerator()->create_course();
        $groupdata = array();
        $groupdata['courseid'] = $course->id;
        $groupdata['name'] = 'Group Test 1';
        $groupdata['description'] = 'Group Test 1 description';
        $groupdata['descriptionformat'] = FORMAT_MOODLE;
        $groupdata['enrolmentkey'] = 'groupkey';
        $group = self::getDataGenerator()->create_group($groupdata);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint1' => 1,
                    'password' => 'correctkey'));
        enrol_self_external::enrol_user($course->id, 'wrongkey');
    }

    /**
     * Test self unenrol
     */
    public function test_unenrol_self() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Check if self and manual enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('self');
        $this->assertNotEmpty($selfplugin);
        $manualplugin = enrol_get_plugin('manual');
        $this->assertNotEmpty($manualplugin);

        // Get student and teacher role for testing.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->assertNotEmpty($teacherrole);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Self enrol student.
        $this->setUser($student);
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course->id);

        // Manual enrol teacher.
        $maninstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manualplugin->enrol_user($maninstance, $teacher->id, $teacherrole->id);

        // Check if user is enrolled.
        require_once($CFG->dirroot . '/enrol/externallib.php');
        $enrolledusers = core_enrol_external::get_enrolled_users($course->id);
        $this->assertEquals(2, count($enrolledusers));

        // Unenrol current user (student).
        enrol_self_external::unenrol_user($course->id);
        $this->setUser($teacher);
        $enrolledusers = core_enrol_external::get_enrolled_users($course->id);
        $this->assertEquals(1, count($enrolledusers));
    }

    /**
     * Test user unenrol
     */
    public function test_unenrol_user() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Check if self and manual enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('self');
        $this->assertNotEmpty($selfplugin);
        $manualplugin = enrol_get_plugin('manual');
        $this->assertNotEmpty($manualplugin);

        // Get student and teacher role for testing.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname'=>'editingteacher'));
        $this->assertNotEmpty($teacherrole);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Self enrol student.
        $this->setUser($student);
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course->id);

        // Manual enrol teacher.
        $maninstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manualplugin->enrol_user($maninstance, $teacher->id, $teacherrole->id);

        // Check if user is enrolled.
        require_once($CFG->dirroot . '/enrol/externallib.php');
        $enrolledusers = core_enrol_external::get_enrolled_users($course->id);
        $this->assertEquals(2, count($enrolledusers));

        // As teacher, unenrol student.
        $this->setUser($teacher);
        enrol_self_external::unenrol_user($course->id, $student->id);
        $enrolledusers = core_enrol_external::get_enrolled_users($course->id);
        $this->assertEquals(1, count($enrolledusers));
    }

    /**
     * Test unenrol a user who is not enrolled.
     * @expectedException moodle_exception
     */
    public function test_unenrol_notenrolled_user() {
        global $DB;
        $this->resetAfterTest(true);

        // Get enrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        // Create course and enable self enrolment.
        $course = $this->getDataGenerator()->create_course();
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        // Try unenrol current user.
        enrol_self_external::unenrol_user($course->id);
    }

    /**
     * Test unenrol self without proper capability
     * @expectedException moodle_exception
     */
    public function test_unenrol_self_without_capability() {
        global $DB;
        $this->resetAfterTest(true);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'guest'));

        // Create user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Create course and enable self enrolment.
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));

        enrol_self_external::enrol_user($course->id);

        // Try unenrol current user.
        enrol_self_external::unenrol_user($course->id);
    }

    /**
     * Test unenrol a user without proper capability
     * @expectedException moodle_exception
     */
    public function test_unenrol_user_without_capability() {
        global $DB;

        $this->resetAfterTest(true);

        // Check if self and manual enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('self');
        $this->assertNotEmpty($selfplugin);
        $manualplugin = enrol_get_plugin('manual');
        $this->assertNotEmpty($manualplugin);

        // Get student and teacher role for testing.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->assertNotEmpty($teacherrole);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Create course.
        $course = $this->getDataGenerator()->create_course();

        // Self enrol student.
        $this->setUser($student);
        $id = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $studentrole->id));
        enrol_self_external::enrol_user($course->id);

        // Manual enrol teacher.
        $maninstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manualplugin->enrol_user($maninstance, $teacher->id, $teacherrole->id);

        // As teacher, unenrol student.
        $this->setUser($teacher);
        enrol_self_external::unenrol_user($course->id, $student->id);
    }
}
