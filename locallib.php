<?php

/**
 * locallib for the quizaccess_proctoring plugin.
 *
 * @package   quizaccess_proctoring
 * @copyright 2020 Brain Station 23
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build image URL from hash
 *
 * @param string $webcampicture filname hash or URL
 * @param context $context course module context instance
 * @return string URL
 */
function proctoring_get_image_url($webcampicture, $context){
    // if $webcampicture length != 32 we have an URL
    if(strlen($webcampicture) != 32){
        return $webcampicture;
    }else{
        return moodle_url::make_pluginfile_url(
            $context->id,
            'quizaccess_proctoring',
            'picture',
            0,
            '/',
            $webcampicture,
        );
    }
}

/**
 * Delete image
 *
 * @param string $fileName
 * @param context $context course module context instance
 * @param file_storage $fileStorage filestorage instance
 * @return bool true if file got deleted, otherwise false
 */
function proctoring_delete_image($fileName, $context, $fileStorage){
    //backwards compatibility
    if(strlen($fileName) > 32){
        $parts= parse_url($fileName);
        $pathParts = explode('/', $parts['path']);
        // Get file
        $file = $fileStorage->get_file(
            $context->id,
            'quizaccess_proctoring',
            'picture',
            $pathParts[5],
            '/',
            $pathParts[6],
        );
    }else{
        // Get file
        $file = $fileStorage->get_file(
            $context->id,
            'quizaccess_proctoring',
            'picture',
            0,
            '/',
            $fileName
        );
    }

    // Delete it if it exists
    if ($file) {
        return $file->delete();
    }else{
        return false;
    }
}