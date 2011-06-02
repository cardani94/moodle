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
 * Factory for generating data
 *
 * This will generate plugin generator objects to generate data. It will internally
 * check if the plugin is installed or not and has generator.php with class extending
 * generator_base class.
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/moodlelib.php');

class generator {
    /**
     * generator file which will be searched is genetrator.php
     * @static
     */
    protected static $generatorfilename = 'generator.php';

    /**
     * generator class should have name generator_{pluginname}
     * @static
     */
    protected static $generatorclassprefix = 'generator_';

    /**
     * list of all the plugins which needs to be ignored.
     */
    protected $ignoreplugins = array('generator');

    /**
     * generator object. (Single instance of generator should exist at one time)
     * @static
     */
    protected static $generator = null;

    /**
     * list of all the plugin generators which have been initalized.
     */
    protected $plugingenerators = array();

    /**
     * list of plugins which have generator.php
     */
    protected $pluginswithgenerator = array();

    /**
     * list of plugins which does not have generator.php.
     * currently we are not using this.
     */
    protected $pluginswithoutgenerator = array();

    /**
     * A private constructor to prevent direct creation of object
     * (Singleton pattern). It should initilaise plugin list which has generator.php
     */
    private function __construct() {
        $this->create_pluginlist();
    }

    /**
     * Singleton method, we don't want more then one generator instances.
     *
     * @return generator instance of generator
     */
    public static function get_generator() {
        if (is_null(self::$generator)) {
            self::$generator = new generator();
        }

        return self::$generator;
    }

