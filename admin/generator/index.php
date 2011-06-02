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
 * Random data generator for testing
 *
 * Generates course, forum data
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(dirname(__FILE__) . '/generator.php');
require_once(dirname(__FILE__) . '/generator_form.php');

admin_externalpage_setup('generator');

$continue = optional_param('continue', '', PARAM_INT);
$verbose = optional_param('verbose', '', PARAM_INT);
$generate  = optional_param('generate', '', PARAM_INT);
$cancel = optional_param('cancel', '', PARAM_RAW_TRIMMED);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generatortitle', 'generator'));

//Increase memory and timeout as it might need a lot of memory
raise_memory_limit(MEMORY_HUGE);

//Show Warning for every page
echo $OUTPUT->heading(get_string('generatorwarning', 'generator'), 4);

//clear all buffers to show navigation on left
while (@ob_end_flush());

$generator = generator::get_generator();

if ($generate && confirm_sesskey()) { //Generate data
    //create list of all plugin generators which were selected by admin
    initialize_generators();
    $mform = new generator_form(null, 'showconfiguration');
    if ($mform->get_data()) {
        $generatorlist = array();
        try {
            foreach ($generator->get_plugin_generators() as $pluginname => $plugingenerator) {
                array_push($generatorlist, $pluginname);
                $verbose = empty ($verbose) ? false : true;
                $plugingenerator->generate_data($verbose);
            }
        } catch (Exception $exception) {
            $OUTPUT->heading('Error occured in Plugin generator', 5);
            //Handle data cleanup if something gets wrong...
            //Start cleaning data from reverse of proirty, so the dependencies should be taken
            //care off.
            $generators = array_reverse($generatorlist);
            foreach ($generators as $pluginname) {
                $plugingenerator = $generator->get_plugin_generator($pluginname);
                $plugingenerator->show_progress(
                        "Removing data created by {$plugingenerator->longname}", 6);
                $plugingenerator->clean_data();
            }
        }

        //Show continue button.
        echo $OUTPUT->single_button(new moodle_url('/'), 'continue');
    } else if ($mform->is_cancelled()) {
        //create all generators
        initialize_generators(true);
        //Show the list of plugin generators and let user choose what he/she wants.
        $mform = new generator_form(null, 'selectgenerators');
        $mform->display();
    } else {
        $mform->display();
    }
} else if ($continue && confirm_sesskey()) { //Show configuration
    //create list of all plugin generators which were selected by admin
    initialize_generators();
    //show configuration for all generators.
    $mform = new generator_form(null, 'showconfiguration');
    $mform->display();
} else {
    //create all generators
    initialize_generators(true);
    //Show the list of plugin generators and let user choose what he/she wants.
    $mform = new generator_form(null, 'selectgenerators');
    $mform->display();
}

echo $OUTPUT->footer();

/**
 * fills plugigenerators list with all user selected plugin generators.
 */
function initialize_generators($all = false) {
    $generator = generator::get_generator();
    foreach ($generator->get_plugins_with_generator() as $pluginname) {
        //check if generator is selected or not
        if (optional_param($pluginname, '', PARAM_INT) || $all) {
            $generator->get_plugin_generator($pluginname);
        }
    }
}