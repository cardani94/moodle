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
if (!defined('REPORT_LOG_MAX_DISPLAY')) {
    define('REPORT_LOG_MAX_DISPLAY', 150); // Days.
}

trait core_reports_logging {
    /** @var \core\log\manager log manager */
    protected $logmanager;

    /** @var string selected reader pluginname */
    public $selectedreader = null;

    /**
     * Get a list of enabled reader objects
     *
     * @param bool $nameonly if true only reader names will be returned.
     * @return array \core\log\reader list of reader objects is returend as an array.
     */
    public function get_readers($nameonly = false) {
        if (!isset($this->manager)) {
            $this->logmanager = get_log_manager();
        }

        $readers = $this->logmanager->get_readers();
        if ($nameonly) {
            foreach ($readers as $pluginname => $reader) {
                $readers[$pluginname] = $reader->get_name();
            }
        }
        return $readers;
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
     * Helper function for backward compatibility of legacy logs.
     *
     * @param \core\log\reader|string $reader reader to check if it's legacy log reader.
     * @return bool true if leagcy reader else false.
     */
    public function is_legacy_reader($reader) {
        if (empty($reader)) {
            throw new coding_exception('No reader passed');
        }

        if (is_object($reader)) {
            if ($reader instanceof logstore_legacy\log\store) {
                return true;
            } else {
                return false;
            }
        } else {
            if ($reader == 'logstore_legacy') {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Print reader selector or return as html.
     *
     * @param bool $return if true, selector html will be returned.
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

    /**
     * Build log data for log report.
     *
     * @param string $reader reader pluginname from which logs will be fetched.
     * @param stdClass|int $course (optional) course record or id
     * @param int $userid (optional) id of user to filter records for.
     * @param string $component (optional) component name to filter.
     * @param int|string $modid (optional) module id or site_errors for filtering errors.
     * @param string $action (optional) action name to filter.
     * @param int $groupid (optional) groupid of user.
     * @param int $edulevel (optional) educational level.
     * @param int $date date (optional) from which records will be fetched.
     * @param int $limitfrom (optional) return a subset of records, starting at this point.
     * @param int $limitnum (optional) return a subset comprising this many records in total (required if $limitfrom is set).
     * @param string $order (optional) sortorder of fetched records
     * @return array.
     */
    public function build_logs($reader, $course = 0, $userid = 0, $component = "", $modid = 0, $action = "",
            $groupid = -1, $edulevel = -1, $date = 0, $limitfrom = 0, $limitnum = 100, $order = "timecreated ASC") {

        global $DB, $SESSION, $USER;
        // It is assumed that $date is the GMT time of midnight for that day,
        // and so the next 86400 seconds worth of logs are printed.

        if (!empty($course)) {
            // Setup for group handling.
            // If the group mode is separate, and this user does not have editing privileges, then only the user's group can be viewed.
            $context = context_course::instance($course->id);
            if ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/course:managegroups', $context)) {
                if (isset($SESSION->currentgroup[$course->id])) {
                    $groupid =  $SESSION->currentgroup[$course->id];
                } else {
                    $groupid = groups_get_all_groups($course->id, $USER->id);
                    if (is_array($groupid)) {
                        $groupid = array_shift(array_keys($groupid));
                        $SESSION->currentgroup[$course->id] = $groupid;
                    } else {
                        $groupid = 0;
                    }
                }
            } else if (!$course->groupmode) { // If this course doesn't have groups, no groupid can be specified.
                $groupid = 0;
            }

            $joins = array();
            $params = array();

            if ($course->id != SITEID || $modid != 0) {
                $joins[] = "courseid = :courseid";
                $params['courseid'] = $course->id;
            }
        }

        if ('site_errors' === $modid) {
            $joins[] = "( action='error' OR action='infected' OR action='failed' )";
        } else if ($modid) {
            $joins[] = "contextinstanceid = :contextinstanceid";
            $params['contextinstanceid'] = $modid;
        }

        if ($action) {
            $firstletter = substr($action, 0, 1);
            if ($firstletter == '-') {
                $joins[] = $DB->sql_like('action', ':action', false, true, true);
                $params['action'] = '%'.substr($action, 1).'%';
            } else {
                $joins[] = $DB->sql_like('action', ':action', false);
                $params['action'] = '%'.$action.'%';
            }
        }


        // Getting all members of a group.
        if ($groupid and !$userid) {
            if ($gusers = groups_get_members($groupid)) {
                $gusers = array_keys($gusers);
                $joins[] = 'userid IN (' . implode(',', $gusers) . ')';
            } else {
                $joins[] = 'userid = 0'; // No users in groups, so we want something that will always be false.
            }
        }
        else if ($userid) {
            $joins[] = "userid = :userid";
            $params['userid'] = $userid;
        }

        if ($date) {
            $enddate = $date + 86400;
            $joins[] = "timecreated > :date AND timecreated < :enddate";
            $params['date'] = $date;
            $params['enddate'] = $enddate;
        }

        if ($component) {
            $joins[] = "component = :component";
            $params['component'] = $component;
        }

        if ($edulevel >= 0 && !($this->is_legacy_reader($reader))) {
            $joins[] = "edulevel = :edulevel";
            $params['edulevel'] = $edulevel;
        }

        $selector = implode(' AND ', $joins);

        $result = array();
        $result['events'] = $reader->get_events($selector, $params, $order, $limitfrom, $limitnum);
        $result['totalcount'] = $reader->get_events_count($selector, $params);
        return $result;
    }

    /**
     * Helper function to returns list of course shortname and user fullname used in events list.
     *
     * @param \core\event\base $events list of events.
     * @return array list of user fullname and course shortnames.
     */
    public function get_users_and_courses_used($events) {
        $result = array();
        $result['userfullname'] = array();
        $result['courseshortname'] = array();
        // For each event cache full username and course. //TODO use request muc.
        foreach ($events as $event) {
            $logextra = $event->get_logextra();
            if (!isset($result['userfullname'][$event->userid])) {
                $result['userfullname'][$event->userid] = fullname(get_complete_user_data('id', $event->userid));
            }
            if (!empty($logextra['realuserid']) && !isset($result['userfullname'][$logextra['realuserid']])) {
                $result['userfullname'][$logextra['realuserid']] = fullname(get_complete_user_data('id', $logextra['realuserid']));
            }
            if (!empty($event->relateduserid) && !isset($result['userfullname'][$event->relateduserid])) {
                $result['userfullname'][$event->relateduserid] = fullname(get_complete_user_data('id', $event->relateduserid));
            }

            if (!isset($result['courseshortname'][$event->courseid])) {
                if ($event->courseid == SITEID) {
                    $result['courseshortname'][$event->courseid] = $SITE->shortname;
                } else if (!empty($event->courseid)) {
                    $url = new moodle_url("/course/view.php", array('id' => $event->courseid));
                    $course = get_course($event->courseid, false);
                    $result['courseshortname'][$event->courseid] = html_writer::link($url, format_string($course->shortname));
                }
            }
        }
        return $result;
    }
}