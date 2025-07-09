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
 * Observer for gradebook visibility actions.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradebook_visibility;

/**
 * Observer class for gradebook visibility events and scheduled actions.
 *
 * Handles gradebook visibility logic in response to events such as grade item creation
 * and runs scheduled visibility updates via the cron method.
 *
 * Main responsibilities:
 * - Automatically hides grade items when their parent category is hidden.
 * - Applies scheduled rules to show or hide categories and grade items at specific times.
 * - Ensures consistency between category and grade item "hidden" states.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Event observer: when a new grade item is created, hide it if its category is hidden.
     *
     * @param \core\event\grade_item_created $event The event object for grade item creation.
     * @return void
     */
    public static function grade_item_created(\core\event\grade_item_created $event) {
        global $DB;

        // Get the newly created grade item.
        $item = $DB->get_record('grade_items', ['id' => $event->objectid], '*', IGNORE_MISSING);
        if (!$item || empty($item->categoryid)) {
            return;
        }
        // Get the parent category of the item.
        $category = $DB->get_record('grade_categories', ['id' => $item->categoryid]);
        if (!$category) {
            return;
        }
        // Check if the category total grade_item is hidden.
        $catitem = $DB->get_record('grade_items', ['itemtype' => 'category', 'iteminstance' => $category->id]);
        if ($catitem && $catitem->hidden) {
            // Hide the new grade item and the category structure (defensive patch).
            $DB->set_field('grade_items',      'hidden', 1, ['id' => $item->id]);
            $DB->set_field('grade_categories', 'hidden', 1, ['id' => $category->id]);
            $DB->set_field('grade_categories', 'timemodified', time(), ['id' => $category->id]);
        } else if ($category->hidden) {
            // Parano fallback: if the category is flagged hidden, hide everything.
            $DB->set_field('grade_items',      'hidden', 1, ['id' => $item->id]);
            $DB->set_field('grade_categories', 'hidden', 1, ['id' => $category->id]);
            $DB->set_field('grade_categories', 'timemodified', time(), ['id' => $category->id]);
        }
    }

    /**
     * Scheduled task (cron): applies all scheduled gradebook visibility actions.
     *
     * Processes rules stored in the local_grcatvis_schedule table, updating visibility of
     * categories/items according to their schedule, and logs results.
     *
     * @return void
     */
    public static function cron() {
        global $DB;

        try {
            $now      = time();
            $schedules = $DB->get_records_select(
                'local_grcatvis_schedule',
                'status=0 AND scheduled_at <= ?',
                [ $now ]
            );

            foreach ($schedules as $schedule) {
                $log = '';
                try {
                    // 1. Find all courses matching the schedule rule.
                    $matchtype = $schedule->course_shortname_matchtype ?? 'equals';
                    switch ($matchtype) {
                        case 'contains':
                            $courseselect = "shortname LIKE ?";
                            $courseparam  = '%' . $schedule->course_shortname . '%';
                            break;
                        case 'startswith':
                            $courseselect = "shortname LIKE ?";
                            $courseparam  = $schedule->course_shortname . '%';
                            break;
                        case 'endswith':
                            $courseselect = "shortname LIKE ?";
                            $courseparam  = '%' . $schedule->course_shortname;
                            break;
                        default: // Equals.
                            $courseselect = "shortname = ?";
                            $courseparam  = $schedule->course_shortname;
                            break;
                    }
                    $courses = $DB->get_records_select('course', $courseselect, [ $courseparam ]);

                    if (empty($courses)) {
                        throw new \moodle_exception(get_string('error_nocourse', 'local_gradebook_visibility',
                            (object) [
                                'shortname' => $schedule->course_shortname,
                                'matchtype' => get_string('matchtype_' . $matchtype, 'local_gradebook_visibility'),
                            ]
                        ));
                    }

                    foreach ($courses as $course) {
                        // 2. Handle rule for all categories if idnumber is empty.
                        if (trim($schedule->category_idnumber) === '') {
                            // All categories in the course.
                            $categories = $DB->get_records('grade_categories', [ 'courseid' => $course->id ]);
                            foreach ($categories as $category) {
                                $log .= get_string('log_category_found', 'local_gradebook_visibility',
                                    (object) [
                                        'id'       => $category->id,
                                        'idnumber' => $category->idnumber,
                                    ]
                                ) . "\n";
                                self::set_category_visibility_cascade($category, $schedule->action == 'show', $log);
                            }
                        } else {
                            // Search by idnumber using matchtype.
                            $catmatchtype = $schedule->category_idnumber_matchtype ?? 'equals';
                            switch ($catmatchtype) {
                                case 'contains':
                                    $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                                    $catparam  = [ $course->id, '%' . $schedule->category_idnumber . '%' ];
                                    break;
                                case 'startswith':
                                    $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                                    $catparam  = [ $course->id, $schedule->category_idnumber . '%' ];
                                    break;
                                case 'endswith':
                                    $catselect = "courseid = ? AND itemtype = 'category' AND idnumber LIKE ?";
                                    $catparam  = [ $course->id, '%' . $schedule->category_idnumber ];
                                    break;
                                default: // Equals.
                                    $catselect = "courseid = ? AND itemtype = 'category' AND idnumber = ?";
                                    $catparam  = [ $course->id, $schedule->category_idnumber ];
                                    break;
                            }

                            $gradeitemlist = $DB->get_records_select('grade_items', $catselect, $catparam);

                            if (empty($gradeitemlist)) {
                                $log .= get_string('log_nogradeitem', 'local_gradebook_visibility',
                                    (object) [
                                        'idnumber'        => $schedule->category_idnumber,
                                        'matchtype'       => get_string('matchtype_' . $catmatchtype, 'local_gradebook_visibility'),
                                        'courseshortname' => $course->shortname,
                                    ]
                                ) . "\n";
                            } else {
                                foreach ($gradeitemlist as $gradeitem) {
                                    $category = $DB->get_record('grade_categories',
                                        [ 'id' => $gradeitem->iteminstance ], '*', MUST_EXIST);
                                    $log .= get_string('log_category_found', 'local_gradebook_visibility',
                                        (object) [
                                            'id'       => $category->id,
                                            'idnumber' => $gradeitem->idnumber,
                                        ]
                                    ) . "\n";
                                    self::set_category_visibility_cascade($category, $schedule->action == 'show', $log);
                                }
                            }
                        }

                        // 3. Always handle the course total (itemtype = 'course').
                        $coursetotalitem = $DB->get_record('grade_items', [
                            'courseid' => $course->id,
                            'itemtype' => 'course',
                        ]);
                        if ($coursetotalitem) {
                            $DB->set_field('grade_items', 'hidden',
                                $schedule->action == 'show' ? 0 : 1, [ 'id' => $coursetotalitem->id ]);
                            $log .= get_string('log_coursetotal_status', 'local_gradebook_visibility',
                                (object) [
                                    'gradeitemid' => $coursetotalitem->id,
                                    'status'      => $schedule->action == 'show'
                                        ? get_string('shown', 'local_gradebook_visibility')
                                        : get_string('hidden', 'local_gradebook_visibility'),
                                ]
                            ) . "\n";
                        }
                    }

                    $schedule->status      = 1;
                    $schedule->executed_at = time();
                    $schedule->log         = $log ?: get_string('log_ok', 'local_gradebook_visibility');
                } catch (\Throwable $e) {
                    $schedule->status = 2;
                    $schedule->log    = $e->getMessage();
                }
                $DB->update_record('local_grcatvis_schedule', $schedule);
            }
        } catch (\Throwable $e) {
            debugging('Gradebook Visibility CRON crashed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Optionally, you could log this somewhere else, or notify admins.
        }

        // Always sync the hidden field of the category with its "category" grade_item.
        $catitems = $DB->get_records('grade_items', ['itemtype' => 'category']);
        foreach ($catitems as $catitem) {
            $cat = $DB->get_record('grade_categories', ['id' => $catitem->iteminstance]);
            if ($cat && $cat->hidden != $catitem->hidden) {
                // Sync category structure with the category total.
                $DB->set_field('grade_categories', 'hidden', $catitem->hidden, ['id' => $cat->id]);
                $DB->set_field('grade_categories', 'timemodified', time(), ['id' => $cat->id]);
            }
        }
        if (class_exists('\cache_helper')) {
            \cache_helper::purge_by_event('changesincourse');
        }
    }

    /**
     * Recursively sets visibility for a category, its grade items, and subcategories.
     *
     * @param \stdClass $category Grade category object.
     * @param bool $visible True to show, false to hide.
     * @param string &$log Log output, passed by reference.
     * @param int $level Recursion level (for log prefix). Default is 0.
     * @return void
     */
    private static function set_category_visibility_cascade($category, $visible, &$log, $level = 0) {
        global $DB;
        $prefix = str_repeat('-', $level);

        // 1. Update the category (grade_categories).
        $DB->set_field('grade_categories', 'hidden', $visible ? 0 : 1, ['id' => $category->id]);
        $DB->set_field('grade_categories', 'timemodified', time(), ['id' => $category->id]);

        // 2. Update the category total grade_item.
        $categoryitem = $DB->get_record('grade_items', [
            'itemtype'     => 'category',
            'iteminstance' => $category->id,
        ]);
        if ($categoryitem) {
            $DB->set_field('grade_items', 'hidden', $visible ? 0 : 1, ['id' => $categoryitem->id]);
        }

        // 3. Update all child grade items (except the total itself).
        $items = $DB->get_records('grade_items', ['categoryid' => $category->id]);
        foreach ($items as $item) {
            if ($item->itemtype != 'category') {
                $DB->set_field('grade_items', 'hidden', $visible ? 0 : 1, ['id' => $item->id]);
            }
        }

        // 4. Recurse on subcategories.
        $subcategories = $DB->get_records('grade_categories', ['parent' => $category->id]);
        foreach ($subcategories as $subcat) {
            self::set_category_visibility_cascade($subcat, $visible, $log, $level + 1);
        }
        if (class_exists('\cache_helper')) {
            \cache_helper::purge_by_event('changesincourse');
        }
    }
}
