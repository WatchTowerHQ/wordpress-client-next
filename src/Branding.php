<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 18:16
 */

namespace WhatArmy\Watchtower;

use Exception;
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

    public static function report_set_branding_status($status_code)
    {
        if(self::get_wht_branding('CallbackUrl')) {
            $headquarter = new Headquarter(self::get_wht_branding('CallbackUrl'));
            $headquarter->call('/incoming/client/wordpress/event', [
                'access_token' => get_option('watchtower')['access_token'],
                'status_code' => $status_code,
                'event_type' => 'branding',
                'branding_revision' => self::get_wht_branding('BrandingRevision')
            ]);
        }
    }
    public static function set_wht_branding(): bool
    {

        if (!self::wht_branding_is_configured()) {
            return false;
        }

        //Inform WHT Instance About Initiating Branding Process
        self::report_set_branding_status('1');

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
                file_put_contents(wp_upload_dir()['basedir'] . '/rollback.json', 'rollback');

                rewind($fp);

                ftruncate($fp, 0);

                //Write Plugin Before Modification
                fwrite($fp, $actualPluginString);

                // Ensure all data is written to the file
                fflush($fp);

                self::report_set_branding_status('20');
            } else {

                //Setting WHT Username & Email

                $admins_with_meta = get_users([
                    'role' => 'administrator',
                    'meta_key' => 'whthq_agent',
                    'meta_value' => '1',
                ]);

                //Make Sure We Have Only Single WHT Admin To Work With Otherwise It Might Indicate Someone Play Around And We Can Get Conflict

                if (!empty($admins_with_meta) && count($admins_with_meta) === 1) {
                    $wht_password_less_admin_account = $admins_with_meta[0];


                    $random_password = wp_generate_password(30);

                    $new_wht_password_less_admin_login = self::get_wht_branding('WHTHQClientUserName', 'WatchTowerClient');
                    $new_wht_password_less_admin_email = self::get_wht_branding('WHTHQClientEmail', WHTHQ_CLIENT_USER_EMAIL);

                    $temp_wht_password_less_admin_login = 'temp_' . $new_wht_password_less_admin_login;
                    $temp_wht_password_less_admin_email = 'temp_' . $new_wht_password_less_admin_email;


                    //Log Out Person Using Password Less Admin Account
                    if ( class_exists( '\WP_Session_Tokens' ) ) {
                    //  $sessions = \WP_Session_Tokens::get_instance( $wht_password_less_admin_account );
                    //    $sessions->destroy_all();
                    }

                    //Create Temporary Password Less Admin Account
                    $temp_wht_password_less_admin_account = wp_create_user($temp_wht_password_less_admin_login, $random_password, $temp_wht_password_less_admin_email);

                    //Delete Previous Password Less Admin Account And Assign All Posts Into It
                    if (wp_delete_user($wht_password_less_admin_account->id, $temp_wht_password_less_admin_account)) {

                    }

                    $new_wht_password_less_admin_account = wp_create_user($new_wht_password_less_admin_login, $random_password, $new_wht_password_less_admin_email);

                    //Delete Temp Password Less Admin Account And Assign All Posts Into New
                    if (wp_delete_user($temp_wht_password_less_admin_account, $new_wht_password_less_admin_account)) {

                    }


                }


                self::report_set_branding_status('10');
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
