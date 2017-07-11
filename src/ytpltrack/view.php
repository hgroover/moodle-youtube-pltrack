<?php
/**
 * Prints a particular instance of ytpltrack
 *
 * This is where we present the playlist with the specified parameters.
 * For details on the YouTube IFrame API, see 
 * https://developers.google.com/youtube/iframe_api_reference
 *
 * @package    mod_ytpltrack
 * @copyright  2017 Henry Groover <henry.groover@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... ytpltrack instance ID - it should be named as the first character of the module. (?translation needed)

if ($id) {
    $cm         = get_coursemodule_from_id('ytpltrack', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $ytpltrack  = $DB->get_record('ytpltrack', array('id' => $cm->instance), '*', MUST_EXIST);
	//printf( "<!-- id = %s cm = %s course = %s ytpltrack = %s -->", $id, print_r($cm, TRUE), print_r($course, TRUE), print_r($ytpltrack, TRUE) );
	$n = $ytpltrack->id;
} else if ($n) {
    $ytpltrack  = $DB->get_record('ytpltrack', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $ytpltrack->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('ytpltrack', $ytpltrack->id, $course->id, false, MUST_EXIST);
	//printf( "<!-- n = %s cm = %s course = %s ytpltrack = %s -->", $n, print_r($cm, TRUE), print_r($course, TRUE), print_r($ytpltrack, TRUE) );
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

/*
$event = \mod_newmodule\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $newmodule);
$event->trigger();
*/

// Print the page header.

