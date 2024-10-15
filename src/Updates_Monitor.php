<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-13
 * Time: 17:46
 */

namespace WhatArmy\Watchtower;


class Updates_Monitor
{
    public $isMultisite;
    private int $updateNotificationGapInSeconds = 600;

    /**
     * Updates_Monitor constructor.
     */
    public function __construct()
    {
        add_action('_core_updated_successfully', [&$this, 'core_updated_successfully']);
        add_action('activated_plugin', [&$this, 'hooks_activated_plugin']);
        add_action('deactivated_plugin', [&$this, 'hooks_deactivated_plugin']);
        add_action('upgrader_process_complete', [&$this, 'hooks_plugin_install_or_update'], 10, 2);

        // Add hooks to listen for when the update checks are completed
        add_action('set_site_transient_update_plugins', [&$this, 'handle_set_site_transient_update_plugins']);
        add_action('set_site_transient_update_themes', [&$this, 'handle_set_site_transient_update_themes']);
        add_action('set_site_transient_update_core', [&$this, 'handle_set_site_transient_update_core']);
        
        $this->isMultisite = is_multisite();
    }

    private function notify_wht_headquarter_about_updates($update_type, $to_update)
    {
        $headquarters = get_option('whthq_headquarters', []);
        foreach ($headquarters as $callback => $last_used) {
            if (!empty($callback) && !empty($last_used) && ($last_used >= time() - WHTHQ_MAX_HEADQUARTER_IDLE_TIME_SECONDS)) {
                $headquarter = new Headquarter($callback);
                $headquarter->setCurlTimeoutInSeconds(5);
                $headquarter->setRetryDelaySeconds(180);
                $headquarter->setRetryTimes(3);
                $headquarter->retryOnFailure('/incoming/client/wordpress/event', [
                    'event_type' => 'updates_available',
                    'update_type' => $update_type,
                    'require_update' => $to_update,
                ]);
            }
        }
    }

    public function handle_set_site_transient_update_plugins()
    {
        $plugin_updates = get_site_transient('update_plugins');
        if (!empty($plugin_updates->response)) {
            $plugins_to_update = [];
            foreach ($plugin_updates->response as $plugin_basename => $update_info) {

                $plugins_to_update[] = [
                    'basename' => $plugin_basename,
                    'new_version' => $update_info->new_version,
                ];
            }

            $cache_key = 'wht_plugins_updates_' . sha1(serialize($plugins_to_update));

            if (get_transient($cache_key) === false) {
                $this->notify_wht_headquarter_about_updates('plugins',$plugins_to_update);
                set_transient($cache_key, true, $this->updateNotificationGapInSeconds);
            }

        }
    }

    public function handle_set_site_transient_update_themes()
    {
        $theme_updates = get_site_transient('update_themes');
        if (!empty($theme_updates->response)) {
            $themes_to_update = [];
            foreach ($theme_updates->response as $theme => $update_info) {
                $themes_to_update[] = [
                    'theme' => $theme,
                    'new_version' => $update_info['new_version'],
                ];
            }

            $cache_key = 'wht_themes_updates_' . sha1(serialize($themes_to_update));
            if (get_transient($cache_key) === false) {
                $this->notify_wht_headquarter_about_updates('themes',$themes_to_update);
                set_transient($cache_key, true, $this->updateNotificationGapInSeconds);
            }

        }
    }

    public function handle_set_site_transient_update_core()
    {
        $core_updates = get_site_transient('update_core');

        $core_to_update = [];
        if (!empty($core_updates->updates)) {
            foreach ($core_updates->updates as $update) {
                if ($update->response == 'upgrade') {
                    $core_to_update[] = [
                        'new_version' => $update->current];
                }
            }

            //Need To Check If There Are Actual Updates Because WP Return Download Link To Same Version
            if(!empty($core_to_update)) {
                $cache_key = 'wht_core_updates_' . sha1(serialize($core_to_update));
                if (get_transient($cache_key) === false) {
                    $this->notify_wht_headquarter_about_updates('core', $core_to_update);
                    set_transient($cache_key, true, $this->updateNotificationGapInSeconds);
                }
            }

        }
    }

