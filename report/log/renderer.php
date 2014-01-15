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
class report_log implements renderable {
    use \core_reports_logging;

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

    /** @var string order to sort */
    public $order;

    /** @var int group id */
    public $groupid;

    /**
     * Constructor.
     *
     * @param string $reader (optional)reader pluginname from which logs will be fetched.
     * @param stdClass|int $course (optional) course record or id
     * @param int $userid (optional) id of user to filter records for.
     * @param int|string $modid (optional) module id or site_errors for filtering errors.
     * @param string $action (optional) action name to filter.
     * @param int $groupid (optional) groupid of user.
     * @param int $edulevel (optional) educational level.
     * @param bool $showcourses (optional) show courses.
     * @param bool $showusers (optional) show users.
     * @param bool $showreport (optional) show report.
     * @param moodle_url $url (optional) page url.
     * @param int $date date (optional) from which records will be fetched.
     * @param int $page (optional) page number.
     * @param int $perpage (optional) number of records to show per page.
     * @param string $order (optional) sortorder of fetched records
     */
    public function __construct($reader = "", $course = 0, $userid = 0, $modid = 0, $action = "", $groupid = -1, $edulevel = -1,
            $showcourses = false, $showusers = false, $showreport = true, $url = "", $date = 0, $page = 0, $perpage = 100,
            $order = "timecreated ASC") {

        global $PAGE;
        // Use first reader as selected reader, if not passed.
        if (empty($reader)) {
            $readers = $this->get_readers();
            reset($readers);
            $reader = key($readers);
        }
        // Use page url if empty.
        if (empty($url)) {
            $url = $PAGE->url;
        }
        $this->selectedreader = $reader;
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
    }

    /**
     * Return list of log table fields as cols => header
     *
     * @return array list of log fields to display.
     */
    public function get_table_fields() {
        // Prepend coursename if showing all courses.
        if ($this->course->id == SITEID) {
            $cols = array(
                'course' => get_string('course'),
                'time' => get_string('time'),
                'fullnameuser' => get_string('fullnameuser'),
                'relatedfullnameuser' => get_string('relatedfullnameuser'),
                'context' => get_string('context'),
                'component' => get_string('component'),
                'eventname' => get_string('eventname'),
                'description' => get_string('description'),
                'origin' => get_string('origin'),
                'ip' => get_string('ip')
            );
        } else {
            $cols = array(
                'time' => get_string('time'),
                'fullnameuser' => get_string('fullnameuser'),
                'relatedfullnameuser' => get_string('relatedfullnameuser'),
                'context' => get_string('context'),
                'component' => get_string('component'),
                'eventname' => get_string('eventname'),
                'description' => get_string('description'),
                'origin' => get_string('origin'),
                'ip' => get_string('ip')
            );
        }
        return $cols;
    }

