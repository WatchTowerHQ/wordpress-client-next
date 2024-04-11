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

    /**
     * Updates_Monitor constructor.
     */
    public function __construct()
    {
        add_action('_core_updated_successfully', [&$this, 'core_updated_successfully']);
        add_action('activated_plugin', [&$this, 'hooks_activated_plugin']);
        add_action('deactivated_plugin', [&$this, 'hooks_deactivated_plugin']);
        add_action('upgrader_process_complete', [&$this, 'hooks_plugin_install_or_update'], 10, 2);
        $this->isMultisite = is_multisite();
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
            Utils::set_wht_branding();
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

                if($slug === 'watchtowerhq')
                {
                    file_put_contents(wp_upload_dir()['basedir'] . '/update.json','bla');
                }
            }
        }
    }

    public function cleanup_old(int $months = 12)
    {

    }
}
