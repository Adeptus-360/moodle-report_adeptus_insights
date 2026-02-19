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

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Learner dashboard data aggregation.
 *
 * Gathers personal progress data for the currently logged-in learner.
 * All queries are strictly scoped to $USER->id for privacy compliance.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_dashboard {

    /** @var int The learner's user ID. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $userid Must be the current $USER->id.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Get all dashboard data as a template context array.
     *
     * @return array Template context.
     */
    public function get_template_data(): array {
        $courses = $this->get_courses_with_completion();
        $timespent = $this->get_time_spent();
        $grades = $this->get_grade_summary();
        $activities = $this->get_activity_completion();

        // Merge time and grade data into courses.
        foreach ($courses as &$course) {
            $cid = $course['id'];
            $course['time_spent'] = $timespent[$cid] ?? '0h 00m';
            $course['time_seconds'] = isset($timespent[$cid . '_raw']) ? $timespent[$cid . '_raw'] : 0;
            $course['grade'] = $grades[$cid]['grade'] ?? '-';
            $course['grade_percentage'] = $grades[$cid]['percentage'] ?? 0;
            $course['has_grade'] = isset($grades[$cid]);
        }
        unset($course);

        // Summary stats.
        $totalcourses = count($courses);
        $completedcourses = count(array_filter($courses, fn($c) => $c['completion_percentage'] >= 100));
        $totaltimeseconds = array_sum(array_column($courses, 'time_seconds'));
        $averagegrade = 0;
        $gradedcount = 0;
        foreach ($grades as $g) {
            if (isset($g['percentage']) && $g['percentage'] > 0) {
                $averagegrade += $g['percentage'];
                $gradedcount++;
            }
        }
        $averagegrade = $gradedcount > 0 ? round($averagegrade / $gradedcount, 1) : 0;

        return [
            'courses' => array_values($courses),
            'has_courses' => $totalcourses > 0,
            'activities' => array_values($activities),
            'has_activities' => !empty($activities),
            'summary' => [
                'total_courses' => $totalcourses,
                'completed_courses' => $completedcourses,
                'in_progress_courses' => $totalcourses - $completedcourses,
                'total_time' => time_calculator::format_duration($totaltimeseconds),
                'average_grade' => $averagegrade,
                'average_grade_display' => $gradedcount > 0 ? $averagegrade . '%' : '-',
            ],
        ];
    }

    /**
     * Get enrolled courses with completion percentage.
     *
     * @return array
     */
    private function get_courses_with_completion(): array {
        $enrolled = enrol_get_users_courses($this->userid, true, 'id, fullname, shortname');
        $result = [];

        foreach ($enrolled as $course) {
            $info = new \completion_info($course);
            $completions = $info->get_completions($this->userid);
            $total = count($completions);
            $complete = 0;
            foreach ($completions as $comp) {
                if ($comp->is_complete()) {
                    $complete++;
                }
            }
            $percentage = $total > 0 ? round(($complete / $total) * 100) : 0;

            $result[] = [
                'id' => (int)$course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'completion_percentage' => $percentage,
                'completed_activities' => $complete,
                'total_activities' => $total,
                'completion_bar_class' => $this->get_bar_class($percentage),
            ];
        }

        return $result;
    }

    /**
     * Get time spent per course using the time_calculator.
     *
     * @return array Keyed by course ID => formatted string, plus {id}_raw => seconds.
     */
    private function get_time_spent(): array {
        global $DB;

        $fromtime = time() - (90 * DAYSECS); // Last 90 days.
        $totime = time();

        $sql = "SELECT userid, courseid, SUM(
                    CASE
                        WHEN time_delta > 0 AND time_delta <= " . time_calculator::SESSION_TIMEOUT . "
                        THEN time_delta
                        ELSE 0
                    END
                ) AS total_seconds
                FROM (
                    SELECT
                        userid,
                        courseid,
                        timecreated,
                        timecreated - LAG(timecreated) OVER (
                            PARTITION BY userid, courseid ORDER BY timecreated
                        ) AS time_delta
                    FROM {logstore_standard_log}
                    WHERE timecreated >= :fromtime
                      AND timecreated < :totime
                      AND userid = :userid
                      AND anonymous = 0
                      AND courseid > 0
                ) deltas
                GROUP BY userid, courseid";

        try {
            $records = $DB->get_records_sql($sql, [
                'fromtime' => $fromtime,
                'totime' => $totime,
                'userid' => $this->userid,
            ]);
        } catch (\dml_exception $e) {
            debugging('Learner dashboard time query failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        $result = [];
        foreach ($records as $rec) {
            $seconds = (int)$rec->total_seconds;
            $result[$rec->courseid] = time_calculator::format_duration($seconds);
            $result[$rec->courseid . '_raw'] = $seconds;
        }

        return $result;
    }

    /**
     * Get grade summary per course.
     *
     * @return array Keyed by course ID.
     */
    private function get_grade_summary(): array {
        $enrolled = enrol_get_users_courses($this->userid, true, 'id');
        $result = [];

        foreach ($enrolled as $course) {
            $gradeitem = \grade_item::fetch_course_item($course->id);
            if (!$gradeitem) {
                continue;
            }
            $grade = $gradeitem->get_grade($this->userid, false);
            if ($grade && !is_null($grade->finalgrade)) {
                $percentage = 0;
                if ($gradeitem->grademax > $gradeitem->grademin) {
                    $percentage = round(
                        (($grade->finalgrade - $gradeitem->grademin) /
                        ($gradeitem->grademax - $gradeitem->grademin)) * 100,
                        1
                    );
                }
                $result[$course->id] = [
                    'grade' => grade_format_gradevalue($grade->finalgrade, $gradeitem),
                    'percentage' => $percentage,
                ];
            }
        }

        return $result;
    }

    /**
     * Get activity completion details across all courses.
     *
     * @return array
     */
    private function get_activity_completion(): array {
        $enrolled = enrol_get_users_courses($this->userid, true, 'id, fullname');
        $activities = [];

        foreach ($enrolled as $course) {
            $info = new \completion_info($course);
            if (!$info->is_enabled()) {
                continue;
            }
            $completions = $info->get_completions($this->userid);
            foreach ($completions as $comp) {
                $cminfo = $comp->get_course_module();
                if (!$cminfo) {
                    continue;
                }
                $activities[] = [
                    'course_name' => format_string($course->fullname),
                    'activity_name' => format_string($cminfo->name),
                    'module_type' => $cminfo->modname,
                    'module_icon' => 'fa-' . $cminfo->modname,
                    'is_complete' => $comp->is_complete(),
                    'status_class' => $comp->is_complete() ? 'success' : 'secondary',
                    'status_text' => $comp->is_complete()
                        ? get_string('completed', 'completion')
                        : get_string('notyetcompleted', 'completion'),
                ];
            }
        }

        return $activities;
    }

    /**
     * Get Bootstrap progress bar colour class.
     *
     * @param int $percentage
     * @return string
     */
    private function get_bar_class(int $percentage): string {
        if ($percentage >= 100) {
            return 'bg-success';
        } else if ($percentage >= 50) {
            return 'bg-info';
        } else if ($percentage >= 25) {
            return 'bg-warning';
        }
        return 'bg-danger';
    }
}