    /**
     * Return log data totalcount and logs.
     *
     * @return array log data to display.
     */
    public function get_logs() {
        global $OUTPUT;

        // Build log report and process it.
        $logevents = $this->build_logs($this->get_reader_object($this->selectedreader), $this->course, $this->selecteduser,
                "", $this->selectedmodid, $this->action, $this->groupid, $this->edulevel, $this->selecteddate,
                $this->page * $this->perpage, $this->perpage, $this->order);

        // Fetch userfullname and course shortname to be shown in report.
        $eventextradata = $this->get_users_and_courses_used($logevents['events']);

        // Check if logs are created for legacy reader.
        $legacyreader = $this->is_legacy_reader($this->selectedreader);

        // Get log data from event and update list.
        foreach ($logevents['events'] as $key => $event) {
            // If user can't view this event then remove it from list.
            if (!$event->can_view()) {
                unset($logevents['events'][$key]);
                continue;
            }

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
            if ($this->course->id == SITEID) {
                if (empty($event->courseid)) {
                    $row[] = $SITE->shortname;
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
                $username = get_string('loggedas', 'core', $a);
            } else {
                $username = $eventextradata['userfullname'][$event->userid];
                $username = html_writer::link(new moodle_url("/user/view.php?id={$event->userid}&course={$event->courseid}"), $username);
            }
            $row[] = $username;

            // Add affected user.
            if (!$legacyreader) {
                if (!empty($event->relateduserid)) {
                    $row[] = html_writer::link(new moodle_url("/user/view.php?id={$event->relateduserid}&course={$event->courseid}"),
                            $eventextradata['userfullname'][$event->relateduserid]);
                } else {
                    $row[] = '-';
                }
            } else {
                $row[] = '-';
            }

            // Add context name.
            $contextname = get_string('other');
            if ($context) {
                $contextname = $context->get_context_name(true);
                if ($url = $context->get_url()) {
                    $contextname = $OUTPUT->action_link($url, $contextname , new popup_action('click', $url, 'fromloglive'),
                            array('height' => 440, 'width' => 700));
                }
            }
            if ($url = $event->get_url()) {
                $contextname = $OUTPUT->action_link($url, $contextname , new popup_action('click', $url, 'fromloglive'),
                        array('height' => 440, 'width' => 700));
            }
            $row[] = $contextname;

            // Component.
            $componentname = $event->component;
            if (($event->component === 'core') || ($event->component === 'legacy')){
                $row[] = get_string('coresystem');
            } else if (get_string_manager()->string_exists('pluginname', $event->component)) {
                $row[] = get_string('pluginname', $event->component);
            } else {
                $row[] = $componentname;
            }

            // Event name.
            if ($legacyreader) {
                $row[] = $event->eventname;
            } else {
                $row[] = $event->get_name();
            }

            // Description.
            $row[] = $event->get_description();

            // Add event origin, normally IP/cron.
            $row[] = $logextra['origin'];
            $link = new moodle_url("/iplookup/index.php?ip={$logextra['ip']}&user=$event->userid");
            $row[] = $OUTPUT->action_link($link, $logextra['ip'], new popup_action('click', $link, 'iplookup',
                    array('height' => 440, 'width' => 700)));
            // Replace event data with log data to show.
            $logevents['events'][$key] = $row;
        }

        return $logevents;
    }

    /**
     * Helper function to return list of activities to show in selection filter.
     *
     * @return array list of activities.
     */
    public function get_activities_list() {
        $activities = array();
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

        $sitecontext = context_system::instance();
        if (has_capability('report/log:view', $sitecontext) && ($this->course->id == SITEID)) {
            $activities["site_errors"] = get_string("siteerrors");
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
        $context = context_course::instance($this->course->id);

        $selectedgroup = $this->groupid;
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
        $legacyreader = $this->is_legacy_reader($this->selectedreader);
        // Prepare the list of action options.
        // TODO: Put this list in some class and get list properly.
        if ($legacyreader) {
            $actions = array(
                'view' => get_string('view'),
                'add' => get_string('add'),
                'update' => get_string('update'),
                'delete' => get_string('delete'),
                '-view' => get_string('allchanges')
            );
        } else {
            $actions = array(
                'viewed' => get_string('view'),
                'add' => get_string('add'),
                'created' => get_string('create'),
                'updated' => get_string('update'),
                'deleted' => get_string('delete'),
                'loggedin' => get_string('login'),
                'loggedout' => get_string('logout'),
                'assigned' => get_string('assign'),
                'submitted' => get_string('submitted'),
                'completed' => get_string('completed'),
                '-viewed' => get_string('allchanges')
                );
        }
        return $actions;
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
        global $DB;

        $courses = array();

        $sitecontext = context_system::instance();
        // First check to see if we can override showcourses and showusers.
        $numcourses =  $DB->count_records("course");
        // Check if course filter should be shown.
        if ((has_capability('report/log:view', $sitecontext)) && ($this->showcourses ||
                (empty($this->showcourses) && ($numcourses < COURSE_MAX_COURSES_PER_DROPDOWN)))) {
            $this->showcourses = true;
            if ($ccc = $DB->get_records("course", null, "fullname", "id,shortname,fullname,category")) {
                foreach ($ccc as $cc) {
                    if ($cc->category) {
                        $courses[$cc->id] = format_string(get_course_display_name_for_list($cc));
                    } else {
                        $courses[$cc->id] = format_string($cc->fullname) . ' (Site)';
                    }
                }
            }
            asort($courses);
        } else {
            $this->showcourses = false;
        }
        return $courses;
    }

    /**
     * Return list of groups.
     *
     * @return array list of groups.
     */
    public function get_group_list() {
        $context = context_course::instance($this->course->id);
        $groups = array();
        if (($this->course->groupmode == VISIBLEGROUPS) ||
                ($this->course->groupmode == SEPARATEGROUPS and has_capability('moodle/site:accessallgroups', $context))) {
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
        global $CFG;

        $context = context_course::instance($this->course->id);
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
            $return['users'] = $users;
        }
        return $users;
    }

    /**
     * Return list of date options.
     *
     * @return array date options.
     */
    public function get_date_options() {
        // Get all the possible users.
        // Define limitfrom and limitnum for queries below.
        // If $showusers is enabled... don't apply limitfrom and limitnum.
        $limitfrom = empty($showusers) ? 0 : '';
        $limitnum  = empty($showusers) ? COURSE_MAX_USERS_PER_DROPDOWN + 1 : '';

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

        if (!$this->course->startdate or ($this->course->startdate > $timenow)) {
            $this->course->startdate = $this->course->timecreated;
        }

        $numdates = 1;
        while ($timemidnight > $this->course->startdate and $numdates < 365) {
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
}

/**
 * Report log renderer's for printing reports.
 *
 * @package    report_log
 * @copyright  2014 Rajesh Taneja <rajesh.taneja@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_log_renderer extends plugin_renderer_base {

    /**
     * Render log report page.
     *
     * @param report_log $reportlog object of report_log.
     */
    public function render_report_log(report_log $reportlog) {
        $this->report_selector_form($reportlog);
        if ($reportlog->showreport) {
            $this->log_report($reportlog);
        }
    }

    /**
     * Print report log.
     *
     * @param report_log $reportlog object of report_log.
     */
    public function log_report(report_log $reportlog) {
        // Create table.
        $table = new \flexible_table('reportlog');
        $tablefields = $reportlog->get_table_fields();
        $table->define_baseurl($reportlog->url);
        $table->define_columns(array_keys($tablefields));
        $table->define_headers(array_values($tablefields));
        $table->sortable(false);
        $table->collapsible(false);
        $table->pageable(true);
        $table->setup();

        $legacyreader = $reportlog->is_legacy_reader($reportlog->selectedreader);
        $logdata = $reportlog->get_logs();

        // Display total record count and pageing bar.
        echo html_writer::tag('div', get_string("displayingrecords", "", $logdata['totalcount']), array('class' => 'info'));
        echo $this->output->paging_bar($logdata['totalcount'], $reportlog->page, $reportlog->perpage, $reportlog->url.
                "&perpage=" . $reportlog->perpage);

        // Print log data.
        foreach ($logdata['events'] as $row) {
            $table->add_data($row);
        }

        $table->finish_output();
        echo $this->output->paging_bar($logdata['totalcount'], $reportlog->page, $reportlog->perpage, $reportlog->url. "&perpage=" .
                $reportlog->perpage);
    }

    /**
     * Prints/return reader selector
     *
     * @param report_log $reportlog log report.
     */
    public function reader_selector(report_log $reportlog) {
       $readers = $reportlog->get_readers(true);
       if (empty($readers)) {
           $readers = array(get_string('noreaderenabled'));
       }
       $select = new single_select($reportlog->url, 'reader', $readers, $reportlog->selectedreader, null);
       $select->set_label(get_string('selectreader'));
       echo $this->output->render($select);
    }

    /**
     * This function is used to generate and display selector form
     *
     * @param report_log $reportlog log report.
     */
    public function report_selector_form(report_log $reportlog) {
        $url = new moodle_url('/report/log/index.php');
        echo "<form class=\"logselectform\" action=\"$url\" method=\"get\">\n";
        echo "<div>\n";
        echo "<input type=\"hidden\" name=\"chooselog\" value=\"1\" />\n";
        echo "<input type=\"hidden\" name=\"showusers\" value=\"$reportlog->showusers\" />\n";
        echo "<input type=\"hidden\" name=\"showcourses\" value=\"$reportlog->showcourses\" />\n";

        // Add course selector.
        $courses = $reportlog->get_course_list();
        if (!empty($courses)) {
            if ($reportlog->showcourses) {
                echo html_writer::label(get_string('selectacourse'), 'menuid', false, array('class' => 'accesshide'));
                echo html_writer::select($courses, "id", $reportlog->course->id, false);
            } else {
                $courses = array();
                $courses[$reportlog->course->id] = get_course_display_name_for_list($reportlog->course) .
                        (($reportlog->course->id == SITEID) ? ' (' . get_string('site') . ') ' : '');
                echo html_writer::label(get_string('selectacourse'), 'menuid', false, array('class' => 'accesshide'));
                echo html_writer::select($courses, "id", $reportlog->course->id, false);
                if (has_capability('report/log:view', $sitecontext)) {
                    $a = new stdClass();
                    $a->url = new moodle_url('/report/log/index.php', array('chooselog' => 0,
                        'group' => $reportlog->get_selected_group(), 'user' => $reportlog->selecteduser,
                        'id' => $reportlog->course->id, 'date' => $reportlog->selecteddate, 'modid' => $reportlog->selectedmodid,
                        'showcourses' => 1, 'showusers' => $reportlog->showusers));
                    print_string('logtoomanycourses','moodle',$a);
                }
            }
        }

        // Add group selector.
        $groups = $reportlog->get_group_list();
        if (!empty($groups)) {
            echo html_writer::label(get_string('selectagroup'), 'menugroup', false, array('class' => 'accesshide'));
            echo html_writer::select($groups, "group", $reportlog->groupid, get_string("allgroups"));
        }

        // Add user selector.
        $users = $reportlog->get_user_list();
        if (!empty($users)) {
            if ($reportlog->showusers) {
                echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
                echo html_writer::select($users, "user", $reportlog->selecteduser, get_string("allparticipants"));
            } else {
                $users = array();
                if (!empty($reportlog->selecteduser)) {
                    $users[$reportlog->selecteduser] = $reportlog->get_selected_user_fullname();
                } else {
                    $users[0] = get_string('allparticipants');
                }
                echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
                echo html_writer::select($users, "user", $reportlog->selecteduser, false);
                $a = new stdClass();
                $a->url = new moodle_url('/report/log/index.php', array('chooselog' => 0,
                        'group' => $reportlog->get_selected_group(), 'user' => $reportlog->selecteduser,
                        'id' => $reportlog->course->id, 'date' => $reportlog->selecteddate, 'modid' => $reportlog->selectedmodid,
                        'showcourses' => 1, 'showusers' => $reportlog->showusers, 'showcourses' => $reportlog->showcourses));
                print_string('logtoomanyusers', 'moodle', $a);
            }
        }

        // Add date selector.
        $dates = $reportlog->get_date_options();
        echo html_writer::label(get_string('date'), 'menudate', false, array('class' => 'accesshide'));
        echo html_writer::select($dates, "date", $reportlog->selecteddate, get_string("alldays"));

        // Add activity selector.
        $activities = $reportlog->get_activities_list();
        echo html_writer::label(get_string('activities'), 'menumodid', false, array('class' => 'accesshide'));
        echo html_writer::select($activities, "modid", $reportlog->selectedmodid, get_string("allactivities"));

        // Add actions selector.
        echo html_writer::label(get_string('actions'), 'menumodaction', false, array('class' => 'accesshide'));
        echo html_writer::select($reportlog->get_actions(), 'modaction', $reportlog->action, get_string("allactions"));

        // Add edulevel.
        $edulevel = $reportlog->get_edulevel_options();
        echo html_writer::label(get_string('edulevel'), 'menuedulevel', false, array('class' => 'accesshide'));
        echo html_writer::select($edulevel, 'edulevel', $reportlog->edulevel, false);

        // Add reader option.
        // If there is some reader available then only show submit button.
        $readers = $reportlog->get_readers(true);
        if (!empty($readers)) {
            if (count($readers) == 1) {
                $attributes = array('type' => 'hidden', 'name' => 'reader', 'value' => key($readers));
                echo html_writer::empty_tag('input', $attributes);
            } else {
                echo html_writer::label(get_string('selectreader'), 'menureader', false, array('class' => 'accesshide'));
                echo html_writer::select($readers, 'reader', $reportlog->selectedreader, false);
            }

            echo '<input type="submit" value="'.get_string('gettheselogs').'" />';
        }
        echo '</div>';
        echo '</form>';
    }
}