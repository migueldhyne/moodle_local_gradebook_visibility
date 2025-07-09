# Gradebook Visibility

![GPLv3 license](https://img.shields.io/badge/License-GPLv3-blue.svg)

## Description

This Moodle plugin (local type) allows you to schedule automatic show/hide actions for gradebook categories in one or multiple courses, based on flexible rules to be executed at a specific date and time.

## Features

- **Flexible rule creation:** Define rules based on course shortname (with match types: equals, contains, starts with, ends with) and grade category idnumber (with the same match types).
- **Bulk actions:** Target one or many courses and categories with a single rule.
- **Precise scheduling:** Actions can be scheduled for any date and time.
- **Simulation mode:** "Test Rule" lets you preview which courses and categories would be affected before saving a rule.
- **Duplicate rules:** Quickly create similar rules with a single click.
- **Logging:** All actions and outcomes are logged and easily viewable in the admin interface.
- **Sortable table:** Admin overview table can be sorted by any column.
- **Moodle 4.x+ compatible.**

## Installation

1. Copy the plugin folder to `local/gradebook_visibility` in your Moodle installation.
2. Log in as an administrator to complete the installation.
3. Find the plugin interface under **Site administration > Plugins > Grade Category Visibility Schedule**.

## Usage

- From the admin interface, add a new rule by specifying:
  - Course shortname and its match type,
  - Grade category idnumber and its match type,
  - Action (show/hide),
  - Scheduled date and time.
- Use the **"Test Rule"** button to preview (dry-run) which grade categories will be affected before actually saving.
- Use the **"Duplicate"** action to quickly copy an existing rule.
- Edit or delete rules at any time.
- Every scheduled execution is logged and can be viewed in the rule list.

## How It Works

- The plugin uses Moodle’s cron/observer system to execute pending rules at the scheduled times.
- Each rule is processed, affecting all matching grade categories according to the specified action (show or hide).
- Results and logs are stored for auditing.

## Security

- The plugin will not allow you to save a rule if both the course and category criteria are empty (to prevent accidental mass changes).
- All actions require administrator permissions (`moodle/site:config`).
- CSRF protection is enforced using Moodle's `sesskey`.

## Multilingual

- All interface strings are available in English and French.
- Additional languages can be added via standard Moodle language customization.

## FAQ

**Q: Can I hide or show the main course total?**  
A: Yes. If you do not specify a category idnumber, the rule will affect the main course total.

**Q: What happens if I leave both criteria blank?**  
A: The rule will not be saved and you will receive an error message.

**Q: Who can use this plugin?**  
A: Only site administrators with the proper capabilities can manage or run these rules.

**Q: Does "Test Rule" modify any data?**  
A: No, the simulation only displays what *would* be affected; no data is changed.

## License

This plugin is licensed under the [GNU General Public License v3](https://www.gnu.org/licenses/gpl-3.0.html) (GPLv3), like all official Moodle plugins.

---