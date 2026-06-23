<?php
/**
 * Author: WhatArmy
 * Date: 2019-06-07
 * Time: 18:30
 */

namespace WhatArmy\Watchtower;

/**
 * Class Schedule
 * @package WhatArmy\Watchtower
 */
class Schedule
{

    /**
     * @param $callbackHeadquarterUrl
     * @param $backup_name
     * @param string $file_extension
     */
    public static function call_headquarter($callbackHeadquarterUrl, $backup_name, string $file_extension = 'zip')
    {
        $headquarter = new Headquarter($callbackHeadquarterUrl);
        $backup_origin = WHTHQ_BACKUP_DIR . '/' . join('.', [$backup_name, $file_extension]);
        $headquarter->call('/backup', [
            'access_token' => get_option('watchtower')['access_token'],
            'backup_name' => join('.', [$backup_name, $file_extension]),
            'backup_md5' => md5_file($backup_origin),
            'memory_limit' => ini_get('memory_limit'),
            'mysql_backup' => ['origin' => str_replace(ABSPATH, '', $backup_origin), 'type' => 'file', 'sha1' => sha1_file($backup_origin), 'filesize' => filesize($backup_origin)]
        ]);
    }

    /**
     * @param $callbackHeadquarterUrl
     * @param $filename
     */
    public static function call_headquarter_mysql_ready($callbackHeadquarterUrl, $filename)
    {
        $headquarter = new Headquarter($callbackHeadquarterUrl);
        $backup_origin = WHTHQ_BACKUP_DIR . '/' . $filename;

        $headquarter->setCurlTimeoutInSeconds(25);
        $headquarter->setRetryDelayMinutes(5);
        $headquarter->setRetryTimes(5);

        $headquarter->retryOnFailure('/incoming/client/wordpress/event', [
            'event_type' =>'mysql_backup_ready',
            'filename' => $filename,
            'memory_limit' =>ini_get('memory_limit'),
            'mysql_backup' => ['origin' => str_replace(ABSPATH, '', $backup_origin), 'type' => 'file', 'sha1' => sha1_file($backup_origin), 'filesize' => filesize($backup_origin)]
        ]);
    }

    /**
     * @param $callbackHeadquarterUrl
     * @param $progress
     * @param $filename
     */
    public static function call_headquarter_mysql_status($callbackHeadquarterUrl, $status_code,  $progress, $filename, $throttle = false)
    {
        if (!$throttle || !get_transient('call_headquarter_mysql_status_lock')) {
            $headquarter = new Headquarter($callbackHeadquarterUrl);

            $headquarter->setCurlTimeoutInSeconds(5);
            $headquarter->setRetryDelaySeconds(30);
            $headquarter->setRetryTimes(2);

            $headquarter->retryOnFailure('/incoming/client/wordpress/event', [
                'event_type' => 'mysql_backup_status',
                'status_code' => $status_code,
                'progress' => $progress,
                'filename' => $filename,
            ]);

            if($throttle)
            {
                // Set a transient to prevent re-triggering for 1 minute
                set_transient('call_headquarter_mysql_status_lock', true, 60);
            }

        }

    }

    /**
     * @param $callbackHeadquarterUrl
     * @param $status
     * @param $filename
     */
    public static function call_headquarter_status($callbackHeadquarterUrl, $status, $filename)
    {
        $headquarter = new Headquarter($callbackHeadquarterUrl);
        $headquarter->call('/backup_status', [
            'access_token' => get_option('watchtower')['access_token'],
            'status' => $status,
            'filename' => $filename,
        ]);
    }

    /**
     * @param $filename
     */
    public static function cancel_queue_and_cleanup($filename)
    {
        $group = Utils::extract_group_from_filename($filename);
        $group_slug = Utils::slugify($group);

        if (strpos($filename, '.sql.gz') !== false) {
            as_unschedule_all_actions('add_to_dump', [], $group_slug);
            delete_option('whthq_dump_queue_' . $group);
            foreach ([
                WHTHQ_BACKUP_DIR . '/' . $filename,
                WHTHQ_BACKUP_DIR . '/' . $group . '_dump_tmp.sql',
                WHTHQ_BACKUP_DIR . '/' . $group . '_dump.sql',
            ] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        if (strpos($filename, '.zip') !== false) {
            $actions = as_get_scheduled_actions([
                'hook' => 'add_to_zip',
                'group' => $group_slug,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ]);
            foreach ($actions as $action) {
                $args = $action->get_args();
                if (isset($args['files']['data_file'])) {
                    $file = WHTHQ_BACKUP_DIR . '/' . $args['files']['data_file'];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            as_unschedule_all_actions('add_to_zip', [], $group_slug);
        }
    }

    /**
     * @param null $group
     * @param string $hook
     */
    public static function clean_queue($group = null, string $hook = 'add_to_zip')
    {
        if ($group !== null) {
            as_unschedule_all_actions($hook, [], Utils::slugify($group));
        } else {
            as_unschedule_all_actions($hook);
        }
    }

    public static function clean_older_than_days($days = 3)
    {
        $store = \ActionScheduler_Store::instance();
        $cutoff = as_get_datetime_object(gmdate('U') - ($days * DAY_IN_SECONDS));

        foreach (['add_to_zip', 'add_to_dump'] as $hook) {
            $action_ids = $store->query_actions([
                'hook' => $hook,
                'date' => $cutoff->format('Y-m-d H:i:s'),
                'date_compare' => '<=',
                'per_page' => -1,
            ]);
            foreach ($action_ids as $action_id) {
                try {
                    $store->delete_action($action_id);
                } catch (\Exception $e) {
                    // Action may already be deleted
                }
            }
        }
    }

    /**
     * @param $status
     * @param null $group
     * @return int
     */
    public static function status($status, $group = null): int
    {
        $store = \ActionScheduler_Store::instance();
        $args = [
            'hook' => 'add_to_zip',
            'status' => $status,
            'per_page' => -1,
        ];
        if ($group !== null) {
            $args['group'] = Utils::slugify($group);
        }
        return (int) $store->query_actions($args, 'count');
    }
}
