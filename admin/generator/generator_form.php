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
 * Renders data options and results
 *
 * It will show developer all the data options to generate data.
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/generator.php');

class generator_form extends moodleform {

    /**
     * moodleform abstract method used for defining form definition
     */
    public function definition() {
        $mform =& $this->_form;
        switch ($this->_customdata) {
            case 'showconfiguration':
                $this->show_generator_configuration($mform);
                break;
            case 'selectgenerators' :
                $this->show_available_generator($mform);
                break;
            default:
                break;
        }
    }

    /**
     * Validate configuration for plugin generator
     *
     * @param array data posted form data
     * @param array file information about uploadeded file
     *
     * @return array error message
     */
    public function validation($data, $files) {
        parent::validation($data, $files);
        //check if any generator has any validation to do and validate it.
        if (isset($data['generate'])) {
            $generator = generator::get_generator();
            foreach ($generator->get_plugin_generators() as $pluginname => $plugingenerator) {
                if ($error = $plugingenerator->validate_configuration($data, $files)) {
                    return $error;
                }
            }
        }
    }
    /**
     * Show all available plugin generators for user selection.
     *
     * @param MoodleQuickForm $mform form object in which configuration options
     *        will be inserted.
     */
    private function show_available_generator(MoodleQuickForm $mform) {
        //Basic configuation
        $mform->addElement('checkbox', 'verbose', 'Verbose');
        $mform->setType('verbose', PARAM_INT);
        $mform->addHelpButton('verbose', 'generatorverbose', 'generator');

        $mform->addElement('checkbox', 'detailed', 'Detailed configuration');
        $mform->setType('detailed', PARAM_INT);
        $mform->addHelpButton('detailed', 'generatorconfiguration', 'generator');

        $mform->addElement('checkbox', 'randomize', 'Random data');
        $mform->setType('randomize', PARAM_INT);
        $mform->disabledIf('randomize', 'detailed', 'checked');
        //$mform->addHelpButton('detailed', 'generatorrandomizer', 'generator');

        //Show all generators
        $mform->addElement('header', 'generatorselection', 'Select plugin generators');
        $generator = generator::get_generator();

        foreach ($generator->get_plugin_generators() as $pluginname => $plugingenerator) {
            $mform->addElement('checkbox', $pluginname, $plugingenerator->longname);
            $mform->setDefault($pluginname, 'selected');
            $mform->setType($pluginname, PARAM_INT);

            if (!is_null($plugingenerator->generatorprerequisite)) {
                foreach ($plugingenerator->generatorprerequisite as $prerequisite) {
                    $mform->disabledIf($pluginname, $prerequisite);
                }
            }
            $mform->addHelpButton($pluginname, $pluginname.'generator', 'generator');
        }

        //Add hidden continue filed to move to next stage
        $mform->addElement('hidden', 'continue', '1');
        $mform->setType('continue', PARAM_INT);

        //add action button continue.
        $this->add_action_buttons(false, 'Continue');
    }

    /**
     * Show all selected plugin generators configuration for user input
     *
     * @param MoodleQuickForm $mform form object in which configuration options
     *        will be inserted.
     */
    private function show_generator_configuration(MoodleQuickForm $mform) {
        $detailed = false;
        if (optional_param('detailed', '', PARAM_INT)) {
            $detailed = true;
        }
        $randomize = false;
        if (optional_param('randomize', '', PARAM_INT)) {
            $randomize = true;
        }

        //if verbose add it as hidden
        if (optional_param('verbose', '', PARAM_INT)) {
            $mform->addElement('hidden', 'verbose', '1');
            $mform->setType('verbose', PARAM_INT);
        } else if (optional_param('randomize', '', PARAM_INT)) {
            //global randomizer only works for non-verbose mode.
            $mform->addElement('hidden', 'randomize', '1');
            $mform->setType('randomize', PARAM_INT);
            echo '<span style="color:red;"><strong>Below values are maximum values and data will be randomized</strong></span>';
        }

        //Show configuration for all plugins
        $generator = generator::get_generator();
        foreach ($generator->get_plugin_generators() as $pluginname => $plugingenerator) {
            $mform->addElement('hidden', $pluginname, '1');
            $mform->setType($pluginname, PARAM_INT);

            //Group all plugin configurations in one fieldset
            $mform->addElement('header', 'element_'.$plugingenerator->shortname,
                               $plugingenerator->longname);
            $plugingenerator->generator_configuration($mform, $detailed, $randomize);
            $mform->closeHeaderBefore('element_'.$plugingenerator->shortname);
        }

        //Add hidden continue filed to move to next stage
        $mform->addElement('hidden', 'generate', '1');
        $mform->setType('generate', PARAM_INT);

        //Add action button generate
        $this->add_action_buttons(true, 'generate');

    }
}