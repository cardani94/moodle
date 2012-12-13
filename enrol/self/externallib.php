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
 * Self enrol plugin external functions
 *
 * @package    enrol_self
 * @copyright  2012 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * Self enrolment external functions.
 *
 * @package    enrol_self
 * @copyright  2012 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.5
 */
class enrol_self_external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function enrol_user_parameters() {
        return new external_function_parameters(
                array('courseid' => new external_value(PARAM_INT, 'The course to enrol the user role in'),
                      'enrolmentkey' => new external_value(PARAM_RAW, 'Password key for self enrolment', VALUE_DEFAULT, null)
                )
            );
    }

    /**
     * Self enrolment of user.
     *
     * @param int $courseid id of the course in which user wnats to enrol
     * @param string $enrolmentkey optional password string required for self enrolment.
     */
    public static function enrol_user($courseid, $enrolmentkey = null) {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_user_parameters(),
                array('courseid' => $courseid, 'enrolmentkey' => $enrolmentkey));

        // Retrieve the self enrolment plugin.
        $enrol = enrol_get_plugin('self');
        if (empty($enrol)) {
            throw new moodle_exception('wsselfpluginnotinstalled', 'enrol_self');
        }

        // Check self enrolment plugin instance is enabled/exist.
        $enrolinstances = enrol_get_instances($params['courseid'], true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "self") {
                $instance = $courseenrolinstance;
                break;
            }
        }
        if (empty($instance)) {
            throw new moodle_exception('wsnoinstance', 'enrol_self');
        }

        $data = array();
        if (!empty($params['enrolmentkey'])) {
            $data['enrolpassword'] = $params['enrolmentkey'];
        }

        $selfenrol = $enrol->self_enrol($instance, $data);
        if ($selfenrol === enrol_self_plugin::ERRGUESTENROL) {
            throw new moodle_exception('errorguestuser', 'enrol_self');
        } else if ($selfenrol === enrol_self_plugin::ERRALREADYENROL) {
            throw new moodle_exception('erroralreadyenrolled', 'enrol_self');
        } else if ($selfenrol === enrol_self_plugin::ERRNOENROLYET) {
            throw new moodle_exception('errornoenrolyet', 'enrol_self');
        } else if ($selfenrol === enrol_self_plugin::ERRENROLOVER) {
            throw new moodle_exception('errorenrolover', 'enrol_self');
        } else if ($selfenrol === enrol_self_plugin::ERRENROLKEY) {
            throw new moodle_exception('passwordinvalid', 'enrol_self');
        } else if ($selfenrol === enrol_self_plugin::ERRNOCOHORTMEMBER) {
            $cohort = $DB->get_record('cohort', array('id' => $instance->customint5));
            if (!$cohort) {
                $a = '';
            }
            $context = context::instance_by_id($cohort->contextid);
            self::validate_context($context);
            $a = format_string($cohort->name, true, array('context' => $context));
            throw new moodle_exception('cohortnonmemberinfo', 'enrol_self', $a);
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function enrol_user_returns() {
        return null;
    }

    /**
     * Returns description of unenrol_user parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_user_parameters() {
        return new external_function_parameters(
                array('courseid' => new external_value(PARAM_INT, 'Id of course from which user want to unenrol'),
                      'userid' => new external_value(PARAM_RAW, 'Optional userid who should be unenrolled', VALUE_DEFAULT, null)
                )
            );
    }

    /**
     * Self unenrolment of user.
     *
     * @param int $courseid id of the course from which user want to unenrol.
     * @param int $userid (optional) id of user else current user is unenrolled.
     */
    public static function unenrol_user($courseid, $userid = null) {
        global $DB, $CFG, $USER;
        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::unenrol_user_parameters(),
                array('courseid' => $courseid));

        // Retrieve the self enrolment plugin.
        $enrol = enrol_get_plugin('self');
        if (empty($enrol)) {
            throw new moodle_exception('wsselfpluginnotinstalled', 'enrol_self');
        }

        // Check self enrolment plugin instance is enabled/exist.
        $enrolinstances = enrol_get_instances($params['courseid'], true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "self") {
                $instance = $courseenrolinstance;
                break;
            }
        }
        if (empty($instance)) {
            throw new moodle_exception('wsnoinstance', 'enrol_self');
        }

        if (!$enrol->allow_unenrol($instance)) {
            throw new moodle_exception('errorunenrol', 'enrol_self');
        }

        $context = context_course::instance($courseid);
        self::validate_context($context);

        // If userid is not empty then check capability.
        if (!empty($userid)) {
            if (($userid != $USER->id) && !has_capability('enrol/self:unenrol', $context)) {
                throw new moodle_exception('errorunenrol', 'enrol_self');
            } else if (($userid == $USER->id) &&
                    !has_any_capability(array('enrol/self:unenrol', 'enrol/self:unenrolself'), $context)) {
                // Current user should have either capability to unenrol.
                throw new moodle_exception('errorunenrol', 'enrol_self');
            }
        } else {
            // Current user should have either capability to unenrol.
            if (!has_any_capability(array('enrol/self:unenrol', 'enrol/self:unenrolself'), $context)) {
                throw new moodle_exception('errorunenrol', 'enrol_self');
            }
            $userid = $USER->id;
        }

        // Check if user was enrolled in course.
        if (!$ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid))) {
            throw new moodle_exception('errorunenrol', 'enrol_self');
        }

        $enrol->unenrol_user($instance, $userid);
    }

    /**
     * Returns description of unenrol_user result value.
     *
     * @return null
     */
    public static function unenrol_user_returns() {
        return null;
    }
}
