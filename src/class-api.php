<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 16:03
 */

namespace WhatArmy\Watchtower;

use WhatArmy\Watchtower\Files\File_Backup;
use WhatArmy\Watchtower\Mysql\Mysql_Backup;
use WP_REST_Request as WP_REST_Request;
use WP_REST_Response as WP_REST_Response;

/**
 * Class Api
 * @package WhatArmy\Watchtower
 */
class Api
{
    protected $access_token;

    const API_VERSION = 'v1';
    const API_NAMESPACE = 'wht';

    /**
     * Api constructor.
     */
    public function __construct()
    {
        if (array_key_exists('access_token', get_option('watchtower', []))) {
            $this->access_token = get_option('watchtower')['access_token'];

            add_action('rest_api_init', function () {
                $this->routes();
            });
        }
    }

    /**
     * Routing List
     */
    private function routes()
    {
        register_rest_route($this->route_namespace(), 'test', $this->resolve_action('test_action'));
        register_rest_route($this->route_namespace(), 'get/core', $this->resolve_action('get_core_action'));
        register_rest_route($this->route_namespace(), 'get/plugins', $this->resolve_action('get_plugins_action'));
        register_rest_route($this->route_namespace(), 'get/themes', $this->resolve_action('get_themes_action'));
        register_rest_route($this->route_namespace(), 'get/all', $this->resolve_action('get_all_action'));
        register_rest_route($this->route_namespace(), 'user_logs', $this->resolve_action('get_user_logs_action'));

        /**
         * Password Less Access
         */
        register_rest_route($this->route_namespace(), 'access/generate_ota',
            $this->resolve_action('access_generate_ota_action'));

        /**
         * Backups
         */
        register_rest_route($this->route_namespace(), 'backup/file/list',
            $this->resolve_action('list_backup_file_action'));
        register_rest_route($this->route_namespace(), 'backup/file/get',
            $this->resolve_action('get_backup_file_action'));
        register_rest_route($this->route_namespace(), 'backup/file/run',
            $this->resolve_action('run_backup_file_action'));
        register_rest_route($this->route_namespace(), 'backup/file/run_queue',
            $this->resolve_action('run_backup_file_queue_action'));
        register_rest_route($this->route_namespace(), 'backup/mysql/run',
            $this->resolve_action('run_backup_db_action'));
        register_rest_route($this->route_namespace(), 'backup/cancel',
            $this->resolve_action('cancel_backup_action'));

        /**
         * Utilities
         */
        register_rest_route($this->route_namespace(), 'utility/cleanup',
            $this->resolve_action('run_cleanup_action'));

        register_rest_route($this->route_namespace(), 'utility/upgrade_plugin',
            $this->resolve_action('run_upgrade_plugin_action'));

        register_rest_route($this->route_namespace(), 'utility/upgrade_theme',
            $this->resolve_action('run_upgrade_theme_action'));

        register_rest_route($this->route_namespace(), 'utility/upgrade_core',
            $this->resolve_action('run_upgrade_core_action'));

    }

