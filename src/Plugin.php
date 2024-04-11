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
    public \Plugin_Upgrader $upgrader;

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
        if (!function_exists('show_message')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!class_exists('\Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        $this->upgrader = new \Plugin_Upgrader(new Updater_Skin());
    }

    /**
     * @return array
     */
    public function get(): array
    {
        do_action("wp_update_plugins");
        $plugins = get_plugins();
        $plugins_list = [];
        $updates_list = get_site_transient('update_plugins');
        foreach ($plugins as $name => $details) {
            $plugins_list[] = [
                'name' => $details['Name'],
                'slug' => rtrim($this->get_plugin_slug($name, $details), '-php'),
                'basename' => $name,
                'version' => $details['Version'],
                'is_active' => $this->is_active($name),
                'updates' => $this->check_updates($updates_list->response, $name),
            ];
        }

        return $plugins_list;
    }

    private function get_plugin_slug($name, $details)
    {
        $name = $this->get_name($name);
        $name = $this->get_real_slug($name, $details['PluginURI']);

        return sanitize_title($name);
    }

    private function get_name($name)
    {
        return strstr($name, '/') ? dirname($name) : $name;
    }

    private function get_real_slug($name, $url)
    {
        $slug = $name;
        $match = preg_match('/https?:\/\/wordpress\.org\/(?:extend\/)?(?:plugins|themes)\/([^\/]+)\/?/', $url, $matches);

        if (1 === $match) {
            $slug = $matches[1];
        }

        return sanitize_title($slug);
    }

    /**
     * @param $pluginPath
     * @return bool
     */
    private function is_active($pluginPath): bool
    {
        $is_active = false;
        if (is_plugin_active($pluginPath)) {
            $is_active = true;
        }

        return $is_active;
    }

    /**
     * @param $updates_list
     * @param $pluginPath
     * @return array
     */
    private function check_updates($updates_list, $pluginPath): array
    {
        if (!empty($updates_list)) {
            if (isset($updates_list[$pluginPath])) {
                return [
                    'required' => true,
                    'version' => $updates_list[$pluginPath]->new_version
                ];
            } else {
                return [
                    'required' => false,
                ];
            }
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
