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
 * Jmeter test plan generator.
 *
 * @package    core_test
 * @copyright  2015 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
if (!defined('JMETER_TEMPLATE')) {
    define('JMETER_TEMPLATE', __DIR__ . '/../../../testplan.jmx.dist');
}

/**
 * Jmeter test plan generator.
 *
 * @package    core_test
 * @copyright  2015 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_jmeter_generator {
    /**
     * Keep testplan dom for appending more information.
     *
     * @var DOMDocument
     */
    private static $dom;

    /**
     * Class constructor
     *
     * @param string $url URL for BrowserMobProxy instance
     */
    public function __construct() {
        global $CFG;
        if (empty(self::$dom)) {
            $doc = new DOMDocument();
            $doc->load(JMETER_TEMPLATE);
        }
    }



}