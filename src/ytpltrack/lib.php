<?php
/**
 * Library of interface functions and constants for module ytpltrack
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the ytpltrack specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_ytpltrack
 * @copyright  2017 Henry Groover <henry.groover@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

 /**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function ytpltrack_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
		case FEATURE_COMPLETION_TRACKS_VIEWS:
		//case FEATURE_GROUPS:
		//case FEATURE_GROUPINGS:
		//case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}


/**
 * Saves a new instance of the ytpltrack into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $ytpltrack Submitted data from the form in mod_form.php
 * @param mod_ytpltrack_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted ytpltrack record
 */
function ytpltrack_add_instance(stdClass $ytpltrack, mod_ytpltrack_mod_form $mform = null) {
    global $DB, $USER;

    $ytpltrack->timecreated = time();
	$ytpltrack->creator = $USER->id;

    $ytpltrack->id = $DB->insert_record('ytpltrack', $ytpltrack);

    ytpltrack_grade_item_update($ytpltrack);

    return $ytpltrack->id;
}

/**
 * Updates an instance of the ytpltrack in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $ytpltrack An object from the form in mod_form.php
 * @param mod_ytpltrack_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function ytpltrack_update_instance(stdClass $ytpltrack, mod_ytpltrack_mod_form $mform = null) {
    global $DB;

    $ytpltrack->timemodified = time();
    $ytpltrack->id = $ytpltrack->instance;

    $result = $DB->update_record('ytpltrack', $ytpltrack);

    ytpltrack_grade_item_update($ytpltrack);

    return $result;
}

/**
 * Removes an instance of the ytpltrack from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function ytpltrack_delete_instance($id) {
    global $DB;

    if (! $ytpltrack = $DB->get_record('ytpltrack', array('id' => $id))) {
        return false;
    }

    // Delete dependent records from ytpltrack_views, ytpltrack_viewdetails
	$yviews = $DB->get_records_sql( "SELECT id FROM mdl_ytpltrack_views WHERE instance = :instance", array( "instance" => $id ) );
	foreach ($yviews as $key => $value)
	{
		$DB->delete_records_select( 'ytpltrack_viewdetails', "viewid = ?", array( $value->id ) );
	}

	$DB->delete_records_select( 'ytpltrack_views', 'instance = ?', array( $id ) );
	
    $DB->delete_records('ytpltrack', array('id' => $id));

    ytpltrack_grade_item_delete($ytpltrack);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * (Shown in user profile, reports, outline report, only for sitewide
 * instances not associated with a specific course)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $ytpltrack The ytpltrack instance record
 * @return stdClass|null
 */
