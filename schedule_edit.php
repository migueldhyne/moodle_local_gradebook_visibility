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
 * Admin page for editing or duplicating scheduled visibility rules in local_gradebook_visibility.
 *
 * Handles record creation, update, deletion, and dry-run simulation.
 * Ensures capability checks and session validation for all actions.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$id         = optional_param('id', 0, PARAM_INT);
$delete     = optional_param('delete', 0, PARAM_INT);
$duplicate  = optional_param('duplicate', 0, PARAM_INT);

$redirecturl = new moodle_url('/local/gradebook_visibility/admin.php');

global $DB, $USER, $OUTPUT;

// Deletion (GET only; session check).
if ($delete && confirm_sesskey()) {
    $DB->delete_records('local_grcatvis_schedule', [ 'id' => $delete ]);
    redirect($redirecturl, get_string('deleted', 'local_gradebook_visibility'), 2);
}

// Record preparation.
if ($duplicate) {
    $original = $DB->get_record('local_grcatvis_schedule', [ 'id' => $duplicate ], '*', MUST_EXIST);
    $record = clone $original;
    $record->id = 0;
    $record->status = 0;
    $record->executed_at = null;
    $record->log = '';
    $record->scheduled_at = time() + 3600;
    \core\notification::info(get_string('duplicated_rule', 'local_gradebook_visibility'));
} else if ($id) {
    $record = $DB->get_record('local_grcatvis_schedule', [ 'id' => $id ], '*', MUST_EXIST);
    if (empty($record->course_shortname_matchtype)) {
        $record->course_shortname_matchtype = 'equals';
    }
    if (empty($record->category_idnumber_matchtype)) {
        $record->category_idnumber_matchtype = 'equals';
    }
} else {
    $record = (object) [
        'id' => 0,
        'course_shortname' => '',
        'course_shortname_matchtype' => 'equals',
        'category_idnumber' => '',
        'category_idnumber_matchtype' => 'equals',
        'action' => 'hide',
        'scheduled_at' => time() + 3600,
    ];
}

// Form processing (POST only, session protected).
$testrunhtml = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && data_submitted() && confirm_sesskey()) {
    $istest = isset($_POST['testrule']);
    $record->course_shortname = required_param('course_shortname', PARAM_RAW_TRIMMED);
    $record->course_shortname_matchtype = optional_param('course_shortname_matchtype', 'equals', PARAM_ALPHA);
    $record->category_idnumber = required_param('category_idnumber', PARAM_RAW_TRIMMED);
    $record->category_idnumber_matchtype = optional_param('category_idnumber_matchtype', 'equals', PARAM_ALPHA);
    $record->action = required_param('action', PARAM_ALPHA);
    $record->scheduled_at = strtotime(required_param('scheduled_at', PARAM_RAW_TRIMMED));
    $record->adminid = $USER->id;
    $record->status = 0;
    $record->executed_at = null;
    $record->log = '';
    $record->course_shortname_matchtype = (string) $record->course_shortname_matchtype;
    $record->category_idnumber_matchtype = (string) $record->category_idnumber_matchtype;

    // Check: at least one field must be filled (for save, not dry-run).
    if (!$istest && empty($record->course_shortname) && empty($record->category_idnumber)) {
        \core\notification::error(get_string('error_rule_empty', 'local_gradebook_visibility'));
    } else if ($istest) {
        // Dry-run simulation.
        $testrunhtml .= $OUTPUT->notification(get_string('testrun_results', 'local_gradebook_visibility'), 'info');
        $testrunhtml .= '<div style="background:#eef;padding:1em;margin:1em 0">';
        // Course search.
        switch ($record->course_shortname_matchtype ?? 'equals') {
            case 'contains':
                $courseselect = "shortname LIKE ?";
                $courseparam = '%' . $record->course_shortname . '%';
                break;
            case 'startswith':
                $courseselect = "shortname LIKE ?";
                $courseparam = $record->course_shortname . '%';
                break;
            case 'endswith':
                $courseselect = "shortname LIKE ?";
                $courseparam = '%' . $record->course_shortname;
                break;
            default:
                $courseselect = "shortname = ?";
                $courseparam = $record->course_shortname;
        }
        $courses = $DB->get_records_select('course', $courseselect, [ $courseparam ]);
        if (empty($courses)) {
            $testrunhtml .= get_string('testrun_no_course', 'local_gradebook_visibility');
        } else {
            $testrunhtml .= '<b>' . get_string('testrun_courses', 'local_gradebook_visibility') . '</b><ul>';
            foreach ($courses as $course) {
                $testrunhtml .= '<li>' . $course->shortname . ' (id=' . $course->id . ')';
                // Grade category search per course.
                switch ($record->category_idnumber_matchtype ?? 'equals') {
                    case 'contains':
                        $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                        $catparam = [ $course->id, '%' . $record->category_idnumber . '%' ];
                        break;
                    case 'startswith':
                        $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                        $catparam = [ $course->id, $record->category_idnumber . '%' ];
                        break;
                    case 'endswith':
                        $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                        $catparam = [ $course->id, '%' . $record->category_idnumber ];
                        break;
                    default:
                        $catselect = "courseid = ? AND itemtype = 'category' AND idnumber = ?";
                        $catparam = [ $course->id, $record->category_idnumber ];
                }
                $gilist = $DB->get_records_select('grade_items', $catselect, $catparam);
                if (empty($gilist)) {
                    $testrunhtml .= '<ul><li><i>' . get_string('testrun_no_cat', 'local_gradebook_visibility') . '</i></li></ul>';
                } else {
                    $testrunhtml .= '<ul>';
                    foreach ($gilist as $gi) {
                        $cat = $DB->get_record('grade_categories', [ 'id' => $gi->iteminstance ]);
                        $catid = $cat ? $cat->id : '-';
                        $a = (object) [
                            'idnumber' => $gi->idnumber,
                            'catid' => $catid,
                        ];
                        $testrunhtml .= '<li>' . get_string('testrun_cat', 'local_gradebook_visibility', $a) . '</li>';
                    }
                    $testrunhtml .= '</ul>';
                }
                $testrunhtml .= '</li>';
            }
            $testrunhtml .= '</ul>';
        }
        $testrunhtml .= '</div>';
        // Do not save, stay on form.
    } else {
        if ($record->id) {
            $DB->update_record('local_grcatvis_schedule', $record);
        } else {
            // Use custom SQL insert if needed.
            $sql = "INSERT INTO {local_grcatvis_schedule}
                (course_shortname, course_shortname_matchtype, category_idnumber, category_idnumber_matchtype,
                action, scheduled_at, adminid, status, executed_at, log)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $record->course_shortname,
                $record->course_shortname_matchtype,
                $record->category_idnumber,
                $record->category_idnumber_matchtype,
                $record->action,
                $record->scheduled_at,
                $record->adminid,
                $record->status,
                $record->executed_at,
                $record->log,
            ];
            $DB->execute($sql, $params);
        }
        redirect($redirecturl, get_string('saved', 'local_gradebook_visibility'), 2);
    }
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gradebook_visibility/schedule_edit.php', [ 'id' => $id ]));
$PAGE->set_title(get_string('editschedule', 'local_gradebook_visibility'));
$PAGE->set_heading(get_string('editschedule', 'local_gradebook_visibility'));

