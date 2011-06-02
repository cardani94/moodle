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
 * random data generator for user subsystem.
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot .'/admin/generator/baseclass.php');
require_once($CFG->dirroot .'/admin/generator/lib.php');
require_once($CFG->dirroot .'/course/externallib.php');

class generator_course extends generator_base {

    protected $courses = array();
    protected $categoryprefix = 'Category ';
    protected $courseshortprefix = 'CourseShortName ';
    protected $maxcategorydescription = 500;
    protected $maxcoursefullname = 100;
    protected $maxcoursesummary = 500;

    /**
     * Constructor initalizing generator information. There are three essentail
     * variables we nedd
     */
    public function __construct() {
        //short name of user plugin
        $this->shortname = 'course';

        //long name of user plugin
        $this->longname = 'Course generator';

        //There is no prerequisite for this plugin
        $this->generatorprerequisite = null;

        //Fill fullname, summary, summaryformat and format with pre-defined list
        $this->initalize_data();
    }

    /**
     * Initalize data array of lastnames, firstnames, domains and cities as this
     * information can't be extracted from system.
     */
    protected function initalize_data() {
        $this->courses['categories'] = array();
        $this->courses['courses'] = array();
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
        //Max hierarcy of categories.
        $mform->addElement('text', 'coursescategorymaxhierarchy',
                           'Course category hierarchy', 'size = "10"');
        $mform->setDefault('coursescategorymaxhierarchy', 0);
        $mform->setType('coursescategorymaxhierarchy', PARAM_INT);
        $mform->addHelpButton('coursescategorymaxhierarchy', 'coursescategorymaxhierarchy',
                              'generator');

        //No. of course categories
        $mform->addElement('text', 'coursecategorymaxnumber',
                           'Number of course categories per hierarchy', 'size = "10"');
        $mform->setDefault('coursecategorymaxnumber', 10);
        $mform->setType('coursecategorymaxnumber', PARAM_INT);

        //Category name prefix
        if ($detailed) {
            $mform->addElement('text', 'coursecategorynameprefix', 'Course category name prefix',
                               'size = "50"');
        } else {
            $mform->addElement('hidden', 'coursecategorynameprefix', 'Course Category ');
        }
        $mform->setDefault('coursecategorynameprefix', 'Course Category ');
        $mform->setType('coursecategorynameprefix', PARAM_ALPHANUM);

        //Number of courses to generate
        $mform->addElement('text', 'coursemaxnumber', 'Number of courses per category',
                           'size = "10"');
        $mform->setDefault('coursemaxnumber', 5);
        $mform->setType('coursemaxnumber', PARAM_INT);

        //Course name prefix
        if ($detailed) {
            $mform->addElement('text', 'coursenameprefix', 'Course short name prefix',
                               'size = "50"');
        } else {
            $mform->addElement('hidden', 'coursenameprefix', 'Course short name ');
        }
        $mform->setDefault('coursenameprefix', 'Course short name ');
        $mform->setType('coursenameprefix', PARAM_ALPHANUM);

        //random theme, language and translation option
        if ($detailed) {
            $mform->addElement('checkbox', 'coursegeneratorrandomize', 'Random Data');
        } else {
            if ($randomize) {
                $mform->addElement('hidden', 'coursegeneratorrandomize', '1');
            }
        }
        $mform->setType('coursegeneratorrandomize', PARAM_INT);
    }


