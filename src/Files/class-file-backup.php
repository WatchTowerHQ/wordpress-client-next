<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 16:03
 */

namespace WhatArmy\Watchtower\Files;


use SplFileObject;
use Symfony\Component\Finder\Finder;
use WhatArmy\Watchtower\Schedule;
use WhatArmy\Watchtower\Utils;
use ZipArchive;

/**
 * Class Backup
 * @package WhatArmy\Watchtower
 */
class File_Backup
{
    public $backupName;

    /**
     * Backup constructor.
     */
    public function __construct()
    {
        $this->backupName = date('Y_m_d__H_i_s') . "_" . Utils::random_string();
        add_action('add_to_zip', [$this, 'add_to_zip']);
    }

    public function poke_queue()
    {
        //todo: to implementation
    }

    /**
     * @param $job
     */
    public function add_to_zip($job)
    {
        if (defined('WPE_ISP')) {
            ini_set('memory_limit', '512M');
        }
        $archive_location = WHTHQ_BACKUP_DIR . '/' . $job['zip'] . '.zip';
        $zippy = new ZipArchive();
        if (!file_exists($archive_location)) {
            $zippy->open($archive_location, ZipArchive::CREATE);
        } else {
            $zippy->open($archive_location);
        }

        $fileList = json_decode(file_get_contents(WHTHQ_BACKUP_DIR . '/' . $job['data_file']));

        foreach ($fileList as $file) {
            $zippy->addFile(ABSPATH . $file, $file);
        }
        $zippy->close();


        $failed = Schedule::status('failed', $job['zip']);
        $pending = Schedule::status('pending', $job['zip']);
        $in_progress = Schedule::status('in-progress', $job['zip']);
        unlink(WHTHQ_BACKUP_DIR . '/' . $job['data_file']); //remove
        if ($failed == 0 && $pending == 0 && $in_progress == 1 && $job['last'] == true) {
            $this->backupName = $job['zip'];
            Schedule::clean_queue($job['zip']);
            Schedule::call_headquarter($job['callbackHeadquarter'], $this->backupName);
        }

        Schedule::call_headquarter_status($job['callbackHeadquarter'], $job['queue'], $job['zip'] . '.zip');
    }


    /**
     * @param $callbackHeadquarterUrl
     * @return array
     */
    private function exclusions($callbackHeadquarterUrl)
    {
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        $data = file_get_contents($callbackHeadquarterUrl . WHTHQ_BACKUP_EXCLUSIONS_ENDPOINT, false,
            stream_context_create($arrContextOptions));
        $ret = array();

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

    /**
     * @param $callbackHeadquarterUrl
     * @return $this
     */
    private function create_job_list($callbackHeadquarterUrl)
    {
        $jobFile = WHTHQ_BACKUP_DIR . '/backup.job';

        if (file_exists($jobFile)) {
            unlink($jobFile);
        }

        $excludes = $this->exclusions($callbackHeadquarterUrl);
        $finder = new Finder();
        $finder->in(ABSPATH);
        $finder->followLinks(false);
        $finder->ignoreDotFiles(false);
        $finder->ignoreVCS(true);
        $finder->ignoreUnreadableDirs(true);
        // Skip unreadable files too
        $files = $finder->filter(
            function (\SplFileInfo $file) use ($excludes) {
                $path = $file->getPathname();
                if (!$file->isReadable() || Utils::strposa($path, $excludes) || strpos($path, WHTHQ_BACKUP_DIR_NAME)) {
                    return false;
                }
            }
        );


        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            $path = $file->getPathname();
            file_put_contents($jobFile, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        return $this;
    }

    /**
     * @param $name
     * @param $data
     * @return mixed
     */
    private function create_job_part_file($name, $data)
    {
        if (!file_exists(WHTHQ_BACKUP_DIR . "/" . $name)) {
            file_put_contents(WHTHQ_BACKUP_DIR . '/' . $name, json_encode($data));
        }

        return $name;
    }

    /**
     * @param $callback_url
     * @return string
     */
    public function run($callback_url)
    {
        Utils::cleanup_old_backups(WHTHQ_BACKUP_DIR);
        Utils::create_backup_dir();

        $this->create_job_list($callback_url);

        $jobTotal = $this->job_count();
        $file = new SplFileObject(WHTHQ_BACKUP_DIR . "/backup.job", "r");
        $ct = 0;
        $arr = [];
        $par = 1;
        while (!$file->eof()) {
            $f = str_replace(ABSPATH, "", $file->fgets());
            if ($f != '') {
                array_push($arr, trim($f));
                $ct++;
            }

            if ($ct == WHTHQ_BACKUP_FILES_PER_QUEUE) {
                as_schedule_single_action(time(), 'add_to_zip', [
                    'files' => [
                        "data_file" => $this->create_job_part_file('part_' . Utils::random_string(6), $arr),
                        "zip" => $this->backupName,
                        "last" => false,
                        "callbackHeadquarter" => $callback_url,
                        "queue" => $par . "/" . $jobTotal,
                    ]
                ], Utils::slugify($this->backupName));
                $par++;
                $arr = [];
                $ct = 0;
            }
            if ($file->eof()) {
                as_schedule_single_action(time(), 'add_to_zip', [
                    'files' => [
                        "data_file" => $this->create_job_part_file('part_' . Utils::random_string(6), $arr),
                        "zip" => $this->backupName,
                        "last" => true,
                        "callbackHeadquarter" => $callback_url,
                        "queue" => $par . "/" . $jobTotal,
                    ]
                ], Utils::slugify($this->backupName));
                $arr = [];
                $ct = 0;
            }
        }
        $file = null;

        return $this->backupName;
    }

    /**
     * @return int
     */
    public function job_count()
    {
        $file = new SplFileObject(WHTHQ_BACKUP_DIR . "/backup.job", 'r');
        $file->seek(PHP_INT_MAX);
        $sum = ($file->key() + 1) / WHTHQ_BACKUP_FILES_PER_QUEUE;
        return ceil($sum);
    }

}