    /**
     * Insert Logs to DB
     *
     * @param $data
     */
    private function insertLog($data)
    {
        global $wpdb;

        if (is_multisite()) {
            $old_blog = $wpdb->blogid;
            $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogs as $blog_id) {
                switch_to_blog($blog_id);
                (new User_Logs())->insert($data['action'],$data['who']);
            }
            switch_to_blog($old_blog);
        } else {
            (new User_Logs())->insert($data['action'],$data['who']);
        }

    }

    /**
     * Core Update
     *
     * @param $wp_version
     */
    public function core_updated_successfully($wp_version)
    {
        global $pagenow;

        // Auto updated
        if ('update-core.php' !== $pagenow) {
            $object_name = 'WordPress Auto Updated |' . $wp_version;
            $who = 0;
        } else {
            $object_name = 'WordPress Updated | ' . $wp_version;
            $who = get_current_user_id();
        }

        $this->insertLog([
            'who' => $who,
            'action' => $object_name,
        ]);

    }


    /**
     * @param $action
     * @param $plugin_name
     */
    protected function _add_log_plugin($action, $plugin_name)
    {
        // Get plugin name if is a path
        if (false !== strpos($plugin_name, '/')) {
            $plugin_dir = explode('/', $plugin_name);
            $plugin_data = array_values(get_plugins('/' . $plugin_dir[0]));
            $plugin_data = array_shift($plugin_data);
            $plugin_name = $plugin_data['Name'];
        }

        $this->insertLog([
            'who' => get_current_user_id(),
            'action' => $action . ' ' . $plugin_name,
        ]);
    }

    /**
     * @param $plugin_name
     */
    public function hooks_deactivated_plugin($plugin_name)
    {
        $this->_add_log_plugin('Deactivated', $plugin_name);
    }

    /**
     * @param $plugin_name
     */
    public function hooks_activated_plugin($plugin_name)
    {
        $this->_add_log_plugin('Activated', $plugin_name);
    }

    /**
     * @param $upgrader
     * @param $extra
     */
    public function hooks_plugin_install_or_update($upgrader, $extra)
    {
        if (!isset($extra['type']) || 'plugin' !== $extra['type']) {
            return;
        }

        if ('install' === $extra['action']) {
            $path = $upgrader->plugin_info();
            if (!$path) {
                return;
            }

            $data = get_plugin_data($upgrader->skin->result['local_destination'] . '/' . $path, true, false);

            $this->insertLog([
                'who' => get_current_user_id(),
                'action' => 'Installed Plugin: ' . $data['Name'] . ' | Ver.' . $data['Version'],
            ]);


            if ($path === 'watchtowerhq/watchtowerhq.php') {
                wp_schedule_single_event(time() + 1, 'wht_branding_set_hook', [wp_generate_password(10)]);
            }
        }

        if ('update' === $extra['action']) {
            if (isset($extra['bulk']) && true == $extra['bulk']) {
                $slugs = $extra['plugins'];
            } else {
                if (!isset($upgrader->skin->plugin)) {
                    return;
                }

                $slugs = [$upgrader->skin->plugin];
            }

            foreach ($slugs as $slug) {
                $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug, true, false);

                $this->insertLog([
                    'who' => get_current_user_id(),
                    'action' => 'Updated Plugin: ' . $data['Name'] . ' | Ver.' . $data['Version'],
                ]);

                if ($slug === 'watchtowerhq') {
                    wp_schedule_single_event(time() + 1, 'wht_branding_set_hook', [wp_generate_password(10)]);
                }
            }
        }
    }

    public function cleanup_old(int $months = 12)
    {

    }
}
