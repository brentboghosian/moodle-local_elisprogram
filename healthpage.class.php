<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('page.class.php');

/// The health check page
class healthpage extends pm_page {
    var $pagename = 'health';
    var $section = 'admn';

    const SEVERITY_NOTICE = 'notice';
    const SEVERITY_ANNOYANCE = 'annoyance';
    const SEVERITY_SIGNIFICANT = 'significant';
    const SEVERITY_CRITICAL = 'critical';

    function can_do_default() {
        $context = context_system::instance();
        return has_capability('moodle/site:config', $context);
    }

    function build_navbar_default($who = null) {
        global $CFG, $PAGE;

        $this->navbar->add(get_string('learningplan', 'local_elisprogram'), "{$CFG->wwwroot}/local/elisprogram/");
        $baseurl = $this->url;
        $baseurl->remove_all_params();
        $this->navbar->add(get_string('pluginname', 'tool_health'),
                           $baseurl->out(true, array('s' => $this->pagename)));
    }

    /**
     * Initialize the page variables needed for display.
     */
    function get_page_title_default() {
        return get_string('pluginname', 'tool_health');
    }

    function display_default() {
        global $OUTPUT, $core_health_checks;
        $verbose = $this->optional_param('verbose', false, PARAM_BOOL);
        @set_time_limit(0);

        $issues = array(
            healthpage::SEVERITY_CRITICAL => array(),
            healthpage::SEVERITY_SIGNIFICANT => array(),
            healthpage::SEVERITY_ANNOYANCE => array(),
            healthpage::SEVERITY_NOTICE => array(),
            );
        $problems = 0;

        $healthclasses = $core_health_checks;

        //include health classes from other files
        $plugin_types = array('eliscore', 'elisprogram');

        foreach ($plugin_types as $plugin_type) {
            $plugins = get_plugin_list($plugin_type);
            foreach ($plugins as $plugin_shortname => $plugin_path) {
                $health_file_path = $plugin_path . '/health.php';
                if (is_readable($health_file_path)) {
                    include_once $health_file_path;
                    $varname = "${plugin_shortname}_health_checks";
                    if (isset($$varname)) {
                        $healthclasses = array_merge($healthclasses, $$varname);
                    }
                }
            }
        }

        if ($verbose) {
            echo get_string('health_checking', 'local_elisprogram');
        }
        foreach ($healthclasses as $classname) {
            $problem = new $classname;
            if ($verbose) {
                echo "<li>$classname";
            }
            if($problem->exists()) {
                $severity = $problem->severity();
                $issues[$severity][$classname] = array(
                    'severity'    => $severity,
                    'description' => $problem->description(),
                    'title'       => $problem->title()
                    );
                ++$problems;
                if ($verbose) {
                    echo " - FOUND";
                }
            }
            if ($verbose) {
                echo '</li>';
            }
            unset($problem);
        }
        if ($verbose) {
            echo '</ul>';
        }

        if($problems == 0) {
            echo '<div id="healthnoproblemsfound">';
            echo get_string('healthnoproblemsfound', 'tool_health');
            echo '</div>';
        } else {
            echo $OUTPUT->heading(get_string('healthproblemsdetected', 'tool_health'));
            foreach($issues as $severity => $healthissues) {
                if(!empty($issues[$severity])) {
                    echo '<dl class="healthissues '.$severity.'">';
                    foreach($healthissues as $classname => $data) {
                        echo '<dt id="'.$classname.'">'.$data['title'].'</dt>';
                        echo '<dd>'.$data['description'];
                        echo '<form action="index.php#solution" method="get">';
                        echo '<input type="hidden" name="s" value="health" />';
                        echo '<input type="hidden" name="action" value="solution" />';
                        echo '<input type="hidden" name="problem" value="'.$classname.'" /><input type="submit" value="'.get_string('healthsolution', 'tool_health').'" />';
                        echo '</form></dd>';
                    }
                    echo '</dl>';
                }
            }
        }
    }

