<?php
/**
 * data.php - data handler for ytpltrack playlist viewer updates
 * Copyright (C) 2017 Henry Groover <henry.groover@gmail.com>
 * GPL v2 license
 * Handles data posted from AJAX
 * Apparently the only "right" way to do this is to integrate with the Moodle framework as an external web service:
 * https://docs.moodle.org/dev/Adding_a_web_service_to_a_plugin
**/

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
//require_once(dirname(__FILE__).'/lib.php');

function exit_error($msg)
{
	print( $msg );
	exit(1);
}

function my_optional_param( $name, $default, $argtype )
{
	if (isset( $_POST[$name] )) return $_POST[$name];
	return $default;
}

// Just for grins, what do we get with only config.php? Apparently not the param infrastructure
$n  = my_optional_param('n', 0, 0 /*PARAM_INT*/);  // ytpltrack instance ID

if ($n) {
	//exit_error( 'Debug 1: got n=' + $n );
    $ytpltrack  = $DB->get_record('ytpltrack', array('id' => $n), '*', MUST_EXIST);
	//exit_error( 'Debug 2: ' + print_r($ytpltrack, TRUE) );
    $course     = $DB->get_record('course', array('id' => $ytpltrack->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('ytpltrack', $ytpltrack->id, $course->id, false, MUST_EXIST);
} else {
    exit_error('You must specify a course_module ID or an instance ID: ' . print_r($_POST, TRUE) );
}
$instance = $n;

require_login($course, true, $cm);

// Post format: n=<instance-id>&c=<count>&a={0|1}&t=<index>,<video-id>,<pause-count>,<length-seconds>,<total-played>[,...]

$c = my_optional_param('c',0, 0 /*PARAM_INT*/); // Count
if ($c == 0) exit_error('Count must be specified (c)');
$t = my_optional_param('t','', 1 /*PARAM_STR*/); // Timing data
if ($t == '') exit_error('Timing data missing (t)');
$a = my_optional_param('a',0, 0 /*PARAM_INT*/); // Append

$at = explode(',', $t);
if (sizeof($at) != $c * 5) exit_error("Unexpected length " + sizeof($at) + "; expected " + ($c * 5) );

$dbgmsg = "";

// Check for existing record
$yviews = $DB->get_records_sql( "SELECT * FROM mdl_ytpltrack_views WHERE instance = :instance AND userid = :uid", array( "instance" => $instance, "uid" => $USER->id ) );
if (sizeof($yviews) < 1)
{
	// Create new view record
	$is_update = 0;
	$viewid = 0;
	$yvdetails = array();
}
else
{
	// Update view record
	$is_update = 1;
	$viewid = array_keys($yviews)[0];
	$dbgmsg .= sprintf( "; viewid %s update keys %s", $viewid, print_r( $yviews, TRUE ) );
	//$viewid = $yviews[$viewid]->id; // Always 0!
	$yvdetails = $DB->get_records_sql( "SELECT * FROM mdl_ytpltrack_viewdetails WHERE viewid = :viewid ORDER BY id", array( "viewid" => $viewid ) );
	$dbgmsg .= sprintf( "; yvdetails.size = %d", sizeof($yvdetails) );
}

// FIXME figure out a way for append to work... Should probably load in view.php and combine...

$totalviewed = 0.0;
$totalduration = 0.0;
$totalcapped = 0.0;
$countraw = $c;
$countfull = 0;

// Summarize
for ($n = 0; $n < $c; $n++)
{
	$details[] = new stdClass();
	$details[$n]->viewid = 0; // Circle back
	$details[$n]->videoid = $at[$n * 5 + 1];
	if ($details[$n]->videoid) $countfull++;
	$details[$n]->pausecount = $at[$n * 5 + 2];
	$duration = $at[$n * 5 + 3];
	$details[$n]->duration = $duration;
	$totalduration += $duration;
	$viewed = $at[$n * 5 + 4];
	$details[$n]->viewed = $viewed;
	$totalviewed += $viewed;
	if ($viewed > $duration) $viewed = $duration;
	$totalcapped += $viewed;
}

$master = new stdClass();
$master->instance = $instance;
$master->userid = $USER->id;
$master->lastupdate = time();
$master->countraw = $countraw;
$master->countfull = $countfull;
$master->totalviewed = $totalviewed;
$master->totalduration = $totalduration;
$master->totalcapped = $totalcapped;

$update_count = -1;
if ($is_update)
{
	// Update master record
	$master->firstupdate = $yviews[$viewid]->firstupdate;
	$master->id = $viewid;
	$DB->update_record( 'ytpltrack_views', $master );
	// If number of entries in playlist changed, we may have to insert some records
	$update_count = 0;
	$di = array();
	$du = array();
	for ($n = 0; $n < $c; $n++)
	{
		if ($n < sizeof($yvdetails))
		{
			$key = array_keys($yvdetails)[$n];
			$du[$key] = $details[$n];
			$dbgmsg .= sprintf( "; [%d]=%s\n%s", $n, $key, print_r($yvdetails[$key], TRUE) );
			// Despite selecting *, $yvdetails[key]->id is always 0, as is $yvdetails[key]->viewid
			$du[$key]->id = $key; //$yvdetails[$key]->id; // Disregard everything else
			$du[$key]->viewid = $viewid;
			$dbgmsg .= sprintf( "; du[%s]=%s", $key, print_r($du[$key], TRUE) );
			$DB->update_record( 'ytpltrack_viewdetails', $du[$key] );
			$update_count++;
			continue;
		}
		$details[$n]->viewid = $viewid;
		$di[] = $details[$n];
	}
	if (sizeof($di) > 0) 
	{
		$dbgmsg .= sprintf( "; insert %d", sizeof($di) );
		$DB->insert_records('ytpltrack_viewdetails', $di);
	}
}
else
{
	// Insert master record and get viewid
	$master->firstupdate = $master->lastupdate;
	$viewid = $DB->insert_record( 'ytpltrack_views', $master );
	$dbgmsg .= sprintf( "; new %d size %d", $viewid, sizeof($details) );
	// Set view id in details
	for ($n = 0; $n < $c; $n++)
	{
		$details[$n]->viewid = $viewid;
	}
	// Insert details
	$DB->insert_records( 'ytpltrack_viewdetails', $details );
}

print( "OK {$is_update}/{$update_count}/{$viewid}\n" );
//print( "OK {$is_update}/{$update_count}/{$viewid} {$dbgmsg}\n" );
