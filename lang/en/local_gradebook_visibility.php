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
 * English language file for the local_gradebook_visibility plugin.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Action (show / hide)';
$string['actionerror'] = 'An error occurred while performing the action.';
$string['actionsuccess'] = 'Action completed successfully.';
$string['addnewschedule'] = 'Add a new schedule';
$string['autohide_desc'] = 'Newly added items or categories inherit the hidden state of their parent category, if any.';
$string['backtoadmin'] = 'Back to admin';
$string['cancel'] = 'Cancel';
$string['category_idnumber'] = 'Category ID Number (empty = applies to everything)';
$string['confirm_delete'] = 'Delete this schedule?';
$string['course_shortname'] = 'Course short name';
$string['delete'] = 'Delete';
$string['deleted'] = 'Deleted';
$string['duplicate'] = "Duplicate";
$string['duplicated_rule'] = "Rule duplicated, edit and save to confirm.";
$string['edit'] = 'Edit';
$string['editschedule'] = 'Edit scheduled action';
$string['error_nocourse'] = 'No course found for the rule (shortname: {\$a->shortname}, match type: {\$a->matchtype})';
$string['error_rule_empty'] = 'You must fill at least one of the two fields: Course shortname or Category idnumber.';
$string['executed_at'] = 'Executed on';
$string['hidden'] = 'hidden';
$string['hide'] = 'Hide';
$string['log'] = 'Log';
$string['log_cat_notfound'] = 'WARNING: No grade_item of type category found for category {\$a->catid}';
$string['log_cat_status'] = 'Category (grade_item {\$a->gradeitemid}) {\$a->status}';
$string['log_category_found'] = 'Category found (ID={\$a->id}) via grade_items.idnumber {\$a->idnumber}.';
$string['log_coursetotal_status'] = 'Course total item (ID: {\$a->gradeitemid}) {\$a->status}';
$string['log_item_status'] = 'Item {\$a->itemid} {\$a->status}';
$string['log_nogradeitem'] = 'No category grade_item with idnumber: {\$a->idnumber} (matchtype: {\$a->matchtype}) for course: {\$a->courseshortname}';
$string['log_ok'] = 'OK';
$string['matchtype'] = 'Type';
$string['matchtype_contains'] = 'contains';
$string['matchtype_endswith'] = 'ends with';
$string['matchtype_equals'] = 'equals';
$string['matchtype_startswith'] = 'starts with';
$string['pluginname'] = 'Gradebook Visibility';
$string['save'] = 'Save';
$string['savechanges'] = "Save changes";
$string['saved'] = 'Saved';
$string['scheduled_actions'] = 'Scheduled actions';
$string['scheduled_at'] = 'Action date and time';
$string['scheduleheading'] = 'Schedule visibility';
$string['see_log'] = 'View';
$string['show'] = 'Show';
$string['shown'] = 'shown';
$string['status'] = 'Status';
$string['status_error'] = 'Error';
$string['status_executed'] = 'Executed';
$string['status_planned'] = 'Planned';
$string['testrule'] = 'Test this rule';
$string['testrun_cat'] = 'Category: [IDNumber: {\$a->idnumber}], Category ID: {\$a->catid}';
$string['testrun_courses'] = 'Matched courses:';
$string['testrun_no_cat'] = 'No category found for this course.';
$string['testrun_no_course'] = 'No course affected by this rule.';
$string['testrun_results'] = 'Simulation: preview of items affected by this rule';