    function display_solution() {
        global $OUTPUT;

        $classname = $this->required_param('problem', PARAM_SAFEDIR);

        //import files needed for other health classes
        $plugin_types = array('eliscore', 'elisprogram');

        foreach ($plugin_types as $plugin_type) {
            $plugins = get_plugin_list($plugin_type);
            foreach ($plugins as $plugin_shortname => $plugin_path) {
                $health_file_path = $plugin_path . '/health.php';
                if (is_readable($health_file_path)) {
                    include_once $health_file_path;
                }
            }
        }
        $problem = new $classname;
        $data = array(
            'title'       => $problem->title(),
            'severity'    => $problem->severity(),
            'description' => $problem->description(),
            'solution'    => $problem->solution()
            );

        $OUTPUT->heading(get_string('pluginname', 'tool_health'));
        $OUTPUT->heading(get_string('healthproblemsolution', 'tool_health'));
        echo '<dl class="healthissues '.$data['severity'].'">';
        echo '<dt>'.$data['title'].'</dt>';
        echo '<dd>'.$data['description'].'</dd>';
        echo '<dt id="solution" class="solution">'.get_string('healthsolution', 'tool_health').'</dt>';
        echo '<dd class="solution">'.$data['solution'].'</dd></dl>';
        echo '<form id="healthformreturn" action="index.php#'.$classname.'" method="get">';
        echo '<input type="hidden" name="s" value="health" />';
        echo '<input type="submit" value="'.get_string('healthreturntomain', 'tool_health').'" />';
        echo '</form>';
    }
}

class crlm_health_check_base {
    function exists() {
        return false;
    }
    function title() {
        return '???';
    }
    function severity() {
        return healthpage::SEVERITY_NOTICE;
    }
    function description() {
        return '';
    }
    function solution() {
        return '';
    }
}

global $core_health_checks;
$core_health_checks = array(
        'cron_lastruntimes_check',
        'health_duplicate_enrolments',
        'health_stale_cm_class_moodle',
        'health_curriculum_course',
        'health_user_sync',
        'cluster_orphans_check',
        'track_classes_check',
        'completion_export_check',
        'dangling_completion_locks',
        'duplicate_course_los',
        'duplicate_usertracks',
        'old_context_records',
);

/**
 * Checks for pre-2.6 context records.
 */
class old_context_records extends crlm_health_check_base {
    /** @var array Array of tables and the number of old context records. */
    protected $count = array();

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;

        $tables = array('context', 'role_context_levels', 'local_eliscore_field_clevels', 'local_eliscore_fld_cat_ctx');
        foreach ($tables as $table) {
            $sql = 'SELECT count(1) FROM {'.$table.'} WHERE contextlevel IN (1001, 1002, 1003, 1004, 1005, 1006)';
            $this->count[$table] = $DB->count_records_sql($sql);
        }
    }

    /**
     * Check for problem existence.
     *
     * @return bool Whether the problem exists or not.
     */
    public function exists() {
        foreach ($this->count as $table => $count) {
            if ($count > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Problem severity.
     *
     * @return string Severity of the problem.
     */
    public function severity() {
        return healthpage::SEVERITY_NOTICE;
    }

    /**
     * Problem title.
     *
     * @return string Title of the problem.
     */
    public function title() {
        return get_string('health_oldcontextrecs', 'local_elisprogram');
    }

    /**
     * Problem description.
     *
     * @return string Description of the problem.
     */
    public function description() {
        $problemtables = array();
        foreach ($this->count as $table => $count) {
            if ($count > 0) {
                $problemtables[] = $table;
            }
        }
        $problemtables = implode(', ', $problemtables);
        return get_string('health_oldcontextrecsdesc', 'local_elisprogram', $problemtables);
    }

    /**
     * Problem solution.
     *
     * @return string Solution to the problem.
     */
    public function solution() {
        $msg = get_string('health_oldcontextrecssoln', 'local_elisprogram');
        return $msg;
    }
}

/**
 * Checks for duplicate CM enrolment records.
 */
class health_duplicate_enrolments extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/student.class.php');
        global $DB;

        $sql = "SELECT COUNT('x')
                FROM {".student::TABLE."} enr
                INNER JOIN (
                    SELECT id
                    FROM {".student::TABLE."}
                    GROUP BY userid, classid
                    HAVING COUNT(id) > 1
                ) dup
                ON enr.id = dup.id";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_duplicate', 'local_elisprogram');
    }
    function description() {
        return get_string('health_duplicatedesc', 'local_elisprogram', $this->count);
    }
    function solution() {
        $msg = get_string('health_duplicatesoln', 'local_elisprogram');
        return $msg;
    }
}