    /* This should generate data for plugin and update
     *
     * @param bool $verbose options if true should echo details of what is being inserted
     * @return bool true if success else false
     */
    public function generate_data($verbose = true) {
        global $OUTPUT, $CFG, $DB;

        //get all the configuration parameters passed by user.
        $numberofcoursecat = optional_param('coursecategorymaxnumber', '', PARAM_INT);
        $coursehierarchy = optional_param('coursescategorymaxhierarchy', '', PARAM_INT);
        $coursecatnameprefix = optional_param('coursecategorynameprefix', '', PARAM_ALPHANUM);
        $numberofcourses = optional_param('coursemaxnumber', '', PARAM_INT);
        $coursenameprefix = optional_param('coursenameprefix', '', PARAM_ALPHANUM);
        $randomdata = optional_param('coursegeneratorrandomize', '', PARAM_INT);
        $randomdata = empty($randomdata) ? false : true;

        //check if we have category name with provided prefix, if yes then increment the
        //counter correspondingly
        $counter = get_last_suffixed_counter($coursecatnameprefix, 'course_categories',
                                             'name');
        //Create course categories
        $this->show_progress("Generating {$numberofcoursecat} course categories for ".
                              "{$coursehierarchy} level...", self::START_PROCESS);
        $this->create_course_category($numberofcoursecat, $coursehierarchy, 0,
                                      $coursecatnameprefix, $counter, $randomdata, $verbose);

        //create courses
        $counter = get_last_suffixed_counter($coursenameprefix, 'course', 'shortname');
        $this->show_progress("Generating {$numberofcourses} courses per categories...", self::PROCESSING);
        $this->create_courses($numberofcourses, $coursenameprefix, ++$counter, $randomdata,
                              $verbose);

        //Show it's done successuffully...
        $this->show_progress("Course categories and courses are generated successfully!", self::END_PROCESS);
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
        if ($data['coursecategorymaxnumber'] < 0) {
            return array('coursecategorymaxnumber' =>
                'Course Categories should be a positive number');
        } else if ($data['coursescategorymaxhierarchy'] < 0) {
            return array('coursescategorymaxhierarchy' =>
                'Course Categories hierarchy should be a positive number');
        } else if ($data['coursemaxnumber'] < 0) {
            return array('coursemaxnumber' =>
                'Number of courses should be a positive number');
        } else {
            return null;
        }
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
        if (is_null($dataarray)) {
            return $this->courses;
        } else if (is_array($dataarray)) {
            if (array_key_exists('categories', $dataarray)) { //return categories
                shuffle($this->courses['categories']);
                return array_slice($shufflevalue, 0, abs($dataarray['categories']), true);
            } else if (array_key_exists('courses', $dataarray)) { //return courses.
                shuffle($this->courses['courses']);
                return array_slice($shufflevalue, 0, abs($dataarray['courses']), true);
            }
        }
    }

