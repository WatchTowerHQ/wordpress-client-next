<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 18:16
 */

namespace WhatArmy\Watchtower;

use Exception;

/**
 * Branding
 * @package WhatArmy\Watchtower
 */
class Branding
{
    public function __construct()
    {
        add_action('wht_branding_set_hook', [$this, 'wht_branding_set']);

        if(self::wht_branding_is_configured()) {
            add_filter('all_plugins', [$this, 'all_plugins_branding_handle']);
            add_filter('plugins_api', [$this, 'override_plugin_details'], 10, 3);
        }
    }


    public function override_plugin_details($result, $action, $args)
    {
        if ($action === 'plugin_information' && isset($args->slug) && $args->slug === 'watchtowerhq') {

            // Use a static flag to avoid infinite loops
            static $already_fetching = false;

            if ($already_fetching) {
                return $result;
            }

            // Set flag to true to prevent recursion
            $already_fetching = true;

            // Fetch the original plugin data
            $result = plugins_api($action, $args);

            // Reset the flag after fetching
            $already_fetching = false;

            // Modify only the "View Details" link
            if (is_object($result)) {

                $result->name = self::get_wht_branding('Name');
                $result->author = self::get_wht_branding('Author');
                $result->homepage = self::get_wht_branding('PluginURI');
                $result->sections['description'] = self::get_wht_branding('Description');

                unset($result->sections['installation']);
                unset($result->sections['faq']);
                unset($result->sections['changelog']);
                unset($result->sections['screenshots']);
                unset($result->sections['reviews']);

                unset($result->contributors);
                unset($result->ratings);


                // Add custom banners
                $result->banners = array(
                    'low' => 'https://yourwebsite.com/banner-low.jpg',
                    'high' => 'https://yourwebsite.com/banner-high.jpg',
                );


            }
            error_log(serialize($result));
        }

        return $result;
    }
    public function all_plugins_branding_handle($plugins)
    {
        $plugin_slug = 'watchtowerhq/watchtowerhq.php';
        if (isset($plugins[$plugin_slug])) {
            $plugins[$plugin_slug]['Name'] = self::get_wht_branding('Name');
            $plugins[$plugin_slug]['Description'] = self::get_wht_branding('Description');
            $plugins[$plugin_slug]['Author'] = self::get_wht_branding('Author');
            $plugins[$plugin_slug]['AuthorURI'] = self::get_wht_branding('AuthorURI');
            $plugins[$plugin_slug]['PluginURI'] = self::get_wht_branding('PluginURI');
        }
        return $plugins;
    }

    public static function get_wht_branding($key, $defaultValue = false)
    {
        if (is_readable(WHTHQ_BRANDING_FILE)) {
            try {
                $jsonObj = json_decode(file_get_contents(WHTHQ_BRANDING_FILE), $associative = true, $depth = 512, JSON_THROW_ON_ERROR);
                return (isset($jsonObj[$key]) && $jsonObj[$key] !== '') ? $jsonObj[$key] : $defaultValue;
            } catch (Exception $e) {
                return $defaultValue;
            }
        }
        return $defaultValue;
    }

    public function wht_branding_set()
    {
        //self::set_wht_branding();
    }

    public static function restore_default_whthq_client_account(): void
    {
        //Migrate the legacy method for identifying administrative accounts by email used with the WHTHQ client
        Password_Less_Access::mark_whthq_admin_client_with_meta_key();

        $admins_with_meta = get_users([
            'role' => 'administrator',
            'meta_key' => 'whthq_agent',
            'meta_value' => '1',
        ]);
        //Make Sure We Have Only Single WHT Admin To Work With Otherwise It Might Indicate Someone Play Around And We Can Get Conflict
        if (!empty($admins_with_meta) && count($admins_with_meta) === 1) {
            $admin = reset($admins_with_meta);
            $admin_id = $admin->ID;

            $user_data = ['ID' => $admin_id];

            $user_data['user_email'] = WHTHQ_CLIENT_USER_EMAIL;
            $user_data['display_name'] = WHTHQ_CLIENT_USER_NAME;
            $user_data['first_name'] = WHTHQ_CLIENT_USER_NAME;

            wp_update_user($user_data);
            clean_user_cache($admin_id);
        }
    }

