<?php

// Baseline version is 2017070401
function xmldb_ytpltrack_upgrade($oldversion=0) {
    if ($oldversion < 2017070401) {
        // Add new fields to ytpltrack table.
        //$table = new xmldb_table('ytpltrack');
        //$field = new xmldb_field('showcode');
        //$field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'savecert');
        //if (!$dbman->field_exists($table, $field)) {
        //    $dbman->add_field($table, $field);
        //}
        // Add new fields to certificate_issues table.
        //$table = new xmldb_table('ytpltrack_views');
        //$field = new xmldb_field('code');
        //$field->set_attributes(XMLDB_TYPE_CHAR, '50', null, null, null, null, 'certificateid');
        //if (!$dbman->field_exists($table, $field)) {
        //    $dbman->add_field($table, $field);
        //}
 
        // Module savepoint reached.
        upgrade_mod_savepoint(true, 2017070401, 'ytpltrack');
    }
}