function ytpltrack_user_outline($course, $user, $mod, $ytpltrack) {
// Other tables: mdl_course_modules.id is the id passed to view.php, mdl_course_modules.instance => mdl_ytpltrack.id
// $course is a record from mdl_course: id, category, fullname, shortname, summary, summaryformat
// $user is a record from mdl_user: id, username, firstname, lastname, email, city, country, lang, timezone, etc.
// $mod is a record from mdl_course_modules: id, course, module, instance, section, etc.
// SELECT y.course, y.playlist, y.name, v.id, v.instance, v.userid, v.lastupdate, v.countfull, v.countraw, v.totalcapped, v.totalduration FROM mdl_ytpltrack y LEFT JOIN `mdl_ytpltrack_views` v ON (y.id=v.instance) WHERE y.course=152 ORDER BY v.lastupdate DESC
    $return = new stdClass();
	global $DB;
	$ytpltrackview = $DB->get_record('ytpltrack_views', array('instance' => $mod->instance, 'userid' => $user->id));
    $return->time = $ytpltrackview->lastupdate;
    $return->info = $user->firstname . ' ' . $user->lastname;
	if ($ytpltrackview->lastupdate)
	{
		$return->info .= ' last viewed ';
		$return->info .= date("d-M-Y H:i:s", $ytpltrackview->lastupdate);
		if ($ytpltrackview->countfull == $ytpltrackview->countraw)
		{
			$return->info .= sprintf(" %d videos, %.1f%% complete", $ytpltrackview->countfull, 100.0 * $ytpltrackview->totalcapped / $ytpltrackview->totalduration );
		}
		else
		{
			$return->info .= sprintf( " %d/%d videos watched so far (%.1f minutes)", $ytpltrackview->countfull, $ytpltrackview->countraw, $ytpltrackview->totalviewed / 60.0 );
		}
	}
	else
	{
		$return->info .=  ' has not started viewing';
	}
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 * (Shown in user profile, reports, complete report)
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $ytpltrack the module instance record
 */
function ytpltrack_user_complete($course, $user, $mod, $ytpltrack) {
	global $DB;
	$ytv = $DB->get_record('ytpltrack_views', array('instance' => $mod->instance, 'userid' => $user->id));
	if ($ytv->lastupdate)
	{
		printf( "<p>%s %s last viewed %s</p>\n", $user->firstname, $user->lastname, date("d-M-Y H:i:s", $ytv->lastupdate) );
	}
	else
	{
		printf( "<p>%s %s has not viewed cm %d</p>\n", $user->firstname, $user->lastname, $mod->id );
	}
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of ytpltrack.
 *
 * @param int $instanceid
 * @return stdClass list of participants
 */
function ytpltrack_get_participants($instanceid) {
    global $DB;

    $sql = "SELECT DISTINCT u.id, u.id
              FROM {user} u, {ytpltrack_views} v
             WHERE v.instance = :instanceid
               AND u.id = v.userid";
    return  $DB->get_records_sql($sql, array('instanceid' => $instanceid));
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in ytpltrack activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function ytpltrack_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link ytpltrack_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
/**
 * This function  returns activity for all readers in a course since a given time.
 * It is initiated from the "Full report of recent activity" link in the "Recent Activity" block.
 * Using the "Advanced Search" page (cousre/recent.php?id=99&advancedfilter=1),
 * results may be restricted to a particular course module, user or group
 *
 * This function is called from: {@link course/recent.php}
 *
 * @param array(object) $activities sequentially indexed array of course module objects
 * @param integer $index length of the $activities array
 * @param integer $timestart start date, as a UNIX date
 * @param integer $courseid id in the "course" table
 * @param integer $coursemoduleid id in the "course_modules" table
 * @param integer $userid id in the "users" table (default = 0)
 * @param integer $groupid id in the "groups" table (default = 0)
 * @return void adds items into $activities and increments $index
 *     for each reader attempt, an $activity object is appended
 *     to the $activities array and the $index is incremented
 *     $activity->type : module type (always "reader")
 *     $activity->defaultindex : index of this object in the $activities array
 *     $activity->instance : id in the "reader" table;
 *     $activity->name : name of this reader
 *     $activity->section : section number in which this reader appears in the course
 *     $activity->content : array(object) containing information about reader attempts to be printed by {@link print_recent_mod_activity()}
 *         $activity->content->attemptid : id in the "reader_quiz_attempts" table
 *         $activity->content->attempt : the number of this attempt at this quiz by this user
 *         $activity->content->score : the score for this attempt
 *         $activity->content->timestart : the server time at which this attempt started
 *         $activity->content->timefinish : the server time at which this attempt finished
 *     $activity->user : object containing user information
 *         $activity->user->userid : id in the "user" table
 *         $activity->user->fullname : the full name of the user (see {@link lib/moodlelib.php}::{@link fullname()})
 *         $activity->user->picture : $record->picture;
 *     $activity->timestamp : the time that the content was recorded in the database
 */

function ytpltrack_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $coursemoduleid, $userid=0, $groupid=0) {
    global $CFG, $DB, $USER;

	/**
    // Don't allow students to see each other's scores
    $coursecontext = ytpltrack_get_context(CONTEXT_COURSE, $courseid);
    if (! has_capability('mod/ytpltrack:viewbooks', $coursecontext)) {
        return; // can't view recent activity
    }
    if (! has_capability('mod/ytpltrack:viewreports', $coursecontext)) {
        $userid = $USER->id; // force this user only (e.g. student)
    }
	**/
	$modinfo = get_fast_modinfo($courseid);
	$course  = $modinfo->get_course();
	$cms = $modinfo->get_cms();

	// Build up a list of users and instances, and remove extraneous cms records
    $readers = array(); // ytpltrack.id => cmid
    $users   = array(); // cmid => array(userids)

    foreach ($cms as $cmid => $cm) {
        if ($cm->modname=='ytpltrack' && ($coursemoduleid==0 || $coursemoduleid==$cmid)) {
            // save mapping from ytpltrack.id => coursemoduleid
            $readers[$cm->instance] = $cmid;
            // initialize array of users who have recently attempted this playlist viewer instance
            $users[$cmid] = array();
        } else {
            // we are not interested in this mod
            unset($cms[$cmid]);
        }
    }

    if (class_exists('user_picture')) {
        // Moodle >= 2.6
        $userfields = user_picture::fields('u', null, 'useruserid');
    } else {
        // Moodle <= 2.5
        $userfields ='u.firstname,u.lastname,u.picture,u.imagealt,u.email';
    }

    $select = 'yv.*, ' . $userfields;
    $from   = '{ytpltrack_views} yv '.
              'JOIN {user} u ON yv.userid = u.id ';
    list($where, $params) = $DB->get_in_or_equal(array_keys($readers));
    $where  = 'yv.instance ' . $where;
    $order  = 'yv.userid, yv.instance';

    if ($groupid) {
        // restrict search to a users from a particular group
        $from   .= ', {groups_members} gm';
        $where  .= ' AND yv.userid = gm.userid AND gm.id = ?';
        $params[] = $groupid;
    }
    if ($userid) {
        // restrict search to a single user
        $where .= ' AND yv.userid = ?';
        $params[] = $userid;
    }
    if ($timestart) {
        $where .= ' AND yv.lastupdate > ?';
        $params[] = $timestart;
    }

    if (! $attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
        return; // no recent viewing activity
    }

	// Clean up userfields into an array of actual {user} fields (no wildcards)
    $userfields = str_replace('u.', '', $userfields);
    $userfields = explode(',', $userfields);
    $userfields = preg_grep('/^[a-z]+$/', $userfields);

    foreach (array_keys($attempts) as $attemptid) {
        $attempt = &$attempts[$attemptid];

        if (! array_key_exists($attempt->instance, $readers)) {
            continue; // invalid instance - shouldn't happen !!
        }

        $cmid = $readers[$attempt->instance];
        $userid = $attempt->userid;
        if (! array_key_exists($userid, $users[$cmid])) {
            $users[$cmid][$userid] = (object)array(
                'id' => $userid,
                'userid' => $userid,
                'views' => array()
            );
            foreach ($userfields as $userfield) {
                $users[$cmid][$userid]->$userfield = $attempt->$userfield;
            }
        }
        // add this view by this user at this course module
        $users[$cmid][$userid]->views[$attempt->instance] = &$attempt;
    }

	// Finally, append to the array
    foreach ($cms as $cmid => $cm) {
        if (empty($users[$cmid])) {
            continue;
        }
        // add an activity object for each user's view of this playlist instance
        foreach ($users[$cmid] as $userid => $user) {

            // get index of last (=most recent) attempt
            $max_unumber = max(array_keys($user->views));

            $options = array('context' => $cm->context);
            if (method_exists($cm, 'get_formatted_name')) {
                $name = $cm->get_formatted_name($options);
            } else {
                $name = format_string($cm->name, true,  $options);
            }

            $activities[$index++] = (object)array(
                'type' => 'ytpltrack',
                'cmid' => $cmid,
                'name' => $name,
                'user' => $user,
                'views'  => $user->views,
                'timestamp' => $user->views[$max_unumber]->lastupdate
            );
        }
    } 
}

/**
 * Prints single activity item prepared by {@link ytpltrack_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function ytpltrack_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;
	
    static $dateformat = null;
    if (is_null($dateformat)) {
        $dateformat = get_string('strftimerecentfull');
    }

    $table = new html_table();
    $table->cellpadding = 3;
    $table->cellspacing = 0;

    if ($detail) {
        $row = new html_table_row();

        $cell = new html_table_cell('&nbsp;', array('width'=>15));
        $row->cells[] = $cell;

        // activity icon and link to activity
        $src = $OUTPUT->pix_url('icon', $activity->type);
        $img = html_writer::empty_tag('img', array('src'=>$src, 'class'=>'icon', 'alt'=>$activity->name));

        // link to activity
        $href = new moodle_url('/mod/ytpltrack/view.php', array('id' => $activity->cmid));
        $link = html_writer::link($href, $activity->name);

        $cell = new html_table_cell("$img $link");
        $cell->colspan = 9;
        $row->cells[] = $cell;

        $table->data[] = $row;
    }

    $row = new html_table_row();

    // set rowspan to (number of views) + 1
    $rowspan = count($activity->views) + 1;

    $cell = new html_table_cell('&nbsp;', array('width'=>15));
    $cell->rowspan = $rowspan;
    $row->cells[] = $cell;

    $picture = $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    $cell = new html_table_cell($picture, array('width'=>35, 'valign'=>'top', 'class'=>'forumpostpicture'));
    $cell->rowspan = $rowspan;
    $row->cells[] = $cell;

    $href = new moodle_url('/user/view.php', array('id'=>$activity->user->userid, 'course'=>$courseid));
    $cell = new html_table_cell(html_writer::link($href, fullname($activity->user)));
    $cell->colspan = 8; // Match number of args passed to table row constructor below
    $row->cells[] = $cell;

    $table->data[] = $row;

    foreach ($activity->views as $attempt) {
        $duration = '&nbsp;';

		// FIXME need to add reports!
        $href = new moodle_url('/mod/ytpltrack/view.php', array('n'=>$attempt->instance, 'user' => $attempt->userid));
        $link = html_writer::link($href, userdate($attempt->lastupdate, $dateformat));

		/***
        switch ($attempt->passed) {
            case 'true':
                $passed = get_string('passed', 'mod_reader');
                $class = 'passed';
                break;
            case 'credit':
                $passed = get_string('credit', 'mod_reader');
                $class = 'passed';
                break;
            case 'credit':
            default:
                $passed = get_string('failed', 'mod_reader');
                $class = 'failed';
        }
		***/
		$passed = "n/a";
        $passed = html_writer::tag('span', $passed, array('class' => $class));

        //$readinglevel = get_string('readinglevelshort', 'mod_reader', $attempt->difficulty);
		$readinglevel = "n/a";

		if ($attempt->countfull >= $attempt->countraw)
		{
			$percentage = sprintf("%.1f%%", $attempt->totalcapped * 100.0 / $attempt->totalduration );
			$watched = $attempt->countfull;
		}
		else
		{
			$percentage = "n/a";
			$watched = sprintf( "%d/%d", $attempt->countfull, $attempt->countraw );
		}
        $table->data[] = new html_table_row(array(
            new html_table_cell($watched . " watched"),
            new html_table_cell($percentage),
            new html_table_cell($attempt->totalviewed),
            new html_table_cell($attempt->totalduration),
            new html_table_cell($link)
        ));
    }
    echo html_writer::table($table);
}

