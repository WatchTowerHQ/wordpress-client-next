<?php

namespace WhatArmy\Watchtower\Mysql;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Druidfi\Mysqldump\Mysqldump;
use WhatArmy\Watchtower\Schedule;
use WhatArmy\Watchtower\Utils;

class Mysql_Backup
{
    private $db;
    public string $group;
    public string $backupName;

    /**
     * Backup constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        add_action('add_to_dump', [$this, 'add_to_dump']);
        add_action('wht_cleanup', [$this, 'cleanup']);

    }

    /**
     * @param $callback_url
     * @return string
     * @throws \Exception
     */
    public function run($callback_url): string
    {
        Utils::cleanup_old_backups(WHTHQ_BACKUP_DIR);
        Utils::create_backup_dir();
        $this->group = date('Y_m_d__H_i_s') . "_" . Utils::random_string();
        $dir = WHTHQ_BACKUP_DIR . '/' . $this->group;

        $mysqldumpDetected = Utils::detectMysqldumpLocation();

        if ($mysqldumpDetected !== false && Utils::db_size() < 60) {
            $this->runMysqlDump($callback_url, $dir, $mysqldumpDetected);
        } else {
            $this->runInQueue($callback_url, $dir);
        }

        return $this->group . '_dump.sql.gz';
    }


    private function runMysqlDump($callback_url, $dir, $mysqldumpBinary)
    {

        $command = $mysqldumpBinary . ' -h ' . DB_HOST . ' -u ' . DB_USER;
        if (!empty(DB_PASSWORD)) {
            $command .= ' -p' . DB_PASSWORD;
        }
        $command .= ' ' . DB_NAME . ' > ' . WHTHQ_BACKUP_DIR . '/' . $this->group . '_dump.sql';
        exec($command, $output, $returnVar);
        if ($returnVar === 0) {
            $this->add_finish_job($dir, $callback_url, (2 * 10) + 10);
        }
    }

    /**
     * @param $callback_url
     * @param $dir
     * @throws \Exception
     */
    private function runInQueue($callback_url, $dir): void
    {
        $stats = $this->prepare_jobs();
        $this->dump_structure($stats, $dir);
        $ct = 1;
        foreach ($stats as $table) {
            if ($this->should_separate($table)) {
                $parts = $this->split_to_parts($table);
                foreach ($parts as $part) {
                    $this->dispatch_job([
                        'job' => [
                            "table" => $table['name'],
                            "range" => ['start' => $part['start'], 'end' => $part['end']],
                            "dir" => $dir,
                            "last" => false,
                            "filename" => $this->group . '_dump.sql',
                            "file" => Utils::slugify($this->group),
                            "callbackHeadquarter" => $callback_url,
                            "queue" => $ct . '/' . count($parts),
                        ]
                    ], Utils::slugify($this->group), $ct * 10);
                    $ct++;
                }
            } else {
                $this->dump_data($table['name'], $dir, null);
            }
        }
        $this->add_finish_job($dir, $callback_url, ($ct * 10) + 10);
    }

    public function prepare_jobs(): array
    {
        return $this->db_stats();
    }


    private function db_stats(): array
    {
        global $wpdb;
        $tables_stats = $this->db->get_results("SELECT table_name 'name', round(((data_length + index_length)/1024/1024),2) 'size_mb' 
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
                    'count' => $this->db->get_var("SELECT COUNT(*) FROM `" . esc_sql($table[0]) . "`"),
                    'size' => $table[1],
                ];
            }

        }

        $to_ret = json_decode(json_encode($to_ret), true);

        return array_map(function ($t, $k) {
            $t['name'] = $k;
            return $t;
        }, $to_ret, array_keys($to_ret));
    }

    private function dispatch_job($data, $group = '', $additional_time = 0): void
    {
        as_schedule_single_action(time() + $additional_time, 'add_to_dump', $data, $group);
    }

    private function dispatch_cleanup_job($data, $group = '', $additional_time = 0): void
    {
        as_schedule_single_action(time() + $additional_time, 'wht_cleanup', $data, $group);
    }

    private function should_separate($table_stat): bool
    {
        $result = false;
        if ($table_stat['count'] >= WHTHQ_DB_RECORDS_MAX) {
            $result = true;
        }
        return $result;
    }

    /**
     * @throws \Exception
     */
    private function dump_data($table, $dir, $range = null): void
    {
        $dumpSettings = [
            'no-create-info' => true,
            'include-tables' => [$table],
            'skip-comments' => true,
        ];
        $dump = new Mysqldump("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings);
        if (is_array($range)) {
            $range = $range['start'] === 1 ? [0, (int) WHTHQ_DB_RECORDS_MAX] : [($range['start'] - 1), (int) WHTHQ_DB_RECORDS_MAX];

            $dump->setTableLimits([
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
    private function merge($file, $result): void
    {
        $input = fopen($file, 'rb');
        $output = fopen($result, 'ab');

        while (!feof($input)) {
            // Read in 8 KB chunks (adjust as necessary)
            fwrite($output, fread($input, 8192));
        }

        fclose($input);
        fclose($output);

        unlink($file); // Delete the original file
    }

    /**
     * @throws \Exception
     */
    private function dump_structure($tables, $dir): void
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
    private function split_to_parts($table): array
    {
        $ranges = [];
        $start = 1;
        $end = WHTHQ_DB_RECORDS_MAX;
        foreach (range(1, ceil($table['count'] / WHTHQ_DB_RECORDS_MAX)) as $part) {
            $ranges[] = [
                'start' => $start,
                'end' => $end - ($end === WHTHQ_DB_RECORDS_MAX ? 0 : 1),
            ];
            $start = $start + WHTHQ_DB_RECORDS_MAX;
            $end = $start + WHTHQ_DB_RECORDS_MAX;
        }
        return $ranges;
    }

    public function cleanup($job): void
    {
        Schedule::clean_queue($job['group'], 'add_to_dump');
    }

    public function add_to_dump($job): void
    {
        $progress = explode('/', $job['queue']);
        $percent = ceil(((int) $progress[0] / (int) $progress[1]) * 100);

        $backupFilename = join('.', [$job['filename'], 'gz']);

        if (!$job['last']) {
            $this->dump_data($job['table'], $job['dir'], $job['range']);
            //Throttle This Request Since Looks Like It's Being Called Couple Times In Row
            Schedule::call_headquarter_mysql_status($job['callbackHeadquarter'], 2, $percent, $backupFilename, true);
        } else {
            $this->backupName = $job['dir'] . '_dump.sql';
            $this->dispatch_cleanup_job([
                'job' => [
                    'group' => $job['file'],
                ]
            ]);


            Schedule::call_headquarter_mysql_status($job['callbackHeadquarter'], 5, $percent, $backupFilename);

            Utils::gzCompressFile($this->backupName);
            unlink($this->backupName);

            Schedule::call_headquarter_mysql_ready($job['callbackHeadquarter'], $backupFilename);
        }
    }


    /**
     * @param $dir
     * @param $callback_url
     * @param int $additional_time
     * @return void
     */
    private function add_finish_job($dir, $callback_url, int $additional_time = 0): void
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
        ], Utils::slugify($this->group), $additional_time);
    }
}
