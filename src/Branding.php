<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 18:16
 */

namespace WhatArmy\Watchtower;

use Exception;
use stdClass;
use WP_Session_Tokens;

/**
 * Branding
 * @package WhatArmy\Watchtower
 */
class Branding
{
    public function __construct()
    {
        add_action('wht_branding_set_hook', [$this, 'wht_branding_set']);
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
        self::set_wht_branding();
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

        //Prepare To Reinstall Stock Plugin
        self::simulate_need_for_update_of_watchtowerhq_plugin();

        //Reinstall Stock Plugin
        $plugin = new Plugin();
        $plugin->doUpdate('watchtowerhq/watchtowerhq.php');

        if (file_exists(WHTHQ_BRANDING_FILE)) {
            //Inform WHT Instance About Failed De-Branding Process
            self::report_set_branding_status(12, $branding_revision);
        } else {
            //Inform WHT Instance About Success De-Branding Process
            self::report_set_branding_status(11, $branding_revision);
        }
        return true;
    }


    static function simulate_need_for_update_of_watchtowerhq_plugin()
    {
        // Define the plugin slug (the identifier for the plugin)
        $plugin_slug = 'watchtowerhq/watchtowerhq.php';

        // Fetch the current plugin update transient (cached data for plugin updates)
        $update_plugins = get_site_transient('update_plugins');

        // Check if the transient exists and is a valid object
        if (is_object($update_plugins)) {

            // Check if the plugin is in the 'no_update' section (i.e., no update is currently needed)
            if (isset($update_plugins->no_update[$plugin_slug])) {

                // Store the plugin data temporarily
                $item = $update_plugins->no_update[$plugin_slug];

                // Remove the plugin from the 'no_update' section
                unset($update_plugins->no_update[$plugin_slug]);

                // Move the plugin to the 'response' section, which simulates that an update is available
                $update_plugins->response[$plugin_slug] = $item;

                // Update the 'last_checked' timestamp to the current time
                $update_plugins->last_checked = time();

                // Save the modified plugin update transient back to the database
                set_site_transient('update_plugins', $update_plugins);
            }
        }
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
            return false;
        }

        //Inform WHT Instance About Initiating Branding Process
        self::report_set_branding_status(1);

        $existing_plugin_data = get_plugin_data(WHTHQ_MAIN, false, false);

        $wht_branding['Name'] = self::get_wht_branding('Name', $existing_plugin_data['Name']);
        $wht_branding['PluginURI'] = self::get_wht_branding('PluginURI', $existing_plugin_data['PluginURI']);
        $wht_branding['Description'] = self::get_wht_branding('Description', $existing_plugin_data['Description']);
        $wht_branding['Author'] = self::get_wht_branding('Author', $existing_plugin_data['Author']);
        $wht_branding['AuthorURI'] = self::get_wht_branding('AuthorURI', $existing_plugin_data['AuthorURI']);


        $replacement = "//<--AUTO-GENERATED-PLUGIN-HEADER-START-->
/**
 * Plugin Name: {$wht_branding['Name']}
 * Plugin URI: {$wht_branding['PluginURI']}
 * Description: {$wht_branding['Description']}
 * Author: {$wht_branding['Author']}
 * Version: {$existing_plugin_data['Version']}
 * Requires PHP: {$existing_plugin_data['RequiresPHP']}
 * Author URI: {$wht_branding['AuthorURI']}
 * License: GPLv2 or later
 * Text Domain: {$existing_plugin_data['TextDomain']}
 **/
 //<--AUTO-GENERATED-PLUGIN-HEADER-END-->";

        $startMarking = '//<--AUTO-GENERATED-PLUGIN-HEADER-START-->';
        $endMarking = '//<--AUTO-GENERATED-PLUGIN-HEADER-END-->';

        //Check If Plugin File Exist And Is Readable
        if (!is_file(WHTHQ_MAIN)) {
            return false;
        }

        if (!is_readable(WHTHQ_MAIN)) {
            return false;
        }

        $fp = fopen(WHTHQ_MAIN, 'r+');

        if (flock($fp, LOCK_EX)) {

            $actualPluginString = fread($fp, filesize(WHTHQ_MAIN));

            $startPosition = strpos($actualPluginString, $startMarking);
            $endPosition = strpos($actualPluginString, $endMarking);

            if ($startPosition === false || $endPosition === false || $endPosition <= $startPosition) {
                return false;
            }

            // Calculate the length of the content between start and end position
            $lengthToReplace = $endPosition - $startPosition + strlen($endMarking);

            // Perform the replacement
            $newPluginString = substr_replace($actualPluginString, $replacement, $startPosition, $lengthToReplace);

            rewind($fp);

            ftruncate($fp, 0);

            //Write plugin file with new header
            fwrite($fp, $newPluginString);

            // Ensure all data is written to the file
            fflush($fp);

            //Validate For Syntax Error

            if (!Utils::selftest()) {
                //error rollback changes
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log('Applying WHTHQ branding caused critical error, rolling back.');
                }

                rewind($fp);

                ftruncate($fp, 0);

                //Write Plugin Before Modification
                fwrite($fp, $actualPluginString);

                // Ensure all data is written to the file
                fflush($fp);

                self::report_set_branding_status(20);
            } else {

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
            }

            flock($fp, LOCK_UN);

        } else {
            //Problem Locking File
            return false;
        }
        fclose($fp);

        return true;
    }
}
