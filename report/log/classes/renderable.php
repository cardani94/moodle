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
 * Log report renderer.
 *
 * @package    report_log
 * @copyright  2014 Rajesh Taneja <rajesh.taneja@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Report log renderable class.
 *
 * @package    report_log
 * @copyright  2014 Rajesh Taneja <rajesh.taneja@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_log_renderable implements renderable {
    /** @var \core\log\manager log manager */
    protected $logmanager;

    /** @var string selected log reader pluginname */
    public $selectedlogreader = null;

    /** @var int page number */
    public $page;

    /** @var int perpage records to show */
    public $perpage;

    /** @var stdClass course record */
    public $course;

    /** @var moodle_url url of report page */
    public $url;

    /** @var int selected date from which records should be displayed */
    public $selecteddate;

    /** @var int selected user id for which logs are displayed */
    public $selecteduser;

    /** @var int selcetd moduleid */
    public $selectedmodid;

    /** @var string selected action filter */
    public $action;

    /** @var int educational level */
    public $edulevel;

    /** @var bool show courses */
    public $showcourses;

    /** @var bool show users */
    public $showusers;

    /** @var bool show report */
    public $showreport;

    /** @var bool show selector form */
    public $showselectorform;

    /** @var string selected log format */
    public $selectedlogformat;

    /** @var string order to sort */
    public $order;

    /** @var int group id */
    public $groupid;

    /**
     * Constructor.
     *
     * @param string $logreader (optional)reader pluginname from which logs will be fetched.
     * @param stdClass|int $course (optional) course record or id
     * @param int $userid (optional) id of user to filter records for.
     * @param int|string $modid (optional) module id or site_errors for filtering errors.
     * @param string $action (optional) action name to filter.
     * @param int $groupid (optional) groupid of user.
     * @param int $edulevel (optional) educational level.
     * @param bool $showcourses (optional) show courses.
     * @param bool $showusers (optional) show users.
     * @param bool $showreport (optional) show report.
     * @param bool $showselectorform (optional) show selector form.
     * @param moodle_url|string $url (optional) page url.
     * @param int $date date (optional) from which records will be fetched.
     * @param string $logformat log format.
     * @param int $page (optional) page number.
     * @param int $perpage (optional) number of records to show per page.
     * @param string $order (optional) sortorder of fetched records
     */
    public function __construct($logreader = "", $course = 0, $userid = 0, $modid = 0, $action = "", $groupid = 0, $edulevel = -1,
            $showcourses = false, $showusers = false, $showreport = true, $showselectorform = true, $url = "", $date = 0,
            $logformat='showashtml', $page = 0, $perpage = 100, $order = "timecreated ASC") {

        global $PAGE;
        // Use first reader as selected reader, if not passed.
        if (empty($logreader)) {
            $readers = $this->get_readers();
            if (!empty($readers)) {
                reset($readers);
                $logreader = key($readers);
            } else {
                $logreader = null;
            }
        }
        // Use page url if empty.
        if (empty($url)) {
            $url = new moodle_url($PAGE->url);
        } else {
            $url = new moodle_url($url);
        }
        $this->selectedlogreader = $logreader;

        // Use site course id, if course is empty.
        if (!empty($course) && is_int($course)) {
            $course = get_course($course);
        }
        $this->course = $course;

        $this->selecteduser = $userid;
        $this->selecteddate = $date;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->url = $url;
        $this->order = $order;
        $this->selectedmodid = $modid;
        $this->action = $action;
        $this->groupid = $groupid;
        $this->edulevel = $edulevel;
        $this->showcourses = $showcourses;
        $this->showusers = $showusers;
        $this->showreport = $showreport;
        $this->showselectorform = $showselectorform;
        $this->selectedlogformat = $logformat;
    }

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
            echo get_string('nologreaderenabled', 'report_log');
            return;
        }
        foreach ($readers as $k => $v) {
            $options[$k] = $v->get_name();
        }
        $select = new single_select($PAGE->url, 'reader', $options, $reader, null);
        $select->set_label(get_string('selectreader'));
        if ($return) {
            return $select;
        }
        echo $OUTPUT->render($select);
    }

    /**
     * Build log data for log report.
     *
     * @param \core\log\reader $reader reader object from which logs will be fetched.
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
    public function build_logs(\core\log\reader $reader, $course = 0, $userid = 0, $component = "", $modid = 0, $action = "",
            $groupid = 0, $edulevel = -1, $date = 0, $limitfrom = 0, $limitnum = 100, $order = "timecreated ASC") {

        global $DB, $SESSION, $USER;
        // It is assumed that $date is the GMT time of midnight for that day,
        // and so the next 86400 seconds worth of logs are printed.

        $joins = array();
        $params = array();

        if (!empty($course)) {
            // Setup for group handling.
            // If the group mode is separate, and this user does not have editing privileges,
            // then only the user's group can be viewed.
            $context = context_course::instance($course->id);
            if ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/course:managegroups', $context)) {
                if (isset($SESSION->currentgroup[$course->id])) {
                    $groupid = $SESSION->currentgroup[$course->id];
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

            $joins[] = "courseid = :courseid";
            $params['courseid'] = $course->id;
        }

        if ('site_errors' === $modid) {
            $joins[] = "( action='error' OR action='infected' OR action='failed' )";
        } else if ($modid) {
            $joins[] = "contextinstanceid = :contextinstanceid";
            $params['contextinstanceid'] = $modid;
        }

        if ($action) {
            // In new logs we have a field to pick, and in legacy try get this from action.
            if ($reader instanceof logstore_legacy\log\store) {
                $action = $this->get_legacy_crud_action($action);
                $firstletter = substr($action, 0, 1);
                if ($firstletter == '-') {
                    $joins[] = $DB->sql_like('action', ':action', false, true, true);
                    $params['action'] = '%'.substr($action, 1).'%';
                } else {
                    $joins[] = $DB->sql_like('action', ':action', false);
                    $params['action'] = '%'.$action.'%';
                }
            } else {
                $joins[] = "crud = :crud";
                $params['crud'] = $action;
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
        } else if ($userid) {
            $joins[] = "userid = :userid";
            $params['userid'] = $userid;
        }

        if ($date) {
            $joins[] = "timecreated > :date";
            $params['date'] = $date;
        }

        if ($component) {
            $joins[] = "component = :component";
            $params['component'] = $component;
        }

        if ($edulevel >= 0) {
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
        global $SITE;

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
                if ($event->courseid == $SITE->id) {
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

    /**
     * Return list of columns in table.
     *
     * @return array columns array.
     */
    public function get_table_cols() {
        $cols = array();
        if (empty($this->course)) {
            $cols = array(
                'course' => get_string('course')
            );
        }
        $cols += array(
            'time' => get_string('time'),
            'fullnameuser' => get_string('fullnameuser'),
            'relatedfullnameuser' => get_string('eventrelatedfullnameuser', 'report_log'),
            'context' => get_string('eventcontext', 'report_log'),
            'component' => get_string('eventcomponent', 'report_log'),
            'eventname' => get_string('eventname'),
            'description' => get_string('description'),
            'origin' => get_string('eventorigin', 'report_log'),
            'ip' => get_string('ip_address')
        );
        return $cols;
    }

    /**
     * Setup log table and return it.
     *
     * @return flexible_table table with headers.
     */
    public function setup_table() {
        // Prepend coursename if showing all courses.
        $cols = $this->get_table_cols();

        $table = new \flexible_table('reportlog');
        $table->define_baseurl($this->url);
        $table->define_columns(array_keys($cols));
        $table->define_headers(array_values($cols));
        $table->sortable(false);
        $table->collapsible(false);
        $table->pageable(true);
        $table->setup();
        return $table;
    }

    /**
     * Return log data totalcount and logs.
     *
     * @todo Frontpage records will not be filtered as SITE and frontpage share same courseid.
     * @return array log data to display.
     */
    public function get_logs() {
        global $OUTPUT;

        // Build log report and process it.
        $readers = $this->get_readers();
        $logevents = $this->build_logs($readers[$this->selectedlogreader], $this->course, $this->selecteduser,
                "", $this->selectedmodid, $this->action, $this->groupid, $this->edulevel, $this->selecteddate,
                $this->page * $this->perpage, $this->perpage, $this->order);

        // Fetch userfullname and course shortname to be shown in report.
        $eventextradata = $this->get_users_and_courses_used($logevents['events']);

        // Get log data from event and update list.
        foreach ($logevents['events'] as $key => $event) {
            if ($event->contextid) {
                $context = context::instance_by_id($event->contextid, IGNORE_MISSING);
            } else {
                $context = false;
            }

            // Get extra event data for origin and realuserid.
            $logextra = $event->get_logextra();

            // Create log row data.
            $row = array();
            // Add course shortname if all courses are displayed.
            if (empty($this->course)) {
                if (empty($event->courseid)) {
                    $row[] = '-';
                } else {
                    $row[] = $eventextradata['courseshortname'][$event->courseid];
                }
            }

            // Add time stamp.
            $recenttimestr = get_string('strftimerecent', 'core_langconfig');
            $row[] = userdate($event->timecreated, $recenttimestr);

            // Add username who did the action.
            if (!empty($logextra['realuserid'])) {
                $a = new stdClass();
                $a->realusername = html_writer::link(new moodle_url("/user/view.php?id={$event->userid}&course={$event->courseid}"),
                        $eventextradata['userfullname'][$logextra['realuserid']]);
                $a->asusername = html_writer::link(new moodle_url("/user/view.php?id={$event->userid}&course={$event->courseid}"),
                        $eventextradata['userfullname'][$event->userid]);
                $username = get_string('eventloggedas', 'report_log', $a);
            } else {
                $username = $eventextradata['userfullname'][$event->userid];
                $params = array('id' => $event->userid);
                if ($event->courseid) {
                    $params['course'] = $event->courseid;
                }
                $username = html_writer::link(new moodle_url("/user/view.php", $params), $username);
            }
            $row[] = $username;

            // Add affected user.
            if (!empty($event->relateduserid)) {
                $row[] = html_writer::link(new moodle_url("/user/view.php?id=" . $event->relateduserid . "&course=" .
                        $event->courseid), $eventextradata['userfullname'][$event->relateduserid]);
            } else {
                $row[] = '-';
            }

            // Add context name.
            $contextname = get_string('other');
            if ($context) {
                $contextname = $context->get_context_name(true);
                if ($url = $context->get_url()) {
                    $contextname = $OUTPUT->action_link($url, $contextname , new popup_action('click', $url, 'contextname'),
                            array('height' => 440, 'width' => 700));
                }
            }
            $row[] = $contextname;

            // Component.
            $componentname = $event->component;
            if (($event->component === 'core') || ($event->component === 'legacy')) {
                $row[] = get_string('coresystem');
            } else if (get_string_manager()->string_exists('pluginname', $event->component)) {
                $row[] = get_string('pluginname', $event->component);
            } else {
                $row[] = $componentname;
            }

            // Event name.
            $eventname = $event->get_name();
            if ($url = $event->get_url()) {
                $eventname = $OUTPUT->action_link($url, $eventname , new popup_action('click', $url, 'action'),
                        array('height' => 440, 'width' => 700));
            }
            $row[] = $eventname;

            // Description.
            $row[] = $event->get_description();

            // Add event origin, normally IP/cron.
            $row[] = $logextra['origin'];
            $link = new moodle_url("/iplookup/index.php?ip={$logextra['ip']}&user=$event->userid");

            // Add event ip.
            $row[] = $OUTPUT->action_link($link, $logextra['ip'], new popup_action('click', $link, 'iplookup',
                    array('height' => 440, 'width' => 700)));
            // Replace event data with log data to show.
            $logevents['events'][$key] = $row;
        }

        return $logevents;
    }

    /**
     * Helper function to return log formats.
     *
     * @return array log formats.
     */
    public function get_log_formats() {
        return array('showashtml' => get_string('displayonpage'),
                'downloadascsv' => get_string('downloadtext'),
                'downloadasods' => get_string('downloadods'),
                'downloadasexcel' => get_string('downloadexcel'));
    }
    /**
     * Helper function to return list of activities to show in selection filter.
     *
     * @return array list of activities.
     */
    public function get_activities_list() {
        $activities = array();

        // For site just return site errors option.
        $sitecontext = context_system::instance();
        if (empty($this->course) && has_capability('report/log:view', $sitecontext)) {
            $activities["site_errors"] = get_string("siteerrors");
            return $activities;
        }

        $modinfo = get_fast_modinfo($this->course);
        if (!empty($modinfo->cms)) {
            $section = 0;
            $thissection = array();
            foreach ($modinfo->cms as $cm) {
                if (!$cm->uservisible || !$cm->has_view()) {
                    continue;
                }
                if ($cm->sectionnum > 0 and $section <> $cm->sectionnum) {
                    $activities[] = $thissection;
                    $thissection = array();
                }
                $section = $cm->sectionnum;
                $modname = strip_tags($cm->get_formatted_name());
                if (core_text::strlen($modname) > 55) {
                    $modname = core_text::substr($modname, 0, 50)."...";
                }
                if (!$cm->visible) {
                    $modname = "(".$modname.")";
                }
                $key = get_section_name($this->course, $cm->sectionnum);
                if (!isset($thissection[$key])) {
                    $thissection[$key] = array();
                }
                $thissection[$key][$cm->id] = $modname;
            }
            if (!empty($thissection)) {
                $activities[] = $thissection;
            }
        }
        return $activities;
    }

    /**
     * Helper function to get selected group.
     *
     * @return int selected group.
     */
    public function get_selected_group() {
        global $SESSION, $USER;

        // No groups for system.
        if (empty($this->course)) {
            return 0;
        }

        $context = context_course::instance($this->course->id);

        $selectedgroup = 0;
        // Setup for group handling.
        $groupmode = groups_get_course_groupmode($this->course);
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            $selectedgroup = -1;
        } else if ($groupmode) {
            $selectedgroup = $this->groupid;
        } else {
            $selectedgroup = 0;
        }

        if ($selectedgroup === -1) {
            if (isset($SESSION->currentgroup[$this->course->id])) {
                $selectedgroup = $SESSION->currentgroup[$this->course->id];
            } else {
                $selectedgroup = groups_get_all_groups($this->course->id, $USER->id);
                if (is_array($selectedgroup)) {
                    $selectedgroup = array_shift(array_keys($selectedgroup));
                    $SESSION->currentgroup[$this->course->id] = $selectedgroup;
                } else {
                    $selectedgroup = 0;
                }
            }
        }
        return $selectedgroup;
    }

    /**
     * Return list of actions for log reader.
     *
     * @todo get list from some automatic class.
     * @return array list of action options.
     */
    public function get_actions() {
        $actions = array(
                'c' => get_string('create'),
                'r' => get_string('view'),
                'u' => get_string('update'),
                'd' => get_string('delete'),
                '' => get_string('allchanges')
                );
        return $actions;
    }

    /**
     * Helper function to get legacy crud action.
     *
     * @param string $crud crud action
     * @return string legacy action.
     */
    public function get_legacy_crud_action($crud) {
        $legacyactionmap = array('c' => 'add', 'r' => 'view', 'u' => 'update', 'd' => 'delete');
        if (array_key_exists($crud, $legacyactionmap)) {
            return $legacyactionmap[$crud];
        } else {
            // From old legacy log.
            return '-view';
        }
    }

    /**
     * Return selected user fullname.
     *
     * @return string user fullname.
     */
    public function get_selected_user_fullname() {
        global $DB;

        $user = $DB->get_record('user', array('id' => $this->selecteduser));
        return fullname($user);
    }

    /**
     * Return list of courses to show in selector.
     *
     * @return array list of courses.
     */
    public function get_course_list() {
        global $DB, $SITE;

        $courses = array();

        $sitecontext = context_system::instance();
        // First check to see if we can override showcourses and showusers.
        $numcourses = $DB->count_records("course");
        // Check if course filter should be shown.
        if ((has_capability('report/log:view', $sitecontext)) && ($this->showcourses ||
                (empty($this->showcourses) && ($numcourses < COURSE_MAX_COURSES_PER_DROPDOWN)))) {
            $courses[0] = get_string('sitelogs');
            $this->showcourses = true;
            if ($ccc = $DB->get_records("course", null, "fullname", "id,shortname,fullname,category")) {
                foreach ($ccc as $cc) {
                    if ($cc->category) {
                        $courses[$cc->id] = format_string(get_course_display_name_for_list($cc));
                    } else {
                        $courses[$cc->id] = $SITE->shortname;
                    }
                }
            }
            asort($courses);
        } else {
            if (!empty($this->course->id)) {
                $coursecontext = context_course::instance($this->course->id);
                if (has_capability('report/log:view', $coursecontext)) {
                    $courses[$this->course->id] = format_string(get_course_display_name_for_list($this->course));
                } else {
                    $this->showcourses = false;
                }
            } else {
                $this->showcourses = false;
            }
        }
        return $courses;
    }

    /**
     * Return list of groups.
     *
     * @return array list of groups.
     */
    public function get_group_list() {

        // No groups for system.
        if (empty($this->course)) {
            return array();
        }

        $context = context_course::instance($this->course->id);
        $groups = array();
        $groupmode = groups_get_course_groupmode($this->course);
        if (($groupmode == VISIBLEGROUPS) ||
                ($groupmode == SEPARATEGROUPS and has_capability('moodle/site:accessallgroups', $context))) {
            // Get all groups.
            if ($cgroups = groups_get_all_groups($this->course->id)) {
                foreach ($cgroups as $cgroup) {
                    $groups[$cgroup->id] = $cgroup->name;
                }
            }
        }
        return $groups;
    }

    /**
     * Return list of users.
     *
     * @return array list of users.
     */
    public function get_user_list() {
        global $CFG, $SITE;

        $courseid = $SITE->id;
        if (!empty($this->course)) {
            $courseid = $this->course->id;
        }
        $context = context_course::instance($courseid);
        $limitfrom = empty($this->showusers) ? 0 : '';
        $limitnum  = empty($this->showusers) ? COURSE_MAX_USERS_PER_DROPDOWN + 1 : '';
        $courseusers = get_enrolled_users($context, '', $this->groupid, 'u.id, ' . get_all_user_name_fields(true, 'u'),
                null, $limitfrom, $limitnum);

        $users = array();
        if (($this->showusers) || (count($courseusers) < COURSE_MAX_USERS_PER_DROPDOWN && empty($this->showusers))) {
            $this->showusers = true;
            if ($courseusers) {
                foreach ($courseusers as $courseuser) {
                     $users[$courseuser->id] = fullname($courseuser, has_capability('moodle/site:viewfullnames', $context));
                }
            }
            $users[$CFG->siteguest] = get_string('guestuser');
        }
        return $users;
    }

    /**
     * Return list of date options.
     *
     * @return array date options.
     */
    public function get_date_options() {
        global $SITE;

        $strftimedate = get_string("strftimedate");
        $strftimedaydate = get_string("strftimedaydate");

        // Get all the possible dates.
        // Note that we are keeping track of real (GMT) time and user time.
        // User time is only used in displays - all calcs and passing is GMT.
        $timenow = time(); // GMT.

        // What day is it now for the user, and when is midnight that day (in GMT).
        $timemidnight = usergetmidnight($timenow);

        // Put today up the top of the list.
        $dates = array("$timemidnight" => get_string("today").", ".userdate($timenow, $strftimedate) );

        // If course is empty, get it from frontpage.
        $course = $SITE;
        if (!empty($this->course)) {
            $course = $this->course;
        }
        if (!$course->startdate or ($course->startdate > $timenow)) {
            $course->startdate = $course->timecreated;
        }

        $numdates = 1;
        while ($timemidnight > $course->startdate and $numdates < 365) {
            $timemidnight = $timemidnight - 86400;
            $timenow = $timenow - 86400;
            $dates["$timemidnight"] = userdate($timenow, $strftimedaydate);
            $numdates++;
        }
        return $dates;
    }

    /**
     * Return list of edulevel.
     *
     * @todo do it nicely from some class.
     * @return array list of edulevels.
     */
    public function get_edulevel_options() {
        $edulevels = array(
                    -1 => get_string("edulevel"),
                    1 => get_string('edulevelteacher'),
                    2 => get_string('edulevelparticipating'),
                    0 => get_string('edulevelother')
                    );
        return $edulevels;
    }

    /**
     * Print log in csv format.
     *
     * @return boolean success
     */
    public function print_log_csv() {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');

        $csvexporter = new csv_export_writer('tab');

        $header = array_values($this->get_table_cols());

        $strftimedatetime = get_string("strftimedatetime");

        $csvexporter->set_filename('logs', '.txt');
        $title = array(get_string('savedat').userdate(time(), $strftimedatetime));
        $csvexporter->add_data($title);
        $csvexporter->add_data($header);

        $logdata = $this->get_logs();

        if (empty($logdata['events'])) {
            return true;
        }

        // Fill csv expoter with data.
        foreach ($logdata['events'] as $row) {
            $csvexporter->add_data($row);
        }

        $csvexporter->download_file();
        return true;
    }

    /**
     * Prints log in xls format.
     *
     * @return boolean success
     */
    public function print_log_xls() {

        global $CFG;

        require_once("$CFG->libdir/excellib.class.php");

        $logdata = $this->get_logs();

        $strftimedatetime = get_string("strftimedatetime");

        $nropages = ceil(count($logdata['events']) / (EXCELROWS - FIRSTUSEDEXCELROW + 1));
        $filename = 'logs_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $filename .= '.xls';

        $workbook = new MoodleExcelWorkbook('-');
        $workbook->send($filename);

        $worksheet = array();
        $headers = array_values($this->get_table_cols());

        // Creating worksheets.
        for ($wsnumber = 1; $wsnumber <= $nropages; $wsnumber++) {
            $sheettitle = get_string('logs') . ' ' . $wsnumber . '-' . $nropages;
            $worksheet[$wsnumber] = $workbook->add_worksheet($sheettitle);
            $worksheet[$wsnumber]->set_column(1, 1, 30);
            $worksheet[$wsnumber]->write_string(0, 0, get_string('savedat').
                                        userdate(time(), $strftimedatetime));
            $col = 0;
            foreach ($headers as $item) {
                $worksheet[$wsnumber]->write(FIRSTUSEDEXCELROW - 1, $col, $item, '');
                $col++;
            }
        }

        if (empty($logdata['events'])) {
            $workbook->close();
            return true;
        }

        $formatdate = $workbook->add_format();
        $formatdate->set_num_format(get_string('log_excel_date_format'));

        $row = FIRSTUSEDEXCELROW;
        $wsnumber = 1;
        $myxls = $worksheet[$wsnumber];
        foreach ($logdata['events'] as $log) {
            if ($nropages > 1) {
                if ($row > EXCELROWS) {
                    $wsnumber++;
                    $myxls = $worksheet[$wsnumber];
                    $row = FIRSTUSEDEXCELROW;
                }
            }
            // Fill rows.
            $colscount = count($headers);
            for ($i = 0; $i < $colscount; $i++) {
                $myxls->write($row, 0, $log[$i], '');
            }
            $row++;
        }

        $workbook->close();
        return true;
    }

    /**
     * Print log in ods format.
     *
     * @return boolean success.
     */
    public function print_log_ods() {

        global $CFG;

        require_once("$CFG->libdir/odslib.class.php");

        $logdata = $this->get_logs();

        $strftimedatetime = get_string("strftimedatetime");

        $nropages = ceil(count($logdata['events']) / (EXCELROWS - FIRSTUSEDEXCELROW + 1));
        $filename = 'logs_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $filename .= '.ods';

        $workbook = new MoodleODSWorkbook('-');
        $workbook->send($filename);

        $worksheet = array();
        $headers = array_values($this->get_table_cols());

        // Creating worksheets.
        for ($wsnumber = 1; $wsnumber <= $nropages; $wsnumber++) {
            $sheettitle = get_string('logs').' '.$wsnumber.'-'.$nropages;
            $worksheet[$wsnumber] = $workbook->add_worksheet($sheettitle);
            $worksheet[$wsnumber]->set_column(1, 1, 30);
            $worksheet[$wsnumber]->write_string(0, 0, get_string('savedat').
                                        userdate(time(), $strftimedatetime));
            $col = 0;
            foreach ($headers as $item) {
                $worksheet[$wsnumber]->write(FIRSTUSEDEXCELROW - 1, $col, $item, '');
                $col++;
            }
        }

        if (empty($logdata['events'])) {
            $workbook->close();
            return true;
        }

        $formatdate = $workbook->add_format();
        $formatdate->set_num_format(get_string('log_excel_date_format'));

        $row = FIRSTUSEDEXCELROW;
        $wsnumber = 1;
        $myods = $worksheet[$wsnumber];
        foreach ($logdata['events'] as $log) {
            if ($nropages > 1) {
                if ($row > EXCELROWS) {
                    $wsnumber++;
                    $myods = $worksheet[$wsnumber];
                    $row = FIRSTUSEDEXCELROW;
                }
            }
            // Fill rows.
            $colscount = count($headers);
            for ($i = 0; $i < $colscount; $i++) {
                $myods->write_string($row, 0, $log[$i], '');
            }
            $row++;
        }

        $workbook->close();
        return true;
    }
}
