<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-10
 * Time: 18:03
 */

namespace WhatArmy\Watchtower;

use Exception;
use FilesystemIterator;
use Random\RandomException;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\Finder;
use WhatArmy\Watchtower\Iterators\ErrorHandlingRecursiveDirectoryIterator;

/**
 * Class Utils
 * @package WhatArmy\Watchtower
 */
class Utils
{

    public static function php_version(): string
    {
        preg_match("#^\d+(\.\d+)*#", phpversion(), $match);
        return $match[0];
    }

    /**
     * @param int $length
     * @return string
     * @throws RandomException
     */
    public static function random_string(int $length = 12): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        // Use cryptographically secure random number generation
        if (function_exists('random_int')) {
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            }
        } else {
            // Fallback to mt_rand() if random_int() is not available (still better than rand())
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
            }
        }
        
        return $randomString;
    }

    /**
     * @param $size
     * @return string
     */
    public static function size_human_readable($size): string
    {
        $sizes = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $retstring = '%01.2f %s';
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
    public static function is_json($string): bool
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
    public static function strposa($haystack, $needle, $offset = 0): bool
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
    public static function extract_group_from_filename($filename): string
    {
        if (strpos($filename, '_dump.sql.gz') !== false) {
            return explode('_dump.sql.gz', $filename)[0];
        }
        if (strpos($filename, '.zip') !== false) {
            return explode('.zip', $filename)[0];
        }
        return '';
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
     * @return string
     */
    public static function slugify($text): string
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

    public static function isFuncAvailable($func): bool
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

    public static function createLocalBackupExclusions($clientBackupExclusions, $type = 'dir'): array
    {
        $localBackupExclusions = [
            [
                'type' => 'file',
                'isContentDir' => 1,
                'path' => 'plugins/watchtowerhq/stubs/web.config.stub',
            ],
            [
                'type' => 'dir',
                'isContentDir' => 0,
                'path' => str_replace(ABSPATH, '', WHTHQ_BACKUP_DIR),
            ]];

        $clientBackupExclusions = array_merge($localBackupExclusions, $clientBackupExclusions);

        $ret = [];
        foreach ($clientBackupExclusions as $d) {
            //Skip Entries That Are Only For WPE
            if (!function_exists('is_wpe') && isset($d['onlyWPE']) && $d['onlyWPE']) {
                continue;
            }

            if ($d['isContentDir']) {
                $ret[ WP_CONTENT_DIR . '/' . $d['path']] = $d['type'];
            } else {
                $ret[ABSPATH . $d['path']] = $d['type'];
            }
        }
        return $ret;
    }

    public static function getBackupExclusions($callbackHeadquarterUrl): array
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
                if ($d->isContentDir) {
                    $p = WP_CONTENT_DIR . '/' . $d->path;
                } else {
                    $p = ABSPATH . $d->path;
                }
                $ret[] = $p;
            }
        }

        return $ret;
    }

    static function checkIfIteratorElementIsSameType($iteratorElement, $type)
    {
        return  ($iteratorElement->isDir() ? 'dir' : 'file') === $type;
    }

    static function getFileSystemStructure($baseDir, $excludedPaths): array
    {
        //This Create WHT Backup Directory That Is Required For Fallback During Filesystem Iteration
        Utils::create_backup_dir();

        $filesystem = [];

        // Use the custom IgnorantRecursiveDirectoryIterator to avoid errors on unreadable directories
        $directoryIterator = new ErrorHandlingRecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS);
        $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, function ($path) use ($excludedPaths) {
            $fullPath = $path->getPathname();

            // Skip excluded paths and their subdirectories
            foreach ($excludedPaths as $excluded => $excludedType) {
                if ($fullPath === $excluded && self::checkIfIteratorElementIsSameType($path, $excludedType)) {
                    return false;  //File Or Directory Is Excluded
                }
                if ($excludedType === 'dir' && strpos($fullPath, rtrim($excluded, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
                    return false; // Exclude this path and its children
                }
            }

            return true;  //Include this path
        });

        $filesystemIterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($filesystemIterator as $filesystemEntry) {
            $fullPath = $filesystemEntry->getPathname();
            $isFile = $filesystemEntry->isFile(); // Cache to prevent multiple filesystem call

            // Skip unreadable files
            if ($isFile && !$filesystemEntry->isReadable()) {
                continue;
            }

            // Add to the filesystem array
            $filesystem[] = [
                'type' => $isFile ? 'file' : 'dir',
                'origin' => str_replace(ABSPATH, '', $fullPath),  // Making the path relative
                'filesize' => $isFile ? $filesystemEntry->getSize() : 0
            ];
        }

        return $filesystem;
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
        $possiblePaths = [
            "/usr/bin/mysqldump",
            "/bin/mysqldump",
            "/usr/local/bin/mysqldump",
            "/usr/sfw/bin/mysqldump",
            "/usr/xdg4/bin/mysqldump",
            "/opt/bin/mysqldump",
            "/opt/homebrew/bin/mysqldump",
            "/opt/bitnami/mariadb/bin/mysqldump",
        ];
        foreach ($possiblePaths as $path) {
            if (@is_executable($path)) {
                return $path;
            }
        }
        return false;
    }

    /**
     * @return mixed
     */
    public static function db_size()
    {
        global $wpdb;
        $queryStr = $wpdb->prepare('SELECT ROUND(SUM(((DATA_LENGTH + INDEX_LENGTH)/1024/1024)),2) AS "MB"
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = %s', $wpdb->dbname);
        $query = $wpdb->get_row($queryStr);
        return $query->MB;
    }

public static function selftest():bool
{
    // Initialize cURL
    $ch = curl_init(get_site_url(null,'?rest_route=/wht/v1/test'));
    $postData = ['access_token'=>get_option('watchtower')['access_token']];
    // Set cURL options for POST request
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

    // Ignore HTTPS errors
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    // Follow redirects
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Return the response instead of outputting it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    //Set Useragent To Prevent WordFence Trigger
    curl_setopt($ch, CURLOPT_USERAGENT, "WatchTowerHQ-plugin/self (https://www.watchtowerhq.co/about-crawlers/)");

    // Suppress errors and warnings
    $response = @curl_exec($ch);

    // Check for any errors
    if (curl_errno($ch)) {
        // If an error occurs, return false
        curl_close($ch);
        return false;
    }

    // Close cURL
    curl_close($ch);

    // Check if response is valid JSON
    $decodedResponse = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSON is invalid
        return false;
    }

    // Check if "version" field exists in the response
    if (isset($decodedResponse['version'])) {
        return true;  // Response is valid and contains "version"
    } else {
        return false;  // "version" field is missing
    }
}

    public static function wht_supports_encryption(): bool
    {
        try {
            // Check if OpenSSL extension is loaded
            if (!extension_loaded('openssl')) {
                return false;
            }

            // Confirm AES-256-GCM is available
            $methods = openssl_get_cipher_methods(true);
            if (!in_array('aes-256-gcm', $methods, true)) {
                return false;
            }

            // Safely get the watchtower option
            $watchtower = get_option('watchtower');
            if (!is_array($watchtower) || !isset($watchtower['access_token']) ||
                strlen($watchtower['access_token']) !== 32) {
                return false;
            }

            // Runtime sanity check: encrypt + decrypt a sample message
            $key = $watchtower['access_token'];

            // Generate secure random bytes for IV
            $iv = random_bytes(12);  // 96-bit nonce required by GCM

            $tag = '';  // Will be filled by openssl_encrypt
            $pt = 'probe';

            // Encrypt test message
            $ct = openssl_encrypt($pt, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($ct === false || strlen($tag) !== 16) {
                return false;
            }

            // Decrypt and verify
            $rt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $rt === $pt;
        } catch (Exception $e) {
            // Handle any exceptions (like from random_bytes)
            return false;
        }
    }

    /**
     * Build encrypted payload for request using AES-256-GCM.
     */
    public static function buildEncryptedPayload($data): array
    {
        $plaintext = json_encode($data, JSON_UNESCAPED_SLASHES);
        $key = get_option('watchtower')['access_token'];
        $iv = random_bytes(12); // 96-bit nonce for GCM
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return [
            'encrypted' => true,
            'alg' => 'AES-256-GCM',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    public static function doesContainTransparentEncryptionPayload($incomingPayload): bool
    {
        if(!is_array($incomingPayload)) {
            return false;
        }

        if (!isset($incomingPayload['iv'], $incomingPayload['tag'], $incomingPayload['ciphertext'])) {
            return false;
        }

         return true;
    }
    public static function tryTransparentlyDecryptPayload($incomingData)
    {
       if(!Utils::doesContainTransparentEncryptionPayload($incomingData))
       {
           return $incomingData;
       }

        $iv = base64_decode($incomingData['iv']);
        $tag = base64_decode($incomingData['tag']);
        $ciphertext = base64_decode($incomingData['ciphertext']);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', get_option('watchtower')['access_token'], OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            return null;
        }
        $decoded = json_decode($plaintext, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $incomingData;
    }
}
