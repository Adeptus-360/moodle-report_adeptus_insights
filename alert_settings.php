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
 * Alert settings configuration page for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_adeptus_insights\alert_engine;

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:managealerts', $context);

$PAGE->set_url(new moodle_url('/report/adeptus_insights/alert_settings.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('alert_settings', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('alert_settings', 'report_adeptus_insights'));
$PAGE->set_pagelayout('admin');

// Enterprise tier check.
if (!alert_engine::is_available()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('alert_enterprise_only', 'report_adeptus_insights'),
        'warning'
    );
    echo html_writer::tag('p', get_string('alert_upgrade_prompt', 'report_adeptus_insights'));
    echo html_writer::link(
        new moodle_url('/report/adeptus_insights/subscription.php'),
        get_string('alert_upgrade_button', 'report_adeptus_insights'),
        ['class' => 'btn btn-primary']
    );
    echo $OUTPUT->footer();
    die();
}

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $enabled = optional_param('alert_enabled', 0, PARAM_INT);
    $inactivitydays = optional_param('alert_inactivity_days', 14, PARAM_INT);
    $completionthreshold = optional_param('alert_completion_threshold', 30, PARAM_INT);
    $frequency = optional_param('alert_frequency', 'weekly', PARAM_ALPHA);
    $recipientroles = optional_param('alert_recipient_roles', 'manager', PARAM_TEXT);

    // Validate.
    $inactivitydays = max(1, min(365, $inactivitydays));
    $completionthreshold = max(1, min(100, $completionthreshold));
    if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
        $frequency = 'weekly';
    }

    set_config('alert_enabled', $enabled, 'report_adeptus_insights');
    set_config('alert_inactivity_days', $inactivitydays, 'report_adeptus_insights');
    set_config('alert_completion_threshold', $completionthreshold, 'report_adeptus_insights');
    set_config('alert_frequency', $frequency, 'report_adeptus_insights');
    set_config('alert_recipient_roles', $recipientroles, 'report_adeptus_insights');

    redirect(
        new moodle_url('/report/adeptus_insights/alert_settings.php'),
        get_string('alert_settings_saved', 'report_adeptus_insights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load current settings.
$enabled = (int) get_config('report_adeptus_insights', 'alert_enabled');
$inactivitydays = (int) get_config('report_adeptus_insights', 'alert_inactivity_days') ?: 14;
$completionthreshold = (int) get_config('report_adeptus_insights', 'alert_completion_threshold') ?: 30;
$frequency = get_config('report_adeptus_insights', 'alert_frequency') ?: 'weekly';
$recipientroles = get_config('report_adeptus_insights', 'alert_recipient_roles') ?: 'manager';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('alert_settings', 'report_adeptus_insights'));
?>

<form method="post" action="<?php echo $PAGE->url; ?>" class="mform">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <fieldset>
        <legend><?php echo get_string('alert_general_settings', 'report_adeptus_insights'); ?></legend>

        <div class="form-group row mb-3">
            <label class="col-sm-3 col-form-label" for="alert_enabled">
                <?php echo get_string('alert_enabled', 'report_adeptus_insights'); ?>
            </label>
            <div class="col-sm-9">
                <select name="alert_enabled" id="alert_enabled" class="form-control custom-select">
                    <option value="1" <?php echo $enabled ? 'selected' : ''; ?>><?php echo get_string('yes'); ?></option>
                    <option value="0" <?php echo !$enabled ? 'selected' : ''; ?>><?php echo get_string('no'); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group row mb-3">
            <label class="col-sm-3 col-form-label" for="alert_inactivity_days">
                <?php echo get_string('alert_inactivity_days', 'report_adeptus_insights'); ?>
            </label>
            <div class="col-sm-9">
                <input type="number" name="alert_inactivity_days" id="alert_inactivity_days"
                       class="form-control" value="<?php echo $inactivitydays; ?>" min="1" max="365">
                <small class="form-text text-muted"><?php echo get_string('alert_inactivity_days_desc', 'report_adeptus_insights'); ?></small>
            </div>
        </div>

        <div class="form-group row mb-3">
            <label class="col-sm-3 col-form-label" for="alert_completion_threshold">
                <?php echo get_string('alert_completion_threshold', 'report_adeptus_insights'); ?>
            </label>
            <div class="col-sm-9">
                <input type="number" name="alert_completion_threshold" id="alert_completion_threshold"
                       class="form-control" value="<?php echo $completionthreshold; ?>" min="1" max="100">
                <small class="form-text text-muted"><?php echo get_string('alert_completion_threshold_desc', 'report_adeptus_insights'); ?></small>
            </div>
        </div>

        <div class="form-group row mb-3">
            <label class="col-sm-3 col-form-label" for="alert_frequency">
                <?php echo get_string('alert_frequency', 'report_adeptus_insights'); ?>
            </label>
            <div class="col-sm-9">
                <select name="alert_frequency" id="alert_frequency" class="form-control custom-select">
                    <option value="daily" <?php echo $frequency === 'daily' ? 'selected' : ''; ?>><?php echo get_string('alert_freq_daily', 'report_adeptus_insights'); ?></option>
                    <option value="weekly" <?php echo $frequency === 'weekly' ? 'selected' : ''; ?>><?php echo get_string('alert_freq_weekly', 'report_adeptus_insights'); ?></option>
                    <option value="monthly" <?php echo $frequency === 'monthly' ? 'selected' : ''; ?>><?php echo get_string('alert_freq_monthly', 'report_adeptus_insights'); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group row mb-3">
            <label class="col-sm-3 col-form-label" for="alert_recipient_roles">
                <?php echo get_string('alert_recipient_roles', 'report_adeptus_insights'); ?>
            </label>
            <div class="col-sm-9">
                <?php
                $allroles = ['manager' => get_string('manager', 'role'), 'editingteacher' => get_string('editingteacher', 'role'), 'teacher' => get_string('teacher', 'role')];
                $selectedroles = array_map('trim', explode(',', $recipientroles));
                foreach ($allroles as $shortname => $displayname) {
                    $checked = in_array($shortname, $selectedroles) ? 'checked' : '';
                    echo "<div class='form-check'>";
                    echo "<input type='checkbox' class='form-check-input' name='roles[{$shortname}]' value='{$shortname}' {$checked} id='role_{$shortname}'>";
                    echo "<label class='form-check-label' for='role_{$shortname}'>{$displayname}</label>";
                    echo "</div>";
                }
                ?>
                <input type="hidden" name="alert_recipient_roles" id="alert_recipient_roles" value="<?php echo s($recipientroles); ?>">
                <small class="form-text text-muted"><?php echo get_string('alert_recipient_roles_desc', 'report_adeptus_insights'); ?></small>
            </div>
        </div>
    </fieldset>

    <div class="form-group row">
        <div class="col-sm-9 offset-sm-3">
            <button type="submit" class="btn btn-primary"><?php echo get_string('savechanges'); ?></button>
        </div>
    </div>
</form>

<script>
// Sync checkboxes to hidden field.
document.querySelector('form').addEventListener('submit', function() {
    var checked = [];
    document.querySelectorAll('input[name^="roles["]:checked').forEach(function(cb) {
        checked.push(cb.value);
    });
    document.getElementById('alert_recipient_roles').value = checked.join(',');
});
</script>

<?php
echo $OUTPUT->footer();
