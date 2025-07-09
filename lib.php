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
 * Utility functions for local_gradebook_visibility plugin.
 *
 * @package    local_gradebook_visibility
 * @copyright  2025 Miguël Dhyne <miguel.dhyne@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Recursively sets a category, its category total, all grade items, and subcategories as hidden or visible.
 *
 * This function traverses the gradebook category tree and enforces visibility (or hidden) for
 * a category and all its descendants, including grade items and subcategories.
 *
 * @param int $categoryid The ID of the grade category to start from.
 * @param bool $visible If true, sets all elements as visible; if false, sets as hidden.
 * @param array $visited (Optional) Used internally for recursion guard; do not pass manually.
 * @return void
 */
function local_gradebook_visibility_recursive_force_visibility(int $categoryid, bool $visible, array &$visited = []) {
    global $DB;

    // Prevent infinite loops in case of category cycles.
    if (in_array($categoryid, $visited, true)) {
        return;
    }
    $visited[] = $categoryid;

    // Set the visibility of the category itself.
    $DB->set_field('grade_categories', 'hidden', $visible ? 0 : 1, ['id' => $categoryid]);
    $DB->set_field('grade_categories', 'timemodified', time(), ['id' => $categoryid]);

    // Set the visibility of the associated category total grade_item.
    $catitem = $DB->get_record('grade_items', [
        'itemtype'     => 'category',
        'iteminstance' => $categoryid,
    ], 'id');
    if ($catitem) {
        $DB->set_field('grade_items', 'hidden', $visible ? 0 : 1, ['id' => $catitem->id]);
        $DB->set_field('grade_items', 'timemodified', time(), ['id' => $catitem->id]);
    }

    // Set the visibility of all grade_items in this category, except the total itself.
    $items = $DB->get_records('grade_items', ['categoryid' => $categoryid]);
    foreach ($items as $record) {
        if ($record->itemtype !== 'category') {
            $DB->set_field('grade_items', 'hidden', $visible ? 0 : 1, ['id' => $record->id]);
            $DB->set_field('grade_items', 'timemodified', time(), ['id' => $record->id]);
        }
    }

    // Recurse into subcategories.
    $subcats = $DB->get_records('grade_categories', ['parent' => $categoryid]);
    foreach ($subcats as $subcat) {
        local_gradebook_visibility_recursive_force_visibility($subcat->id, $visible, $visited);
    }
}

/**
 * Applies hiding logic if a parent category is hidden.
 *
 * If any parent category is hidden, this function will hide the given grade item (for activities or manual items),
 * or the entire category subtree (for category totals). It never re-shows items if the parent becomes visible.
 *
 * @param object $item The grade_items record (as a stdClass).
 * @return void
 */
function local_gradebook_visibility_apply_parent_hiding($item) {
    global $DB;
    $timestamp = time();

    if ($item->itemtype === 'category') {
        // Handle the case for category totals.
        $cat = $DB->get_record('grade_categories', ['id' => $item->iteminstance]);
        if (!$cat) {
            return;
        }

        $pathids = explode('/', trim($cat->path, '/'));
        // Check all ancestors except itself (from nearest to farthest).
        foreach (array_reverse($pathids) as $catid) {
            if ($catid == $cat->id) {
                continue;
            }
            $parentcat = $DB->get_record('grade_categories', ['id' => $catid], 'id,hidden');
            if ($parentcat && (int)$parentcat->hidden === 1) {
                // If a parent is hidden, hide the entire subtree under this category.
                local_gradebook_visibility_recursive_force_visibility($cat->id, false);
                // Update timemodified for category and its total.
                $DB->set_field('grade_categories', 'timemodified', $timestamp, ['id' => $cat->id]);
                $DB->set_field('grade_items', 'timemodified', $timestamp, ['id' => $item->id]);
                return;
            }
        }
        // If no parent is hidden, do nothing (no automatic re-show).
        return;

    } else if (in_array($item->itemtype, ['mod', 'manual'])) {
        // Handle activity or manual grade items.
        $cat = $DB->get_record('grade_categories', ['id' => $item->categoryid]);
        if (!$cat) {
            return;
        }

        $pathids = explode('/', trim($cat->path, '/'));
        foreach (array_reverse($pathids) as $catid) {
            $parentcat = $DB->get_record('grade_categories', ['id' => $catid], 'id,hidden');
            if ($parentcat && (int)$parentcat->hidden === 1) {
                // If a parent is hidden, hide only this grade item.
                $DB->set_field('grade_items', 'hidden', 1, ['id' => $item->id]);
                $DB->set_field('grade_items', 'timemodified', $timestamp, ['id' => $item->id]);
                return;
            }
        }
        // If no parent is hidden, do nothing.
        return;
    }
}
