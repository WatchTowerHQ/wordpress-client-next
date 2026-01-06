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

            // Clear BOM and premature output before REST responses to prevent JSON decode errors
            add_filter('rest_pre_serve_request', [$this, 'clear_bom_before_response'], 10, 4);
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
        register_rest_route(
            $this->route_namespace(),
            'access/generate_ota',
            $this->resolve_action([$this, 'access_generate_ota_action'])
        );

        /**
         * Backups
         */
        register_rest_route(
            $this->route_namespace(),
            'backup/files/list',
            $this->resolve_action([$this, 'get_backup_files_list_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/files/list/detailed',
            $this->resolve_action([$this, 'get_backup_files_list_detailed_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/directories/list/detailed',
            $this->resolve_action([$this, 'get_directories_list_detailed_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/files/get',
            $this->resolve_action([$this, 'get_backup_files_content_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/file/run',
            $this->resolve_action([$this, 'run_backup_file_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/file/run_queue',
            $this->resolve_action([$this, 'run_backup_file_queue_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/mysql/run',
            $this->resolve_action([$this, 'run_backup_db_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/mysql/delete',
            $this->resolve_action([$this, 'delete_backup_db_action'])
        );
        register_rest_route(
            $this->route_namespace(),
            'backup/cancel',
            $this->resolve_action([$this, 'cancel_backup_action'])
        );

        /**
         * Utilities
         */
        register_rest_route(
            $this->route_namespace(),
            'utility/cleanup',
            $this->resolve_action([$this, 'run_cleanup_action'])
        );

        register_rest_route(
            $this->route_namespace(),
            'utility/upgrade_plugin',
            $this->resolve_action([$this, 'run_upgrade_plugin_action'])
        );

        register_rest_route(
            $this->route_namespace(),
            'utility/upgrade_theme',
            $this->resolve_action([$this, 'run_upgrade_theme_action'])
        );

        register_rest_route(
            $this->route_namespace(),
            'utility/upgrade_core',
            $this->resolve_action([$this, 'run_upgrade_core_action'])
        );

        /**
         * Branding
         */
        register_rest_route(
            $this->route_namespace(),
            'branding/set',
            $this->resolve_action([$this, 'run_set_branding_action'])
        );

        register_rest_route(
            $this->route_namespace(),
            'branding/remove',
            $this->resolve_action([$this, 'run_remove_branding_action'])
        );
    }

    public function run_set_branding_action(WP_REST_Request $request): WP_REST_Response
    {
        //Make sure JSON file is valid before saving
        try {
            $jsonObj = json_decode($request->get_param('branding'), $associative = true, $depth = 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return $this->make_response(['status' => 'failed']);
        }

        file_put_contents(WHTHQ_BRANDING_FILE, $request->get_param('branding'));

        Branding::set_wht_branding();

        return $this->make_response(['status' => 'done']);
    }

    public function run_remove_branding_action(WP_REST_Request $request): WP_REST_Response
    {
        Branding::remove_wht_branding($request->get_param('branding_revision'));

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


    /**
     * @throws \Exception
     */
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
        foreach (Utils::tryTransparentlyDecryptPayload($request['wht_backup_origins']) as $object_origin) {
            // Security: Validate path is within WordPress root to prevent arbitrary file read
            if (!$this->is_path_within_abspath($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'error' => 'access_denied'];
                continue;
            }

            if (file_exists($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'created_timestamp' => filemtime($object_origin), 'type' => 'file', 'sha1' => sha1_file($object_origin), 'filesize' => filesize($object_origin), 'file_content' => base64_encode(file_get_contents($object_origin))];
            } else {
                $object_files[] = ['origin' => $object_origin, 'removed' => true];
            }
        }
        return $this->make_response(['files' => Utils::doesContainTransparentEncryptionPayload($request['wht_backup_origins']) ? Utils::buildEncryptedPayload($object_files) : $object_files]);
    }


    public function get_backup_files_list_detailed_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $object_files = [];

        foreach (Utils::tryTransparentlyDecryptPayload($request['wht_backup_origins']) as $object_origin) {
            // Security: Validate path is within WordPress root to prevent path traversal
            if (!$this->is_path_within_abspath($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'error' => 'access_denied'];
                continue;
            }

            if (file_exists($object_origin)) {
                $object_files[] = ['origin' => $object_origin, 'type' => 'file', 'sha1' => sha1_file($object_origin), 'filesize' => filesize($object_origin)];
            } else {
                $object_files[] = ['origin' => $object_origin, 'removed' => true];
            }
        }
        return $this->make_response(['files' => Utils::doesContainTransparentEncryptionPayload($request['wht_backup_origins']) ? Utils::buildEncryptedPayload($object_files) : $object_files]);
    }


    public function get_directories_list_detailed_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $object_directories = [];

        foreach (Utils::tryTransparentlyDecryptPayload($request['wht_backup_origins']) as $object_origin) {
            // Security: Validate path is within WordPress root to prevent path traversal
            if (!$this->is_path_within_abspath($object_origin)) {
                $object_directories[] = ['origin' => $object_origin, 'error' => 'access_denied'];
                continue;
            }

            if (file_exists($object_origin) && is_dir($object_origin)) {
                $object_directories[] = ['origin' => $object_origin, 'type' => 'dir', 'timestamp' => filemtime($object_origin)];
            } else {
                $object_directories[] = ['origin' => $object_origin, 'removed' => true];
            }
        }
        return $this->make_response(['directories' => Utils::doesContainTransparentEncryptionPayload($request['wht_backup_origins']) ? Utils::buildEncryptedPayload($object_directories) : $object_directories]);
    }


    public function get_backup_files_list_action(WP_REST_Request $request): WP_REST_Response
    {
        set_time_limit(300);
        $files = Utils::getFileSystemStructure(ABSPATH, Utils::createLocalBackupExclusions(Utils::tryTransparentlyDecryptPayload($request->get_param('clientBackupExclusions')) ?? []));
        return $this->make_response(['memory_limit' => ini_get('memory_limit'), 'max_input_vars' => ini_get('max_input_vars'), 'files' => Utils::doesContainTransparentEncryptionPayload($request->get_param('clientBackupExclusions')) ? Utils::buildEncryptedPayload($files) : $files]);
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


    public function get_all_action(WP_REST_Request $request): WP_REST_Response
    {
        $core = new Core;
        $plugins = new Plugin;
        $themes = new Theme;

        $this->update_headquarter_callback($request);

        return $this->make_response([
            'core' => $core->get(),
            'plugins' => $plugins->get(),
            'themes' => $themes->get(),
        ]);
    }

    function validate_and_sanitize_callback_domain($domain)
    {
        // Sanitize the input
        $sanitized_domain = sanitize_text_field($domain);

        // Validate the domain
        if ((bool) preg_match('/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $sanitized_domain)) {
            return $sanitized_domain; // Return the sanitized domain if valid
        } else {
            return false; // Return false if invalid
        }
    }

    private function update_headquarter_callback(WP_REST_Request $request): void
    {
        if ($request->get_param('callback_fqdn')) {
            $callback_url = $this->validate_and_sanitize_callback_domain($request->get_param('callback_fqdn'));

            if ($callback_url) {
                $headquarters = get_option('whthq_headquarters', []);
                $headquarters[$callback_url] = time();
                update_option('whthq_headquarters', $headquarters);
            }
        }
    }

    public function get_themes_action(): WP_REST_Response
    {
        $themes = new Theme;
        return $this->make_response($themes->get());
    }


    public function test_action(): WP_REST_Response
    {
        $core = new Core;
        $data = [
            'supports_encryption' => Utils::wht_supports_encryption()
        ];
        return $this->make_response($data);
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
        $provided_token = $request->get_param('access_token');

        if (!\is_string($provided_token) || !\is_string($this->access_token)) {
            return false;
        }

        return \hash_equals($this->access_token, $provided_token);
    }

    /**
     * Validate that a given path is within the WordPress root directory.
     * Prevents path traversal attacks by ensuring files cannot be accessed outside ABSPATH.
     *
     * @param string $path The path to validate
     * @return bool True if path is within ABSPATH, false otherwise
     */
    private function is_path_within_abspath(string $path): bool
    {
        // Cache the WordPress root path to avoid repeated realpath() calls in loops
        static $wp_root = null;
        static $wp_root_len = null;

        if ($wp_root === null) {
            $wp_root = realpath(ABSPATH);
            if ($wp_root === false) {
                return false;
            }
            $wp_root_len = strlen($wp_root);
        }

        $real_path = realpath($path);

        // If path doesn't exist or can't be resolved, deny access
        if ($real_path === false) {
            return false;
        }

        // Ensure the path starts with WordPress root directory
        // Add trailing separator to prevent partial matches (e.g., /var/www/html vs /var/www/html2)
        return strncmp($real_path, $wp_root . DIRECTORY_SEPARATOR, $wp_root_len + 1) === 0 
            || $real_path === $wp_root;
    }

    public function check_ota(WP_REST_Request $request): bool
    {
        $stored_token = get_option('watchtower_ota_token');
        $provided_token = $request->get_param('access_token');

        if (!\is_string($stored_token) || !\is_string($provided_token)) {
            return false;
        }

        return \hash_equals($stored_token, $provided_token);
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

    /**
     * Clear any BOM or premature output before sending REST API responses.
     * 
     * When plugins/themes with BOM are loaded, the BOM bytes get output to WordPress's
     * buffer before the REST response. This causes JSON decode errors on the client side
     * because the response starts with BOM bytes instead of valid JSON.
     * 
     * This filter clears all output buffers before our REST endpoints send their response.
     * 
     * @param bool $served Whether the request has already been served
     * @param mixed $result Result to send to the client
     * @param WP_REST_Request $request Request object
     * @param mixed $server REST server instance
     * @return bool
     */
    public function clear_bom_before_response($served, $result, $request, $server)
    {
        // Only clear buffers for our WatchTower endpoints
        if (strpos($request->get_route(), '/wht/') === 0) {
            $premature_output = '';

            // Discard ALL output buffers (including any BOM or premature output from other plugins)
            while (ob_get_level() > 0) {
                $content = ob_get_clean();
                if ($content !== false) {
                    $premature_output = $content . $premature_output;
                }
            }

            // Start a fresh buffer for the clean JSON response
            ob_start();

            // If we captured any premature output, add it to the response for debugging
            if (!empty($premature_output) && $result instanceof WP_REST_Response) {
                $data = $result->get_data();
                if (is_array($data)) {
                    $data['premature_output'] = base64_encode($premature_output);
                    $result->set_data($data);
                }
            }
        }

        return $served;
    }
}