    /**
     * @return WP_REST_Response
     */
    public function run_upgrade_core_action()
    {
        $core = new Core();
        $res = $core->upgrade();
        return $this->make_response($res);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_upgrade_theme_action(WP_REST_Request $request)
    {
        $plugin = new Theme();
        $res = $plugin->doUpdate($request->get_param('toUpdate'));
        return $this->make_response($res);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_upgrade_plugin_action(WP_REST_Request $request)
    {
        $plugin = new Plugin();
        $res = $plugin->doUpdate($request->get_param('toUpdate'));
        return $this->make_response($res);
    }

    /**
     * @return WP_REST_Response
     */
    public function run_cleanup_action()
    {
        Schedule::clean_queue();
        Utils::cleanup_old_backups(WHTHQ_BACKUP_DIR, 1);

        return $this->make_response('cleaned');
    }

    /**
     * @return WP_REST_Response
     */
    public function access_generate_ota_action()
    {
        $access = new Password_Less_Access;
        return $this->make_response($access->generate_ota());
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_backup_file_queue_action(WP_REST_Request $request)
    {
        $backup = new File_Backup();
        $backup->poke_queue();

        return $this->make_response('done');
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function cancel_backup_action(WP_REST_Request $request)
    {
        Schedule::cancel_queue_and_cleanup($request->get_param('filename'));

        return $this->make_response('done');
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_backup_db_action(WP_REST_Request $request)
    {
        $backup = new Mysql_Backup();
        $filename = $backup->run($request->get_param('callbackUrl'));

        return $this->make_response(['filename' => $filename]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_backup_file_action(WP_REST_Request $request)
    {
        $object_files = [];
        foreach ($request['wht_backup_origins'] as $object_origin) {
            if (file_exists($object_origin)) {
                array_push($object_files, ['sha1' => sha1_file($object_origin), 'file_content' => base64_encode(file_get_contents($object_origin))]);
            }
        }
        return $this->make_response(['files' => $object_files]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_backup_file_action(WP_REST_Request $request)
    {
        $filesListRaw = Utils::allFilesList();
        $files = [];
        foreach ($filesListRaw as $file) {
            if ($file->isDir()) {
                continue;
            }
            array_push($files, ['origin'=>$file->getPathname(),'filesize'=>$file->getSize()]);
        }
        return $this->make_response(['files' => $files]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function run_backup_file_action(WP_REST_Request $request)
    {
        $backup = new File_Backup();
        $filename = $backup->run($request->get_param('callbackUrl'));

        return $this->make_response(['filename' => $filename . '.zip']);
    }


    /**
     * @return WP_REST_Response
     */
    public function get_user_logs_action()
    {
        $user_logs = new User_Logs;
        return $this->make_response($user_logs->get());
    }

    /**
     * @return WP_REST_Response
     */
    public function get_all_action()
    {
        $core = new Core;
        $plugins = new Plugin;
        $themes = new Theme;

        return $this->make_response([
            'core' => $core->get(),
            'plugins' => $plugins->get(),
            'themes' => $themes->get(),
        ]);
    }

    /**
     * @return WP_REST_Response
     */
    public function get_themes_action()
    {
        $themes = new Theme;
        return $this->make_response($themes->get());
    }

    /**
     * @return WP_REST_Response
     */
    public function test_action()
    {
        $core = new Core;
        return $this->make_response();
    }

    /**
     * @return WP_REST_Response
     */
    public function get_core_action()
    {
        $core = new Core;
        return $this->make_response($core->get());
    }

    /**
     * @return WP_REST_Response
     */
    public function get_plugins_action()
    {
        $plugins = new Plugin;
        return $this->make_response($plugins->get());
    }

    /**
     * @param array $data
     * @param int $status_code
     * @return WP_REST_Response
     */
    private function make_response($data = [], $status_code = 200)
    {
        $core = new Core;
        $response = new WP_REST_Response([
            'version' => $core->test()['version'],
            'data' => $data
        ]);
        $response->set_status($status_code);

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_permission(WP_REST_Request $request)
    {
        return $request->get_param('access_token') == $this->access_token;
    }

    /**
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_ota(WP_REST_Request $request)
    {
        return $request->get_param('access_token') == get_option('watchtower_ota_token');
    }

    /**
     * @param callable $_action
     * @param string $method
     * @return array
     */
    private function resolve_action($_action, $method = 'POST')
    {
        return [
            'methods' => $method,
            'callback' => [$this, $_action],
            'permission_callback' => [$this, ($_action == 'access_login_action') ? 'check_ota' : 'check_permission']
        ];
    }

    /**
     * @return string
     */
    private function route_namespace()
    {
        return join('/', [self::API_NAMESPACE, self::API_VERSION]);
    }
}