/**
 * Checks that the local_elisprogram_cls_mdl table doesn't contain any links to stale
 * CM class records.
 */
class health_stale_cm_class_moodle extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/classmoodlecourse.class.php');
        require_once elispm::lib('data/pmclass.class.php');
        global $DB;
        $sql = "SELECT COUNT(*)
                  FROM {".classmoodlecourse::TABLE."} clsmdl
             LEFT JOIN {".pmclass::TABLE."} cls on clsmdl.classid = cls.id
                 WHERE cls.id IS NULL";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_stale', 'local_elisprogram');
    }
    function description() {
        global $CFG;
        return get_string('health_staledesc', 'local_elisprogram',
                   array('count' => $this->count,
                         'table' => $CFG->prefix . classmoodlecourse::TABLE));
    }
    function solution() {
        global $CFG;

        $msg = get_string('health_stalesoln', 'local_elisprogram').
                "<br/> USE {$CFG->dbname}; <br/>".
                " DELETE FROM {$CFG->prefix}". classmoodlecourse::TABLE ." WHERE classid NOT IN (
                SELECT id FROM {$CFG->prefix}". pmclass::TABLE ." );";
        return $msg;
    }
}

/**
 * Checks that the local_elisprogram_pgm_crs table doesn't contain any links to
 * stale CM course records.
 */
class health_curriculum_course extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/curriculumcourse.class.php');
        require_once elispm::lib('data/course.class.php');
        global $DB;
        $sql = "SELECT COUNT(*)
                  FROM {".curriculumcourse::TABLE."} curcrs
             LEFT JOIN {".course::TABLE."} crs on curcrs.courseid = crs.id
                 WHERE crs.id IS NULL";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_curriculum', 'local_elisprogram');
    }
    function description() {
        global $CFG;
        return get_string('health_curriculumdesc', 'local_elisprogram',
                   array('count' => $this->count,
                         'table' => $CFG->prefix . curriculumcourse::TABLE));
    }
    function solution() {
        global $CFG;

        $msg = get_string('health_curriculumsoln', 'local_elisprogram').
                "<br/> USE {$CFG->dbname}; <br/>".
                "DELETE FROM {$CFG->prefix}". curriculumcourse::TABLE ." WHERE courseid NOT IN (
                 SELECT id FROM {$CFG->prefix}". course::TABLE ." );";
        return $msg;

    }
}

/**
 * Checks if there are more Moodle users than ELIS users
 */
class health_user_sync extends crlm_health_check_base {
    function __construct() {
        global $CFG, $DB;
        $params = array($CFG->mnet_localhost_id, $CFG->mnet_localhost_id);
        $sql = "SELECT COUNT('x')
                  FROM {user}
                 WHERE username != 'guest'
                   AND deleted = 0
                   AND confirmed = 1
                   AND mnethostid = ?
                   AND idnumber != ''
                   AND firstname != ''
                   AND lastname != ''
                   AND email != ''
                   AND NOT EXISTS (
                         SELECT 'x'
                           FROM {".user::TABLE."} cu
                          WHERE cu.idnumber = {user}.idnumber
                     )
                   AND NOT EXISTS (
                       SELECT 'x'
                         FROM {".user::TABLE."} cu
                        WHERE cu.username = {user}.username
                          AND {user}.mnethostid = ?
                     )";

        $this->count = $DB->count_records_sql($sql, $params);

        $sql = "SELECT COUNT('x')
                  FROM {user} usr
                 WHERE deleted = 0
                   AND idnumber IN (
                         SELECT idnumber
                           FROM {user}
                          WHERE username != 'guest'
                            AND deleted = 0
                            AND confirmed = 1
                            AND mnethostid = ?
                            AND id != usr.id
                     )";

        $this->dupids = $DB->count_records_sql($sql, $params);
    }

