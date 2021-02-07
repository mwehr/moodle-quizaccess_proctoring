<?php

/**
 * Task configuration for the quizaccess_proctoring plugin.
 * Default: Runs every day at 01:00 am
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => '\quizaccess_proctoring\task\remove_outdated_images',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '1',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);

