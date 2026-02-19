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
 * Role helper for determining user view mode in Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for role-based view mode detection.
 */
class role_helper {

    /** @var string Admin/manager full-access mode. */
    const MODE_ADMIN = 'admin';

    /** @var string Teacher scoped mode. */
    const MODE_TEACHER = 'teacher';

    /** @var string Learner self-service mode. */
    const MODE_LEARNER = 'learner';

    /**
     * Determine the view mode for the current user.
     *
     * Priority: admin > teacher > learner.
     *
     * @return string One of the MODE_* constants.
     */
    public static function get_view_mode(): string {
        $syscontext = \context_system::instance();

        // Admin/manager: has site config capability.
        if (has_capability('moodle/site:config', $syscontext)) {
            return self::MODE_ADMIN;
        }

        // Teacher: has teacher dashboard capability.
        if (has_capability('report/adeptus_insights:viewteacherdashboard', $syscontext)) {
            return self::MODE_TEACHER;
        }

        // Learner: has learner dashboard capability.
        if (has_capability('report/adeptus_insights:viewlearnerdashboard', $syscontext)) {
            return self::MODE_LEARNER;
        }

        // Fallback: if they can view the plugin at all, give admin mode (managers).
        return self::MODE_ADMIN;
    }

    /**
     * Get course IDs where the current user has a teacher role.
     *
     * @return array Array of course IDs.
     */
    public static function get_teacher_course_ids(): array {
        global $USER;

        $courses = enrol_get_users_courses($USER->id, true, 'id');
        $teachercourseids = [];

        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            if (has_capability('moodle/course:update', $coursecontext)) {
                $teachercourseids[] = (int) $course->id;
            }
        }

        return $teachercourseids;
    }

    /**
     * Get the teacher reports configuration.
     *
     * Returns report definitions curated for teachers.
     *
     * @param array $courseids Course IDs to scope to.
     * @return array Array of report configs with auto-applied courseids parameter.
     */
    public static function get_teacher_reports(array $courseids): array {
        return [
            [
                'key' => 'completion_by_course',
                'titlekey' => 'teacher_report_completion',
                'desckey' => 'teacher_report_completion_desc',
                'icon' => 'fa-check-circle',
                'params' => ['courseids' => implode(',', $courseids)],
            ],
            [
                'key' => 'quiz_attempts',
                'titlekey' => 'teacher_report_quiz',
                'desckey' => 'teacher_report_quiz_desc',
                'icon' => 'fa-question-circle',
                'params' => ['courseids' => implode(',', $courseids)],
            ],
            [
                'key' => 'forum_activity',
                'titlekey' => 'teacher_report_forum',
                'desckey' => 'teacher_report_forum_desc',
                'icon' => 'fa-comments',
                'params' => ['courseids' => implode(',', $courseids)],
            ],
            [
                'key' => 'grade_overview',
                'titlekey' => 'teacher_report_grades',
                'desckey' => 'teacher_report_grades_desc',
                'icon' => 'fa-graduation-cap',
                'params' => ['courseids' => implode(',', $courseids)],
            ],
        ];
    }

    /**
     * Get the learner reports configuration.
     *
     * @param int $userid The learner's user ID.
     * @return array Array of report configs with auto-applied userid parameter.
     */
    public static function get_learner_reports(int $userid): array {
        return [
            [
                'key' => 'my_completion',
                'titlekey' => 'learner_report_completion',
                'desckey' => 'learner_report_completion_desc',
                'icon' => 'fa-check-circle',
                'params' => ['userid' => $userid],
            ],
            [
                'key' => 'my_grades',
                'titlekey' => 'learner_report_grades',
                'desckey' => 'learner_report_grades_desc',
                'icon' => 'fa-star',
                'params' => ['userid' => $userid],
            ],
            [
                'key' => 'my_activity',
                'titlekey' => 'learner_report_activity',
                'desckey' => 'learner_report_activity_desc',
                'icon' => 'fa-line-chart',
                'params' => ['userid' => $userid],
            ],
        ];
    }
}
