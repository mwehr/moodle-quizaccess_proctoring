<?php

/**
 * Outdated image remove task for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proctoring\task;

defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . '/mod/quiz/accessrule/proctoring/locallib.php');

class remove_outdated_images extends \core\task\scheduled_task {
    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('removeoutdatedimages', 'quizaccess_proctoring');
    }

    /**
     * Run task deleting images and proctoring logs
     */
    public function execute() {
        global $DB;

        $imagelifetime = (int)get_config('quizaccess_proctoring', 'imagelifetime');

        if (empty($imagelifetime) || $imagelifetime <= 0) {
            return;
        }

        $imagelifetime = time() - ($imagelifetime * 3600 * 24); // Value in days.

        $logEntryList = $DB->get_records_sql(
            'SELECT * FROM {quizaccess_proctoring_logs} where timemodified < :imagelifetime',
             $params= ['imagelifetime' => $imagelifetime]);

        mtrace('Starting purging images from quiz proctoring');
        $imgCnt = 0;
        $fs = get_file_storage();
        foreach ($logEntryList as $logEntry) {
            if(!empty($logEntry->webcampicture)){
                if(proctoring_delete_image(
                        $logEntry->webcampicture,
                        \context_module::instance($logEntry->quizid),
                        $fs)){
                    $imgCnt++;
                }
            }
        }

        if(!empty($logEntryList)) {
            $DB->execute(
                'DELETE FROM {quizaccess_proctoring_logs} where timemodified < :imagelifetime',
                $params = ['imagelifetime' => $imagelifetime]);
        }

        mtrace("Purged $imgCnt image(s)");
    }
}