    /**
     * create new course categories, sub-categories... depending on hierarcy level.
     *
     * @param int    $numberofcat number of parent course categories to be created.
     * @param int    $hierarchycount how many level of subcategories should be created.
     * @param int    $parentid id of the parent of the new category. If 0, it will have no
     *               parent course category
     * @param string $name prefix name shich should be added to category name. Category
     *               name is created with $name and $sequencenumber
     * @param int    $sequencenumber sequence number which will be suffixed to category name
     * @param bool   $random if true then categories will be randomly generated.
     */
    protected function create_course_category($numberofcat, $hierarchycount, $parentid, $name,
                                              $sequencenumber, $random, $verbose) {
        global $DB;

        //Create parent categories
        for ($count = 0; $count < $numberofcat; $count++) {
            //increment $sequence number by 1 as we want the first sequence number to
            //be 1 or 1 greater then the last value.
            $sequencenumber++;

            $newcategory = new stdClass();

            $newhierarchycount = $hierarchycount;
            //Create child category if $parentid is not 0.
            if ($parentid != 0) {
                $newcategory->parent = $parentid;
            } else {
                //If parent then check if random then randomly give Hiereracy count
                //to sub categories. For parent the hierarcy will be constant.
                if ($random && $hierarchycount > 1) {
                    $newhierarchycount = rand(0, $hierarchycount-1);
                }
            }

            //Course category dataset.
            $newcategory->name = $name.$sequencenumber;
            if ($random) {
                $format = rand(1, 2)%2;
                $newcategory->descriptionformat = $format;
                $format = empty($format) ? true: false;
                $newcategory->description = "Category Description for ".$newcategory->name.
                        ": ".get_random_string(rand(20, $this->maxcategorydescription), $format);
                $newcategory->sortorder = rand(1, $newhierarchycount);
            } else {
                $newcategory->descriptionformat = 1;
                $newcategory->description = get_random_string($this->maxcategorydescription, false);
                $newcategory->sortorder = 999;
            }

            //Insert course category record in data.
            $newcategory->id = $DB->insert_record('course_categories', $newcategory);
            $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);

            //Add category id to the generator data for later usage.
            array_push($this->courses['categories'], $newcategory->id);

            if ($verbose) {
                $this->show_progress("Category: {$newcategory->name} created.", self::PROCESSING, 6, false);
            }

            //recusrsively create categories...
            for ($counter = 0; $counter < $newhierarchycount; $counter++) {
                $subcatcounter = get_last_suffixed_counter('sub_'.$name, 'course_categories',
                                                           'name');
                $this->create_course_category(1, $newhierarchycount-1, $newcategory->id,
                                              'sub_'.$name, $subcatcounter, $random, $verbose);
            }
        }

    }

    /**
     * create new courses in each category which has been created, during this session.
     *
     * @param int    $numberofcourses number of courses to be created in each category
     * @param string $shortnameprefix prefix which will be added to course shortname
     * @param int    $sequencenumber if shortname with $shortnameprefix exists then this
     *               should be the max. number which shortname have.
     * @param bool   $randomdata if true then courses in each category will be randomly placed
     *               with random data.
     */
    protected function create_courses($numberofcourses, $shortnameprefix, $sequencenumber,
                                      $randomdata, $verbose) {
        global $CFG;
        //formats which are supported.
        $courseformats = array_keys(get_plugin_list('format'));

        //array of courses which will be an input to externallib create_courses
        $courses = array();

        //if no categories are created then move it to default category.
        if (count($this->courses['categories']) === 0) {
            $this->courses['categories'] = array(1);
        }

        //Create courses for all the categories genearted.
        foreach ($this->courses['categories'] as $categoryid) {
            $newnumberofcourses = $numberofcourses;
            //If randomdata, then number of courses shoyld vary.
            if ($randomdata) {
                $newnumberofcourses = rand(1, $numberofcourses);
            }

            //Create number of courses for one categegory
            for ($count = 0; $count < $newnumberofcourses; $count++) {
                //create course array with dummy data
                $course = array();
                $course['shortname'] = $shortnameprefix.$sequencenumber;
                $course['categoryid'] = $categoryid;
                if (!$randomdata) {
                    //ini
                    $fullname = $course['shortname']." - ";
                    $fullname .= get_random_string($this->maxcoursefullname, true);
                    $summary = $course['shortname']." Summary: ";
                    $summary .= get_random_string($this->maxcoursesummary, true);

                    $course['fullname'] = $fullname;
                    $course['summary'] = $summary;
                    $course['summaryformat'] = 1;
                    $course['format'] = 'weeks';
                    $course['numsections'] = 10;
                    $course['startdate'] = mktime();
                    $course['showgrades'] = 1;
                    $course['newsitems'] = 5;
                    $course['maxbytes'] = 2097152;
                    $course['showreports'] = 1;
                    $course['visible'] = 1;
                    $course['hiddensections'] = 0;
                    $course['groupmode'] = 0;
                    $course['groupmodeforce'] = 0;
                    $course['enablecompletion'] = 1;
                    $course['completionstartonenrol'] = 1;
                    $course['completionnotify'] = 1;
                    $course['lang'] = 'en';
                } else {
                    $maxbytes = array_keys(get_max_upload_sizes($CFG->maxbytes));
                    $fullname = $course['shortname']." - ";
                    $fullname .= get_random_string($this->maxcoursefullname, true);
                    $summary = $course['shortname']." Summary: ";
                    $summary .= get_random_string($this->maxcoursesummary, true);

                    $course['fullname'] = $fullname;
                    $course['summary'] = $summary;
                    $course['summaryformat'] = 1;
                    $course['format'] = $courseformats[rand(0, count($courseformats) -1)];
                    $course['numsections'] = rand(1, 52);
                    $course['startdate'] = mktime();
                    $course['showgrades'] = rand(0, 1);
                    $course['newsitems'] = rand(0, 10);
                    $course['maxbytes'] = $maxbytes[rand(0, count($maxbytes) - 1)];
                    $course['showreports'] = rand(0, 1);
                    $course['visible'] = rand(0, 1);
                    $course['hiddensections'] = rand(0, 1);
                    $course['groupmode'] = rand(0, 1);
                    $course['groupmodeforce'] = rand(0, 1);
                    $course['enablecompletion'] = rand(0, 1);
                    $course['completionstartonenrol'] = rand(0, 1);
                    $course['completionnotify'] = rand(0, 1);
                    $course['lang'] = 'en';
                }
                //Push user array to list of users array.
                array_push($courses, $course);
                $sequencenumber++;
            }
        }
        //Create users using externallib
        $extrenalcourselib = new moodle_course_external();
        $coursecreated = $extrenalcourselib->create_courses($courses);
        foreach ($coursecreated as $key => $value) {
            array_push($this->courses['courses'], $value['id']);
        }
    }

    /**
     * Clean data generated by this plugin generator
     */
    public function clean_data() {
        global $DB;

        //if we have data then loop through and delete data
        if (!empty($this->courses)) {
            //delete all generated courses.
            $DB->delete_records_list('course', 'id', $this->courses['courses']);

            //delete all generated categories
            $DB->delete_records_list('course_categories', 'id', $this->courses['categories']);
        }
    }
}