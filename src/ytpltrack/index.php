<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);           // Course ID
 
// Ensure that the course specified is valid
if (!$course = $DB->get_record('course', array('id'=> $id))) {
    print_error('Course ID is incorrect');
}

// Some items borrowed from survey. Documentation for module api is ???
require_course_login( $course );
$PAGE->set_page_layout('incourse');

// Get list of instances
