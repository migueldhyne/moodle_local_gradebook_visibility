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
 * Scheduled task for processing gradebook visibility schedules.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradebook_visibility\task;

/**
 * Class process_schedules
 *
 * Executes periodic operations for gradebook visibility, such as applying hiding rules
 * and processing scheduled actions.
 *
 * @package    local_gradebook_visibility
 */
class process_schedules extends \core\task\scheduled_task {

    /**
     * Returns the name of the scheduled task (displayed in the admin interface).
     *
     * @return string The name of the scheduled task.
     */
    public function get_name() {
        return get_string('pluginname', 'local_gradebook_visibility');
    }

    /**
     * Executes the scheduled task.
     *
     * Loads utility functions, processes recently modified grade items, and executes
     * all plugin-scheduled actions.
     *
     * @return void
     */
    public function execute() {
        global $DB, $CFG;

        // Load plugin utility functions.
        require_once($CFG->dirroot.'/local/gradebook_visibility/lib.php');

        // 1. Process grade_items that have been modified since the last cron run.
        $taskclassname = '\local_gradebook_visibility\task\process_schedules';
        $lastcron = $DB->get_field('task_scheduled', 'lastruntime', ['classname' => $taskclassname]);

        if ($lastcron) {
            // Find all grade_items modified in the hour before the last cron run.
            $onehourbefore = $lastcron - 3600;
            $recentitems = $DB->get_records_select('grade_items', 'timemodified > ?', [$onehourbefore]);
            foreach ($recentitems as $item) {
                // Apply parent hiding logic to each item.
                \local_gradebook_visibility_apply_parent_hiding($item);
            }
        }

        // 2. Execute any scheduled actions for the plugin (plugin cron logic).
        \local_gradebook_visibility\observer::cron();
    }
}
