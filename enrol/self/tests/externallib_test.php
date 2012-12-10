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
 * @package   enrol_self
 * @copyright 2013 Rajesh Taneja <rajesh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.5
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
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Check if self enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('self');
        self::assertNotEmpty($selfplugin);

        // Get student role and teacher role for testing.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        self::assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        self::assertNotEmpty($teacherrole);
        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);

        // Create user.
        $student = self::getDataGenerator()->create_user();
        $manager = self::getDataGenerator()->create_user();

        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Create courses.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course3 = self::getDataGenerator()->create_course();
        $course4 = self::getDataGenerator()->create_course();

        // Check self enrol without password.
        $id = $selfplugin->add_instance($course1,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1));
        self::setUser($student);
        enrol_self_external::enrol_user($id);

        self::setUser($manager);

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course2,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $teacherrole->id,
                    'customint6' => 1,
                    'password' => 'test'));
        self::setUser($student);
        enrol_self_external::enrol_user($id, 'test');
        self::setUser($manager);

        // Create cohort and add member.
        $cohort = self::getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $student->id);
        $id = $selfplugin->add_instance($course3,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1,
                    'customint5' => $cohort->id));
        self::setUser($student);
        enrol_self_external::enrol_user($id);
        self::setUser($manager);

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
                    'customint6' => 1,
                    'password' => 'correctkey'));
        self::setUser($student);
        enrol_self_external::enrol_user($id, 'groupkey');
        // Try enrol user again and it should not throw error.
        enrol_self_external::enrol_user($id, 'groupkey');

        // Check we retrieve the good total number of enrolled users.
        self::setUser($manager);

        require_once($CFG->dirroot . '/enrol/externallib.php');
        $enrolledusers1 = core_enrol_external::get_enrolled_users($course1->id);
        self::assertEquals(1, count($enrolledusers1));
        $enrolledusers2 = core_enrol_external::get_enrolled_users($course2->id);
        self::assertEquals(1, count($enrolledusers2));
        $enrolledusers3 = core_enrol_external::get_enrolled_users($course3->id);
        self::assertEquals(1, count($enrolledusers3));
        $enrolledusers4 = core_enrol_external::get_enrolled_users($course4->id);
        self::assertEquals(1, count($enrolledusers4));
    }

    /**
     * Test if invalid enrol instance is passed
     * @expectedException moodle_exception
     */
    public function test_enrol_invalid_enrol_instance() {
        $this->resetAfterTest(true);
        $student = self::getDataGenerator()->create_user();
        self::setUser($student);

        enrol_self_external::enrol_user(0);
    }

    /**
     * Test when no new enrolments are allowed.
     * @expectedException moodle_exception
     */
    public function test_enrol_no_new_enrolments() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        // Try enrol user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($id);
    }

    /**
     * Test guest user should not self enrol.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_guest_user() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1));

        self::setGuestUser();
        enrol_self_external::enrol_user($id);
    }

    /**
     * Test enroling existing enrolled user, and user is suspended.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrolled_user() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1));

        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($id);

        // Set status of user to be suspended and try enrol again.
        self::setUser($manager);
        $instance = $DB->get_record('enrol', array('id' => $id), '*', MUST_EXIST);
        $selfplugin->update_user_enrol($instance, $user->id, 1);
        self::setUser($user);
        enrol_self_external::enrol_user($id);
    }

    /**
     * Test enroling user in self enrolled course whose start date is in future.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrol_notstarted() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        $starttime = time() + 60; // Make start time in future.
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1,
                    'enrolstartdate' => $starttime));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($id);
    }

    /**
     * Test enroling user in self enrolled course whose end date is in past.
     * @expectedException moodle_exception
     */
    public function test_enrol_user_enrol_finshed() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        // Make start time in future.
        $endtime = time() - 60;
        // Try enrol guest user and you should get exception.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1,
                    'enrolenddate' => $endtime));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling user in self enrolled course with wrong password key
     * @expectedException moodle_exception
     */
    public function test_enrol_user_wrong_key() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();
        // Check self enrol with password.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1,
                    'password' => 'correctkey'));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($course->id, 'wrongkey');
    }

    /**
     * Test enroling user in self enrolled course where user is not a member of cohort
     * @expectedException moodle_exception
     */
    public function test_enrol_user_no_cohort_member() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

        // Get selenrol plugin and student role.
        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $course = self::getDataGenerator()->create_course();

        // Create cohort.
        $cohort = self::getDataGenerator()->create_cohort();

        // Check self enrol with password.
        $id = $selfplugin->add_instance($course,
                array('status' => ENROL_INSTANCE_ENABLED,
                    'roleid' => $studentrole->id,
                    'customint6' => 1,
                    'customint5' => $cohort->id));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($course->id);
    }

    /**
     * Test enroling user in self enrolled course with wrong group password key
     * @expectedException moodle_exception
     */
    public function test_enrol_user_wrong_group_key() {
        global $DB;
        $this->resetAfterTest(true);

        $managerrole = $DB->get_record('role', array('shortname'=>'manager'));
        self::assertNotEmpty($managerrole);
        $manager = self::getDataGenerator()->create_user();
        role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        self::setUser($manager);

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
                    'customint6' => 1,
                    'password' => 'correctkey'));
        // Try enrol user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        enrol_self_external::enrol_user($course->id, 'wrongkey');
    }
}