    function exists() {
        return $this->count > 0 || $this->dupids > 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_user_sync', 'local_elisprogram');
    }
    function description() {
        $msg = '';
        if ($this->count > 0) {
            $msg = get_string('health_user_syncdesc', 'local_elisprogram', $this->count);
        }
        if ($this->dupids > 0) {
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_dupiddesc', 'local_elisprogram', $this->dupids);
        }
        return $msg;
    }
    function solution() {
        global $CFG;

        $msg = '';
        if ($this->dupids > 0) {
            $msg = get_string('health_user_dupidsoln', 'local_elisprogram');
        }
        if ($this->count > $this->dupids) {
            // ELIS-3963: Only run migrate script if more mismatches then dups
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_syncsoln', 'local_elisprogram', $CFG->wwwroot);
        }
        return $msg;
    }
}

class cluster_orphans_check extends crlm_health_check_base {
    function __construct() {
        global $DB;

        //needed for db table constants
        require_once(elispm::lib('data/userset.class.php'));

        $this->parentBad = array();

        $sql = "SELECT child.name
                FROM
                {".userset::TABLE."} child
                WHERE NOT EXISTS (
                    SELECT *
                    FROM {".userset::TABLE."} parent
                    WHERE child.parent = parent.id
                )
                AND child.parent != 0";

        if ($clusters = $DB->get_recordset_sql($sql)) {
            foreach ($clusters as $cluster) {
                $this->parentBad[] = $cluster->name;
            }
            $clusters->close();
        }
    }

    function exists() {
        $returnVal = (count($this->parentBad) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return get_string('health_cluster_orphans', 'local_elisprogram');
    }

    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }

    function description() {
        if (count($this->parentBad) > 0) {
            $msg = get_string('health_cluster_orphansdesc', 'local_elisprogram', array('count'=>count($this->parentBad)));
            foreach ($this->parentBad as $parentName) {
                $msg .= '<li>'.$parentName.'</li>';
            }
            $msg .= '</ul>';
        } else {
            $msg =  get_string('health_cluster_orphansdescnone', 'local_elisprogram'); // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_cluster_orphanssoln', 'local_elisprogram', $CFG->dirroot);
        return $msg;
    }
}

class track_classes_check extends crlm_health_check_base {
    function __construct() {
        global $DB;

        //needed for db table constants
        require_once(elispm::lib('data/track.class.php'));
        require_once(elispm::lib('data/curriculumcourse.class.php'));

        $this->unattachedClasses = array();

        $sql = 'SELECT trkcls.id, trkcls.trackid, trkcls.courseid, trkcls.classid, trk.curid
                FROM {'. trackassignment::TABLE .'} trkcls
                JOIN {'. track::TABLE .'} trk ON trk.id = trkcls.trackid
                JOIN {'. pmclass::TABLE .'} cls ON trkcls.classid = cls.id
                WHERE NOT EXISTS (
                    SELECT *
                    FROM {'. curriculumcourse::TABLE .'} curcrs
                    WHERE trk.curid = curcrs.curriculumid
                    AND cls.courseid = curcrs.courseid
                )';

        if (($trackclasses = $DB->get_recordset_sql($sql)) && $trackclasses->valid()) {
            foreach ($trackclasses as $trackclass) {
                $this->unattachedClasses[] = $trackclass->id;
            }
            $trackclasses->close();
        }
    }

    function exists() {
        $returnVal = (count($this->unattachedClasses) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return get_string('health_trackcheck', 'local_elisprogram');
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function description() {
        $msg = get_string('health_trackcheckdesc', 'local_elisprogram', count($this->unattachedClasses));

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_trackchecksoln', 'local_elisprogram', $CFG->wwwroot);
        return $msg;
    }
}

/**
 * Checks if the completion export block is present.
 */
class completion_export_check extends crlm_health_check_base {
    function exists() {
        global $CFG;
        $exists = is_dir($CFG->dirroot.'/blocks/completion_export');
        return is_dir($CFG->dirroot.'/blocks/completion_export');
    }

    function title() {
        return get_string('health_completion', 'local_elisprogram');
    }

    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }

    function description() {
        return get_string('health_completiondesc', 'local_elisprogram');
    }

    function solution() {
        global $CFG;
        return get_string('health_completionsoln', 'local_elisprogram', $CFG);
    }
}

/**
 * Checks if the completion export block is present.
 */
class cron_lastruntimes_check extends crlm_health_check_base {
    private $blocks = array(); // empty array for none; 'elisadmin' ?
    private $plugins = array(); // TBD: 'local_elisprogram', 'local_eliscore' ?

