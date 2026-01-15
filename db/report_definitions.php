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
 * Report definitions for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

return  [
  0 =>
   [
    'name' => 'Count number of distinct learners and teachers enrolled per category (including all its sub categories)',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT COUNT(DISTINCT lra.userid) AS learners, COUNT(DISTINCT tra.userid) as teachers
FROM prefix_course AS c # mdl_course_categories AS cats
LEFT JOIN prefix_context AS ctx ON c.id = ctx.instanceid
JOIN prefix_role_assignments  AS lra ON lra.contextid = ctx.id
JOIN prefix_role_assignments  AS tra ON tra.contextid = ctx.id
JOIN prefix_course_categories AS cats ON c.category = cats.id
WHERE c.category = cats.id
AND (
	cats.path LIKE \'%/CATEGORYID/%\' #Replace CATEGORYID with the category id you want to count (eg: 80)
	OR cats.path LIKE \'%/CATEGORYID\'
	)
AND lra.roleid=5
AND tra.roleid=3
Student (user) Count in each Course
Including (optional) filter by: year (if included in course fullname).',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  1 =>
   [
    'name' => '-- Seleciona as colunas que serão exibidas no resultado do relatório',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT
    CONCAT(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\', course.id, \'">\', course.fullname, \'</a>\') AS Course,
    CONCAT(\'<a target="_new" href="%%WWWROOT%%/user/index.php?id=\', course.id, \'">Show users</a>\') AS Users,
    COUNT(DISTINCT user.id) AS Students
FROM prefix_role_assignments AS asg
JOIN prefix_context AS context ON asg.contextid = context.id AND context.contextlevel = 50
JOIN prefix_user AS user ON user.id = asg.userid
JOIN prefix_course AS course ON context.instanceid = course.id
WHERE asg.roleid = 5
GROUP BY course.id
ORDER BY Students DESC

===List of all site users by course enrollment (Moodle 2.x)===',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  2 =>
   [
    'name' => '<syntaxhighlight lang="sql">',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT
user2.firstname AS Firstname,
user2.lastname  AS Lastname,
user2.email     AS Email,
user2.city      AS City,
course.fullname AS Course
,(SELECT shortname FROM prefix_role WHERE id=en.roleid) as Role
,(SELECT name      FROM prefix_role WHERE id=en.roleid) as RoleName

FROM prefix_course          AS course
JOIN prefix_enrol           AS en     ON en.courseid = course.id
JOIN prefix_user_enrolments AS ue     ON ue.enrolid  = en.id
JOIN prefix_user            AS user2  ON ue.userid   = user2.id
Enrolled users,which did not login into the Course, even once (Moodle 2)
Designed forMoodle 2 table structure and uses special plugin filter : %%FILTER_SEARCHTEXT:table.field%%

SELECT
user2.id as ID,
ul.timeaccess,
user2.firstname AS Firstname,
user2.lastname AS Lastname,
user2.email AS Email,
user2.city AS City,
user2.idnumber AS IDNumber,
user2.phone1 AS Phone,
user2.institution AS Institution,

IF (user2.lastaccess = 0,\'never\',
DATE_FORMAT(FROM_UNIXTIME(user2.lastaccess),\'%Y-%m-%d\')) AS dLastAccess

,(SELECT DATE_FORMAT(FROM_UNIXTIME(timeaccess),\'%Y-%m-%d\') FROM prefix_user_lastaccess WHERE userid=user2.id and courseid=c.id) as CourseLastAccess

,(SELECT r.name
FROM  prefix_user_enrolments AS uenrol
JOIN prefix_enrol AS e ON e.id = uenrol.enrolid
JOIN prefix_role AS r ON e.id = r.id
WHERE uenrol.userid=user2.id and e.courseid = c.id) AS RoleName

FROM prefix_user_enrolments as ue
JOIN prefix_enrol as e on e.id = ue.enrolid
JOIN prefix_course as c ON c.id = e.courseid
JOIN prefix_user as user2 ON user2 .id = ue.userid
LEFT JOIN prefix_user_lastaccess as ul on ul.userid = user2.id
WHERE c.id=16 AND ul.timeaccess IS NULL
%%FILTER_SEARCHTEXT:user2.firstname%%',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  3 =>
   [
    'name' => 'Enrolled users who have never accessed a given course (simpler version)',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT username, firstname, lastname, idnumber
FROM prefix_user_enrolments ue
JOIN prefix_enrol           en ON ue.enrolid = en.id
JOIN prefix_user            uu ON uu.id      = ue.userid
WHERE en.courseid = 123456
AND NOT EXISTS (
    SELECT * FROM prefix_user_lastaccess la
    WHERE la.userid = ue.userid
    AND la.courseid = en.courseid
)
(Replace 123456 near the middle with your courseid)',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  4 =>
   [
    'name' => 'Lists "loggedin users" from the last 120 days',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT id,username,FROM_UNIXTIME(`lastlogin`) as days
FROM `prefix_user`
WHERE DATEDIFF( NOW(),FROM_UNIXTIME(`lastlogin`) ) < 120
and user count for that same population:

SELECT COUNT(id) as Users  FROM `prefix_user`
WHERE DATEDIFF( NOW(),FROM_UNIXTIME(`lastlogin`) ) < 120',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  5 =>
   [
    'name' => 'Users loggedin within the last 7 days',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT
    l.* FROM mdl_logstore_standard_log l
WHERE
   l.eventname = \'\\\\core\\\\event\\\\user_loggedin\'
   AND FROM_UNIXTIME(l.timecreated, \'%Y-%m-%d\') >= DATE_SUB(NOW(), INTERVAL 7 DAY)

SELECT l.eventname FROM mdl_logstore_standard_log l
GROUP BY l.eventname',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  6 =>
   [
    'name' => 'Lists the users who have only logged into the site once',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT id, username, firstname, lastname, idnumber
FROM prefix_user
WHERE prefix_user.deleted    = 0
  AND prefix_user.lastlogin  = 0
  AND prefix_user.lastaccess > 0
Log in and Log out history complete for a specific user
Contributed by: Randy Thornton

This query uses the logs to show the complete login and logout history for a particular user. ' .
        'You can use it as the basis for further refining the report. Replace the ## in the WHERE clause below ' .
        'with the id number of the user you wish to see. Warning: as always with queries from the logs, this can ' .
        'take a long time to run and may return more data that the maximum limit allowed.

SELECT
l.id AS "Log_event_id",
l.timecreated AS "Timestamp",
DATE_FORMAT(FROM_UNIXTIME(l.timecreated),\'%Y-%m-%d %H:%i\') AS "Time_UTC",
l.action,
u.username,
l.origin,
l.ip
FROM prefix_logstore_standard_log l
JOIN prefix_user u ON u.id = l.userid
WHERE l.action IN (\'loggedin\',\'loggedout\')
AND l.userid = ##
ORDER BY l.timecreated
Students in all courses of some institute
What is the status (deleted or not) of all Students (roleid = 5) in all courses of some Institute

SELECT c.id, c.fullname, u.firstname, u.lastname, u.deleted
FROM prefix_course           AS c
JOIN prefix_context          AS ctx ON c.id         = ctx.instanceid
JOIN prefix_role_assignments AS ra  ON ra.contextid = ctx.id
JOIN prefix_user             AS u   ON u.id         = ra.userid
WHERE ra.roleid      = 5
  AND ctx.instanceid = c.id
  AND u.institution  = \'please enter school name here\'
Full User info (for deleted users)
Including extra custom profile fields (from prefix_user_info_data)

SELECT *
FROM prefix_user            AS u
JOIN prefix_user_info_data  AS uid ON uid.userid = u.id
JOIN prefix_user_info_field AS uif ON (uid.fieldid = uif.id AND uif.shortname = \'class\')
WHERE `deleted` = "1" and `institution`="your school name" and `department` = "your department" and `data` = "class level and number"
User\'s courses
change "u.id = 2" with a new user id

SELECT u.firstname, u.lastname, c.id, c.fullname
FROM prefix_course           AS c
JOIN prefix_context          AS ctx ON c.id         = ctx.instanceid
JOIN prefix_role_assignments AS ra  ON ra.contextid = ctx.id
JOIN prefix_user             AS u   ON u.id         = ra.userid
WHERE u.id = 2
List Users with extra info (email) in current course
blocks/configurable_reports replaces %%COURSEID%% with course id.

SELECT u.firstname, u.lastname, u.email
FROM prefix_role_assignments AS ra
JOIN prefix_context          AS ctx ON ctx.id = ra.contextid   AND ctx.contextlevel = 50
JOIN prefix_course           AS c   ON c.id   = ctx.instanceid AND c.id             = %%COURSEID%%
JOIN prefix_user             AS u   ON u.id   = ra.userid
List Students with enrollment and completion dates in current course
This is meant to be a "global" report in Configurable Reports containing the following: firstname, lastname, ' .
        'idnumber, institution, department, email, student enrolment date, student completion date Note: for ' .
        'PGSQL, use to_timestamp() instead of FROM_UNIXTIME() Contributed by Elizabeth Dalton, Moodle HQ

SELECT
u.firstname
, u.lastname
, u.idnumber
, u.institution
, u.department
, u.email
, FROM_UNIXTIME(cc.timeenrolled)
, FROM_UNIXTIME(cc.timecompleted)

FROM prefix_role_assignments   AS ra
JOIN prefix_context            AS ctx ON ctx.id    = ra.contextid   AND ctx.contextlevel = 50
JOIN prefix_course             AS c   ON c.id      = ctx.instanceid AND c.id             = %%COURSEID%%
JOIN prefix_user               AS u   ON u.id      = ra.userid
JOIN prefix_course_completions AS cc  ON cc.course = c.id           AND cc.userid        = u.id
List of users who have been enrolled for more than 4 weeks
For Moodle 2.2, by Isuru Madushanka Weerarathna

SELECT uenr.userid As User, IF(enr.courseid=uenr.courseid ,\'Y\',\'N\') As Enrolled,
IF(DATEDIFF(NOW(), FROM_UNIXTIME(uenr.timecreated))>=28,\'Y\',\'N\') As EnrolledMoreThan4Weeks
FROM prefix_enrol As enr, prefix_user_enrolments AS uenr
WHERE enr.id = uenr.enrolid AND enr.status = uenr.status
User\'s accumulative time spent in course
A sum up of the time delta between logstore_standard_log user\'s records, considering the a 2-hour session limit.

Uses: current user\'s id %%USERID%% and current course\'s id %%COURSEID%%

And also using a date filter (which can be ignored)

The extra "User" field is used as a dummy field for the Line chart Series field, in which I use X=id, Series=Type, Y=delta.

SELECT
l.id,
l.timecreated,
DATE_FORMAT(FROM_UNIXTIME(l.timecreated),\'%d-%m-%Y\') AS dTime,
@prevtime := (SELECT max(timecreated) FROM mdl_logstore_standard_log
		WHERE userid = %%USERID%% and id < l.id ORDER BY id ASC LIMIT 1) AS prev_time,
IF (l.timecreated - @prevtime < 7200, @delta := @delta + (l.timecreated-@prevtime),0) AS sumtime,
l.timecreated-@prevtime AS delta,
"User" as type

FROM prefix_logstore_standard_log as l,
(SELECT @delta := 0) AS s_init
# Change UserID
WHERE l.userid = %%USERID%% AND l.courseid = %%COURSEID%%
%%FILTER_STARTTIME:l.timecreated:>%% %%FILTER_ENDTIME:l.timecreated:<%%
List of attendees/students that were marked present across courses
This report will pull all Present students across a specific category. Contributed by: Emma Richardson

SELECT u.firstname AS "First Name", u.lastname AS "Last Name", u.Institution AS "District",c.fullname AS "Training", DATE_FORMAT(FROM_UNIXTIME(att.sessdate),\'%d %M %Y\') AS Date

FROM prefix_attendance_sessions AS att
JOIN prefix_attendance_log      AS attlog ON att.id           = attlog.sessionid
JOIN prefix_attendance_statuses AS attst  ON attlog.statusid  = attst.id
JOIN prefix_attendance          AS a      ON att.attendanceid = a.id
JOIN prefix_course              AS c      ON a.course         = c.id
JOIN prefix_user                AS u      ON attlog.studentid = u.id

WHERE attst.acronym = "P"
AND c.category = INSERT YOUR CATEGORY ID HERE
ORDER BY c.fullname
Courses without Teachers
Actually, shows the number of Teachers in a course.

SELECT concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.fullname,\'</a>\') as Course
,(SELECT Count( ra.userid ) AS Users FROM prefix_role_assignments AS ra
JOIN prefix_context AS ctx ON ra.contextid = ctx.id
WHERE ra.roleid = 3 AND ctx.instanceid = c.id) AS Teachers
FROM prefix_course AS c
ORDER BY Teachers ASC
List of deactivated users in a course
List of deactivated users in a specific course

SELECT username, idnumber,
concat(\'<a target="_new" href="%%WWWROOT%%/user/profile.php?id=\',uu.id,\'">\',uu.id,\'</a>\') as userid_and_link,
firstname, lastname, email, suspended as \'suspended/deactivated: 1\'
FROM prefix_user_enrolments ue
JOIN prefix_enrol           en ON ue.enrolid = en.id
JOIN prefix_user            uu ON uu.id      = ue.userid
WHERE en.courseid = 1234567
  AND suspended   = 1


All users individual timezone settings
Contributed by: Randy Thornton

If you allow users to set their own time zones, this can sometimes lead to confusion about due dates and ' .
        'times for assignments. This shows all active users with their personal time zone settings if any.

SELECT
u.username,
IF(u.timezone=99,"-Site Default-",u.timezone) AS "User Timezone"
FROM prefix_user u
WHERE u.deleted = 0
ORDER BY u.timezone DESC
ROLES and PERMISSIONS REPORTS',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  7 =>
   [
    'name' => 'Count all Active Users by Role in a course category (including all of its sub-categories)',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT COUNT(DISTINCT l.userid) as active
FROM mdl_course            AS c
JOIN mdl_context           AS ctx ON ctx.instanceid = c.id
JOIN mdl_role_assignments  AS ra  ON ra.contextid   = ctx.id
JOIN mdl_user_lastaccess   AS l   ON ra.userid      = l.userid
JOIN mdl_course_categories AS cc  ON c.category     = cc.id
WHERE c.category=cc.id AND (
	cc.path LIKE \'%/80/%\'
	OR cc.path LIKE \'%/80\'
)
AND ra.roleid=3  AND ctx.contextlevel=50  #ra.roleid= TEACHER 3, NON-EDITING TEACHER 4, STUDENT 5
AND  l.timeaccess > (unix_timestamp() - ((60*60*24)*NO_OF_DAYS)) #NO_OF_DAYS change to number',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  8 =>
   [
    'name' => 'Role assignments on categories',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT
concat(\'<a target="_new" href="%%WWWROOT%%/course/category.php?id=\',cc.id,\'">\',cc.id,\'</a>\')   AS id,
concat(\'<a target="_new" href="%%WWWROOT%%/course/category.php?id=\',cc.id,\'">\',cc.name,\'</a>\') AS category,
cc.depth, cc.path, r.name AS role,
concat(\'<a target="_new" href="%%WWWROOT%%/user/view.php?id=\',u.id,\'">\',u.lastname,\'</a>\')     AS name,
u.firstname, u.username, u.email
FROM prefix_course_categories      cc
INNER JOIN prefix_context          cx ON cc.id     = cx.instanceid AND cx.contextlevel = \'40\'
INNER JOIN prefix_role_assignments ra ON cx.id     = ra.contextid
INNER JOIN prefix_role             r  ON ra.roleid = r.id
INNER JOIN prefix_user             u  ON ra.userid = u.id
ORDER BY cc.depth, cc.path, u.lastname, u.firstname, r.name, cc.name
Compare role capability and permissions
Compatibility: MySQL and PostgreSQL

SELECT DISTINCT mrc.capability
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'1\' AND rc.contextid = \'1\') AS Manager
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'2\' AND rc.contextid = \'1\') AS Course_Creator
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'3\' AND rc.contextid = \'1\') AS Teacher
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'4\' AND rc.contextid = \'1\') AS Assistant_Teacher
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'5\' AND rc.contextid = \'1\') AS Student
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'6\' AND rc.contextid = \'1\') AS Guest
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'7\' AND rc.contextid = \'1\') AS Authenticated
,(SELECT \'X\' FROM prefix_role_capabilities AS rc WHERE rc.capability = mrc.capability AND rc.roleid = \'8\' AND rc.contextid = \'1\') AS Auth_front
FROM prefix_role_capabilities AS mrc',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  9 =>
   [
    'name' => 'Special Roles',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT ra.roleid,r.name
,concat(\'<a target="_new" href="%%WWWROOT%%/course/user.php?id=1&user=\',ra.userid,\'">\',u.firstname ,\' \',u.lastname,\'</a>\') AS Username
,concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.fullname,\'</a>\') AS Course
FROM prefix_role_assignments AS ra
JOIN prefix_role             AS r   ON r.id           = ra.roleid
JOIN prefix_user             AS u   ON u.id           = ra.userid
JOIN prefix_context          AS ctx ON ctx.id         = ra.contextid AND ctx.contextlevel = 50
JOIN prefix_course           AS c   ON ctx.instanceid = c.id
WHERE ra.roleid > 6
Note: for the meaning of the number 6 see the section on Role ids below.

Permissions Overrides on Categories
(By: Séverin Terrier )

SELECT rc.id, ct.instanceid, ccat.name, rc.roleid, rc.capability, rc.permission,
DATE_FORMAT( FROM_UNIXTIME( rc.timemodified ) , \'%Y-%m-%d\' ) AS timemodified, rc.modifierid, ct.instanceid, ct.path, ct.depth
FROM `prefix_role_capabilities` AS rc
INNER JOIN `prefix_context` AS ct ON rc.contextid = ct.id
INNER JOIN `prefix_course_categories` AS ccat ON ccat.id = ct.instanceid
AND `contextlevel` =40
All Role Assignments with contexts
Contributed by: Randy Thornton

This lists all the roles that have been assigned in the site, along with the role shortname and the type ' .
        'of context where it is assigned, e.g. System, Course, User, etc. The last column, the context instance ' .
        'id, is the id number of the particular object where the assignment has been made. So, if the context is ' .
        'course, then the context instance id means the course id; if a category, then the category id, and so ' .
        'forth. So you can then use that number to locate the particular place where the role is assigned.

SELECT
u.username,
r.shortname AS "Role",
CASE ctx.contextlevel
  WHEN 10 THEN \'System\'
  WHEN 20 THEN \'Personal\'
  WHEN 30 THEN \'User\'
  WHEN 40 THEN \'Course_Category\'
  WHEN 50 THEN \'Course\'
  WHEN 60 THEN \'Group\'
  WHEN 70 THEN \'Course_Module\'
  WHEN 80 THEN \'Block\'
 ELSE CONCAT(\'Unknown context: \',ctx.contextlevel)
END AS "Context_level",
ctx.instanceid AS "Context instance id"
FROM prefix_role_assignments ra
JOIN prefix_user u ON u.id = ra.userid
JOIN prefix_role r ON r.id = ra.roleid
JOIN prefix_context ctx ON ctx.id = ra.contextid
ORDER BY u.username
COURSE REPORTS
Lists "Totally Opened Courses" (visible, opened to guests, with no password)
(By: Séverin Terrier )

SELECT
concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.id,\'</a>\') AS id,
concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.shortname,\'</a>\') AS \'Course\',
concat(\'<a target="_new" href="%%WWWROOT%%/enrol/instances.php?id=\',c.id,\'">Méthodes inscription</a>\') AS \'Enrollment plugins\',
e.sortorder
FROM prefix_enrol AS e, prefix_course AS c
WHERE e.enrol=\'guest\' AND e.status=0 AND e.password=\'\' AND c.id=e.courseid AND c.visible=1',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  10 =>
   [
    'name' => 'Most Active courses',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT count(l.userid) AS Views
FROM `mdl_logstore_standard_log` l, `mdl_user` u, `mdl_role_assignments` r
WHERE l.courseid=35
AND l.userid = u.id
AND (l.timecreated > UNIX_TIMESTAMP(\'2015-01-01 00:00:00\') AND l.timecreated <= UNIX_TIMESTAMP(\'2015-01-31 23:59:59\'))AND r.contextid= (
	 SELECT id
	 FROM mdl_context
	 WHERE contextlevel=50 AND instanceid=l.courseid
 )
AND r.roleid=5
AND r.userid = u.id
Last access time of users to a course
Contributed by: Randy Thornton

This shows all users and their last access time to courses.

SELECT
u.username,
c.shortname,
#la.timeaccess,
DATE_FORMAT(FROM_UNIXTIME(la.timeaccess), \'%Y-%m-%d %H:%i\') AS "Last access time"
FROM prefix_user_lastaccess la
JOIN prefix_user            u  ON u.id = la.userid
JOIN prefix_course          c  ON c.id = la.courseid
Least active or probably empty courses
Contributed by: Randy Thornton

It is difficult to know sometimes when a course is actually empty or was never really in use. Other than ' .
        'the simple case where the course was created and never touched again, in which case the course ' .
        'timecreated and timemodified will be the same, many courses created as shells for teachers or other ' .
        'users may be used once or a few times and have few or no test users enrollments in them. This query ' .
        'helps you see the range of such courses, showing you how many days if any it was used after initial ' .
        'creation, and how many user are enrolled. It denotes a course never ever modified by "-1" instead of ' .
        '"0" so you can sort those to the top. By default it limits this to courses used within 60 days of ' .
        'creation, and to courses with 3 or less enrollments (for example, teacher and assistant and test ' .
        'student account only.) You can easily adjust these numbers. The query includes a link to the course.

SELECT
c.fullname,
CONCAT(\'<a target="_blank" href="%%WWWROOT%%/course/view.php\',CHAR(63),\'id=\',c.id,\'">\',c.shortname,\'</a>\') AS \'CourseLink\',
DATE_FORMAT(FROM_UNIXTIME(c.timecreated), \'%Y-%m-%d %H:%i\') AS \'Timecreated\',
DATE_FORMAT(FROM_UNIXTIME(c.timemodified), \'%Y-%m-%d %H:%i\') AS \'Timemodified\',
CASE
 WHEN c.timecreated = c.timemodified THEN \'-1\'
 ELSE DATEDIFF(FROM_UNIXTIME(c.timemodified),FROM_UNIXTIME(c.timecreated))
END AS \'DateDifference\',
COUNT(ue.id) AS Enroled
FROM prefix_course AS c
JOIN prefix_enrol AS en ON en.courseid = c.id
LEFT JOIN prefix_user_enrolments AS ue ON ue.enrolid = en.id
WHERE DATEDIFF(FROM_UNIXTIME(c.timemodified),FROM_UNIXTIME(c.timecreated) ) < 60
GROUP BY c.id
HAVING COUNT(ue.id) <= 3
ORDER BY c.fullname
Count unique teachers with courses that use at least X module (Moodle19)
You can remove the outer "SELECT COUNT(*) FROM (...) AS ActiveTeachers" SQL query and get the list of the Teachers and Courses.

SELECT COUNT(*)
FROM (SELECT c.id AS CourseID, c.fullname AS Course, ra.roleid AS RoleID, CONCAT(u.firstname, \' \', u.lastname) AS Teacher
,(SELECT COUNT(*) FROM prefix_course_modules cm WHERE cm.course = c.id) AS Modules
FROM prefix_course            AS c
JOIN prefix_context           AS ctx ON c.id         = ctx.instanceid AND ctx.contextlevel = 50
JOIN prefix_role_assignments  AS ra  ON ra.contextid = ctx.id
JOIN prefix_user              AS u   ON u.id         = ra.userid
JOIN prefix_course_categories AS cc  ON cc.id        = c.category
WHERE  ra.roleid = 3
GROUP BY u.id
HAVING Modules > 5) AS ActiveTeachers',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  11 =>
   [
    'name' => 'Resource count for each Course',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT COUNT(l.id) count, l.course, c.fullname coursename
FROM prefix_resource l INNER JOIN prefix_course c on l.course = c.id
GROUP BY course
ORDER BY count DESC
Common resource types count for each Category
Query but for Moodle2+

SELECT mcc.id AS mccid, CONCAT( LPAD( \'\', mcc.depth, \'.\' ) , mcc.name ) AS Category,
mcc.path,

(SELECT COUNT(*)
FROM prefix_url AS u
JOIN prefix_course AS c ON c.id = u.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS URLs,

(SELECT COUNT(*)
FROM prefix_folder AS f
JOIN prefix_course AS c ON c.id = f.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS FOLDERs,

(SELECT COUNT(*)
FROM prefix_page AS p
JOIN prefix_course AS c ON c.id = p.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS PAGEs,

(SELECT COUNT(*)
FROM prefix_book AS b
JOIN prefix_course AS c ON c.id = b.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS BOOKs,

(SELECT COUNT(*)
FROM prefix_label AS l
JOIN prefix_course AS c ON c.id = l.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS LABELs,

(SELECT COUNT(*)
FROM prefix_tab AS t
JOIN prefix_course AS c ON c.id = t.course
JOIN prefix_course_categories AS cc ON cc.id = c.category
WHERE cc.path LIKE CONCAT( \'%/\', mccid, \'%\' )
) AS TABs

FROM prefix_course_categories AS mcc
ORDER BY mcc.path
Detailed Resource Count by Teacher in each course
Including (optional) filter by: year, semester and course id.

SELECT concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.fullname,\'</a>\') AS CourseID
, c.id
,( SELECT DISTINCT CONCAT(u.firstname,\' \',u.lastname)
  FROM prefix_role_assignments AS ra
  JOIN prefix_user AS u ON ra.userid = u.id
  JOIN prefix_context AS ctx ON ctx.id = ra.contextid
  WHERE ra.roleid = 3 AND ctx.instanceid = c.id AND ctx.contextlevel = 50 LIMIT 1) AS Teacher

, (CASE
WHEN c.fullname LIKE \'%תשעב%\' THEN \'2012\'
WHEN c.fullname LIKE \'%תשעא%\' THEN \'2011\'
END ) as Year
, (CASE
WHEN c.fullname LIKE \'%סמסטר א%\' THEN \'Semester A\'
WHEN c.fullname LIKE \'%סמסטר ב%\' THEN \'Semester B\'
WHEN c.fullname LIKE \'%סמסטר ק%\' THEN \'Semester C\'
END ) as Semester
,COUNT(c.id) AS Total
,(SELECT count(*) FROM prefix_course_modules AS cm WHERE cm.course = c.id AND cm.module= 20) AS TABs
,(SELECT count(*) FROM prefix_course_modules AS cm WHERE cm.course = c.id AND cm.module= 33) AS BOOKs

FROM `prefix_resource` as r
JOIN `prefix_course` AS c on c.id = r.course
#WHERE type= \'file\' and reference NOT LIKE \'http://%\'

#WHERE 1=1
#%%FILTER_YEARS:c.fullname%%
#AND c.fullname LIKE \'%2013%\'

GROUP BY course
ORDER BY COUNT(c.id) DESC
List all Courses in and below a certain category
Use this SQL code to retrieve all courses that exist in or under a set category.

$s should be the id of the category you want to know about...

SELECT prefix_course. * , prefix_course_categories. *
FROM prefix_course, prefix_course_categories
WHERE prefix_course.category = prefix_course_categories.id
AND (
prefix_course_categories.path LIKE \'%/$s/%\'
OR prefix_course_categories.path LIKE \'%/$s\'
)
List all Categories in one level below a certain category
Use this PHP code to retrieve a list of all categories below a certain category.

$s should be the id of the top level category you are interested in.

<?php

require_once(\'./config.php\');

$parentid = $s;

$categories= array();

$categories = get_categories($parentid);

echo \'<ol>\';
foreach ($categories as $category)
        {
        echo \'<li><a href="\'.$CFG->wwwroot.\'/course/category.php?id=\'.$category->id.\'">\'.$category->name.\'</a></li>\';
        }
echo \'</ol>\';

?>
All teachers and courses
Contributed by François Parlant',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  12 =>
   [
    'name' => 'not taking into account the END DATE',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT
c.id, c.shortname,
CONCAT(\'<a href="%%WWWROOT%%/course/view.php?id=\', c.id, \'">\',c.fullname,\'</a>\') AS \'Course link\',
u.id as \'prof id\',
u.username, u.firstname, u.lastname, r.shortname as \'role\'
From prefix_user as u
join prefix_user_enrolments ue on ue.userid=u.id
join prefix_enrol en on ue.enrolid=en.id
join prefix_role_assignments ra on u.id=ra.userid
join prefix_role r on ra.roleid=r.id and (r.shortname =\'editingteacher\' or r.shortname =\'teacher\')
join prefix_context cx on cx.id = ra.contextid and cx.contextlevel = 50
JOIN prefix_course c ON c.id = cx.instanceid AND en.courseid = c.id
JOIN prefix_course_categories cc ON c.category = cc.id
WHERE 1=1
%%FILTER_SUBCATEGORIES:cc.path%%
%%FILTER_STARTTIME:c.startdate:>%%
All courses without an END DATE
Contributed by François Parlant

select c.id, c.fullname, c.shortname,
-- c.startdate, c.enddate,
FROM_UNIXTIME(c.startdate,\'%d/%m/%Y\') as "Date début",
FROM_UNIXTIME(c.enddate,\'%d/%m/%Y\') as "Date fin",
CONCAT(\'<a href="https://pedago-msc.campusonline.me/course/view.php?id=\', c.id,\'">voir cours</a>\') AS \'lien cours\',
CONCAT(\'<a href="https://pedago-msc.campusonline.me/user/index.php?id=\', c.id,\'">voir participants</a>\') AS \'lien participants\'
FROM prefix_course  AS c
INNER JOIN prefix_course_categories cc ON c.category = cc.id
WHERE c.enddate = 0
%%FILTER_CATEGORIES:c.path%%
%%FILTER_SUBCATEGORIES:cc.path%%
%%FILTER_STARTTIME:c.startdate:>%%
All courses with custom Outcomes
Contributed by John Provasnik

SELECT courseid AS \'moodle course id\', shortname as \'scale shortname\', usermodified as \'last modified by user with this moodle id\'
FROM grade_outcomes
WHERE 1 = 1
All Courses which uploaded a Syllabus file
+ under specific Category + show first Teacher in that course + link Course\'s fullname to actual course



SELECT
concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\',c.fullname,\'</a>\') as Course
,c.shortname,r.name
,(SELECT CONCAT(u.firstname,\' \', u.lastname) as Teacher
FROM prefix_role_assignments AS ra
JOIN prefix_context AS ctx ON ra.contextid = ctx.id
JOIN prefix_user as u ON u.id = ra.userid
WHERE ra.roleid = 3 AND ctx.instanceid = c.id LIMIT 1) as Teacher
FROM prefix_resource as r
JOIN prefix_course as c ON r.course = c.id
WHERE ( r.name LIKE \'%סילבוס%\' OR r.name LIKE \'%סילאבוס%\' OR r.name LIKE \'%syllabus%\' OR r.name LIKE \'%תכנית הקורס%\' )
AND c.category IN (10,18,26,13,28)
List all courses WITHOUT Syllabus
Contributed by François Parlant

courses without ressource with name starting by "syllabus" (using upper case or lower case)
display the name as a direct link
shows the name of teacher
category with sub category filter',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  13 =>
   [
    'name' => 'start date and end date filters',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT c.id as \'id cours\',
c.shortname, CONCAT(\'<a href="%%WWWROOT%%/course/view.php?id=\', c.id, \'">\',c.fullname,\'</a>\') AS \'Course link\',
u.id, u.username, u.firstname, u.lastname, r.shortname as \'role\'
FROM prefix_user as u
JOIN prefix_user_enrolments ue on ue.userid=u.id
JOIN prefix_enrol en on ue.enrolid=en.id
JOIN prefix_role_assignments ra on u.id=ra.userid
JOIN prefix_role r on ra.roleid=r.id and (r.shortname =\'editingteacher\' or r.shortname =\'teacher\')
JOIN prefix_context cx on cx.id = ra.contextid and cx.contextlevel = 50
JOIN prefix_course c ON c.id = cx.instanceid AND en.courseid = c.id
JOIN prefix_course_categories cc ON c.category = cc.id
WHERE c.id Not in (
  SELECT distinct(r.course)
  FROM prefix_resource AS r
  WHERE LOWER( r.name) LIKE \'syllabus%\'
  GROUP BY r.course)
%%FILTER_SUBCATEGORIES:cc.path%%
%%FILTER_STARTTIME:c.startdate:>%% %%FILTER_ENDTIME:c.enddate:<%%
Count the number of resources whose name starts by "Syllabus"
Contributed by François Parlant

Our school simply asks teachers to drop a file (resource) on their course page and rename this resource (not the file) starting with "syllabus" (case insensitive)

Select
r.name As \'Resource name\',
cc.name AS \'Category\',
CONCAT(\'<a href="%%WWWROOT%%/pluginfile.php/\', ct.id, \'/mod_resource/content/1/\', f.filename, \'">\',f.filename,\'</a>\') AS \'Clickable filename\',

c.fullname AS \'Course name\',
c.shortname AS \'Course shortname\',

# the date filters are connected to this "last modif" field
# userful to check if the syllabus has been updated this year
DATE_FORMAT(FROM_UNIXTIME(f.timemodified), \'%e %b %Y\') AS \'last modif\',

# tell if the file is visible by the students or hidden
IF(cm.visible=0,"masqué","visible") AS \'Visibility\',

# next line tries to give the real path (local path) if you want to create a zip file using an external script)
# notice that the path is in the column "contenthash" and NOT in the column pathhash
# if the contenthash starts with 9af3... then the file is stored in moodledata/filedir/9a/f3/contenthash
# I try to get the path to moodledata from the value of the geoip variable in the mdl_config table... maybe a bad idea
CONCAT(\'"\',(Select left(value, length(value)-25) from prefix_config where name ="geoip2file"),\'/filedir/\', ' .
        'left(f.contenthash,2), "/",substring(f.contenthash,3,2),\'/\', f.contenthash, \'"\') AS \'link\'

FROM prefix_resource AS r
INNER JOIN prefix_course_modules AS cm ON cm.instance = r.id
INNER JOIN prefix_course AS c ON c.id = r.course
INNER JOIN prefix_context AS ct ON ct.instanceid = cm.id
JOIN prefix_course_categories cc ON c.category = cc.id
INNER JOIN prefix_files AS f ON f.contextid = ct.id AND f.mimetype IS NOT NULL AND f.component = \'mod_resource\'
WHERE LOWER( r.name) LIKE \'syllabus%\'
%%FILTER_STARTTIME:f.timemodified:>%% %%FILTER_ENDTIME:f.timemodified:<%%
%%FILTER_SUBCATEGORIES:cc.path%%
List files which have been tagged "Syllabus"
Contributed by François Parlant

Select
t.rawname AS \'rawtag\',
c.shortname AS \'Cours shortname\',
c.fullname AS \'Course name\',
r.name As \'Resource name\',
CONCAT(\'<a href="%%WWWROOT%%/pluginfile.php/\', ti.contextid, \'/mod_resource/content/1/\', f.filename, \'">cliquez ici</a>\') AS \'link\',
ti.contextid AS \'Instance for link\',
f.id AS \'file id\'
FROM prefix_tag_instance AS ti
INNER JOIN prefix_tag AS t ON ti.tagid = t.id
INNER JOIN prefix_course_modules AS cm ON ti.itemid = cm.id
INNER JOIN prefix_course AS c ON cm.course = c.id
INNER JOIN prefix_resource AS r ON r.id = cm.instance
INNER JOIN prefix_files AS f ON f.contextid = ti.contextid AND f.mimetype IS NOT NULL
WHERE t.rawname = \'Syllabus\'
List of courses WITHOUT a resource with a name starting by "syllabus"
Contributed by François Parlant',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  14 =>
   [
    'name' => 'without teachers',
    'category' => '',
    'description' => '',
    'sqlquery' => 'select c.id, c.shortname,
CONCAT(\'<a href="%%WWWROOT%%/course/view.php?id=\', c.id, \'">\',c.fullname,\'</a>\') AS \'Course link\'
FROM prefix_course AS c
INNER JOIN prefix_course_categories cc ON c.category = cc.id
WHERE r.course NOT IN (
  Select r.course
  from prefix_resource AS r
  WHERE LOWER( r.name) LIKE \'syllabus%\'
  GROUP BY r.course)
%%FILTER_SUBCATEGORIES:cc.path%%
%%FILTER_STARTTIME:c.startdate:>%% %%FILTER_ENDTIME:c.enddate:<%%
List of courses have MULTIPLE resource with a name like "Syllabus%"
Contributed by François Parlant

select
r.course,
c.shortname,
CONCAT(\'<a href="%%WWWROOT%%/course/view.php?id=\', r.id, \'">\',c.fullname,\'</a>\') AS \'Course link\'
FROM prefix_resource AS r
INNER JOIN prefix_course AS c ON c.id = r.course
JOIN prefix_course_categories cc ON c.category = cc.id
WHERE LOWER( r.name) LIKE \'syllabus%\'
GROUP BY r.course HAVING count(r.course)>1
%%FILTER_SUBCATEGORIES:cc.path%%
All users enrolled in a course without a role
Identifies All users that are enrolled in a course but are not assigned a role.

SELECT
user.firstname AS Firstname,
user.lastname AS Lastname,
user.idnumber Employee_ID,
course.fullname AS Course

FROM prefix_course AS course
JOIN prefix_enrol AS en ON en.courseid = course.id
JOIN prefix_user_enrolments AS ue ON ue.enrolid = en.id
JOIN prefix_user as user ON user.id = ue.userid',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  15 =>
   [
    'name' => 'WHERE user.id NOT IN (',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT u.id
FROM prefix_course AS c
JOIN prefix_context AS ctx ON c.id = ctx.instanceid
JOIN prefix_role_assignments AS ra ON ra.contextid = ctx.id
JOIN prefix_role AS r ON r.id = ra.roleid
JOIN prefix_user AS u ON u.id = ra.userid
WHERE c.id=course.id
)
ORDER BY Course, Lastname, Firstname
List course resources accumulative file size and count
This is the main (first) report, which has a link (alias) to a second report (the following on this page) which list each file in the course.

SELECT c.id "CourseID", context.id "ContextID"
,CONCAT(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\', c.id, \'">\', c.fullname ,\'</a>\') AS "Course Name"
, COUNT(*) "Course Files" , ROUND( SUM( f.filesize ) /1048576 ) AS file_size_MB
,CONCAT(\'<a target="_new" href="%%WWWROOT%%/blocks/configurable_reports/viewreport.php?alias=coursefiles&courseid=1&filter_courses=\', c.id, \'">List files</a>\') AS "List Files"

FROM mdl_files AS f
JOIN mdl_context AS context ON context.id = f.contextid
JOIN mdl_course AS c ON c.id = (
  SELECT instanceid
  FROM mdl_context
  WHERE id = SUBSTRING_INDEX( SUBSTRING_INDEX( context.path, \'/\' , -2 ) , \'/\', 1 ) )
WHERE filesize >0
GROUP BY c.id
With this report, you will have to define "alias" report property to "coursefiles" for it to be able to ' .
        'be called from the above report. And also setup (add) a FILTER_COURSES filter.

SELECT
id ,CONCAT(\'<a target="_new" href="%%WWWROOT%%/pluginfile.php/\', contextid, \'/\', component, \'/\', ' .
        'filearea, \'/\', itemid, \'/\', filename, \'">\', filename,\'</a>\') AS "File"
,filesize, mimetype ,author, license, timecreated, component, filearea, filepath

FROM mdl_files AS f
WHERE filesize >0
            AND f.contextid
            IN (   SELECT id
                     FROM mdl_context
                    WHERE path
                     LIKE (   SELECT CONCAT(\'%/\',id,\'/%\')
                                  AS contextquery
                                FROM mdl_context
                               WHERE 1=1
			        %%FILTER_COURSES:instanceid%%
                                 AND contextlevel = 50
                           )
                )
Which courses has redundant topics
This report list several "active topics" calculations, per course. which should give an administrator ' .
        'some indications for which topics/sections/weeks are filled with resources and activities and which ' .
        'ones are empty and not used (usually, at the end of the course).

The following, second SQL query, could be used to "trim" down those redundant course topics/sections/weeks ' .
        'by updating the course format\'s numsection (Number of sections) setting. (It\'s a per course format setting!)

SELECT id, format,
concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',c.id,\'">\', c.fullname,\'</a>\') AS Course

,(SELECT value  FROM  `mdl_course_format_options` WHERE  `courseid` = c.id AND `format` = c.format AND `name` = \'numsections\' ) AS "numsections"
,(SELECT COUNT(*) FROM  `mdl_course_sections` WHERE  `course` = c.id AND `sequence` !=  \'\' ) AS "Non empty sections count"
,(SELECT COUNT(*) FROM  `mdl_course_sections` WHERE  `course` = c.id ) AS "Total section count"
,(SELECT COUNT(*) FROM  `mdl_course_sections` WHERE  `course` = c.id AND sequence IS NOT NULL) AS "Non NULL sections count"
,(SELECT COUNT(*) FROM  `mdl_course_sections` WHERE  `course` = c.id AND name != \'\') AS "Non empty section Name count"
 ,(SELECT COUNT(*) FROM mdl_course_modules cm WHERE cm.course = c.id) "Modules count"

FROM mdl_course AS c
The following SQL REPLACE query is used for "fixing" (updating) the "numsections" of a specific course ' .
        'format "onetopics" (you can always change it, or discard it to use this SQL REPLACE on all course formats)',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  16 =>
   [
    'name' => 'REPLACE INTO `mdl_course_format_options` (`id`, `courseid`, `format`, `sectionid`, `name`, `value`)',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT NULL, c.id, \'onetopic\', \'0\', \'numsections\', (SELECT COUNT(*) FROM `mdl_course_sections` WHERE `course` = c.id AND name != \'\')
FROM `mdl_course` c where format = \'onetopic\'
Hidden Courses with Students Enrolled
Contributed by Eric Strom

This query identifies courses with student enrollment that are currently hidden from students. Includes ' .
        'the defined course start date, count of students and instructors, and a clickable email link of ' .
        'instructor (first found record if more than one).

SELECT c.visible AS Visible,
DATE(FROM_UNIXTIME(c.startdate)) AS StartDate,
concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php\',CHAR(63),\'id=\',
c.id,\'">\',c.idnumber,\'</a>\') AS Course_ID,

(SELECT COUNT( ra.userid ) FROM prefix_role_assignments AS ra
JOIN prefix_context AS ctx ON ra.contextid = ctx.id
WHERE ra.roleid = 5 AND ctx.instanceid = c.id) AS Students,

(SELECT COUNT( ra.userid ) FROM prefix_role_assignments AS ra
JOIN prefix_context AS ctx ON ra.contextid = ctx.id
WHERE ra.roleid = 3 AND ctx.instanceid = c.id) AS Instructors,

(SELECT DISTINCT concat(\'<a href="mailto:\',u.email,\'">\',u.email,\'</a>\')
  FROM prefix_role_assignments AS ra
  JOIN prefix_user AS u ON ra.userid = u.id
  JOIN prefix_context AS ctx ON ctx.id = ra.contextid
  WHERE ra.roleid = 3 AND ctx.instanceid = c.id AND ctx.contextlevel = 50 LIMIT 1) AS \'Instructor_Email\',

now() AS Report_Timestamp

FROM prefix_course AS c
WHERE c.visible = 0 AND (SELECT COUNT( ra.userid ) FROM prefix_role_assignments AS ra ' .
        'JOIN prefix_context AS ctx ON ra.contextid = ctx.id WHERE ra.roleid = 5 AND ctx.instanceid = c.id) > 0
ORDER BY StartDate, Instructor_Email, Course_ID',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  17 =>
   [
    'name' => 'Course formats used on my system',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT COUNT(*) \'Count\', c.format \'Format\'
FROM prefix_course AS c
GROUP BY c.format',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
  18 =>
   [
    'name' => 'Course catalogue with future courses',
    'category' => '',
    'description' => '',
    'sqlquery' => 'SELECT CONCAT(\'<a href="%%WWWROOT%%/course/info.php?id=\',course.id,\'">\',course.fullname,\'</a>\') AS Kurs, FROM_UNIXTIME(startdate, \'%Y/%m/%d\') AS Beginn
FROM prefix_course AS course
WHERE DATEDIFF(NOW(),FROM_UNIXTIME(startdate)) < 0
ORDER BY startdate


Enrolment methods used in all courses
List of all the enrolment methods attached to all courses with their type, enabled status, sort order, ' .
        'and custom name if any. Includes a link directly the each course\'s enrolment methods settings page. ' .
        'Known to work in 3.11 (should work in most earlier version.) This report could serve as the basis and ' .
        'be easily expanded to show the various settings details for the methods if you want.

Contributed by: Randy Thornton

SELECT
CONCAT(\'<a target="_new" href="%%WWWROOT%%/enrol/instances.php?id=\',c.id,\'">\',c.shortname,\'</a>\') AS "Course",
e.enrol AS "Method",
CASE e.status
   WHEN 0 THEN \'Enabled\'
   WHEN 1 THEN \'-\'
   ELSE e.status
END AS "Status",
IF(e.name IS NOT NULL,e.name,\'-\') AS "Custom name"

FROM prefix_enrol e
JOIN prefix_course c ON c.id = e.courseid
ORDER BY c.shortname,e.sortorder
List of all courses with a specific topic
List of all courses with link, shortname and name which contains a specific named topic. E.g: if there ' .
        'are topics in a course which are named "Introduction to programming" you can find these courses.

SELECT concat(\'<a target="_new" href="%%WWWROOT%%/course/view.php?id=\',cs.course,\'">\',cs.course,\'</a>\') AS courseid,
c.fullname as coursename, c.shortname as shortname
FROM prefix_course_sections AS cs, prefix_course AS c
WHERE c.id = cs.course
AND cs.name LIKE "Introduction to programming"',
    'parameters' =>
     [
    ],
    'charttype' => null,
    'isactive' => 1,
  ],
];
