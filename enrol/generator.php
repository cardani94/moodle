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
 * random data generator for enrol subsystem.
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot .'/admin/generator/baseclass.php');
require_once($CFG->dirroot .'/lib/enrollib.php');
require_once($CFG->dirroot .'/lib/accesslib.php');

class generator_enrol extends generator_base {

    protected $enrolments = array();

    /**
     * Constructor initalizing generator information. There are three essentail
     * variables we nedd
     */
    public function __construct() {
        //short name of user plugin
        $this->shortname = 'enrol';

        //long name of user plugin
        $this->longname = 'Enrol users';

        //There is no prerequisite for this plugin
        $this->generatorprerequisite = array('user', 'course');
    }

    /**
     * This should return any perticular plugin configuration which require user
     * input like how many users, forms etc.
     * 
     * @param MoodleQuickForm $mform object of moodleform to add configuration
     *        configuration
     * @param bool $detailed if true should show all configuration else should show
     *        minimum configuration
     */
    public function generator_configuration(MoodleQuickForm $mform, $detailed, $randomize) {
        $mform->addElement('text', 'maxenroluserpercourse', 'Students per course', 'size = "10"');
        $mform->setDefault('maxenroluserpercourse', 5);
        $mform->setType('maxenroluserpercourse', PARAM_INT);

        //random theme, language and translation option
        if ($detailed) {
            $mform->addElement('text', 'maxenrolteacherpercourse', 'Max teacher per course', 'size = "10"');
            $mform->setDefault('maxenrolteacherpercourse', 1);
            $mform->setType('maxenrolteacherpercourse', PARAM_INT);
            $mform->addElement('checkbox', 'enrolgeneratorrandomize', 'Random Data');
        } else {
            $mform->addElement('hidden', 'maxenrolteacherpercourse', '1');
            if ($randomize) {
                $mform->addElement('hidden', 'enrolgeneratorrandomize', '1');
            }
        }
        $mform->setType('enrolgeneratorrandomize', PARAM_INT);
    }

    /**
     * Validate configuration for plugin generator
     * Any validation check for generator configutaion should be done here.
     *
     * @param object generator object for accessing generated values.
     *
     * @return array error message
     */
    public function validate_configuration($data, $file) {
        //if generated users are less then required users per course then throw error
        if ($data['usergeneratornumber'] < $data['maxenroluserpercourse']) {
            return array('maxenroluserpercourse' =>
                'Maximum enrolments should be less then generated users');
        } else if ($data['maxenrolteacherpercourse'] < 0) {
            return array('maxenrolteacherpercourse' =>
                'Maximum teachers should be 0 or more');
        } else {
            return null;
        }
    }

    /* This should generate data for plugin and update
     *
     * @param bool $verbose options if true should echo details of what is being inserted
     * @return bool true if success else false
     */
    public function generate_data($verbose = true) {
        $this->show_progress('Assigning role and showing status to students', self::START_PROCESS);
        $coursegenerator = generator::get_generator()->get_plugin_generator('course');
        $usergenerator = generator::get_generator()->get_plugin_generator('user');

        $enroldata = array();

        //get all the configuration parameters passed by user.
        $maxuserpercourse = optional_param('maxenroluserpercourse', '', PARAM_INT);
        $maxteacherpercourse = optional_param('maxenrolteacherpercourse', '', PARAM_INT);
        $randomdata = optional_param('enrolgeneratorrandomize', '', PARAM_INT);
        $randomdata = empty($randomdata) ? false : true;

        //get all recently generated courses
        $coursedata = $coursegenerator->data();
        $courses = $coursedata['courses'];
        if ($randomdata) {
            shuffle($courses);
        }

        //get all recently generated users
        $users = $usergenerator->data(0);
        if ($randomdata) {
            shuffle($users);
        }

        foreach ($courses as $courseid) {
            $enrolment = array();
            $numberofenrolments = $maxuserpercourse;
            $context = get_context_instance(CONTEXT_COURSE, $courseid);
            if ($randomdata) {
                $numberofenrolments = rand(0, $maxuserpercourse);
            }
            if($verbose) {
                $this->show_progress("Assigning {$numberofenrolments} users and ".
                    "{$maxteacherpercourse} teachers to course {$courseid}", self::PROCESSING);
            }
            for ($count = 0; $count < $numberofenrolments; $count++) {
                $enrolment['roleid'] = 5; //students
                $enrolment['userid'] = $users[$count];
                $enrolment['contextid'] = $context->id;
                $enrolment['courseid'] = $courseid;
                //Push user array to list of enrolments array.
                array_push($enroldata, $enrolment);
                $this->enrol_user($enrolment);
            }
            shuffle($enroldata);
            for ($i = 0; $i < $maxteacherpercourse; $i++) {
                $teacherdata = $enroldata[$i];
                $teacherdata['roleid'] = 3; //teacher
                array_push($enroldata, $teacherdata);
                $this->enrol_user($teacherdata);
            }
            if ($randomdata) {
                shuffle($users);
            }
        }
        $this->enrolments = $enroldata;
        $this->show_progress('Role assignment and enrolment complete', self::END_PROCESS);
    }

    /**
     * Enrol user in the course
     */
    protected function enrol_user($enrolment) {
        //assign role to user
        //not using external lib as it doesn't accept theme set for page.
        role_assign($enrolment['roleid'], $enrolment['userid'], $enrolment['contextid']);
        //enrol user
        enrol_try_internal_enrol($enrolment['courseid'], $enrolment['userid'], $enrolment['roleid']);
    }

    /**
     * This should return data which was generated by generate_data, to be shared
     * by other generator's so that database hits can be reduced.
     * if this plugin data is used by other plugins then they should be retrived
     * by this function.
     * Try keep it minimum to avoid memory issues, like for users just the id is
     * used often so no need to keep rest of the data.
     * 
     * @param array variablename => number of values
     * @return array
     */
    public function data($dataarray = null) {
        //Return full array if dataarray is null
        $this->enrolments;
    }

    /**
     * Clean data generated by this plugin generator
     */
    public function clean_data() {
        //Only manual enrolements are done
        $plugin = enrol_get_plugin('manual');
        foreach ($this->enrolments as $enrolment) {
            $instances = enrol_get_instances($enrolment['courseid'], false);
            foreach ($instances as $instance) {
                if ('manual' === $instance->enrol) {
                    $plugin->unenrol_user($instance, $enrolment['userid']);
                    break;
                }
            }

            role_unassign($enrolment['roleid'], $enrolment['userid'], $enrolment['contextid']);
        }
    }
}