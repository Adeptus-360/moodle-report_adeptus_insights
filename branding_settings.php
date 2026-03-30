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
 * White-label branding settings page for Adeptus Insights.
 *
 * Allows Enterprise-tier admins to configure custom logo, colours,
 * footer text, header text, and "Powered by" toggle for reseller branding.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_adeptus_insights\branding_manager;

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:managebranding', $context);

$PAGE->set_url(new moodle_url('/report/adeptus_insights/branding_settings.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('branding_settings', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('branding_settings', 'report_adeptus_insights'));
$PAGE->set_pagelayout('admin');

// Enterprise tier check.
if (!branding_manager::is_whitelabel_available()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('branding_enterprise_only', 'report_adeptus_insights'),
        'warning'
    );
    echo html_writer::tag('p', get_string('branding_upgrade_prompt', 'report_adeptus_insights'));
    echo html_writer::link(
        new moodle_url('/report/adeptus_insights/subscription.php'),
        get_string('branding_upgrade_button', 'report_adeptus_insights'),
        ['class' => 'btn btn-primary']
    );
    echo $OUTPUT->footer();
    die();
}

$manager = new branding_manager();

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $settings = [
        'primary_colour'   => optional_param('primary_colour', '#2980b9', PARAM_TEXT),
        'secondary_colour' => optional_param('secondary_colour', '#7f8c8d', PARAM_TEXT),
        'footer_text'      => optional_param('footer_text', '', PARAM_TEXT),
        'header_text'      => optional_param('header_text', '', PARAM_TEXT),
        'powered_by'       => optional_param('powered_by', 0, PARAM_INT),
    ];

    // Validate hex colours.
    foreach (['primary_colour', 'secondary_colour'] as $colourkey) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $settings[$colourkey])) {
            $settings[$colourkey] = ($colourkey === 'primary_colour') ? '#2980b9' : '#7f8c8d';
        }
    }

    $manager->save_whitelabel_settings($settings);

    // Handle logo upload.
    $draftitemid = file_get_submitted_draft_itemid('whitelabel_logo');
    if ($draftitemid) {
        $manager->save_whitelabel_logo($draftitemid);
    }

    redirect(
        new moodle_url('/report/adeptus_insights/branding_settings.php'),
        get_string('branding_settings_saved', 'report_adeptus_insights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load current settings.
$current = $manager->get_whitelabel_settings();
$logourl = $manager->get_whitelabel_logo_url();

// Prepare file picker for logo.
$draftitemid = file_get_submitted_draft_itemid('whitelabel_logo');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'report_adeptus_insights',
    'whitelabel_logo',
    0,
    ['maxfiles' => 1, 'accepted_types' => ['image']]
);

echo $OUTPUT->header();

$component = 'report_adeptus_insights';
$sesskeyval = sesskey();

echo '<form method="post" action="" enctype="multipart/form-data" class="mform">';
echo '<input type="hidden" name="sesskey" value="' . $sesskeyval . '">';

// Logo fieldset.
echo '<fieldset>';
echo '<legend>' . get_string('branding_logo_heading', $component) . '</legend>';
echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="id_whitelabel_logo">';
echo get_string('branding_logo', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
if ($logourl) {
    echo '<div class="mb-2">';
    echo '<img src="' . $logourl . '" alt="Current logo" style="max-height:60px;">';
    echo '</div>';
}
echo '<input type="hidden" name="whitelabel_logo" value="' . $draftitemid . '">';
$PAGE->requires->js_call_amd('core/form-filetreemgr', 'init', [
    $draftitemid,
    'whitelabel_logo',
    'report_adeptus_insights',
    ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.svg']],
]);
echo '<div id="filemanager-' . $draftitemid . '"></div>';
echo '<small class="form-text text-muted">';
echo get_string('branding_logo_desc', $component);
echo '</small>';
echo '</div></div>';
echo '</fieldset>';

// Colours fieldset.
echo '<fieldset>';
echo '<legend>' . get_string('branding_colours_heading', $component) . '</legend>';

echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="primary_colour">';
echo get_string('branding_primary_colour', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
echo '<input type="color" id="primary_colour" name="primary_colour"';
echo ' value="' . s($current['primary_colour']) . '"';
echo ' class="form-control" style="width:80px;height:40px;">';
echo '<small class="form-text text-muted">';
echo get_string('branding_primary_colour_desc', $component);
echo '</small>';
echo '</div></div>';

echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="secondary_colour">';
echo get_string('branding_secondary_colour', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
echo '<input type="color" id="secondary_colour" name="secondary_colour"';
echo ' value="' . s($current['secondary_colour']) . '"';
echo ' class="form-control" style="width:80px;height:40px;">';
echo '<small class="form-text text-muted">';
echo get_string('branding_secondary_colour_desc', $component);
echo '</small>';
echo '</div></div>';
echo '</fieldset>';

// Text fieldset.
echo '<fieldset>';
echo '<legend>' . get_string('branding_text_heading', $component) . '</legend>';

echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="header_text">';
echo get_string('branding_header_text', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
echo '<input type="text" id="header_text" name="header_text"';
echo ' value="' . s($current['header_text']) . '"';
echo ' class="form-control" maxlength="255">';
echo '<small class="form-text text-muted">';
echo get_string('branding_header_text_desc', $component);
echo '</small>';
echo '</div></div>';

echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="footer_text">';
echo get_string('branding_footer_text', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
echo '<input type="text" id="footer_text" name="footer_text"';
echo ' value="' . s($current['footer_text']) . '"';
echo ' class="form-control" maxlength="255">';
echo '<small class="form-text text-muted">';
echo get_string('branding_footer_text_desc', $component);
echo '</small>';
echo '</div></div>';

echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label">';
echo '<label for="powered_by">';
echo get_string('branding_powered_by', $component);
echo '</label></div>';
echo '<div class="col-md-9">';
echo '<div class="form-check">';
echo '<input type="hidden" name="powered_by" value="0">';
$poweredchecked = $current['powered_by'] ? 'checked' : '';
echo '<input type="checkbox" id="powered_by" name="powered_by" value="1"';
echo ' class="form-check-input" ' . $poweredchecked . '>';
echo '<label class="form-check-label" for="powered_by">';
echo get_string('branding_powered_by_desc', $component);
echo '</label>';
echo '</div>';
echo '</div></div>';
echo '</fieldset>';

// Submit button.
echo '<div class="form-group row fitem">';
echo '<div class="col-md-9 offset-md-3">';
echo '<button type="submit" class="btn btn-primary">';
echo get_string('savechanges');
echo '</button>';
echo '</div></div>';
echo '</form>';

echo $OUTPUT->footer();
