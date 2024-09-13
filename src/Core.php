<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 17:31
 */

namespace WhatArmy\Watchtower;

/**
 * Class Core
 * @package WhatArmy\Watchtower
 */
class Core
{
    public array $plugin_data;

    /**
     * Core constructor.
     */
    public function __construct()
    {
        $this->plugin_data = $this->plugin_data();
    }

    /**
     * @return array
     */
    private function plugin_data(): array
    {
        $main_file = explode('/', plugin_basename(WHTHQ_MAIN))[1];
        return get_plugin_data(plugin_dir_path(WHTHQ_MAIN) . $main_file);
    }

    /**
     * @return mixed
     */
    public function wht_plugin_version()
    {
        return $this->plugin_data['Version'];
    }

    /**
     * @return array
     */
    public function test(): array
    {
        return [
            'version' => $this->wht_plugin_version(),
        ];
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return [
            'site_name' => get_option('blogname'),
            'site_description' => get_option('blogdescription'),
            'site_url' => get_site_url(),
            'is_multisite' => (is_multisite() == true ? 'true' : 'false'),
            'template' => get_option('template'),
            'wp_version' => get_bloginfo('version'),
            'admin_email' => get_option('admin_email'),
            'php_version' => Utils::php_version(),
            'updates' => $this->check_updates(),
            'is_public' => get_option('blog_public'),
            'installation_size' => $this->installation_file_size(),
            'comments' => wp_count_comments(),
            'comments_allowed' => get_default_comment_status() == 'open',
            'site_ip' => $this->external_ip(),
            'db_size' => Utils::db_size(),
            'timezone' => [
                'gmt_offset' => get_option('gmt_offset'),
                'string' => get_option('timezone_string'),
                'server_timezone' => date_default_timezone_get(),
            ],
            'admins_list' => $this->admins_list(),
            'users' => count_users(),
            'admin_url' => admin_url(),
            'content_dir' => (defined('WP_CONTENT_DIR')) ? WP_CONTENT_DIR : false,
            'pwp_name' => (defined('PWP_NAME')) ? PWP_NAME : false,
            'wpe_auth' => (defined('WPE_APIKEY')) ? md5('wpe_auth_salty_dog|' . WPE_APIKEY) : false,
            'system_info' => [
                'system_command' => Utils::isFuncAvailable('system'),
                'mysql_dump_location' => Utils::detectMysqldumpLocation() ? Utils::detectMysqldumpLocation() : 'n/a',
                'php_version' => Utils::php_version(),
            ],
            'branding_revision' => Utils::get_wht_branding('BrandingRevision','N/A'),
            'debug' => [
                'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
                'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
                'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            ]
        ];
    }

    /**
     * @return array
     */
    private function check_updates(): array
    {
        global $wp_version;
        do_action("wp_version_check"); // force WP to check its core for updates
        $update_core = get_site_transient("update_core"); // get information of updates
        if ('upgrade' == $update_core->updates[0]->response) {
            require_once(ABSPATH . WPINC . '/version.php');
            $new_core_ver = $update_core->updates[0]->current; // The new WP core version
            return [
                'required' => true,
                'new_version' => $new_core_ver,
            ];

        } else {
            return [
                'required' => false,
            ];
        }
    }

    /**
     * @param string $path
     * @param bool $humanReadable
     * @return int|string
     */
    public function installation_file_size($path = ABSPATH, $humanReadable = true)
    {
        $bytesTotal = 0;
        $path = realpath($path);
        if ($path !== false && $path != '' && file_exists($path)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,
                \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD) as $object) {
                if (strpos($object->getPath(), WHTHQ_BACKUP_DIR_NAME) == false && $object->isFile()) {
                    $bytesTotal += $object->getSize();
                }
            }
        }
        if ($humanReadable == true) {
            $bytesTotal = Utils::size_human_readable($bytesTotal);
        }
        return $bytesTotal;
    }

    /**
     * @return false|string|null
     */
    public function external_ip()
    {
        try {
            return file_get_contents("https://api.ipify.org", 0, stream_context_create(["http" => ["timeout" => 10]]));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function get_last_login($user_id) {
        $last_login = get_user_meta($user_id, 'wht_user_last_login', true);
        if ($last_login) {
            return $last_login;
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function db_size()
    {
        global $wpdb;
        $queryStr = 'SELECT  ROUND(SUM(((DATA_LENGTH + INDEX_LENGTH)/1024/1024)),2) AS "MB"
        FROM INFORMATION_SCHEMA.TABLES
	WHERE TABLE_SCHEMA = "' . $wpdb->dbname . '";';
        $query = $wpdb->get_row($queryStr);
        return $query->MB;
    }

    /**
     * @return array
     */
    public function admins_list(): array
    {
        $admins_list = get_users('role=administrator');
        $admins = [];
        foreach ($admins_list as $admin) {
            $admins[] = [
                'login' => $admin->user_login,
                'email' => $admin->user_email,
                'display_name' => $admin->display_name,
                'registered_at' => $admin->user_registered,
                'last_seen_at' => $this->get_last_login($admin->ID),
            ];
        }
        return $admins;
    }


    public function upgrade()
    {
        if (!function_exists('show_message')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('find_core_update')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (!class_exists('WP_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (!class_exists('Core_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';
        }
        if (!class_exists('Automatic_Upgrader_Skin')) {
            include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
        }
        $core = get_site_transient("update_core");
        $upgrader = new \Core_Upgrader(new Updater_Skin());
        $upgrader->init();
        $res = $upgrader->upgrade($core->updates[0]);
        if (is_wp_error($res)) {
            return [
                'error' => 1,
                'message' => 'WordPress core upgrade failed.'
            ];
        } else {
            return 'success';
        }

    }
}
