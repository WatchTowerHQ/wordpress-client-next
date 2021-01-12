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

    public function add_query_vars($vars)
    {
        $vars[] = 'wht_download';
        $vars[] = 'access_token';
        $vars[] = 'backup_name';

        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule('^wht_download/?([a-zA-Z0-9]+)?/?([a-zA-Z]+)?/?',
            'index.php?wht_download=1&access_token=$matches[1]&backup_name=$matches[2]', 'top');

    }

    private function has_access($token)
    {
        if ($token == get_option('watchtower')['access_token']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['wht_download'])) {
            $this->handle_request();
        }
    }

    public function handle_request()
    {
        global $wp;
        $hasAccess = $this->has_access($wp->query_vars['access_token']);
        $file = WHTHQ_BACKUP_DIR.'/'.$wp->query_vars['backup_name'];
        if ($hasAccess == true && file_exists($file)) {
            $this->serveFile($file);
        } else {
            http_response_code(401);
            header('content-type: application/json; charset=utf-8');
            echo json_encode([
                    'status'  => 401,
                    'message' => 'File not exist or wrong token',
                ])."\n";
        }
        exit;
    }

    /**
     * @param $file
     * @param  null  $name
     */
    protected function sendHeaders($file, $name = null)
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
        header('Content-Disposition: attachment; filename="'.$name.'";');
        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($file));
    }

    /**
     * @param $file
     */
    public function serveFile($file)
    {
        self::sendHeaders($file);
        $download_rate = 600 * 10;
        $handle = fopen($file, 'r');
        while (!feof($handle)) {
            $buffer = fread($handle, round($download_rate * 1024));
            echo $buffer;
            if (strpos($file, 'sql.gz') === false) {
                @ob_end_flush();
            }
            flush();
            sleep(1);
        }
        fclose($handle);
        unlink($file);
        exit;
    }

}
