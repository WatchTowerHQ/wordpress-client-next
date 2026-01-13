<?php

namespace WhatArmy\Watchtower;

if (!defined('ABSPATH')) {
    exit;
}

use SplFileObject;

/**
 * Class Debug_Log
 * @package WhatArmy\Watchtower
 */
class Debug_Log
{
    /**
     * Get lines from the WordPress debug.log file with pagination support
     * 
     * @param int $limit Number of lines to retrieve (default: 100)
     * @param int $offset Number of lines to skip from the end (default: 0)
     * @return array
     */
    public function get(int $limit = 100, int $offset = 0): array
    {
        $log_file = $this->get_log_file_path();

        if (!file_exists($log_file)) {
            return [
                'enabled' => $this->is_logging_enabled(),
                'log_file' => $log_file,
                'exists' => false,
                'total_lines' => 0,
                'entries' => []
            ];
        }

        $result = $this->read_log_entries($log_file, $limit, $offset);

        return [
            'enabled' => $this->is_logging_enabled(),
            'log_file' => $log_file,
            'exists' => true,
            'size' => filesize($log_file),
            'modified' => filemtime($log_file),
            'total_lines' => $result['total_lines'],
            'entries' => $result['entries']
        ];
    }

    /**
     * Check if WordPress logging is enabled
     * 
     * @return bool
     */
    private function is_logging_enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }

    /**
     * Get the path to the debug.log file
     * 
     * @return string
     */
    private function get_log_file_path(): string
    {
        // Check if WP_DEBUG_LOG is a custom path
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG)) {
            return WP_DEBUG_LOG;
        }

        // Default location
        return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : '';
    }

    /**
     * Read lines from the log file with pagination support
     * 
     * @param string $file_path
     * @param int $limit Number of lines to retrieve
     * @param int $offset Number of lines to skip from the end
     * @return array ['entries' => array, 'total_lines' => int]
     */
    private function read_log_entries(string $file_path, int $limit, int $offset): array
    {
        if (!is_readable($file_path)) {
            return ['entries' => [], 'total_lines' => 0];
        }

        // For large files, read from the end
        $file = new SplFileObject($file_path, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;

        // Calculate which lines to read
        // offset = 0 means get the last N lines
        // offset = 100 means skip the last 100 lines, then get the next N lines
        $end_line = $total_lines - $offset;
        $start_line = max(0, $end_line - $limit);

        $entries = [];
        $file->seek($start_line);

        $current_line = $start_line;
        while (!$file->eof() && $current_line < $end_line) {
            $line = $file->fgets();
            if ($line && trim($line) !== '') {
                $entries[] = rtrim($line);
            }
            $current_line++;
        }

        return [
            'entries' => $entries,
            'total_lines' => $total_lines
        ];
    }
}
