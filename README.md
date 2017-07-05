# moodle-youtube-pltrack
Moodle activity plugin for tracking Youtube playlist viewing

henry.groover@gmail.com

This plugin uses the relatively simple YouTube IFrame API:
https://developers.google.com/youtube/iframe_api_reference

This uses the API as published in July 2017. No API key is
required - it is an entirely browser-based API.

The plugin was developed and tested with Moodle 3.1 but may
work with earlier versions. Basically, it allows you to create
an activity and keep track of actual time students spend viewing
each video in the playlist.

This may be useful if you have a requirement (in our case,
a regulatory requirement) to keep a record of the actual time
students spend viewing videos provided as part of a curriculum.

It may not work on mobile devices, and the Youtube API won't
work with older browsers which don't support HTML 5, such as
Internet Explorer 7.

One noteworthy lesson whilst creating this module was that
it is not possible to install a module without a db/install.xml
file. The recommended way to create db/install.xml is to use
the XMLDB editor. The XMLDB editor cannot create an install.xml
unless the module directory (in this case mod/ytpltrack/db)
exists. Once the module directory is created, the admin subsystem
forces you (the admin) into "the following modules require attention"
with the new module to be either updated or cancelled. Cancellation
requires removing the new module directory and update requires a
valid install.xml. You see where this is going...

The solution is to directly enter admin/tool/xmldb in the url bar
when stuck on "the following modules require attention". This will
allow you to create the install.xml, add tables to it, and save it.

Chicken or egg paradox solved!