    function exists() {
        global $DB;
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = $DB->get_field('block', 'lastcron', array('name' => $block));
            if ($lastcron < $threshold) {
                return true;
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = $DB->get_field('config_plugins', 'value', array('plugin' => $plugin, 'name' => 'lastcron'));
            if ($lastcron < $threshold) {
                return true;
            }
        }
        $lasteliscron = $DB->get_field('local_eliscore_sched_tasks', 'MAX(lastruntime)', array());
        if ($lasteliscron < $threshold) {
            return true;
        }
        return false;
    }

    function title() {
        return get_string('health_cron_title', 'local_elisprogram');
    }

    function severity() {
        return healthpage::SEVERITY_NOTICE;
    }

    function description() {
        global $DB;
        $description = '';
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = $DB->get_field('block', 'lastcron', array('name' => $block));
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $block;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'local_elisprogram');
                $description .= get_string('health_cron_block', 'local_elisprogram', $a);
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = $DB->get_field('config_plugins', 'value', array('plugin' => $plugin, 'name' => 'lastcron'));
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $plugin;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'local_elisprogram');
                $description .= get_string('health_cron_plugin', 'local_elisprogram', $a);
            }
        }
        $lasteliscron = $DB->get_field('local_eliscore_sched_tasks', 'MAX(lastruntime)', array());
        if ($lasteliscron < $threshold) {
            $lastcron = $lasteliscron ? userdate($lasteliscron) : get_string('cron_notrun', 'local_elisprogram');
            $description .= get_string('health_cron_elis', 'local_elisprogram', $lastcron);
        }
        return $description;
    }

    function solution() {
        return get_string('health_cron_soln', 'local_elisprogram');
    }
}

/**
 * Checks for duplicate PM profile records.
 */
class duplicate_moodle_profile extends crlm_health_check_base {
    function __construct() {
        global $DB;
        $concat = $DB->sql_concat('fieldid', "'/'", 'userid');
        $sql = "SELECT $concat, COUNT(*)-1 AS dup
                  FROM {user_info_data} dat
              GROUP BY fieldid, userid
                HAVING COUNT(*) > 1";
        $this->counts = $DB->get_recordset_sql($sql);
    }
    function exists() {
        return ($this->counts->valid()===true) ? true : false;
    }
    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }
    function title() {
        return get_string('health_dupmoodleprofile', 'local_elisprogram');
    }
    function description() {
        $count = 0;
        foreach ($this->counts as $dup) {
            $count += $dup->dup;
        }
        $this->counts->close();
        return get_string('health_dupmoodleprofiledesc', 'local_elisprogram', $count);
    }
    function solution() {
        global $CFG;
        return get_string('health_dupmoodleprofilesoln', 'local_elisprogram', $CFG->dirroot);
    }
}

/**
 * Checks for any passing completion scores that are unlocked and linked to Moodle grade items which do not exist.
 */
class dangling_completion_locks extends crlm_health_check_base {
    function __construct() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/local/elisprogram/lib/setup.php');
        require_once elispm::lib('data/student.class.php');

        // Check for unlocked, passed completion scores which are not associated with a valid Moodle grade item
        $sql = "SELECT COUNT('x')
                FROM {".student_grade::TABLE."} ccg
                INNER JOIN {".coursecompletion::TABLE."} ccc ON ccc.id = ccg.completionid
                INNER JOIN {".classmoodlecourse::TABLE."} ccm ON ccm.classid = ccg.classid
                INNER JOIN {course} c ON c.id = ccm.moodlecourseid
                LEFT JOIN {grade_items} gi ON (gi.idnumber = ccc.idnumber AND gi.courseid = c.id)
                WHERE ccg.locked = 0
                AND ccc.idnumber != ''
                AND ccg.grade >= ccc.completion_grade
                AND gi.id IS NULL";

