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
 * Displays different views of the logs.
 *
 * @package    report_log
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/report/log/locallib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

$id          = optional_param('id', 0, PARAM_INT);// Course ID
$group       = optional_param('group', 0, PARAM_INT); // Group to display
$user        = optional_param('user', 0, PARAM_INT); // User to display
$date        = optional_param('date', 0, PARAM_INT); // Date to display
$modid       = optional_param('modid', 0, PARAM_FILE); // module->id or 'site_errors'
$modaction   = optional_param('modaction', '', PARAM_PATH); // an action as recorded in the logs
$page        = optional_param('page', '0', PARAM_INT);     // which page to show
$perpage     = optional_param('perpage', '100', PARAM_INT); // how many per page
$showcourses = optional_param('showcourses', 0, PARAM_INT); // whether to show courses if we're over our limit.
$showusers   = optional_param('showusers', 0, PARAM_INT); // whether to show users if we're over our limit.
$chooselog   = optional_param('chooselog', 0, PARAM_INT);
$reader      = optional_param('reader', '', PARAM_COMPONENT); // Reader which will be used for displaying logs.
$edulevel    = optional_param('edulevel', -1, PARAM_INT); // Educational level.

$params = array();
if ($id !== 0) {
    $params['id'] = $id;
}
if ($group !== 0) {
    $params['group'] = $group;
}
if ($user !== 0) {
    $params['user'] = $user;
}
if ($date !== 0) {
    $params['date'] = $date;
}
if ($modid !== 0) {
    $params['modid'] = $modid;
}
if ($modaction !== '') {
    $params['modaction'] = $modaction;
}
if ($page !== '0') {
    $params['page'] = $page;
}
if ($perpage !== '100') {
    $params['perpage'] = $perpage;
}
if ($showcourses !== 0) {
    $params['showcourses'] = $showcourses;
}
if ($showusers !== 0) {
    $params['showusers'] = $showusers;
}
if ($chooselog !== 0) {
    $params['chooselog'] = $chooselog;
}
if ($reader !== '') {
    $params['reader'] = $reader;
}
if (($edulevel != -1)) {
    $params['edulevel'] = $edulevel;
}

// Legacy store hack, as edulevel is not supported.
if ($reader == 'logstore_legacy') {
    $params['edulevel'] = -1;
    $edulevel = -1;
}
$PAGE->set_url('/report/log/index.php', $params);
$PAGE->set_pagelayout('report');

// Get course details.
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);

require_capability('report/log:view', $context);

// Trigger a content view event.
$event = \report_log\event\content_viewed::create(array('courseid' => $course->id,
                                                        'other'    => array('content' => 'logs')));
$event->set_page_detail();
$event->set_legacy_logdata(array($course->id, "course", "report log", "report/log/index.php?id=$course->id", $course->id));
$event->trigger();

if (!empty($page)) {
    $strlogs = get_string('logs'). ": ". get_string('page', 'report_log', $page+1);
} else {
    $strlogs = get_string('logs');
}
$stradministration = get_string('administration');
$strreports = get_string('reports');

// Before we close session, make sure we have editing information in session.
$adminediting = optional_param('adminedit', -1, PARAM_BOOL);
if ($PAGE->user_allowed_editing() && $adminediting != -1) {
    $USER->editing = $adminediting;
}

\core\session\manager::write_close();

if ($course->id == SITEID) {
    admin_externalpage_setup('reportlog', '', null, '', array('pagelayout'=>'report'));
    $PAGE->set_title($course->shortname .': '. $strlogs);
} else {
    $PAGE->set_title($course->shortname .': '. $strlogs);
    $PAGE->set_heading($course->fullname);
}

if (!empty($chooselog)) {
    $userinfo = get_string('allparticipants');
    $dateinfo = get_string('alldays');

    if ($user) {
        $u = $DB->get_record('user', array('id'=>$user, 'deleted'=>0), '*', MUST_EXIST);
        $userinfo = fullname($u, has_capability('moodle/site:viewfullnames', $context));
    }
    if ($date) {
        $dateinfo = userdate($date, get_string('strftimedaydate'));
    }
    if ($course->id != SITEID) {
        $PAGE->navbar->add("$userinfo, $dateinfo");
    }
    echo $OUTPUT->header();
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('chooselogs') .':');
}
$output = $PAGE->get_renderer('report_log');
$url = new moodle_url("index.php", array('id' => $course->id, 'chooselog' => 1, 'user' => $user, 'date' => $date,
    'modid' => $modid, 'modaction' => $modaction, 'group' => $group, 'reader' => $reader, 'edulevel' => $edulevel));
$reportlog = new report_log($reader, $course, $user, $modid, $modaction, $group, $edulevel, $showcourses, $showusers, $chooselog,
        $url, $date, $page, $perpage, 'timecreated DESC');
echo $output->render($reportlog);

echo $output->footer();