    public static function remove_wht_branding(string $branding_revision): bool
    {
        //Inform WHT Instance About Initiating De-Branding Process
        self::report_set_branding_status(2, $branding_revision);

        if (is_file(WHTHQ_BRANDING_FILE) && is_readable(WHTHQ_BRANDING_FILE)) {
            unlink(WHTHQ_BRANDING_FILE);
        }

        //Setting Default WHT Username & Email
        self::restore_default_whthq_client_account();

        if (file_exists(WHTHQ_BRANDING_FILE)) {
            //Inform WHT Instance About Failed De-Branding Process
            self::report_set_branding_status(12, $branding_revision);
        } else {
            //Inform WHT Instance About Success De-Branding Process
            self::report_set_branding_status(11, $branding_revision);
        }
        return true;
    }

    public static function wht_branding_is_configured(): bool
    {
        // Retrieve all branding values
        $name = self::get_wht_branding('Name');
        $pluginURI = self::get_wht_branding('PluginURI');
        $description = self::get_wht_branding('Description');
        $author = self::get_wht_branding('Author');
        $authorURI = self::get_wht_branding('AuthorURI');
        $WHTHQClientUserName = self::get_wht_branding('WHTHQClientUserName');
        $WHTHQClientEmail = self::get_wht_branding('WHTHQClientEmail');

        //Block applying branding if any of branding values contain string that can "break" plugin header comment
        $disallowedPatterns = [
            '/<!--/',
            '/-->/',
            '/\/\*\*/',
            '/\*\*\//'
        ];

        // Check if any of the values are empty or contain disallowed sequences
        $values = [$name, $pluginURI, $description, $author, $authorURI, $WHTHQClientUserName, $WHTHQClientEmail];

        foreach ($values as $value) {
            // Check for empty values
            if (empty($value)) {
                return false;
            }

            // Check for disallowed patterns
            foreach ($disallowedPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function report_set_branding_status($status_code, $branding_revision = '')
    {
        if (empty($branding_revision)) {
            $branding_revision = self::get_wht_branding('BrandingRevision','N/A');
        }

        $headquarters = get_option('whthq_headquarters', []);

        foreach ($headquarters as $callback => $last_used) {
            if (!empty($callback) && !empty($last_used) && ($last_used >= time() - WHTHQ_MAX_HEADQUARTER_IDLE_TIME_SECONDS)) {

                $headquarter = new Headquarter($callback);

                if (in_array($status_code, [20, 10, 11, 12])) {
                    $headquarter->setCurlTimeoutInSeconds(10);
                    $headquarter->setRetryDelayMinutes(5);
                    $headquarter->setRetryTimes(5);
                } else {
                    $headquarter->setCurlTimeoutInSeconds(3);
                }

                $headquarter->retryOnFailure('/incoming/client/wordpress/event', [
                    'status_code' => $status_code,
                    'event_type' => 'branding',
                    'branding_revision' => $branding_revision
                ]);

            }
        }
    }

    public static function set_wht_branding(): bool
    {

        if (!self::wht_branding_is_configured()) {
            self::report_set_branding_status(20);
            return false;
        }

        //Inform WHT Instance About Initiating Branding Process
        self::report_set_branding_status(1);


        //Migrate the legacy method for identifying administrative accounts by email used with the WHTHQ client
        Password_Less_Access::mark_whthq_admin_client_with_meta_key();

        //Setting WHT Username & Email
        $admins_with_meta = get_users([
            'role' => 'administrator',
            'meta_key' => 'whthq_agent',
            'meta_value' => '1',
        ]);

        //Make Sure We Have Only Single WHT Admin To Work With Otherwise It Might Indicate Someone Play Around And We Can Get Conflict

        if (!empty($admins_with_meta) && count($admins_with_meta) === 1) {

            $admin = reset($admins_with_meta);
            $admin_id = $admin->ID;

            $fields = [
                'user_email' => 'WHTHQClientEmail',
                'display_name' => 'WHTHQClientUserName',
                'first_name' => 'WHTHQClientUserName'
            ];

            $user_data = ['ID' => $admin_id];

            foreach ($fields as $field => $branding_key) {
                $meta_value = get_the_author_meta($field, $admin_id);
                $branding_value = self::get_wht_branding($branding_key, '');

                if ($meta_value !== $branding_value && !empty($branding_value)) {
                    $user_data[$field] = $branding_value;
                }
            }

            if (count($user_data) > 1) {
                wp_update_user($user_data);
                clean_user_cache($admin_id);
            }
        }

        self::report_set_branding_status(10);


        return true;
    }
}
