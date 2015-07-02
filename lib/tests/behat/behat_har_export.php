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
 * Export har files for performance testing.
 *
 * @package    core_test
 * @copyright  2015 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../behat/behat_base.php');

require_once __DIR__ . '/../../php-webdriver/PHPWebDriver/WebDriver.php';
require_once __DIR__ . '/../../php-webdriver/PHPWebDriver/WebDriverProxy.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';
require_once 'PHPUnit/Autoload.php';

/**
 * Context for exporting hard files for performance testing.
 *
 * @package    core_test
 * @copyright  2015 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_har_export extends behat_base {

    /**
     * @When /^I export HAR$/
     */
    public function i_export_har() {
        file_put_contents("/tmp/BROWSERMOBHAR.php", var_export(self::$BrowserMob->har, true));
    }

    /**
     * @Then /^I should see network traffic in the HAR file$/
     */
    public function i_should_see_network_traffic_in_the_har_file() {
        assertFileExists('/tmp/BROWSERMOBHAR.php');
    }
}
