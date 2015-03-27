<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_elisprogram_upgrade($oldversion=0) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();
    $result = true;

    // Always upon any upgrade, ensure ELIS scheduled tasks is in good health.
    if ($result) {
        require_once($CFG->dirroot.'/local/eliscore/lib/tasklib.php');
        elis_tasks_update_definition('local_elisprogram');
    }

    if ($result && $oldversion < 2014082500) {
        $file = $CFG->dirroot.'/local/elisprogram/db/install.xml';
        $tables = array('local_elisprogram_crsset', 'local_elisprogram_crssetcrs', 'local_elisprogram_prgcrsset');
        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file($file, $tablename);
            }
        }

        // Update custom context levels.
        \local_eliscore\context\helper::set_custom_levels(\local_elisprogram\context\contextinfo::get_contextinfo());
        \local_eliscore\context\helper::install_custom_levels();

        // Initialize custom context levels.
        \local_eliscore\context\helper::reset_levels();
        \local_eliscore\context\helper::init_levels();

        upgrade_plugin_savepoint($result, '2014082500', 'local', 'elisprogram');
    }

    if ($result && $oldversion < 2015010402) {
        // ELIS-9067: Remove deleted Moodle Course refs in ELIS tables.
        $tablefields = array(
            'local_elisprogram_cls_mdl' => 'moodlecourseid',
            'local_elisprogram_crs_tpl' => 'location'
        );
        foreach ($tablefields as $tablename => $fieldname) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table) && $dbman->field_exists($table, $fieldname)) {
                $where = "NOT EXISTS (SELECT 'x'
                                        FROM mdl_course WHERE id = {{$tablename}}.{$fieldname})";
                $recs = $DB->get_recordset_select($tablename, $where);
                if ($recs && $recs->valid()) {
                    foreach ($recs as $rec) {
                        $rec->{$fieldname} = 0;
                        $DB->update_record($tablename, $rec);
                    }
                    $recs->close();
                }
            }
        }
        upgrade_plugin_savepoint($result, '2015010402', 'local', 'elisprogram');
    }

    return $result;
}
