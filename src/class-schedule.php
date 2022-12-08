<?php
/**
 * Author: Code2Prog
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
    public static function call_headquarter($callbackHeadquarterUrl, $backup_name, $file_extension = 'zip')
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
        global $wpdb;

        $group = Utils::extract_group_from_filename($filename);
        if (strpos($filename, '.sql.gz') !== false) {
            self::clean_queue($group, 'add_to_dump');
            if (file_exists(WHTHQ_BACKUP_DIR . '/' . $filename)) {
                unlink(WHTHQ_BACKUP_DIR . '/' . $filename);
            }
            if (file_exists(WHTHQ_BACKUP_DIR . '/' . $group . '_dump_tmp.sql')) {
                unlink(WHTHQ_BACKUP_DIR . '/' . $group . '_dump_tmp.sql');
            }
            if (file_exists(WHTHQ_BACKUP_DIR . '/' . $group . '_dump.sql')) {
                unlink(WHTHQ_BACKUP_DIR . '/' . $group . '_dump.sql');
            }
        }

        if (strpos($filename, '.zip') !== false) {
            $gr = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'actionscheduler_groups WHERE slug =  "' . Utils::slugify($group) . '"');
            $actions = $wpdb->get_results('SELECT action_id,group_id,args  FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE hook = "add_to_zip" AND group_id = "' . $gr->group_id . '"');
            foreach ($actions as $action) {
                unlink(WHTHQ_BACKUP_DIR . '/' . json_decode($action->args)->files->data_file);
            }
            self::clean_queue($group);
        }
    }

    /**
     * @param null $group
     * @param string $hook
     */
    public static function clean_queue($group = null, string $hook = 'add_to_zip')
    {
        global $wpdb;

        if ($group != null) {
            $gr = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'actionscheduler_groups WHERE slug =  "' . Utils::slugify($group) . '"');
            $actions = $wpdb->get_results('SELECT action_id,group_id  FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE hook = "' . $hook . '" AND group_id = "' . $gr->group_id . '"');
            $wpdb->delete($wpdb->prefix . 'actionscheduler_groups', ['group_id' => $gr->group_id]);

        } else {
            $actions = $wpdb->get_results('SELECT action_id,group_id  FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE hook = "' . $hook . '"');
        }
        foreach ($actions as $action) {
            $wpdb->delete($wpdb->prefix . 'actionscheduler_logs', ['action_id' => $action->action_id]);
            $wpdb->delete($wpdb->prefix . 'actionscheduler_actions', ['action_id' => $action->action_id]);
            $wpdb->delete($wpdb->prefix . 'actionscheduler_groups', ['group_id' => $action->group_id]);
        }
    }

    public static function clean_older_than_days($days = 3)
    {
        global $wpdb;

        $actions = $wpdb->get_results(
            'SELECT action_id,group_id  FROM ' . $wpdb->prefix . 'actionscheduler_actions 
            WHERE (hook = "add_to_zip" OR hook = "add_to_dump") AND scheduled_date_gmt < NOW() - INTERVAL ' . $days . ' DAY'
        );

        foreach ($actions as $action) {
            $wpdb->delete($wpdb->prefix . 'actionscheduler_logs', ['action_id' => $action->action_id]);
            $wpdb->delete($wpdb->prefix . 'actionscheduler_actions', ['action_id' => $action->action_id]);
            $wpdb->delete($wpdb->prefix . 'actionscheduler_groups', ['group_id' => $action->group_id]);
        }
    }

    /**
     * @param $status
     * @param null $group
     * @return int
     */
    public static function status($status, $group = null)
    {
        global $wpdb;
        if ($group != null) {
            $gr = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'actionscheduler_groups WHERE slug =  "' . Utils::slugify($group) . '"');
            $results = $wpdb->get_results('SELECT action_id  FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE hook = "add_to_zip" AND status = "' . $status . '" AND group_id = "' . $gr->group_id . '"');

        } else {
            $results = $wpdb->get_results('SELECT action_id  FROM ' . $wpdb->prefix . 'actionscheduler_actions WHERE hook = "add_to_zip" AND status = "' . $status . '"');
        }

        return count($results);
    }
}