    /**
     * creates or gets plugin generator. If plugin generator has been created the returns
     * the old instance else creates the new plugin generator and put it in pulgingenerators
     * with respect to priority order.
     *
     * @param string $pluginname name of plugin for which generator object should be returned
     * @return generator_{$pluginname}
     */
    public function get_plugin_generator($pluginname) {
        //return plugin if it has been created... Singloton
        if (array_key_exists($pluginname, $this->plugingenerators)) {
            return $this->plugingenerators[$pluginname];
        }

        //check if it's a valid plugin which is installed else return
        if (!in_array($pluginname, $this->pluginswithgenerator)) {
            return null;
        }

        $plugingenerator = null;

        //plugin generator class is always {generatorclassprefix}{pluginname}
        $pluginclass = self::$generatorclassprefix.$pluginname;

        //add the plugin to plugingenerator list with order of there execuation
        if (class_exists($pluginclass)) {
            //Create generator for the required plugin
            $plugingenerator = new $pluginclass();

            //Make sure to have generators odf instance generator_interface
            if ($plugingenerator instanceof generator_base) {
                //Add the plugin generator name with order and instance.
                $this->plugingenerators[$pluginname] = $plugingenerator;

                //sort the generator in the way they should be executed.
                uasort($this->plugingenerators, "sort_generators_with_prerequisite");
            }
        } else {
            throw new coding_exception("Generator for {$pluginname} doesn't have {$pluginclass}.
                                 If this plugin doesn't have a generator then please add this
                                 to ignore list array.");
        }

        return $plugingenerator;
    }

    /**
     * Get the list of installed moodle plugins which has genertor.php and
     * populates $this->pluginswithgenerator and  pluginswithoutgenerator array.
     */
    protected function create_pluginlist() {
        //populate core plugins list which has genertor.
        $this->populate_core_pluginlist();

        //populate plugins and sub-plugins list which has generator
        $plugins = get_plugin_types(false);
        foreach ($plugins as $plugintype => $dir) {
            //Populate if not in ignore list
            if (!in_array($plugintype, $this->ignoreplugins)) {
                $this->populate_installed_plugins($plugintype);
            }
        }

        //sort plugin names we just populated.
        sort($this->pluginswithgenerator);
    }

    /**
     * update pluginswithgenerator and pluginswithoutgenerator array with the list
     * of core plugins. It uses crude method to refer core plugin directory as 
     * get_plugin_directory api doesn't support core subsystem.
     * Checks if generatorfilename is present in core subsystem plugin and If present 
     * then it push the plugin name to pluginswithgenerator else add it to 
     * pluginswithoutgenerator
     *
     */
    protected function populate_core_pluginlist() {
        global $CFG;

        //list of all core plugins and there relative path
        $coreplugins = get_core_subsystems();

        foreach ($coreplugins as $plugin => $dir) {
            $finaldir = get_plugin_directory('core', $plugin);
            //get all core plugins which are not in ignorelist and dir not null
            if (!is_null($dir) && !in_array($plugin, $this->ignoreplugins)) {
                $genetratorfile = $CFG->dirroot . '/' .$dir . '/' . self::$generatorfilename;

                //include files and add the plugin generator to list. Check the file
                //before including to avoid redundunt errors.
                if (file_exists($genetratorfile)) {
                    include_once($genetratorfile);
                    array_push($this->pluginswithgenerator, $plugin);
                } else {
                    array_push($this->pluginswithoutgenerator, $plugin);
                }
            }
        }
    }

    /**
     * update pluginswithgenerator and pluginswithoutgenerator array with the list
     * of installed plugins and sub-plugins. It uses get_plugin_directory api to
     * get the full path of the installed plugin and checks if generatorfilename is
     * present or not. If present then it push the plugin name to pluginswithgenerator
     * else add it to pluginswithoutgenerator
     *
     * @param string $plugintype type of plugin for which installed plugins will be
     *        searched.
     */
    protected function populate_installed_plugins($plugintype) {
        $installedplugins = get_plugin_list($plugintype);

        foreach ($installedplugins as $installedplugin => $dir) {

            //Prefix type if this plugin is a subtype of some plugin
            $pluginprefix = $plugintype.'_';

            //Bypass ignore plugins...
            if (in_array($pluginprefix.$installedplugin, $this->ignoreplugins)) {
                continue;
            }

            //get the directory path as one in $dir might not be valid (theme can be
            //placed in any director)
            $finaldir = get_plugin_directory($plugintype, $installedplugin);

            //Full Path at which generator.php should be present.
            $genetratorfile = $finaldir. '/' . self::$generatorfilename;

            //include files and add the plugin generator to list. Check the file
            //before including to avoid redundunt errors.
            if (file_exists($genetratorfile)) {
                include_once($genetratorfile);
                array_push($this->pluginswithgenerator, $pluginprefix.$installedplugin);
            } else {
                array_push($this->pluginswithoutgenerator, $pluginprefix.$installedplugin);
            }
        }
    }

    /**
     * Returns the list of all plugins with generator
     * 
     * @return array list of plugins with generators
     */
    public function get_plugin_generators() {
        return $this->plugingenerators;
    }

    /**
     * Returns the list of all plugins with generator
     * 
     * @return array list of plugins with generators
     */
    public function get_plugins_with_generator() {
        return $this->pluginswithgenerator;
    }

    /**
     * Returns the list of all plugins without generator
     *
     * @return array list of plugins without generators
     */
    public function get_plugins_without_generator() {
        return $this->pluginswithoutgenerator;
    }
}

/**
 * This will sort the order in which pulgingenerators should generate data.
 * callback function for usort
 */
function sort_generators_with_prerequisite($firstgenerator, $secondgenerator) {
    //if the order is same return 0
    $firstprerequisite = $firstgenerator->generatorprerequisite;
    $secondprerequisite = $secondgenerator->generatorprerequisite;
    if (is_null($firstprerequisite) && is_null($secondprerequisite)) {
        return 0;
    }
    if (!is_null($firstprerequisite) && in_array($secondgenerator->shortname, $firstprerequisite)) {
        return 1;
    }

    if (!is_null($secondgenerator) && in_array($firstgenerator->shortname, $secondprerequisite)) {
        return -1;
    }
}