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
    public $plugin_data;

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
    private function plugin_data()
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
    public function test()
    {
        return [
            'version' => $this->wht_plugin_version(),
        ];
    }

    /**
     * @return array
     */
    public function get()
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
            'comments_allowed' => (get_default_comment_status() == 'open') ? true : false,
            'site_ip' => $this->external_ip(),
            'db_size' => $this->db_size(),
            'timezone' => [
                'gmt_offset' => get_option('gmt_offset'),
                'string' => get_option('timezone_string'),
                'server_timezone' => date_default_timezone_get(),
            ],
            'admins_list' => $this->admins_list(),
            'admin_url' => admin_url(),
            'content_dir' => (defined('WP_CONTENT_DIR')) ? WP_CONTENT_DIR : false,
            'pwp_name' => (defined('PWP_NAME')) ? PWP_NAME : false,
            'wpe_auth' => (defined('WPE_APIKEY')) ? md5('wpe_auth_salty_dog|' . WPE_APIKEY) : false,
        ];
    }

    /**
     * @return array
     */
    private function check_updates()
    {
        global $wp_version;
        do_action("wp_version_check"); // force WP to check its core for updates
        $update_core = get_site_transient("update_core"); // get information of updates

        if ('upgrade' == $update_core->updates[0]->response) {
            require_once(ABSPATH . WPINC . '/version.php');
            $new_core_ver = $update_core->updates[0]->current; // The new WP core version

            return array(
                'required' => true,
                'new_version' => $new_core_ver,
            );

        } else {
            return array(
                'required' => false,

            );
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
                \FilesystemIterator::SKIP_DOTS)) as $object) {
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
     * @return mixed
     */
    public function external_ip()
    {
        $curl = new \Curl();
        $curl->options['CURLOPT_SSL_VERIFYPEER'] = false;
        $curl->options['CURLOPT_SSL_VERIFYHOST'] = false;
        $ip = json_decode($curl->get('https://api.ipify.org?format=json'))->ip;

        return $ip;
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
    public function admins_list()
    {
        $admins_list = get_users('role=administrator');
        $admins = [];
        foreach ($admins_list as $admin) {
            array_push($admins, array(
                'login' => $admin->user_login,
                'email' => $admin->user_email,
            ));
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
            return array(
                'error' => 1,
                'message' => 'WordPress core upgrade failed.'
            );
        } else {
            return 'success';
        }

    }
}