echo $OUTPUT->header();

echo '<a href="admin.php" class="btn btn-link" style="margin-bottom:15px;">
    ← ' . get_string('backtoadmin', 'local_gradebook_visibility') . '
</a>';

echo '<form method="post">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '"/>';
echo '<table class="generaltable">';
echo '<tr>
    <td>' . get_string('course_shortname', 'local_gradebook_visibility') . '</td>
    <td>
        <input type="text" name="course_shortname" value="' . s($record->course_shortname) . '"/>
        <select name="course_shortname_matchtype">
            <option value="equals"' . ($record->course_shortname_matchtype == 'equals' ? ' selected' : '') .
            '>' . get_string('matchtype_equals', 'local_gradebook_visibility') . '</option>
            <option value="contains"' . ($record->course_shortname_matchtype == 'contains' ? ' selected' : '') .
            '>' . get_string('matchtype_contains', 'local_gradebook_visibility') . '</option>
            <option value="startswith"' . ($record->course_shortname_matchtype == 'startswith' ? ' selected' : '') .
            '>' . get_string('matchtype_startswith', 'local_gradebook_visibility') . '</option>
            <option value="endswith"' . ($record->course_shortname_matchtype == 'endswith' ? ' selected' : '') . '>'
            . get_string('matchtype_endswith', 'local_gradebook_visibility') . '</option>
        </select>
    </td>
</tr>';

echo '<tr>
    <td>' . get_string('category_idnumber', 'local_gradebook_visibility') . '</td>
    <td>
        <input type="text" name="category_idnumber" value="' . s($record->category_idnumber) . '"/>
        <select name="category_idnumber_matchtype">
            <option value="equals"' . ($record->category_idnumber_matchtype == 'equals' ? ' selected' : '') . '>'
            . get_string('matchtype_equals', 'local_gradebook_visibility') . '</option>
            <option value="contains"' . ($record->category_idnumber_matchtype == 'contains' ? ' selected' : '') . '>'
            . get_string('matchtype_contains', 'local_gradebook_visibility') . '</option>
            <option value="startswith"' . ($record->category_idnumber_matchtype == 'startswith' ? ' selected' : '') . '>'
            . get_string('matchtype_startswith', 'local_gradebook_visibility') . '</option>
            <option value="endswith"' . ($record->category_idnumber_matchtype == 'endswith' ? ' selected' : '') . '>'
            . get_string('matchtype_endswith', 'local_gradebook_visibility') . '</option>
        </select>
    </td>
</tr>';

echo '<tr><td>' . get_string('action', 'local_gradebook_visibility') . '</td><td>
    <select name="action">
        <option value="hide"' . ($record->action == 'hide' ? ' selected' : '') . '>' .
        get_string('hide', 'local_gradebook_visibility') . '</option>
        <option value="show"' . ($record->action == 'show' ? ' selected' : '') . '>' .
        get_string('show', 'local_gradebook_visibility') . '</option>
    </select>
</td></tr>';
echo '<tr><td>' . get_string('scheduled_at', 'local_gradebook_visibility') . ' (YYYY-MM-DD HH:MM)</td>
<td><input type="text" name="scheduled_at" value="' . date('Y-m-d H:i', $record->scheduled_at) . '"/></td></tr>';
echo '</table>';
echo '<div style="margin-top: 1em;">';
echo '<button type="submit" name="save" class="btn btn-primary" style="margin-right:8px;">'
. get_string('save', 'local_gradebook_visibility') . '</button>';
echo '<button type="submit" name="testrule" class="btn btn-secondary">'
. get_string('testrule', 'local_gradebook_visibility') . '</button>';
echo '</div>';
echo '</form>';

if (!empty($testrunhtml)) {
    echo $testrunhtml;
}

echo $OUTPUT->footer();
