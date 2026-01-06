<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-10
 * Time: 18:49
 */

namespace WhatArmy\Watchtower;

/**
 * Class Password_Less_Access
 * @package WhatArmy\Watchtower
 */
class Password_Less_Access
{

    /**
     * Download constructor.
     */
    public function __construct()
    {
        add_filter('query_vars', [$this, 'add_query_vars'], 0);
        add_action('parse_request', [$this, 'sniff_requests'], 0);
        add_action('init', [$this, 'add_endpoint'], 0);
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'wht_login';
        $vars[] = 'access_token';
        $vars[] = 'redirect_to';
        return $vars;
    }

    // Add API Endpoint
    public function add_endpoint()
    {
        add_rewrite_rule(
            '^wht_login/?([a-zA-Z0-9]+)?/?',
            'index.php?wht_login=1&access_token=$matches[1]',
            'top'
        );

    }

    /**
     *
     */
    public function sniff_requests()
    {
        global $wp;
        if (isset($wp->query_vars['wht_login'])) {

            $after_login_redirect_to = '';
            if (isset($wp->query_vars['redirect_to']) && is_string($wp->query_vars['redirect_to'])) {
                switch ($wp->query_vars['redirect_to']) {
                    case 'updates':
                        $after_login_redirect_to = 'update-core.php';
                        break;
                }
            }

            $this->login($wp->query_vars['access_token'], $after_login_redirect_to);
        }
    }

    static public function mark_whthq_admin_client_with_meta_key()
    {
        $admins_with_email_but_without_meta = get_users([
            'role' => 'administrator',
            'search' => WHTHQ_CLIENT_USER_EMAIL,
            'search_columns' => ['user_email'],
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'whthq_agent',
                    'value' => '1',
                    'compare' => '!=' // If the meta_key exists but the value is not 1
                ],
                [
                    'key' => 'whthq_agent',
                    'compare' => 'NOT EXISTS' // If the meta_key does not exist at all
                ]
            ]
        ]);

        if ($admins_with_email_but_without_meta) {
            $admin = reset($admins_with_email_but_without_meta);
            update_user_meta($admin->ID, 'whthq_agent', '1');
        }
    }

    public function login($access_token, $after_login_redirect_to = '')
    {
        if (!is_string(get_option('watchtower_ota_token'))) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (!is_string($access_token)) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (strlen($access_token) !== 36) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (strlen(get_option('watchtower_ota_token')) !== 36) {
            wp_die(__('Unauthorized access', 'watchtowerhq'));
        }

        if (\hash_equals((string) get_option('watchtower_ota_token'), (string) $access_token)) {
            $random_password = wp_generate_password(30);

            //Migrate the legacy method for identifying administrative accounts by email used with the WHTHQ client
            self::mark_whthq_admin_client_with_meta_key();

            $admins_list = get_users([
                'role' => 'administrator',
                'meta_key' => 'whthq_agent',
                'meta_value' => '1',
            ]);

            if ($admins_list) {
                reset($admins_list);
                $admin = current($admins_list);
                $adm_id = $admin->ID;

                wp_set_password($random_password, $adm_id);

            } else {
                $adm_id = wp_create_user(Branding::get_wht_branding('WHTHQClientUserName', WHTHQ_CLIENT_USER_NAME), $random_password, Branding::get_wht_branding('WHTHQClientEmail', WHTHQ_CLIENT_USER_EMAIL));
                $wp_user_object = new \WP_User($adm_id);
                $wp_user_object->set_role('administrator');
                if (is_multisite()) {
                    grant_super_admin($adm_id);
                }

                update_user_meta($adm_id, 'whthq_agent', '1');
            }

            wp_clear_auth_cookie();
            wp_set_auth_cookie($adm_id, true);
            wp_set_current_user($adm_id);

            update_option('watchtower_ota_token', false);
            wp_safe_redirect(admin_url($after_login_redirect_to));
            exit();
        }
    }

    /**
     * @return array
     */
    public function generate_ota(): array
    {
        $ota_token = 'ota_' . bin2hex(random_bytes(16));
        update_option('watchtower_ota_token', $ota_token);
        return [
            'ota_token' => $ota_token,
            'admin_url' => admin_url(),
        ];
    }
}
