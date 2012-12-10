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
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * Self enrolment external functions.
 *
 * @package   enrol_self
 * @copyright 2012 Rajesh Taneja <rajesh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.5
 */
class enrol_self_external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function enrol_user_parameters() {
        return new external_function_parameters(
                array('enrolinstanceid' => new external_value(PARAM_INT, 'Instance id of self enrolment plugin.'),
                        'enrolmentkey' => new external_value(PARAM_RAW, 'Password key for self enrolment', VALUE_DEFAULT, null)
                )
            );
    }

    /**
     * Self enrolment of user.
     *
     * @param int $enrolinstanceid instance id of self enrolment plugin.
     * @param string $enrolmentkey optional password string required for self enrolment.
     */
    public static function enrol_user($enrolinstanceid, $enrolmentkey = null) {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_user_parameters(),
                array('enrolinstanceid' => $enrolinstanceid, 'enrolmentkey' => $enrolmentkey));

        // Retrieve the self enrolment plugin.
        $enrol = enrol_get_plugin('self');
        if (empty($enrol)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        $enrolinstance = $DB->get_record('enrol', array('id' => $params['enrolinstanceid']), '*', MUST_EXIST);
        $coursecontext = context_course::instance($enrolinstance->courseid, IGNORE_MISSING);
        $categorycontext = get_parent_contextid($coursecontext);
        self::validate_context(get_context_instance_by_id($categorycontext));

        $data = new stdClass();
        if (!empty($params['enrolmentkey'])) {
            $data->enrolpassword = $params['enrolmentkey'];
        }
        // Check if user can self enrol.
        $canselfenrol = $enrol->can_enrol($enrolinstance);
        if ($canselfenrol === enrol_self_plugin::ERRGUESTENROL) {
            throw new moodle_exception('errorguestuser', 'enrol_self');
        } else if ($canselfenrol === enrol_self_plugin::ERRALREADYENROL) {
            // Don't do anything user is enrolled and active.
            return;
        } else if ($canselfenrol === enrol_self_plugin::ERRINACTIVEENROL) {
            throw new moodle_exception('errorinactiveenrolment', 'enrol_self');
        } else if ($canselfenrol === enrol_self_plugin::ERRNONEWENROLS) {
            throw new moodle_exception('errornonewenrols', 'enrol_self');
        } else if ($canselfenrol === enrol_self_plugin::ERRNOCOHORTMEMBER) {
            $cohort = $DB->get_record('cohort', array('id' => $enrolinstance->customint5));
            $a = '';
            if ($cohort) {
                $a = format_string($cohort->name, true, array('context' => context::instance_by_id($cohort->contextid)));
            }
            throw new moodle_exception('cohortnonmemberinfo', 'enrol_self', $a);
        }

        // Check if self_enrol needs password and validate it.
        if ($enrolinstance->password) {
            $passerror = $enrol->validate_self_enrolment_password($enrolinstance, $data->enrolpassword);
            if (!empty($passerror)) {
                $hint = null;
                if (isset($passerror['hint'])) {
                    $hint = $passerror['hint'];
                }
                throw new moodle_exception($passerror['errorcode'], 'enrol_self', $hint);
            }
        }
        // No error, so enrol user.
        $enrol->self_enrol($enrolinstance);
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function enrol_user_returns() {
        return null;
    }
}
