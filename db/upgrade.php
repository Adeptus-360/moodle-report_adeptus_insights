<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_report_adeptus_insights_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025010805) {
        // Define table adeptus_export_tracking to be created.
        $table = new xmldb_table('adeptus_export_tracking');

        // Adding fields to table adeptus_export_tracking.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportname', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('format', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('exportedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table adeptus_export_tracking.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table adeptus_export_tracking.
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('exportedat_idx', XMLDB_INDEX_NOTUNIQUE, ['exportedat']);

        // Conditionally launch create table for adeptus_export_tracking.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus_insights savepoint reached.
        upgrade_plugin_savepoint(true, 2025010805, 'report', 'adeptus_insights');
    }

    return true;
}
?>
