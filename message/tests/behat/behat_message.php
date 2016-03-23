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
 * Behat message-related steps definitions.
 *
 * @package    core_message
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

/**
 * Messaging system steps definitions.
 *
 * @package    core_message
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_message extends behat_base {

    /**
     * Sends a message to the specified user from the logged user. The user full name should contain the first and last names.
     *
     * @Given /^I send "(?P<message_contents_string>(?:[^"]|\\")*)" message to "(?P<user_full_name_string>(?:[^"]|\\")*)" user$/
     * @param string $messagecontent
     * @param string $userfullname
     */
    public function i_send_message_to_user($messagecontent, $userfullname) {

        // Visit home page and follow messages.
        $this->execute_step("behat_general::i_am_on_homepage");

        if ($this->running_javascript()) {
            $this->execute_step("behat_navigation::i_follow_in_the_user_menu", get_string('messages', 'message'),
                true, true);
        } else {
            $this->execute_step("behat_general::click_link", get_string('messages', 'message'),
                false, true);
        }

        $this->execute_step('behat_forms::i_set_the_field_to',
            array(get_string('searchcombined', 'message'), $this->escape($userfullname)),
            false, false);
        $this->execute_step("behat_forms::press_button", get_string('searchcombined', 'message'),
            true, true);

        $this->execute_step("behat_general::click_link", $this->escape(get_string('sendmessageto', 'message', $userfullname)),
            true, true);

        $this->execute_step('behat_forms::i_set_the_field_to',
            array("id_message", $this->escape($messagecontent)),
            false, false);

        $this->execute_step("behat_forms::press_button", get_string('sendmessage', 'message'),
            false, false);
    }

}
