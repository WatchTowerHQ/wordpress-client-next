<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 16:03
 */

namespace WhatArmy\Watchtower;

use Exception;
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
        register_rest_route($this->route_namespace(), 'test', $this->resolve_action([$this, 'test_action']));
        register_rest_route($this->route_namespace(), 'get/core', $this->resolve_action([$this, 'get_core_action']));
        register_rest_route($this->route_namespace(), 'get/plugins', $this->resolve_action([$this, 'get_plugins_action']));
        register_rest_route($this->route_namespace(), 'get/themes', $this->resolve_action([$this, 'get_themes_action']));
        register_rest_route($this->route_namespace(), 'get/all', $this->resolve_action([$this, 'get_all_action']));
        register_rest_route($this->route_namespace(), 'user_logs', $this->resolve_action([$this, 'get_user_logs_action']));

        /**
         * Password Less Access
         */
        register_rest_route($this->route_namespace(), 'access/generate_ota',
            $this->resolve_action([$this, 'access_generate_ota_action']));

        /**
         * Backups
         */
        register_rest_route($this->route_namespace(), 'backup/files/list',
            $this->resolve_action([$this, 'get_backup_files_list_action']));
        register_rest_route($this->route_namespace(), 'backup/files/list/detailed',
            $this->resolve_action([$this, 'get_backup_files_list_detailed_action']));
        register_rest_route($this->route_namespace(), 'backup/files/get',
            $this->resolve_action([$this, 'get_backup_files_content_action']));
        register_rest_route($this->route_namespace(), 'backup/file/run',
            $this->resolve_action([$this, 'run_backup_file_action']));
        register_rest_route($this->route_namespace(), 'backup/file/run_queue',
            $this->resolve_action([$this, 'run_backup_file_queue_action']));
        register_rest_route($this->route_namespace(), 'backup/mysql/run',
            $this->resolve_action([$this, 'run_backup_db_action']));
        register_rest_route($this->route_namespace(), 'backup/mysql/delete',
            $this->resolve_action([$this, 'delete_backup_db_action']));
        register_rest_route($this->route_namespace(), 'backup/cancel',
            $this->resolve_action([$this, 'cancel_backup_action']));

        /**
         * Utilities
         */
        register_rest_route($this->route_namespace(), 'utility/cleanup',
            $this->resolve_action([$this, 'run_cleanup_action']));

        register_rest_route($this->route_namespace(), 'utility/upgrade_plugin',
            $this->resolve_action([$this, 'run_upgrade_plugin_action']));

        register_rest_route($this->route_namespace(), 'utility/upgrade_theme',
            $this->resolve_action([$this, 'run_upgrade_theme_action']));

        register_rest_route($this->route_namespace(), 'utility/upgrade_core',
            $this->resolve_action([$this, 'run_upgrade_core_action']));

        /**
         * Branding
         */
        register_rest_route($this->route_namespace(), 'branding/set',
            $this->resolve_action([$this, 'run_set_branding_action']));

        register_rest_route($this->route_namespace(), 'branding/remove',
            $this->resolve_action([$this, 'run_remove_branding_action']));
    }

    public function run_set_branding_action(WP_REST_Request $request): WP_REST_Response
    {
        //Make sure JSON file is valid before saving
        try {
            $jsonObj = json_decode($request->get_param('branding'), $associative = true, $depth = 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return $this->make_response(['status' => 'failed']);
        }

        file_put_contents(wp_upload_dir()['basedir'] . '/watchtower_branding.json', $request->get_param('branding'));
        Utils::set_wht_branding();

        return $this->make_response(['status' => 'done']);
    }

    public function run_remove_branding_action(WP_REST_Request $request): WP_REST_Response
    {
        if(file_exists(wp_upload_dir()['basedir'] . '/watchtower_branding.json'))
        {
            unlink(wp_upload_dir()['basedir'] . '/watchtower_branding.json');
        }

        return $this->make_response(['status' => 'done']);
    }

    public function run_upgrade_core_action(): WP_REST_Response
    {
        $core = new Core();
        $res = $core->upgrade();
        return $this->make_response($res);
    }


    public function run_upgrade_theme_action(WP_REST_Request $request): WP_REST_Response
    {
        $plugin = new Theme();
        $res = $plugin->doUpdate($request->get_param('toUpdate'));
        return $this->make_response($res);
    }


    public function run_upgrade_plugin_action(WP_REST_Request $request): WP_REST_Response
    {
        $plugin = new Plugin();
        $res = $plugin->doUpdate($request->get_param('toUpdate'));
        return $this->make_response($res);
    }


    public function run_cleanup_action(): WP_REST_Response
    {
        Schedule::clean_queue();
        Utils::cleanup_old_backups(WHTHQ_BACKUP_DIR, 1);

        return $this->make_response('cleaned');
    }


    public function access_generate_ota_action(): WP_REST_Response
    {
        $access = new Password_Less_Access;
        return $this->make_response($access->generate_ota());
    }


    public function run_backup_file_queue_action(WP_REST_Request $request): WP_REST_Response
    {
        $backup = new File_Backup();
        $backup->poke_queue();

        return $this->make_response('done');
    }


    public function cancel_backup_action(WP_REST_Request $request): WP_REST_Response
    {
        Schedule::cancel_queue_and_cleanup($request->get_param('filename'));

        return $this->make_response('done');
    }


    public function run_backup_db_action(WP_REST_Request $request): WP_REST_Response
    {
        $backup = new Mysql_Backup();
        $filename = $backup->run($request->get_param('callbackUrl'));

        return $this->make_response(['filename' => $filename]);
    }


    public function delete_backup_db_action(WP_REST_Request $request): WP_REST_Response
    {
        if (strlen($request->get_param('backup_filename')) > 0) {
            $backup_filename = WHTHQ_BACKUP_DIR . '/' . $request->get_param('backup_filename');
            $was_present = false;
            if (file_exists($backup_filename)) {
                $was_present = true;
                unlink($backup_filename);
            }
            return $this->make_response(['existing' => file_exists($backup_filename), 'was_present' => $was_present]);
        } else {
            return $this->make_response(['error' => 'Missing Backup Filename']);
        }
    }


    public function get_backup_files_content_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $object_files = [];
        foreach ($request['wht_backup_origins'] as $object_origin) {
            if (file_exists($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'created_timestamp' => filemtime($object_origin), 'type' => 'file', 'sha1' => sha1_file($object_origin), 'filesize' => filesize($object_origin), 'file_content' => base64_encode(file_get_contents($object_origin))];
            } else {
                $object_files[] = ['origin' => $object_origin, 'removed' => true];
            }
        }
        return $this->make_response(['files' => $object_files]);
    }


    public function get_backup_files_list_detailed_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $object_files = [];
        foreach ($request['wht_backup_origins'] as $object_origin) {
            if (file_exists($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'type' => 'file', 'sha1' => sha1_file($object_origin), 'filesize' => filesize($object_origin)];
            } else {
                $object_files[] = ['origin' => $object_origin, 'removed' => true];
            }
        }
        return $this->make_response(['files' => $object_files]);
    }


    public function get_backup_files_list_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $filesListRaw = Utils::allFilesList(Utils::createLocalBackupExclusions($request->get_param('clientBackupExclusions')));
        $files = [];
        foreach ($filesListRaw as $file) {
            $files[] = [
                'type' => $file->isDir() ? 'dir' : 'file',
                'origin' => str_replace(ABSPATH, '', $file->getPathname()),
                'filesize' =>$file->getSize()
            ];
        }
        return $this->make_response(['memory_limit' => ini_get('memory_limit'), 'max_input_vars' => ini_get('max_input_vars'), 'files' => $files]);
    }


    public function run_backup_file_action(WP_REST_Request $request): WP_REST_Response
    {
        $backup = new File_Backup();
        $filename = $backup->run($request->get_param('callbackUrl'));

        return $this->make_response(['filename' => $filename . '.zip']);
    }


    public function get_user_logs_action(): WP_REST_Response
    {
        $user_logs = new User_Logs;
        return $this->make_response($user_logs->get());
    }


    public function get_all_action(): WP_REST_Response
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

    public function get_themes_action(): WP_REST_Response
    {
        $themes = new Theme;
        return $this->make_response($themes->get());
    }


    public function test_action(): WP_REST_Response
    {
        $core = new Core;
        return $this->make_response();
    }


    public function get_core_action(): WP_REST_Response
    {
        $core = new Core;
        return $this->make_response($core->get());
    }


    public function get_plugins_action(): WP_REST_Response
    {
        $plugins = new Plugin;
        return $this->make_response($plugins->get());
    }


    private function make_response(array $data = [], int $status_code = 200): WP_REST_Response
    {
        $core = new Core;
        $response = new WP_REST_Response([
            'version' => $core->test()['version'],
            'data' => $data
        ]);
        $response->set_status($status_code);

        return $response;
    }

    public function check_permission(WP_REST_Request $request): bool
    {
        return $request->get_param('access_token') === $this->access_token;
    }

    public function check_ota(WP_REST_Request $request): bool
    {
        return $request->get_param('access_token') === get_option('watchtower_ota_token');
    }

    private function resolve_action(callable $_action, string $method = 'POST'): array
    {
        return [
            'methods' => $method,
            'callback' => $_action,
            'permission_callback' => [$this, ($_action == 'access_login_action') ? 'check_ota' : 'check_permission']
        ];
    }

    private function route_namespace(): string
    {
        return join('/', [self::API_NAMESPACE, self::API_VERSION]);
    }
}
