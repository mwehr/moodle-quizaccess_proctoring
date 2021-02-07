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
 * Report for the quizaccess_proctoring plugin.
 *
 * @package   quizaccess_proctoring
 * @copyright 2020 Brain Station 23
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */


require_once (__DIR__ . '/../../../../config.php');
require_once ($CFG->dirroot . '/lib/tablelib.php');
require_once ($CFG->dirroot . '/mod/quiz/accessrule/proctoring/locallib.php');

// Get vars.
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$studentid = optional_param('studentid', '', PARAM_INT);
$reportid = optional_param('reportid', '', PARAM_INT);
$logaction = optional_param('logaction', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_INT);

$context = context_module::instance($cmid, MUST_EXIST);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');

require_login($course, true, $cm);


$COURSE = $DB->get_record('course', array('id' => $courseid));
$quiz = $DB->get_record('quiz', array('id' => $cm->instance));

$params = array(
    'courseid' => $courseid,
    'userid' => $studentid,
    'cmid' => $cmid
);
if ($studentid) {
    $params['studentid'] = $studentid;
}
if ($reportid) {
    $params['reportid'] = $reportid;
}

$url = new moodle_url(
    '/mod/quiz/accessrule/proctoring/report.php',
    $params
);


$PAGE->set_url($url);
$PAGE->set_pagelayout('course');
$PAGE->set_title($COURSE->shortname . ': ' . get_string('pluginname', 'quizaccess_proctoring'));
$PAGE->set_heading($COURSE->fullname . ': ' . get_string('pluginname', 'quizaccess_proctoring'));

$PAGE->navbar->add(get_string('quizaccess_proctoring', 'quizaccess_proctoring'), $url);

$PAGE->requires->js_call_amd( 'quizaccess_proctoring/lightbox2');

echo $OUTPUT->header();

echo '<div id="main">
<h2>' . get_string('eprotroringreports', 'quizaccess_proctoring') . '' . $quiz->name . '</h2>
<div class="box generalbox m-b-1 adminerror alert alert-info p-y-1">'
    . get_string('eprotroringreportsdesc', 'quizaccess_proctoring') . '</div>
';

if (has_capability('quizaccess/proctoring:deletecamshots', $context, $USER->id)
    && $studentid != null
    && $cmid != null
    && $courseid != null
    && $reportid != null
    && !empty($logaction)
) {
    $logEntryList = $DB->get_records_sql(
        'SELECT * FROM {quizaccess_proctoring_logs} where status = :status',
        $params= ['status' => $status]);

    $fs = get_file_storage();
    foreach ($logEntryList as $logEntry) {
        proctoring_delete_image($logEntry->webcampicture, $context, $fs);
    }

    // Remove logs from quizaccess_proctoring_logs
    $DB->delete_records('quizaccess_proctoring_logs', array('courseid' => $courseid, 'quizid' => $cmid, 'userid' => $studentid, 'status' => $status));

    redirect(
        new moodle_url(
        '/mod/quiz/accessrule/proctoring/report.php',
            array(
                'courseid' => $courseid,
                'cmid' => $cmid
            )
        ),
        'Images deleted!', -11);
}

