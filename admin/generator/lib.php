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
 * General functions used by generator.
 *
 * @package    moodlecore
 * @subpackage generator
 * @copyright  2011 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns random text of defined size with html or raw text
 *
 * @param int $length length of text required
 * @param bool $format if true will return raw text else html text
 * @return string html or raw text
 */
function get_random_string($length, $textformat = true) {
    $tags = array('u', 'b', 'i', 'div', 'span', 'p');
    $umightbeamoodler = array(
        'If you have ever turned on a football game and and the orange uniforms
         of one team caused you to leap up from the sofa and check out moodle.org,
         you might be a Moodler.',
        'If you type "moodle.org" in your browser when you intend to type "google.com."',
        'If you look up recipes in moodle.org.',
        'If you look up recipes in moodle.org... and find them!',
        'If you can spell Dougiamas without having to look it up.',
        'If you can pronounce Dougiamas without having to look it up.',
        'If you check the Moodle forums at 8:30 on a Saturday night.',
        'If you find yourself heading to the bugtracker when the dishwasher is broken.',
        "If you start seeing double square brackets around words you don't know the
         translation of.",
        "If your neighbours wonder why you named your cats Helen and Howard.",
        "Your spouse wants you to take up golf again because they hate being a 'moodle
         widow(er)' more.",
        'You describe the color of something as "Moodle" orange.',
        "If you find yourself logging in to moodle.org during your summer holiday in Italy
         instead of enjoying the beautiful view or going to the beach. (Hi from Italy, Sigi)",
        "If you come all the way from Germany to the US during your summer holiday and in
         addition to visiting lots of places you must absolutely go to a small town in South
         Carolina to find out about the latest Moodle tricks",
        "You try to figure out how to grade e-mails from your friends without a drop down box",
        "You find moodle.org is down for some reason and the first thing you want to do is post
         on moodle.org about it.",
        "Every verb you use is 'to moodle'.",
        "You go into a Chinese restaurant and ask for a side order of Moodles with your
         Egg Foo Young.",
        "You talk about Moodle so much that your non-Moodling, non-native English speaking
         husband asks if the noodles on the plate in front of him are moodles and when you
         ask him if that is what he meant to say he replies, \"Yes, aren't they moodles?\"
         (based on a true story)",
        "You know that the course id for Using Moodle is 5.",
        "And even if you drive a car, you always drive it in 5th gear.",
        "You throw away all but the orange M&M's.",
        "You eat all but the orange M&M's (and keep it for good feng shui in your desktop).",
        "You try to convince your wife and kids that there is a Disney Park in Perth.",
        "The homepage of your computer is http://www.moodle.org.",
        "The homepage of every computer you have ever laid hands on is http://www.moodle.org.",
        "You only recently discovered that typing an address in your browser takes you
         to a page outside Moodle.org",
        "You sometimes refer to your third child as 1.3",
        "Your default response to a rerun on TV is to fire up the laptop and check out moodle.org.",
        "If you put little labels on your TV's remote control that say...
         view.php?id=1, view.php?id=2, etc ...",
        "You have five browser windows open right now, four of which are Moodle sites.",
        "Reading the moodle.org forums is part of the morning routine: coffee, newspaper,
         wake up the children. . .",
        "You find yourself adding items to a list that only the Moodle clique will understand.",
        'You tell people to "Keep Moodling" and that they have been "Moodlised"',
        "If instead of saying Have a good day you begin greeting folks by saying Happy Moodling!
         and even worse when you actually believe that the two statements mean the same thing.
         {Based on a true story}",
        "If normal words start being morphed into Moodle-isms. For example, when someone
         asks How are you? you begin to reply with words like Moodle-rrific and Moodle-tastic.
         Subsequently, you get confused when someone gives you a quizzical look because they
         do not understand what have just said. {Based on a true story}",
        "If your students start showing up to the Friday night football games wanting to know
         when the Moodle chant is going to begin and students begin painting MOODLE on their
         chests! {Based on a true story}",
        "You get custom plates for your new car... Moodle Plate",
        "You get a quote on a custom paint job for your new car Moodle Orange Car",
        "You are looking forward to Christmas vacation so that you will have time to upgrade
         Moodle!",
        'You read something interesting and your first thought is, "I have to post that at
         moodle.org."',
        "You forget that the comment you just ignored from a co-worker won't be retrievable
         later in a Recent Activities block.");

    $randomtext = "";
    while (strlen($randomtext) < $length) {
        reset($umightbeamoodler);
        srand((double) microtime() * 1000000);
        shuffle($umightbeamoodler);
        if ($textformat) {
            $randomtext .= substr($umightbeamoodler[0], 0, $length - strlen($randomtext));
        } else {
            $tag = $tags[rand(0, count($tags) - 1)];
            $text = substr($umightbeamoodler[0], 0, $length - strlen($randomtext)). " ";
            $randomtext .= "<{$tag}>{$text}</{$tag}>";
        }
    }

    return $randomtext;
}

/**
 * Checks $field in $table for any data with $prefix and returns the maximum suffixed
 * number. This is used by all the shortnames in random generator to have consitancy in
 * naming and guessing what data has been filled.
 *
 * @param string $prefix prefix to seach in the database
 * @param string $table name of the table to seach prefix in.
 * @param string $field name of the table field to seach for $prefix.
 * @return int max number which has been suffixed at end of $prefix in $table
 */
function get_last_suffixed_counter($prefix, $table, $field) {
    global $DB;

    if (empty($prefix)) {
        //If not found then there is no username sequence with provided prefix
        return 0;
    }

    $select = $DB->sql_like($field, ':uname');
    $params['uname'] = "$prefix%";
    $sort = 'id DESC';

    $recordsets = $DB->get_records_select($table, $select, $params, $sort, $field);
    $counter = 0;

    //scroll though each found username and compare value. Using crude way, as
    //there is no easy way to find max username sequence.
    $tempcounter = 0;
    foreach ($recordsets as $record) {
        $counter = substr($record->$field, strlen($prefix));
        if ($counter && is_numeric($counter)) {
            if ($counter > $tempcounter) {
                $tempcounter = $counter;
            }
        }
    }

    //If not found then there is no username sequence with provided prefix
    return $tempcounter;
}