        $this->count = $DB->count_records_sql($sql);

/*
        // Check for unlocked, passed completion scores which are associated with a valid Moodle grade item
        // XXX - NOTE: this is not currently being done as it may be that these values were manually unlocked on purpose
        // XXX - NOTE: this is from 1.9 so if / when using this query, update query to 2.x standard
        $sql = "SELECT COUNT('x')
                FROM {$CURMAN->db->prefix_table(USRTABLE)} cu
                INNER JOIN {$CURMAN->db->prefix_table(STUTABLE)} cce ON cce.userid = cu.id
                INNER JOIN {$CURMAN->db->prefix_table(GRDTABLE)} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
                INNER JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} ccc ON ccc.id = ccg.completionid
                INNER JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} ccm ON ccm.classid = ccg.classid
                INNER JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber
                INNER JOIN {$CFG->prefix}course c ON c.id = ccm.moodlecourseid
                INNER JOIN {$CFG->prefix}grade_items gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
                INNER JOIN {$CFG->prefix}grade_grades gg ON (gg.itemid = gi.id AND gg.userid = u.id)
                WHERE ccg.locked = 0
                AND ccg.grade >= ccc.completion_grade
                AND gg.finalgrade >= ccc.completion_grade
                AND ccc.idnumber != ''
                AND gi.itemtype != 'course'
                AND ccg.timemodified > gg.timemodified";

        $this->count += $CURMAN->db->count_records_sql($sql);
*/
    }

    function exists() {
        return $this->count != 0;
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function title() {
        return get_string('health_danglingcompletionlocks','local_elisprogram');
    }

    function description() {
        return get_string('health_danglingcompletionlocksdesc','local_elisprogram', $this->count);
    }

    function solution() {
        $msg = get_string('health_danglingcompletionlockssoln','local_elisprogram');
        return $msg;
    }
}

/**
 * Checks for duplicate course completion elements
 */
class duplicate_course_los extends crlm_health_check_base {
    var $count; // count of max course with duplicate completion elements
    function __construct() {
        global $DB;
        $sql = "SELECT MAX(count) FROM (SELECT COUNT('x') AS count FROM {local_elisprogram_crs_cmp} GROUP BY courseid, idnumber) duplos";
        $this->count = $DB->get_field_sql($sql);
    }
    function exists() {
        return($this->count > 1);
    }
    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT; // ANNOYANCE ???
    }
    function title() {
        return get_string('health_dupcourselos', 'local_elisprogram');
    }
    function description() {
        return get_string('health_dupcourselosdesc', 'local_elisprogram');
    }
    function solution() {
        return get_string('health_dupcourselossoln', 'local_elisprogram');
    }
}

/**
 * Checks for duplicate usertrack records.
 */
class duplicate_usertracks extends crlm_health_check_base {
    var $count;

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;
        $tablename = usertrack::TABLE;
        $sql = "SELECT COUNT(ut1.id) FROM {".$tablename."} ut1, {".$tablename."} ut2 WHERE ut1.id > ut2.id AND ut1.userid = ut2.userid AND ut1.trackid = ut2.trackid";
        $this->count = $DB->get_field_sql($sql);
    }

    /**
     * Check for problem existence.
     * @return int Count of duplicates found.
     */
    public function exists() {
        return($this->count > 0);
    }

    /**
     * Problem severity.
     * @return string Severity of the problem.
     */
    public function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    /**
     * Problem title.
     * @return string Title of the problem.
     */
    public function title() {
        return get_string('health_dupusertrack', 'local_elisprogram');
    }

    /**
     * Problem description.
     * @return string Description of the problem.
     */
    public function description() {
        return get_string('health_dupusertrackdesc', 'local_elisprogram', array('count' => $this->count, 'name' => usertrack::TABLE));
    }

    /**
     * Problem solution.
     * @return string Solution to the problem.
     */
    public function solution() {
        global $CFG;
        return get_string('health_dupusertracksoln', 'local_elisprogram', array('name' => usertrack::TABLE, 'wwwroot' => $CFG->wwwroot));
    }
}