if (has_capability('quizaccess/proctoring:viewreport', $context, $USER->id) && $cmid != null && $courseid != null) {

    // Check if report if for some user.
    if ($studentid != null && $cmid != null && $courseid != null && $reportid != null) {
        // Report for this user.
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
         e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
         from  {quizaccess_proctoring_logs} e INNER JOIN {user} u  ON u.id = e.userid
         WHERE e.courseid = '$courseid' AND e.quizid = '$cmid' AND u.id = '$studentid' AND e.id = '$reportid'";
    }

    if ($studentid == null && $cmid != null && $courseid != null) {
        // Report for all users.
        $sql = "SELECT  DISTINCT e.userid as studentid, u.firstname as firstname, u.lastname as lastname,
                u.email as email, max(e.webcampicture) as webcampicture,max(e.id) as reportid, max(e.status) as status,
                max(e.timemodified) as timemodified
                from  {quizaccess_proctoring_logs} e INNER JOIN {user} u ON u.id = e.userid
                WHERE e.courseid = '$courseid' AND e.quizid = '$cmid'
                group by e.userid, u.firstname, u.lastname, u.email";
    }

    // Print report.
    $table = new flexible_table('proctoring-report-' . $COURSE->id . '-' . $cmid);

    $table->define_columns(array('fullname', 'email', 'dateverified', 'actions'));
    $table->define_headers(
        array(
            get_string('user'),
            get_string('email'),
            get_string('dateverified', 'quizaccess_proctoring'),
            get_string('actions', 'quizaccess_proctoring')
        )
    );
    $table->define_baseurl($url);

    $table->set_attribute('cellpadding', '5');
    $table->set_attribute('class', 'generaltable generalbox reporttable');
    $table->setup();

    // Prepare data.
    $sqlexecuted = $DB->get_recordset_sql($sql);

    foreach ($sqlexecuted as $info) {
        $data = array();
        $data[] = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $info->studentid .
            '&course=' . $courseid . '" target="_blank">' . $info->firstname . ' ' . $info->lastname . '</a>';

        $data[] = $info->email;

        $data[] = date("Y/M/d H:m:s", $info->timemodified);

        $data[] = '<a href="?courseid=' . $courseid .
            '&quizid=' . $cmid . '&cmid=' . $cmid . '&studentid=' . $info->studentid . '&reportid=' . $info->reportid . '">' .
            get_string('picturesreport', 'quizaccess_proctoring') . '</a>';

        $table->add_data($data);
    }
    $table->finish_html();


    // Print image results.
    if ($studentid != null && $cmid != null && $courseid != null && $reportid != null) {

        $data = array();
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
        e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
        from {quizaccess_proctoring_logs} e INNER JOIN {user} u  ON u.id = e.userid
        WHERE e.courseid = '$courseid' AND e.quizid = '$cmid' AND u.id = '$studentid' order by status, timemodified";

        $sqlexecuted = $DB->get_recordset_sql($sql);
        echo '<h3>' . get_string('picturesusedreport', 'quizaccess_proctoring') . '</h3>';

        $tablepictures = new flexible_table('proctoring-report-pictures' . $COURSE->id . '-' . $cmid);

        $tablepictures->define_columns(
            array(get_string('name', 'quizaccess_proctoring'),
                get_string('webcampicture', 'quizaccess_proctoring'),
                'Actions'
            )
        );
        $tablepictures->define_headers(
            array(get_string('name', 'quizaccess_proctoring'),
                get_string('webcampicture', 'quizaccess_proctoring'),
                get_string('actions', 'quizaccess_proctoring')
            )
        );
        $tablepictures->define_baseurl($url);

        $tablepictures->set_attribute('cellpadding', '2');
        $tablepictures->set_attribute('class', 'generaltable generalbox reporttable');

        $tablepictures->setup();

        $user = core_user::get_user($studentid);

        $userinfo = '<table border="0" width="110" height="160px">
                        <tr height="120" style="background-color: transparent;">
                            <td style="border: unset;">'.$OUTPUT->user_picture($user, array('size' => 100)).'</td>
                        </tr>
                        
                        <tr height="50">
                            <td style="border: unset;"><b>' . $info->firstname . ' ' . $info->lastname . '</b></td>
                        </tr>
                    </table>';

        $currentAttempt = 0;
        $attempCnt = 1;
        $pictures = '';
        foreach ($sqlexecuted as $info) {
            if($currentAttempt == 0 ){
                $currentAttempt = $info->status;
                $pictures = '<div class="attemptinfo">'
                                . get_string('attemptstarted', 'quizaccess_proctoring'). ': '
                                . userdate($info->timemodified)
                            .'</div>';
            }elseif ($currentAttempt != $info->status){
                $datapictures = array(
                    ($attempCnt == 1)?$userinfo:null,
                    $pictures,
                    '<a onclick="return confirm(`Are you sure want to delete the pictures?`)" class="text-danger" href="?courseid=' . $courseid .
                    '&quizid=' . $cmid . '&cmid=' . $cmid . '&studentid=' . $info->studentid . '&reportid=' . $info->reportid .'&status=' . $currentAttempt . '&logaction=delete">Delete images</a>'
                );
                $tablepictures->add_data($datapictures);
                $pictures = '<div>Attempt started: '. userdate($info->timemodified) .'</div>';
                $attempCnt++;
                $currentAttempt = $info->status;
            }else {
                if (!empty($info->webcampicture)) {
                    $image_url = proctoring_get_image_url($info->webcampicture, $context, 'picture');
                    $pictures .= '<a href="' . $image_url . '" data-lightbox="' . $currentAttempt . '"' . ' data-title ="' . $info->firstname . ' ' . $info->lastname . '">' .
                        '<img width="100" src="' . $image_url . '" alt="' . $info->firstname . ' ' . $info->lastname . '"/>
                       </a>';
                }
            }
        }
        $datapictures = array(
            ($attempCnt == 1)?$userinfo:null,
            $pictures,
            '<a onclick="return confirm(`Are you sure want to delete the pictures?`)" class="text-danger" href="?courseid=' . $courseid .
            '&quizid=' . $cmid . '&cmid=' . $cmid . '&studentid=' . $info->studentid . '&reportid=' . $info->reportid .'&status=' . $currentAttempt . '&logaction=delete">Delete images</a>'
        );
        $tablepictures->add_data($datapictures);

        $tablepictures->finish_html();
    }

} else {
    // User has not permissions to view this page.
    echo '<div class="box generalbox m-b-1 adminerror alert alert-danger p-y-1">' .
        get_string('notpermissionreport', 'quizaccess_proctoring') . '</div>';
}
echo '</div>';
echo $OUTPUT->footer();

