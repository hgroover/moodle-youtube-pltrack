<?php

// Baseline version is 2017070401
function xmldb_ytpltrack_upgrade($oldversion=0) {
	global $DB;
	
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2017070504) {
        // Add new fields to ytpltrack table.
        $table = new xmldb_table('ytpltrack');
        $field = new xmldb_field('playlist');
        $field->set_attributes(XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, ' ', 'course');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
 
        // Once we reach this point, we can store the new version and consider the module
        // ... upgraded to the version 2017070504 so the next time this block is skipped.
        upgrade_mod_savepoint(true, 2017070504, 'ytpltrack');
    }
	
	if ($oldversion < 2017070605) {
		$table = new xmldb_table('ytpltrack');

		// Rename title to name
        $field = new xmldb_field('title');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, ' ', 'course');
		$dbman->rename_field($table, $field, 'name');

		// Rename description to intro
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'name');
		$dbman->rename_field($table, $field, 'intro');

		// Rename created and modified to timecreated / timemodified
        $field = new xmldb_field('created', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0',
            'introformat');
		$dbman->rename_field($table, $field, 'timecreated');
        $field = new xmldb_field('modified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0',
            'timecreated');
		$dbman->rename_field($table, $field, 'timemodified');
		
		// Add new fields intro_format

        // Define field introformat to be added to newmodule.
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0',
            'intro');

        // Add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
		
		
		// Upgrade complete
		upgrade_mod_savepoint(true, 2017070605, 'ytpltrack');
	}
	
	if ($oldversion < 2017070606) {
		$table = new xmldb_table('ytpltrack');

        // Define field grade to be added to newmodule.
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '100',
            'introformat');

        // Add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

		// Upgrade complete
		upgrade_mod_savepoint(true, 2017070606, 'ytpltrack');
	}
	return true;
}
