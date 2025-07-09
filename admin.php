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
 * Admin page for the local_gradebook_visibility plugin.
 *
 * @package   local_gradebook_visibility
 * @copyright 2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gradebook_visibility/admin.php'));
$PAGE->set_title(get_string('pluginname', 'local_gradebook_visibility'));
$PAGE->set_heading(get_string('pluginname', 'local_gradebook_visibility'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('scheduleheading', 'local_gradebook_visibility'));

global $DB;

// Sorting management (add spaces after commas, no underscores for variables).
$allowedcols = [
    'course_shortname', 'category_idnumber', 'action',
    'scheduled_at', 'status', 'executed_at',
];
$sortcol = in_array($_GET['sort'] ?? '', $allowedcols) ? $_GET['sort'] : 'scheduled_at';
$sortdir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$order = $sortcol . ' ' . $sortdir;
$schedules = $DB->get_records('local_grcatvis_schedule', null, $order);

/**
 * Generates a sortable table header for the admin schedule table.
 *
 * @param string $col Column key.
 * @param string $label Display label for the header.
 * @return string HTML for the sortable column header.
 */
function sortable_header($col, $label) {
    $currentsort = $_GET['sort'] ?? '';
    $currentdir = $_GET['dir'] ?? 'desc';
    $dir = 'asc';
    $icon = '';
    if ($currentsort == $col) {
        if ($currentdir == 'asc') {
            $dir = 'desc';
            $icon = ' ▲';
        } else {
            $dir = 'asc';
            $icon = ' ▼';
        }
    }
    $url = '?sort=' . $col . '&dir=' . $dir;
    return '<a href="' . $url . '">' . $label . $icon . '</a>';
}

/**
 * Returns a localized label for the match type.
 *
 * @param string $t Match type key (equals, contains, startswith, endswith).
 * @return string Localized label.
 */
function matchtype_label($t) {
    switch ($t) {
        case 'contains':
            return get_string('matchtype_contains', 'local_gradebook_visibility');
        case 'startswith':
            return get_string('matchtype_startswith', 'local_gradebook_visibility');
        case 'endswith':
            return get_string('matchtype_endswith', 'local_gradebook_visibility');
        case 'equals':
        default:
            return get_string('matchtype_equals', 'local_gradebook_visibility');
    }
}

/**
 * Returns a formatted HTML label for the schedule status.
 *
 * @param int $status Status code (0: planned, 1: executed, 2: error).
 * @return string HTML span with status.
 */
function status_label($status) {
    switch ($status) {
        case 1:
            return '<span style="color:green;">' .
                get_string('status_executed', 'local_gradebook_visibility') . '</span>';
        case 2:
            return '<span style="color:red;">' .
                get_string('status_error', 'local_gradebook_visibility') . '</span>';
        default:
            return '<span style="color:grey;">' .
                get_string('status_planned', 'local_gradebook_visibility') . '</span>';
    }
}

echo '<a href="schedule_edit.php" class="btn btn-primary" style="margin-bottom:1em;">
        <i class="icon fa fa-plus"></i> ' .
        get_string('addnewschedule', 'local_gradebook_visibility') .
      '</a>';

echo '<style>
.logcollapsible { cursor: pointer; color: #0073aa; text-decoration: underline; }
.logcontent {
    display: none;
    max-width: 300px;
    white-space: pre-wrap;
    background: #f8f8f8;
    border: 1px solid #ddd;
    margin-top: 4px;
    padding: 4px;
}
</style>';

echo '<script>
function toggleLog(id) {
    var l = document.getElementById(id);
    if (l.style.display === "none" || l.style.display === "") {
        l.style.display = "block";
    } else {
        l.style.display = "none";
    }
    return false;
}
</script>';

echo '<table class="generaltable">';
echo '<tr>
    <th>' . sortable_header('course_shortname', get_string('course_shortname', 'local_gradebook_visibility')) .
    '<br/><small>' . get_string('matchtype', 'local_gradebook_visibility') . '</small></th>
    <th>' . sortable_header('category_idnumber', get_string('category_idnumber', 'local_gradebook_visibility')) .
    '<br/><small>' . get_string('matchtype', 'local_gradebook_visibility') . '</small></th>
    <th>' . sortable_header('action', get_string('action', 'local_gradebook_visibility')) . '</th>
    <th>' . sortable_header('scheduled_at', get_string('scheduled_at', 'local_gradebook_visibility')) . '</th>
    <th>' . sortable_header('status', get_string('status', 'local_gradebook_visibility')) . '</th>
    <th>' . sortable_header('executed_at', get_string('executed_at', 'local_gradebook_visibility')) . '</th>
    <th>' . get_string('log', 'local_gradebook_visibility') . '</th>
    <th></th>
</tr>';

foreach ($schedules as $schedule) {
    $logid = 'log_' . $schedule->id;
    $coursetype = isset($schedule->course_shortname_matchtype) ?
        $schedule->course_shortname_matchtype : '';
    $cattype = isset($schedule->category_idnumber_matchtype) ?
        $schedule->category_idnumber_matchtype : '';

    echo '<tr>';
    echo '<td>' . htmlspecialchars($schedule->course_shortname, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)
        . '<br/><small>' . matchtype_label($coursetype) . '</small></td>';
    echo '<td>' . htmlspecialchars($schedule->category_idnumber, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)
        . '<br/><small>' . matchtype_label($cattype) . '</small></td>';
    echo '<td>' .
        ($schedule->action == 'show'
            ? get_string('show', 'local_gradebook_visibility')
            : get_string('hide', 'local_gradebook_visibility'))
        . '</td>';
    echo '<td>' . userdate($schedule->scheduled_at) . '</td>';
    echo '<td>' . status_label($schedule->status) . '</td>';
    echo '<td>' . ($schedule->executed_at ? userdate($schedule->executed_at) : '') . '</td>';
    echo '<td>
        <a href="#" class="logcollapsible" onclick="return toggleLog(\'' . $logid . '\');">' .
            get_string('see_log', 'local_gradebook_visibility') . '</a>
        <div id="' . $logid . '" class="logcontent">'
            . format_text($schedule->log, FORMAT_PLAIN) .
        '</div>
    </td>';
    echo '<td>
        <a href="schedule_edit.php?id=' . $schedule->id . '">' .
            get_string('edit', 'local_gradebook_visibility') . '</a> |
        <a href="schedule_edit.php?delete=' . $schedule->id . '&sesskey=' . sesskey() .
            '" onclick="return confirm(\'' . get_string('confirm_delete', 'local_gradebook_visibility') . '\')">' .
            get_string('delete', 'local_gradebook_visibility') . '</a> |
        <a href="schedule_edit.php?duplicate=' . $schedule->id . '">' .
            get_string('duplicate', 'local_gradebook_visibility') . '</a>
    </td>';
    echo '</tr>';
}
echo '</table>';

echo $OUTPUT->footer();
