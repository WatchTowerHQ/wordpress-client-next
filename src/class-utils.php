<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-10
 * Time: 18:03
 */

namespace WhatArmy\Watchtower;

use http\Exception\RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Class Utils
 * @package WhatArmy\Watchtower
 */
class Utils
{

    public static function php_version()
    {
        preg_match("#^\d+(\.\d+)*#", phpversion(), $match);
        return $match[0];
    }

    /**
     * @param int $length
     * @return string
     */
    public static function random_string($length = 12)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param $size
     * @return string
     */
    public static function size_human_readable($size)
    {
        $sizes = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $retstring = '%01.2f %s';
        if ($retstring === null) {
            $retstring = '%01.2f %s';
        }
        $lastsizestring = end($sizes);
        foreach ($sizes as $sizestring) {
            if ($size < 1024) {
                break;
            }
            if ($sizestring != $lastsizestring) {
                $size /= 1024;
            }
        }
        if ($sizestring == $sizes[0]) {
            $retstring = '%01d %s';
        } // Bytes aren't normally fractional
        return sprintf($retstring, $size, $sizestring);
    }

    /**
     * @param $string
     * @return bool
     */
    public static function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $haystack
     * @param $needle
     * @param int $offset
     * @return bool
     */
    public static function strposa($haystack, $needle, $offset = 0)
    {
        if (!is_array($needle)) {
            $needle = [$needle];
        }
        foreach ($needle as $query) {
            if (strpos($haystack, $query, $offset) !== false) {
                return true;
            } // stop on first true result
        }
        return false;
    }

    /**
     * @param $filename
     * @return string
     */
    public static function extract_group_from_filename($filename)
    {
        if (strpos($filename, '_dump.sql.gz') !== false) {
            return explode('_dump.sql.gz', $filename)[0];
        }
        if (strpos($filename, '.zip') !== false) {
            return explode('.zip', $filename)[0];
        }

    }

    /**
     * @param $needle
     * @param $replace
     * @param $haystack
     * @return string|string[]
     */
    public static function str_replace_once($needle, $replace, $haystack)
    {
        $pos = strpos($haystack, $needle);
        return (false !== $pos) ? substr_replace($haystack, $replace, $pos, strlen($needle)) : $haystack;
    }

    /**
     * @param $path
     * @param float|int $ms
     */
    public static function cleanup_old_backups($path, $ms = 60 * 60 * 12)
    {
        Schedule::clean_older_than_days(2);
        foreach (glob($path . '/*') as $file) {
            if (is_file($file)) {
                if (time() - filemtime($file) >= $ms) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * @param $text
     * @return false|string|string[]|null
     */
    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // trim
        $text = trim($text, '-');
        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // lowercase
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }

    public static function create_backup_dir()
    {
        if (!file_exists(WHTHQ_BACKUP_DIR)) {
            mkdir(WHTHQ_BACKUP_DIR, 0777, true);
        }
        if (!file_exists(WHTHQ_BACKUP_DIR . '/index.html')) {
            @file_put_contents(WHTHQ_BACKUP_DIR . '/index.html',
                file_get_contents(plugin_dir_path(WHTHQ_MAIN) . '/stubs/index.html.stub'));
        }
        if (!file_exists(WHTHQ_BACKUP_DIR . '/.htaccess')) {
            @file_put_contents(WHTHQ_BACKUP_DIR . '/.htaccess',
                file_get_contents(plugin_dir_path(WHTHQ_MAIN) . '/stubs/htaccess.stub'));
        }
        if (!file_exists(WHTHQ_BACKUP_DIR . '/web.config')) {
            @file_put_contents(WHTHQ_BACKUP_DIR . '/web.config',
                file_get_contents(plugin_dir_path(WHTHQ_MAIN) . '/stubs/web.config.stub'));
        }
    }

    public static function gzCompressFile($source, $level = 9)
    {
        $dest = $source . '.gz';
        $mode = 'wb' . $level;
        $error = false;
        if ($fp_out = gzopen($dest, $mode)) {
            if ($fp_in = fopen($source, 'rb')) {
                while (!feof($fp_in)) {
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                }
                fclose($fp_in);
            } else {
                $error = true;
            }
            gzclose($fp_out);
        } else {
            $error = true;
        }
        if ($error) {
            return false;
        } else {
            return $dest;
        }
    }

    public static function isFuncAvailable($func)
    {
        if (ini_get('safe_mode')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if ($disabled) {
            $disabled = explode(',', $disabled);
            $disabled = array_map('trim', $disabled);
            return !in_array($func, $disabled);
        }
        return true;
    }
public static function createLocalBackupExclusions($clientBackupExclusions)
{
    $ret = [];
    foreach ($clientBackupExclusions as $d) {
        if ($d['isContentDir'] == true) {
            $p = WP_CONTENT_DIR . '/' . $d['path'];
        } else {
            $p = ABSPATH . $d['path'];
        }
        array_push($ret, $p);
    }
    return $ret;
}
    public static function getBackupExclusions($callbackHeadquarterUrl)
    {
        $arrContextOptions = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];
        $data = file_get_contents($callbackHeadquarterUrl . WHTHQ_BACKUP_EXCLUSIONS_ENDPOINT, false,
            stream_context_create($arrContextOptions));
        $ret = [];

        if (Utils::is_json($data)) {
            foreach (json_decode($data) as $d) {
                if ($d->isContentDir == true) {
                    $p = WP_CONTENT_DIR . '/' . $d->path;
                } else {
                    $p = ABSPATH . $d->path;
                }
                array_push($ret, $p);
            }
        }

        return $ret;
    }

    public static function allFilesList($excludes = []): Finder
    {
        $finder = new Finder();
        $finder->in(ABSPATH);
        $finder->followLinks(false);
        $finder->ignoreDotFiles(false);
        $finder->ignoreVCS(true);
        $finder->ignoreUnreadableDirs(true);
        return $finder->filter(
            function (\SplFileInfo $file) use ($excludes) {
                $path = $file->getPathname();
                if (!$file->isReadable() || Utils::strposa($path, $excludes) || strpos($path, WHTHQ_BACKUP_DIR_NAME)) {
                    return false;
                }
            }
        );
    }

    public static function detectMysqldumpLocation()
    {
        $mysqldump = `which mysqldump`;
        if (is_executable($mysqldump)) {
            return $mysqldump;
        }
        $mysqldump = dirname(`which mysql`) . "/mysqldump";
        if (is_executable($mysqldump)) {
            return $mysqldump;
        }
        $available = array(
            '/usr/bin/mysqldump', // Linux
            '/usr/local/mysql/bin/mysqldump', //Mac OS X
            '/usr/local/bin/mysqldump', //Linux
            '/usr/mysql/bin/mysqldump' //Linux
        );
        foreach ($available as $apath) {
            if (is_executable($apath)) {
                return $apath;
            }
        }
        return 'n/a';
    }
}
