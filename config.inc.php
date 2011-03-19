<?php


global $CFG;
$CFG = null;


//NOTE: we must not use moodle session because phpMyAdmin requires register globals hack

define('ABORT_AFTER_CONFIG', true);
require('../../config.php'); // this stops immediately at the beginning of lib/setup.php

if (!empty($CFG->passwordsaltmain)) {
    $cfg['blowfish_secret'] = sha1($CFG->passwordsaltmain);
} else {
    die();
}

/* Servers configuration */
$i = 1;

$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['compress']  = false;

if ($CFG->dbhost === 'localhost') {
    $cfg['Servers'][$i]['connect_type'] = 'socket';    
    $cfg['Servers'][$i]['socket']       = '';
    if (!empty($CFG->dboptions['dbsocket']) and (strpos($CFG->dboptions['dbsocket'], '/') !== false or strpos($CFG->dboptions['dbsocket'], '\\') !== false)) {
        $cfg['Servers'][$i]['socket']   = $CFG->dboptions['dbsocket'];
    }    
} else {
    $cfg['Servers'][$i]['connect_type'] = 'tcp';    
    $cfg['Servers'][$i]['port']         = '';
    if (!empty($CFG->dboptions['dbport'])) {
        $cfg['Servers'][$i]['port']     = $CFG->dboptions['dbport'];
    }
}
$cfg['Servers'][$i]['host']    = $CFG->dbhost;
$cfg['Servers'][$i]['only_db'] = $CFG->dbname;

$cfg['AllowAnywhereRecoding']  = false;
$cfg['DefaultCharset']         = 'utf-8';
$cfg['RecodingEngine']         = 'auto';
$cfg['IconvExtraParams']       = '//TRANSLIT';
$cfg['UploadDir']              = "$CFG->dataroot/mysql";
$cfg['SaveDir']                = "$CFG->dataroot/mysql";
$cfg['docSQLDir']              = "$CFG->dataroot/mysql";
$cfg['TempDir']                = "$CFG->dataroot/mysql";

// make sure the dir actually exists
if (!file_exists($CFG->dataroot) or !is_writable($CFG->dataroot)) {
    die();
}

if (!file_exists("$CFG->dataroot/mysql")) {
    $directorypermissions = 02777;
    if (!empty($CFG->directorypermissions)) {
        $directorypermissions = $CFG->directorypermissions;
    }
    
    umask(0000); // just in case some evil code changed it
    mkdir($dir, $directorypermissions, false);
}
