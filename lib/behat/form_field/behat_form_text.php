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
 * Text field class.
 *
 * @package    core_form
 * @category   test
 * @copyright  2014 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__  . '/behat_form_field.php');

/**
 * Class for test-based fields.
 *
 * @package    core_form
 * @category   test
 * @copyright  2014 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_form_text extends behat_form_field {

    /**
     * Sets the value to a field.
     *
     * @param string $value
     * @return void
     */
    public function set_value($value) {
        $this->field->setValue($value);

        if ($this->running_javascript()) {
            // If value is set in dialogue then just trigger chnage event, as move will move the screen and dialogue
            // will be wrongly positioned.
            $dialoguexpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' moodle-dialogue ')]";
            if ($this->session->getDriver()->find($dialoguexpath)) {
                $script = "Syn.trigger('change', {}, {{ELEMENT}})";
                try {
                    $this->session->getDriver()->triggerSynScript($this->field->getXpath(), $script);
                } catch (Exception $e) {
                    // No need to do anything if element has been removed by JS.
                    // This is possible when inline editing element is used.
                }
            } else {
                try {
                    $this->session->getDriver()->moodle_move_to_and_click_on_element($this->field->getXpath());
                } catch (\Exception $e) {
                    return;
                }
            }
        }

    }

    /**
     * Returns the current value of the element.
     *
     * @return string
     */
    public function get_value() {
        return $this->field->getValue();
    }

    /**
     * Matches the provided value against the current field value.
     *
     * @param string $expectedvalue
     * @return bool The provided value matches the field value?
     */
    public function matches($expectedvalue) {
        return $this->text_matches($expectedvalue);
    }

}
