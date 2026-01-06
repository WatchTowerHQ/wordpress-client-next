<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-12
 * Time: 23:57
 */

namespace WhatArmy\Watchtower;

/**
 * Class Download
 * @package WhatArmy\Watchtower
 */
class Download
{

    /**
     * Download constructor.
     */
    public function __construct()
    {
        add_filter('query_vars', [$this, 'add_query_vars'], 0);
        add_action('parse_request', [$this, 'sniff_requests'], 0);
        add_action('init', [$this, 'add_endpoint'], 0);
    }

    /**
     * @param $vars
     * @return mixed
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'wht_download';
        $vars[] = 'wht_download_finished';
        $vars[] = 'wht_download_big_object';
        $vars[] = 'wht_download_big_object_origin';
        $vars[] = 'wht_download_big_object_offset';
        $vars[] = 'wht_download_big_object_length';
        $vars[] = 'access_token';
        $vars[] = 'backup_name';
        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule(
            '^wht_download/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download=1&access_token=$matches[1]&backup_name=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^wht_download_finished/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download_finished=1&access_token=$matches[1]&backup_name=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^wht_download_big_object/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download_big_object=1&access_token=$matches[1]&wht_download_big_object_origin=$matches[2]&wht_download_big_object_offset=$matches[3]&wht_download_big_object_length=$matches[4]',
            'top'
        );
    }

    /**
     * @param $token
     * @return bool
     */
    private function has_access($token): bool
    {
        $watchtower_options = get_option('watchtower');
        $access_token = $watchtower_options['access_token'] ?? '';

        if (!is_string($token) || !is_string($access_token)) {
            return false;
        }

        return hash_equals($access_token, $token);
    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['wht_download']) || isset($wp->query_vars['wht_download_finished'])) {
            $this->handle_request();
        } else if (isset($wp->query_vars['wht_download_big_object']) && isset($wp->query_vars['wht_download_big_object_origin'])) {
            $this->handle_big_object_download_request();
        }
    }

    public function access_denied_response()
    {
        http_response_code(401);
        header('content-type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 401,
            'message' => 'File not exist or wrong token',
        ]) . "\n";
    }

    public function file_not_exist_response()
    {
        http_response_code(404);
        header('content-type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 404,
            'message' => 'File not exist',
        ]) . "\n";
    }

    public function handle_big_object_download_request()
    {
        global $wp;
        $file_path = wp_unslash($wp->query_vars['wht_download_big_object_origin']);

        // Validate file path to prevent path traversal attacks and restrict to WP root
        $real_file_path = realpath($file_path);
        $wp_root = realpath(ABSPATH);

        // Ensure the file exists and remains inside the WordPress root
        if ($real_file_path === false || $wp_root === false || strncmp($real_file_path, $wp_root, strlen($wp_root)) !== 0) {
            $this->access_denied_response();
            exit;
        }

        $wp->query_vars['wht_download_big_object_origin'] = $real_file_path;
        $hasAccess = $this->has_access($wp->query_vars['access_token']);
        if ($hasAccess == true) {
            if (file_exists($wp->query_vars['wht_download_big_object_origin'])) {
                if (isset($wp->query_vars['wht_download_big_object_length']) && isset($wp->query_vars['wht_download_big_object_offset'])) {
                    // Cast to integers and guard against negative values
                    $offset = max(0, (int) $wp->query_vars['wht_download_big_object_offset']);
                    $length = max(0, (int) $wp->query_vars['wht_download_big_object_length']);
                    $this->serveObjectFile($wp->query_vars['wht_download_big_object_origin'], $offset, $length);
                } else {
                    $this->serveFile($wp->query_vars['wht_download_big_object_origin']);
                }

            } else {
                $this->file_not_exist_response();
            }
        } else {
            $this->access_denied_response();
        }
        exit;
    }

    /**
     *
     */
    public function handle_request()
    {
        global $wp;
        $hasAccess = $this->has_access($wp->query_vars['access_token']);
        $file = WHTHQ_BACKUP_DIR . '/' . $wp->query_vars['backup_name'];
        if ($hasAccess == true && file_exists($file)) {
            if (isset($wp->query_vars['wht_download_finished'])) {
                unlink($file);
                http_response_code(200);
                header('content-type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 200,
                    'message' => 'OK',
                ]) . "\n";
            } else {
                $this->serveFile($file);
            }

        } else {
            $this->access_denied_response();
        }
        exit;
    }

    /**
     * @param $size
     * @param $timestamp
     */
    protected function sendObjectHeaders($size, $timestamp)
    {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: no-store, no-cache, must-revalidate, no-transform');
        header('Cache-Control: private', false);
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);
        header('Created-Timestamp: ' . $timestamp);
    }

    /**
     * @param $file
     * @param null $name
     * @param $offset
     */
    protected function sendHeaders($file, $offset, $name = null)
    {
        $mime = (strpos($file, '.zip') !== false) ? 'application/zip' : 'application/gzip';
        if ($name == null) {
            $name = basename($file);
        }
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $name . '";');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (filesize($file) - $offset));
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", filemtime($file)) . " GMT");
        header('Accept-Ranges: bytes');
        if ($offset > 0) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $offset . '-' . (filesize($file) - 1) . '/' . (filesize($file) - 1));
        }
    }

    /**
     * @param $file
     * @return int
     */
    protected function resumeTransferOffset($file)
    {
        if (isset($_SERVER['HTTP_RANGE'])) {
            // if the HTTP_RANGE header is set we're dealing with partial content
            preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
            $offset = intval($matches[1]);
        } else {
            $offset = 0;
        }
        return $offset;
    }

    /**
     * Clear all output buffers and disable output buffering.
     * 
     * Removes any BOM or premature output from other plugins that could corrupt
     * the file stream and cause SHA1 hash mismatches during backup operations.
     */
    private function clearBuffersAndDisableBuffering()
    {
        // Discard ALL output buffers (including any BOM or premature output from other plugins)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Disable output buffering for clean streaming
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', 'Off');
        @ini_set('output_buffering', 'Off');
        @ini_set('output_handler', '');
    }

    /**
     * @param $file
     * @param $offset
     * @param $length
     */
    public function serveObjectFile($file, $offset, $length)
    {
        // Clear buffers and disable buffering to prevent BOM contamination
        $this->clearBuffersAndDisableBuffering();

        // Read file content
        $buffer = file_get_contents($file, FALSE, NULL, $offset, $length);

        // Send headers and content
        self::sendObjectHeaders(strlen($buffer), filemtime($file));
        echo $buffer;

        flush();
        exit;
    }

    /**
     * @param $file
     */
    public function serveFile($file)
    {
        // Clear buffers and disable buffering to prevent BOM contamination
        $this->clearBuffersAndDisableBuffering();

        $offset = self::resumeTransferOffset($file);
        self::sendHeaders($file, $offset);

        $download_rate = 600 * 10;
        $handle = fopen($file, 'rb');
        // seek to the requested offset, this is 0 if it's not a partial content request
        if ($offset > 0) {
            fseek($handle, $offset);
        }
        while (!feof($handle)) {
            $buffer = fread($handle, round($download_rate * 1024));
            echo $buffer;
            if (strpos($file, 'sql.gz') === false) {
                wp_ob_end_flush_all();
            }
            flush();
            //use sleep for all non WPE hosting
            if (!function_exists('is_wpe')) {
                sleep(1);
            }
        }
        fclose($handle);
        exit;
    }

}
