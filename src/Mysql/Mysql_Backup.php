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
            $this->add_finish_job($dir, $callback_url);
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

        $queue_jobs = [];
        foreach ($stats as $table) {
            if ($this->should_separate($table)) {
                $parts = $this->split_to_parts($table);
                foreach ($parts as $part) {
                    $queue_jobs[] = [
                        'table' => $table['name'],
                        'range' => ['start' => $part['start'], 'end' => $part['end']],
                        'chunk_size' => $part['chunk_size'],
                    ];
                }
            } else {
                $this->dump_data($table['name'], $dir, null);
            }
        }

        // Add finish job as last entry
        $queue_jobs[] = ['last' => true];

        $queue_key = 'whthq_dump_queue_' . $this->group;
        update_option($queue_key, [
            'jobs' => $queue_jobs,
            'dir' => $dir,
            'filename' => $this->group . '_dump.sql',
            'group' => $this->group,
            'callbackHeadquarter' => $callback_url,
            'total' => count($queue_jobs),
        ], false);

        // Schedule only the first job
        as_schedule_single_action(time(), 'add_to_dump', [
            'job' => [
                'queue_key' => $queue_key,
                'index' => 0,
            ]
        ], Utils::slugify($this->group));
    }

    public function prepare_jobs(): array
    {
        return $this->db_stats();
    }


    private function db_stats(): array
    {
        global $wpdb;
        $tables_stats = $this->db->get_results("SELECT table_name 'name', round(((data_length + index_length)/1024/1024),2) 'size_mb', avg_row_length
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
                    'avg_row_length' => (int) $table[2],
                ];
            }

        }

        $to_ret = json_decode(json_encode($to_ret), true);

        return array_map(function ($t, $k) {
            $t['name'] = $k;
            return $t;
        }, $to_ret, array_keys($to_ret));
    }

    private function get_memory_budget_bytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === false || $limit === '') {
            $bytes = 256 * 1024 * 1024;
        } else {
            $bytes = wp_convert_hr_to_bytes($limit);
        }
        return (int) ($bytes * 0.4);
    }

    private function calculate_chunk_size(int $avg_row_length): int
    {
        $budget = $this->get_memory_budget_bytes();
        $chunk = (int) ($budget / (max($avg_row_length, 100) * 2));
        return max(500, min(50000, $chunk));
    }

    private function should_separate($table_stat): bool
    {
        return $table_stat['count'] > $this->calculate_chunk_size((int) $table_stat['avg_row_length']);
    }

    /**
     * @throws \Exception
     */
    private function dump_data($table, $dir, $range = null, $chunk_size = null): void
    {
        $dumpSettings = [
            'no-create-info' => true,
            'include-tables' => [$table],
            'skip-comments' => true,
        ];
        $dump = new Mysqldump("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings);
        if (is_array($range)) {
            $limit = $chunk_size ?? (int) WHTHQ_DB_RECORDS_MAX;
            $offset = $range['start'] === 1 ? 0 : ($range['start'] - 1);

            $dump->setTableLimits([
                $table => [$offset, $limit],
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
        $chunk_size = $this->calculate_chunk_size((int) $table['avg_row_length']);
        $ranges = [];
        $start = 1;
        $end = $chunk_size;
        foreach (range(1, ceil($table['count'] / $chunk_size)) as $part) {
            $ranges[] = [
                'start' => $start,
                'end' => $end - ($end === $chunk_size ? 0 : 1),
                'chunk_size' => $chunk_size,
            ];
            $start = $start + $chunk_size;
            $end = $start + $chunk_size;
        }
        return $ranges;
    }

    public function add_to_dump($job): void
    {
        $queue = get_option($job['queue_key']);
        if (!$queue) {
            return;
        }

        $index = $job['index'];
        $current = $queue['jobs'][$index];
        $dir = $queue['dir'];
        $total = $queue['total'];
        $backupFilename = $queue['filename'] . '.gz';
        $callbackUrl = $queue['callbackHeadquarter'];

        if (empty($current['last'])) {
            // Dump this chunk
            $this->dump_data($current['table'], $dir, $current['range'], $current['chunk_size'] ?? null);

            $percent = ceil((($index + 1) / $total) * 100);
            Schedule::call_headquarter_mysql_status($callbackUrl, 2, $percent, $backupFilename, true);

            // Schedule next job in the chain
            $next_index = $index + 1;
            if (isset($queue['jobs'][$next_index])) {
                as_schedule_single_action(time(), 'add_to_dump', [
                    'job' => [
                        'queue_key' => $job['queue_key'],
                        'index' => $next_index,
                    ]
                ], Utils::slugify($queue['group']));
            }
        } else {
            // Finish job
            $this->backupName = $dir . '_dump.sql';

            Schedule::call_headquarter_mysql_status($callbackUrl, 5, 100, $backupFilename);

            Utils::gzCompressFile($this->backupName);
            unlink($this->backupName);

            delete_option($job['queue_key']);

            Schedule::call_headquarter_mysql_ready($callbackUrl, $backupFilename);
        }
    }

    /**
     * @param $dir
     * @param $callback_url
     * @return void
     */
    private function add_finish_job($dir, $callback_url): void
    {
        $queue_key = 'whthq_dump_queue_' . $this->group;
        update_option($queue_key, [
            'jobs' => [['last' => true]],
            'dir' => $dir,
            'filename' => $this->group . '_dump.sql',
            'group' => $this->group,
            'callbackHeadquarter' => $callback_url,
            'total' => 1,
        ], false);

        as_schedule_single_action(time(), 'add_to_dump', [
            'job' => [
                'queue_key' => $queue_key,
                'index' => 0,
            ]
        ], Utils::slugify($this->group));
    }
}