$PAGE->set_url('/mod/ytpltrak/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($ytpltrack->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('newmodule-'.$somevar);
 */
$PAGE->set_cacheable(false);

// Output starts here.
echo $OUTPUT->header();

// Conditions to show the intro can change to look for own settings or whatever.
if ($ytpltrack->intro) {
    echo $OUTPUT->box(format_module_intro('ytpltrack', $ytpltrack, $cm->id), 'generalbox mod_introbox', 'ytpltrackintro');
}

//echo $OUTPUT->heading('Viewing playlist ' . $ytpltrack->playlist);
global $USER;
$instanceid = $ytpltrack->id;
$userid = $USER->id;
$playlist = $ytpltrack->playlist;
$autoplay = 0; // Do not immediately begin playing
$fullurl = 0; // Submit full URLs instead of just video IDs
$width = "843"; // 640x360 suggests 16:9 medium; 853x510 for 16:9 high; 1280x750 for 16:9 HD720. Add 30 to height for widgets.
$height = "540";
$debug = 0;
?>
<!-- 1. The <iframe> (and video player) will replace this <div> tag. -->
    <div id="player"></div>
	<div id="stats" style="font-size:8pt;"><?php
	// Summarize existing stats, if any
	$yviews = $DB->get_records_sql( "SELECT * FROM mdl_ytpltrack_views WHERE instance = :instance AND userid = :uid", array( "instance" => $instanceid, "uid" => $USER->id ) );
	// These will be used later to initialize Javascript
	global $yvdetails, $fk;
	$yvdetails = array();
	$fk = "";
	if (sizeof($yviews) < 1)
	{
		printf( "<p>(no viewing progress recorded yet)</p>" );
	}
	else
	{
		//printf( "<!-- %s -->", print_r($yviews, TRUE) );
		//flush();
		$fk = array_keys($yviews)[0];
		// Do not display total progress unless all have been loaded
		if ($yviews[$fk]->countfull >= $yviews[$fk]->countraw)
		{
			printf( "<p>Viewed %s of %s (%.1f%%) in %d/%d videos</p>", SecondsToHMS($yviews[$fk]->totalcapped), SecondsToHMS($yviews[$fk]->totalduration), 
				100 * $yviews[$fk]->totalcapped / $yviews[$fk]->totalduration, $yviews[$fk]->countfull, $yviews[$fk]->countraw );
		}
		else
		{
			printf( "<p>Viewed %s so far in %d/%d videos</p>", SecondsToHMS($yviews[$fk]->totalcapped), $yviews[$fk]->countfull, $yviews[$fk]->countraw );
		}
		$viewid = $fk;
		$yvdetails = $DB->get_records_sql( "SELECT * FROM mdl_ytpltrack_viewdetails WHERE viewid = :viewid ORDER BY id", array( "viewid" => $viewid ) );
		if (sizeof($yvdetails) > 0)
		{
			// Replicate Javascript updateStats()
			print( "<table border='0'><thead><tr><th>#</th><th>ID</th><th>Duration</th><th>Total played</th><th>Pct</th><th>Times paused</th></tr></thead>" );
			print( "<tbody>" );
			for ($n = 0; $n < sizeof($yvdetails); $n++)
			{
				$key = array_keys($yvdetails)[$n];
				printf( "<tr><td>%d</td>", $n + 1 );
				printf( "<td>%s</td>", $yvdetails[$key]->videoid );
				printf( "<td>%s</td>", SecondsToHMS($yvdetails[$key]->duration) );
				printf( "<td>%s</td>", SecondsToHMS($yvdetails[$key]->viewed) );
				if ($yvdetails[$key]->duration > 0)
				{
					printf( "<td>%.1f%%</td>", 100 * $yvdetails[$key]->viewed / $yvdetails[$key]->duration );
				}
				else
				{
					printf( "<td>&nbsp;</td>" );
				}
				printf( "<td style='text-align:right;'>%s</td>", $yvdetails[$key]->pausecount );
				print( "</tr>\n" );
			}
			print( "</tbody></table>" );
		}
	}
	?></div>
	<div id="links" style="font-size:8pt;"><p>dbg <a href="javascript:setDebug(1)" title="Increase debug level">+</a> / <a href="javascript:setDebug(-1)" title="Decrease debug level">-</a></p></div>
    <div id="debug"></div>
	<!-- not needed
	<div id="formData"><form id="updateForm" method="post" action="data.php">
		<input type="hidden" id="update_n" name="n"/>
		<input type="hidden" id="update_c" name="c"/>
		<input type="hidden" id="update_t" name="t"/>
	</form></div>
	-->

    <script>
      // 2. This code loads the IFrame Player API code asynchronously.
      var tag = document.createElement('script');

      tag.src = "https://www.youtube.com/iframe_api";
      var firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

      // 3. This function creates an <iframe> (and YouTube player)
      //    after the API code downloads.
      var player;
	  var g_playlist = '<?php print $playlist; ?>';
	  var g_dbg = <?php print $debug; ?>;
	  var g_instance = <?php print $instanceid; ?>;
	  var g_ended = 0;
	  var g_updateCountdown = 60; // Countdown in seconds to next update
	  var g_updateInterval = 60; // Interval in seconds between updates
	  function setDebug(add) {
		  if (g_dbg + add >= 0) g_dbg += add;
	  }
	  // Accelerate next update to take place in specified number of seconds
	  function accelerateTo(seconds) {
		  if (g_updateCountdown > seconds + 1)
		  {
			  g_updateCountdown = seconds;
		  }
	  }
	  // Interval handler
	  function onSecondTick() {
		  if (g_updateCountdown <= 0)
		  {
			  g_updateCountdown = g_updateInterval;
			  updateStats();
			  g_updateCountdown = g_updateInterval;
			  return;
		  }
		  g_updateCountdown--;
	  }
      function onYouTubeIframeAPIReady() {
        // Example was: M7lc1UVf-VE
        // Our playlist: PLciv92xQ-Yr1Py06D56k1tin5DU-mb9_B
        player = new YT.Player('player', {
          height: '<?php print $height; ?>',
          width: '<?php print $width; ?>',
          videoId: '',
          events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange,
			'onPlaybackQualityChange': onPlayerPlaybackQualityChange,
			'onError': onPlayerError
          }
        });
      }

      // 4. The API will call this function when the video player is ready.
      function onPlayerReady(event) {
        //event.target.playVideo();
		<?php if ($autoplay) printf( "event.target.loadPlaylist( { list: '%s' } );\n", $playlist );
		else printf( "event.target.cuePlaylist( { list: '%s' } );\n", $playlist ); ?>
		if (g_dbg) logmsg('loading playlist ' + g_playlist );
      }

      // 5. The API calls this function when the player's state changes.
      //    The function indicates that when playing a video (state=1),
      //    the player should play for six seconds and then stop.
      var done = false;
      function onPlayerStateChange(event) {
		if (event.data == YT.PlayerState.ENDED)
		{
			// We apparently get this AFTER the current playlist index has switched, 
			// iff (if and only if) we allowed the previous entry to play to completion,
			// so we need to update the previous playlist end time before starting this one
			g_ended = 2 + 1; // Number of remaining updates (2)
			var lastPlaylist = g_latestIndex;
			logmsg("ended:" + lastPlaylist );
			if (lastPlaylist >= 0)
			{
				playlistTimes[lastPlaylist].push((new Date()).getTime());
				// We will, apparently, get the playing event.
				//playlistTimes[lastPlaylist].push(0-(new Date()).getTime());
			}
			// Perform stats update in 2 seconds
			accelerateTo( 2 );
		}
		else if (event.data == YT.PlayerState.PLAYING)
		{
			g_ended = 0;
			if (null == playlist) 
			{
				logmsg( "playing first" );
				setTimeout( getPlaylistInfo, 100 );
			}
			else 
			{
				var lastPlaylist = event.target.getPlaylistIndex();
				if (lastPlaylist < 0 && g_latestIndex >= 0) lastPlaylist = g_latestIndex;
				else if (lastPlaylist >= 0) g_latestIndex = lastPlaylist;
				logmsg( "playing " + lastPlaylist );
				playlistTimes[lastPlaylist].push(0-(new Date()).getTime());
			}
			setTimeout( function() { getCurrentInfo(event.target); }, 5000 );
		}
		else if (event.data == YT.PlayerState.PAUSED)
		{
			g_ended = 0;
			var lastPlaylist = event.target.getPlaylistIndex();
			if (lastPlaylist < 0 && g_latestIndex >= 0) lastPlaylist = g_latestIndex;
			else if (lastPlaylist >= 0) g_latestIndex = lastPlaylist;
			logmsg( "paused " + lastPlaylist );
			playlistTimes[lastPlaylist].push((new Date()).getTime());
			playlistPause[lastPlaylist]++;
			accelerateTo( 3 );
		}
		else if (event.data == YT.PlayerState.BUFFERING)
		{
			logmsg( "buffering " + event.target.getPlaylistIndex() );
			g_ended = 0;
		}
		else if (event.data == -1)
		{
			logmsg( "unstarted " + event.target.getPlaylistIndex() + " latest " + g_latestIndex );
			g_ended = 0;
			// If we got here by the skip playlist button (->|) there will have been
			// no end event for the previous video, so we need to ensure that it has
			// an end. If it was still playing (and was not paused) we'll have an
			// unbalanced number of entries.
			if (g_latestIndex >= 0 && g_latestIndex != event.target.getPlaylistIndex())
			{
				if (playlistTimes[g_latestIndex].length % 2)
				{
					playlistTimes[g_latestIndex].push((new Date()).getTime());
					logmsg( "evened " + g_latestIndex );
				}
			}
		}
		else 
		{
			var lastPlaylist = event.target.getPlaylistIndex();
			if (lastPlaylist < 0 && g_latestIndex >= 0) lastPlaylist = g_latestIndex;
			else if (lastPlaylist >= 0) g_latestIndex = lastPlaylist;
			if (g_dbg) logmsg("State change[" + lastPlaylist + "]: " + event.data ); //YT.PlayerState);
		}
      }
	  
	  // Extract ID from url
	  function idfromurl(url)
	  {
		  // Parse https://www.youtube.com?foo=bar&v=<id> or https://www.youtube.com?v=<id>
		  var vpat = /[\?\&]v=([^\?\&]+)/g;
		  var vmatch = vpat.exec(url);
		  if (null != vmatch)
		  {
			  var id = vmatch[1];
			  if ("" != id) return id;
		  }
		  return url;
	  }
	  // Update stats
	  function updateStats()
	  {
		  if (g_ended > 0)
		  {
			  if (g_ended == 1) return;
			  g_ended--;
		  }
		  var divStats = document.getElementById("stats");
		  var s = "<table border='0'><thead><tr><th>#</th><th>ID</th><th>Duration</th><th>Total played</th><th>Pct</th><th>Times paused</th></tr></thead>";
		  s += "<tbody>";
		  // Post format: n=<playlist-id>&c=<count>&t=<index>,<video-id>,<pause-count>,<length-seconds>,<total-played>[,...]
		  var postData = ''; //'n=' + g_playlist + '&c=' + playlist.length + '&t=';
		  var n;
		  for (n = 0; n < playlist.length; n++)
		  {
			  s += "<tr>";
			  s += "<td>";
			  s += (n + 1);
			  s += "</td>";
			  s += "<td>";
			  s += "<a href='";
			  s += playlistUrl[n];
			  s += "'>";
			  s += idfromurl(playlistUrl[n]);
			  s += "</a>";
			  s += "</td>";
			  s += "<td>";
			  //s += playlistDuration[n];
			  s += SecondsToHMS( playlistDuration[n] );
			  s += "</td>";
			  if (n) postData += ',';
			  postData += n;
			  postData += ',';
			  postData += idfromurl(playlistUrl[n]);
			  postData += ',';
			  postData += playlistPause[n];
			  postData += ',';
			  postData += playlistDuration[n];
			  postData += ',';
			  var totalPlayedMS = playlistPreviousTimes[n] * 1000;
			  var m;
			  var sDbg;
			  sDbg = '[' + n + ']:';
			  for (m = 0; m < playlistTimes[n].length; m+=2)
			  {
				  // If this is the current playlist, assume we're still playing
				  var endTime = 0;
				  if (n == player.getPlaylistIndex() && player.getPlayerState() == 1) endTime = (new Date()).getTime();
				  if (m < playlistTimes[n].length - 1) endTime = playlistTimes[n][m+1];
				  sDbg += playlistTimes[n][m];
				  sDbg += ',';
				  sDbg += endTime;
				  sDbg += ';';
				// We have pairs of negative start time followed by positive end time
				if (playlistTimes[n][m] >= 0 || endTime <= 0)
				{
					// Invalid entries
					continue;
				}
				totalPlayedMS += (playlistTimes[n][m] + endTime);
			  }
			  postData += (totalPlayedMS/1000.0).toFixed(3);
			  s += "<td>";
			  if (g_dbg > 1) logmsg(sDbg);
			  //s += (totalPlayedMS/1000.0);
			  s += SecondsToHMS( totalPlayedMS/1000 );
			  s += "</td>";
			  var pct = 0;
			  if (playlistDuration[n] > 0) pct = 0.1 * totalPlayedMS / playlistDuration[n];
			  s += "<td>";
			  s += pct.toFixed(2);
			  s += " %</td>";
			  s += "<td style='text-align:right;'>";
			  s += playlistPause[n];
			  s += "</td>";
			  s += "</tr>";
		  }
		  s += "</tbody></table>";
		  divStats.innerHTML = s;
		  logmsg( 'post: ' + postData );
		  // jQuery is already loaded in Moodle. Post to data.php
		  $.post("data.php",
			{
				n: g_instance,
				c: playlist.length,
				t: postData
			},
			function(data, status){
				logmsg("Data: " + data + "\nStatus: " + status);
			});
	  }
	  // Get info on current playlist entry
	  function getCurrentInfo(p) {
		  var n = p.getPlaylistIndex();
		  var id = p.getVideoUrl();
		  if (playlistUrl[n]=='' || (id != '' && playlistUrl[n] != id))
		  {
			logmsg("url[" + n + "]:" + id);
			playlistUrl[n] = id;
		  }
		  if (playlistDuration[n]==0)
		  {
			logmsg("duration:" + p.getDuration());
			playlistDuration[n] = p.getDuration();
		  }
		  // Update status in 2 seconds
		  accelerateTo( 2 );
	  }
	  
	  // Retrieve playlist
	  var playlist = null; // Array of video IDs
	  var playlistTimes = []; // Array of arrays of (start, stop) getTime() values. Start values will be negative
	  var playlistPause = []; // Array of pause counts for each playlist entry
	  var playlistUrl = []; // Array of video URLs
	  var playlistDuration = []; // Array of durations
	  var playlistPreviousTimes = []; // Array of previously played times in seconds
	  var g_latestIndex = -1; // Latest playlist index referenced
	  <?php
	  // Inject previous playtimes along with video IDs
	  if ($fk != "" && sizeof($yvdetails) > 0)
		  for ($n = 0; $n < sizeof($yvdetails); $n++)
		  {
			  $key = array_keys($yvdetails)[$n];
			  printf( "playlistUrl.push('%s');\n\t", $yvdetails[$key]->videoid );
			  printf( "playlistPreviousTimes.push(%.0f);\n\t", $yvdetails[$key]->viewed );
			  printf( "playlistDuration.push(%.0f);\n\t", $yvdetails[$key]->duration );
			  printf( "playlistPause.push(%d);\n\t", $yvdetails[$key]->pausecount );
			  print( "playlistTimes.push([]);\n\t" );
		  }
	  ?>
	  function getPlaylistInfo()
	  {
		  playlist = player.getPlaylist();
		  logmsg( "playlist length:" + playlist.length);
		  // Ensure we can index playlistTimes and playlistPause
		  if (playlist.length > 0)
		  {
			  var n;
			  for (n = 0; n < playlist.length; n++)
			  {
				  playlistTimes.push([]);
				  playlistPause.push(0);
				  playlistUrl.push('');
				  playlistDuration.push(0);
				  playlistPreviousTimes.push(0);
			  }
		  }
		  // We are playing, record start time
		  playlistTimes[player.getPlaylistIndex()].push(0-(new Date()).getTime());
		  // Start stats update heartbeat
		  setInterval( onSecondTick, 1000 );
		  accelerateTo( 2 );
	  }
      function stopVideo() {
		  logmsg("Stopped");
        player.stopVideo();
      }
	  function _logmsg(s)
	  {
		  // Unconditionally log message
		  var dbg = document.getElementById('debug');
		  var html = dbg.innerHTML + '<p>' + s + '</p>';
		  dbg.innerHTML = html;
	  }
	  function logmsg(s)
	  {
		  if (g_dbg <= 0) return;
		  _logmsg(s);
	  }
	  function onPlayerPlaybackQualityChange(event) {
		  logmsg("Playback quality changed: " + event.data);
	  }
	  function onPlayerError(event) {
		  _logmsg("Player error:" + event.data);
	  }
	  
	  // Convert seconds to [h:]m:ss string
	  function SecondsToHMS( nseconds )
	  {
		  var minutes = Math.floor(Math.round(nseconds) / 60);
		  var hours = Math.floor(minutes / 60);
		  var seconds = Math.round(nseconds) % 60;
		  var s = '';
		  if (hours > 0)
		  {
			  s += hours;
			  s += ':';
		  }
		  if (hours > 0 && minutes < 10) s += '0';
		  s += minutes;
		  s += ':';
		  if (seconds < 10) s += '0';
		  s += seconds;
		  return s;
	  }
    </script>
<?php

// Finish the page.
echo $OUTPUT->footer();

function SecondsToHMS( $nseconds )
{
	$minutes = ((int)$nseconds) / 60;
	$hours = $minutes / 60;
	$seconds = $nseconds % 60;
	if ($hours == 0)
	{
		return sprintf( "%d:%02d", $minutes, $seconds );
	}
	return sprintf( "%d:%02d:%02d", $hours, $minutes, $seconds );
}