/*
 * For the given list of courses, this function creates an HTML report
 * of which Reader activities have been completed and which have not

 * This function is called from: {@link course/lib.php}
 *
 * @param array(object) $courses records from the "course" table
 * @param array(array(string)) $htmlarray array, indexed by courseid, of arrays, indexed by module name (e,g, "reader), of HTML strings
 *     each HTML string shows a list of the following information about each open Reader in the course
 *         Reader name and link to the activity  + open/close dates, if any
 *             for teachers:
 *                 how many students have attempted/completed the Reader
 *             for students:
 *                 which Readers have been completed
 *                 which Readers have not been completed yet
 *                 the time remaining for incomplete Readers
 * @return no return value is required, but $htmlarray may be updated
 */
function ytpltrack_print_overview($courses, &$htmlarray) {
    global $CFG, $DB, $USER;
    if (! isset($courses) || ! is_array($courses) || ! count($courses)) {
        return; // no courses
    }

    if (! $readers = get_all_instances_in_courses('ytpltrack', $courses)) {
        return; // no ytpltrack instances
    }

    $str = null;
    $now = time();
    foreach ($readers as $reader) {
		$html = "<div>" . print_r( $reader, TRUE ) . "</div>";
        if (empty($htmlarray[$reader->course]['ytpltrack'])) {
            $htmlarray[$reader->course]['ytpltrack'] = $html;
        } else {
            $htmlarray[$reader->course]['ytpltrack'] .= $html;
        }
	}
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function ytpltrack_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function ytpltrack_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of ytpltrack?
 *
 * This function returns if a scale is being used by one ytpltrack
 * if it has support for grading and scales.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given ytpltrack instance
 */
function ytpltrack_scale_used($newmoduleid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('ytpltrack', array('id' => $newmoduleid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of ytpltrack.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any ytpltrack instance
 */
function ytpltrack_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('ytpltrack', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given ytpltrack instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $ytpltrack instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function ytpltrack_grade_item_update(stdClass $ytpltrack, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($ytpltrack->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($ytpltrack->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $ytpltrack->grade;
        $item['grademin']  = 0;
    } else if ($ytpltrack->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$ytpltrack->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/ytpltrack', $ytpltrack->course, 'mod', 'ytpltrack',
            $ytpltrack->id, 0, null, $item);
}

/**
 * Delete grade item for given ytpltrack instance
 *
 * @param stdClass $ytpltrack instance object
 * @return grade_item
 */
function ytpltrack_grade_item_delete($ytpltrack) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/ytpltrack', $ytpltrack->course, 'mod', 'ytpltrack',
            $ytpltrack->id, 0, null, array('deleted' => 1));
}

/**
 * Update ytpltrack grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $ytpltrack instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function ytpltrack_update_grades(stdClass $ytpltrack, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/ytpltrack', $ytpltrack->course, 'mod', 'ytpltrack', $ytpltrack->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function ytpltrack_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for ytpltrack file areas
 *
 * @package mod_ytpltrack
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function ytpltrack_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the ytpltrack file areas
 *
 * @package mod_ytpltrack
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the ytpltrack's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function ytpltrack_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding ytpltrack nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the ytpltrack module instance
 * @param stdClass $course current course record
 * @param stdClass $module current ytpltrack instance record
 * @param cm_info $cm course module information
 */
function ytpltrack_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the ytpltrack settings
 *
 * This function is called when the context for the page is a ytpltrack module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $newmodulenode ytpltrack administration node
 */
function ytpltrack_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $newmodulenode=null) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * ytpltrack_get_grades
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $ytpltrack
 * @param xxx $userid (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function. See mod/reader for example.
 */
function ytpltrack_get_grades($ytpltrack, $userid=0) {
    global $DB;
	return array();
}

