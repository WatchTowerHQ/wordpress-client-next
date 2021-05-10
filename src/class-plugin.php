<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 16:05
 */

namespace WhatArmy\Watchtower;

/**
 * Class Plugin
 * @package WhatArmy\Watchtower
 */
class Plugin
{
    public $upgrader;

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
        if (!function_exists('show_message')) {
            require_once ABSPATH.'wp-admin/includes/misc.php';
        }
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH.'wp-admin/includes/file.php';
        }

        if (!class_exists('\Plugin_Upgrader')) {
            require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        }

        $this->upgrader = new \Plugin_Upgrader(new Updater_Skin());
    }

    /**
     * @return array
     */
    public function get()
    {
        $plugins = get_plugins();
        $plugins_list = [];
        foreach ($plugins as $plugin_path => $plugin) {
            array_push($plugins_list, [
                'name'      => $plugin['Name'],
                'slug'      => plugin_basename(plugin_dir_path($plugin_path)),
                'basename'  => $plugin_path,
                'version'   => $plugin['Version'],
                'is_active' => $this->is_active($plugin_path),
                'updates'   => $this->check_updates($plugin_path),
            ]);
        }

        return $plugins_list;
    }

    /**
     * @param $pluginPath
     * @return bool
     */
    private function is_active($pluginPath)
    {
        $is_active = false;
        if (is_plugin_active($pluginPath)) {
            $is_active = true;
        }

        return $is_active;
    }

    /**
     * @param $pluginPath
     * @return array
     */
    private function check_updates($pluginPath)
    {
        do_action("wp_update_plugins");
        $list = get_site_transient('update_plugins');
        if (array_key_exists($pluginPath, $list->response)) {
            return [
                'required' => true,
                'version'  => $list->response[$pluginPath]->new_version
            ];
        } else {
            return [
                'required' => false,
            ];
        }
    }

    /**
     * @param $plugins
     * @return array|false
     */
    public function doUpdate($plugins)
    {
        $plugins = explode(',', $plugins);
        return $this->upgrader->bulk_upgrade($plugins);
    }
}
