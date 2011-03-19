<?php
/*
 * Moodle UI integration
 */

defined('MOODLE_INTERNAL') || die();

if (is_siteadmin() and $DB->get_dbfamily() === 'mysql') {
    $ADMIN->add('server', new admin_externalpage('localphpmyadmin', 'phpMyAdmin', new moodle_url('/local/phpmyadmin')));
}