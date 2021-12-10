<?php

namespace WhatArmy\Watchtower\Mysql;

use Ifsnop\Mysqldump\Mysqldump;
use WhatArmy\Watchtower\Schedule;
use WhatArmy\Watchtower\Utils;

class Mysql_Backup
{
    private $db;
    public $group;
    public $backupName;

    /**
     * Backup constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        add_action('add_to_dump', [$this, 'add_to_dump']);

    }

    public function prepare_jobs()
    {
        return $this->db_stats();
    }


    private function db_stats()
    {
        global $wpdb;
        $tables_stats = $this->db->get_results("SELECT table_name 'name', table_rows 'rows', round(((data_length + index_length)/1024/1024),2) 'size_mb' 
                                      FROM information_schema.TABLES 
                                      WHERE table_schema = '" . DB_NAME . "';", ARRAY_N);
        $to_ret = new \stdClass();
        $exclusion = [
            $wpdb->prefix . 'actionscheduler_actions',
            $wpdb->prefix . 'actionscheduler_claims',
            $wpdb->prefix . 'actionscheduler_groups',
            $wpdb->prefix . 'actionscheduler_logs',
        ];
        foreach ($tables_stats as $table) {
            if (!in_array($table[0], $exclusion)) {
                $to_ret->{$table[0]} = [
                    'count' => $table[1],
                    'size' => $table[2],
                ];
            }

        }
        $to_ret = json_decode(json_encode($to_ret), true);
        return array_map(function ($t, $k) {
            $t['name'] = $k;
            return $t;
        }, $to_ret, array_keys($to_ret));
    }

    private function dispatch_job($data, $group = '')
    {
        as_schedule_single_action(time(), 'add_to_dump', $data, $group);
    }

    private function should_separate($table_stat)
    {
        $result = false;
        if ($table_stat['count'] >= WHTHQ_DB_RECORDS_MAX) {
            $result = true;
        }
        return $result;
    }

    private function dump_data($table, $dir, $range = null)
    {
        $dumpSettings = [
            'no-create-info' => true,
            'include-tables' => [$table],
            'skip-comments' => true,
        ];
        $dump = new Mysqldump("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings);
        if (is_array($range)) {
            $range = ($range['start'] === 1) ? 'LIMIT 0,' . (int)WHTHQ_DB_RECORDS_MAX : 'LIMIT ' . ($range['start'] - 1) . "," . ((int)WHTHQ_DB_RECORDS_MAX);
            $dump->setTableWheres([
                $table => $range,
            ]);
        }
        $dump->start($dir . '_dump_tmp.sql');
        $this->merge($dir . '_dump_tmp.sql', $dir . '_dump.sql');
    }

    /**
     * @param $file
     * @param $result
     */
    private function merge($file, $result)
    {
        file_put_contents($result, file_get_contents($file), FILE_APPEND | LOCK_EX);
        unlink($file);
    }

    private function dump_structure($tables, $dir)
    {
        $dumpSettings = [
            'no-data' => true,
            'skip-comments' => true,
        ];
        $dump = new Mysqldump("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings);
        $dump->start($dir . '_dump.sql');
    }

    /**
     * @param $table
     * @return array
     */
    private function split_to_parts($table)
    {
        $ranges = [];
        $start = 1;
        $end = WHTHQ_DB_RECORDS_MAX;
        foreach (range(1, ceil($table['count'] / WHTHQ_DB_RECORDS_MAX)) as $part) {
            array_push($ranges, [
                'start' => $start,
                'end' => $end - ($end === WHTHQ_DB_RECORDS_MAX ? 0 : 1),
            ]);
            $start = $start + WHTHQ_DB_RECORDS_MAX;
            $end = $start + WHTHQ_DB_RECORDS_MAX;
        }
        return $ranges;
    }

    public function add_to_dump($job)
    {
        if ($job['last'] == false) {
            $this->dump_data($job['table'], $job['dir'], $job['range']);
        } else {
            Schedule::call_headquarter_status($job['callbackHeadquarter'], $job['queue'], $job['filename'] . ".gz");
            $this->backupName = $job['dir'] . '_dump.sql';
            Schedule::clean_queue($job['file'], 'add_to_dump');
            Utils::gzCompressFile($this->backupName);
            unlink($this->backupName);
            Schedule::call_headquarter($job['callbackHeadquarter'], $job['filename'], 'gz');
        }
    }

    /**
     * @param $callback_url
     * @param $dir
     */
    private function runInQueue($callback_url, $dir)
    {
        $stats = $this->prepare_jobs();
        $this->dump_structure($stats, $dir);
        $ct = 1;
        foreach ($stats as $table) {
            if ($this->should_separate($table)) {
                foreach ($this->split_to_parts($table) as $part) {
                    $this->dispatch_job([
                        'job' => [
                            "table" => $table['name'],
                            "range" => ['start' => $part['start'], 'end' => $part['end']],
                            "dir" => $dir,
                            "last" => false,
                            "filename" => $this->group . '_dump.sql',
                            "file" => Utils::slugify($this->group),
                            "callbackHeadquarter" => $callback_url,
                            "queue" => $ct . '/' . $ct,
                        ]
                    ], Utils::slugify($this->group));
                    $ct++;
                }
            } else {
                $this->dump_data($table['name'], $dir, null);
            }
        }
        $this->add_finish_job($dir, $callback_url);
    }

    /**
     * @param $callback_url
     * @return string
     */
    public function run($callback_url)
    {
        Utils::cleanup_old_backups(WHTHQ_BACKUP_DIR);
        Utils::create_backup_dir();
        $this->group = date('Y_m_d__H_i_s') . "_" . Utils::random_string();
        $dir = WHTHQ_BACKUP_DIR . '/' . $this->group;

        //TODO: implement system mysqldump backup
        $this->runInQueue($callback_url, $dir);

        return $this->group . '_dump.sql.gz';
    }

    /**
     * @param $dir
     * @param $callback_url
     */
    private function add_finish_job($dir, $callback_url)
    {
        $this->dispatch_job([
            'job' => [
                "dir" => $dir,
                "last" => true,
                "file" => $this->group,
                "filename" => $this->group . '_dump.sql',
                "callbackHeadquarter" => $callback_url,
                "queue" => '100/100'
            ]
        ], Utils::slugify($this->group));
    }
}
