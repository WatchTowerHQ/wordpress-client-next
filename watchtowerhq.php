<?php

use WhatArmy\Watchtower\Watchtower;

defined('ABSPATH') or die('No script kiddies please!');

//DO NOT EDIT BELLOW THIS LINE
//<--AUTO-GENERATED-PLUGIN-HEADER-START-->
/**
 * Plugin Name: WatchTowerHQ
 * Plugin URI: https://wordpress.org/plugins/watchtowerhq/
 * Description: The WatchTowerHQ plugin allows us to monitor, backup, upgrade, and manage your site!
 * Author: WhatArmy
 * Version: 3.16.2
 * Requires PHP: 7.4
 * Author URI: https://watchtowerhq.co/
 * License: GPLv2 or later
 * Text Domain: watchtowerhq
 **/
//<--AUTO-GENERATED-PLUGIN-HEADER-END-->
//DO NOT EDIT ABOVE THIS LINE

/**
 * Constants
 */

define('WHTHQ_MIN_PHP', "7.4");
define('WHTHQ_MAIN', __FILE__);
define('WHTHQ_BASENAME', plugin_basename(__FILE__));
define('WHTHQ_MAIN_URI', plugin_dir_url(__FILE__));
define('WHTHQ_DB_VERSION', '1.0');
define('WHTHQ_PLUGIN_NAME', 'WatchTowerHQ');

define('WHTHQ_CLIENT_USER_NAME', 'WatchTowerClient');
define('WHTHQ_CLIENT_USER_EMAIL', 'wpdev@whatarmy.com');

define('WHTHQ_BACKUP_DIR_NAME', 'watchtower_backups');
define('WHTHQ_BACKUP_EXCLUSIONS_ENDPOINT', '/backupExclusions');
define('WHTHQ_BACKUP_DIR', wp_upload_dir()['basedir'] . '/' . WHTHQ_BACKUP_DIR_NAME);
define('WHTHQ_BACKUP_FILES_PER_QUEUE', class_exists("ZipArchive") ? 450 : 200);
define('WHTHQ_DB_RECORDS_MAX', 6500);
define('WHTHQ_BRANDING_FILE', wp_upload_dir()['basedir'] . '/' . 'WatchTowerClientCustomBranding.json');
define('WHTHQ_MAX_HEADQUARTER_IDLE_TIME_SECONDS', 172800);

// Developer Mode - performance and security may be impacted
if (!defined('WHTHQ_DEV_MODE')) {
    define('WHTHQ_DEV_MODE', false);
}

if (version_compare(PHP_VERSION, WHTHQ_MIN_PHP) >= 0) {
    /**
     * Run App
     */
    require_once(plugin_dir_path(WHTHQ_MAIN) . '/vendor/woocommerce/action-scheduler/action-scheduler.php');
    require __DIR__ . '/vendor/autoload.php';

    new Watchtower();

    // Show admin warning when developer mode is enabled
    if (WHTHQ_DEV_MODE) {
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-warning"><p><strong>%s:</strong> %s</p></div>',
                esc_html(\WhatArmy\Watchtower\Branding::get_wht_branding('Name', 'WatchtowerHQ')),
                esc_html__('Developer mode is enabled. Performance and security may be impacted.', 'watchtowerhq')
            );
        });
    }
} else {
    function whthq_admin_notice__error()
    {
        $class = 'notice notice-error';
        $message = sprintf(
            __('Woops! Your current PHP version (%1$s) is not supported by WatchTower. Please upgrade your PHP version to at least v%2$s. Older than %2$s versions of PHP can cause security and performance problems.', 'watchtowerhq'),
            PHP_VERSION,
            WHTHQ_MIN_PHP
        );

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    add_action('admin_notices', 'whthq_admin_notice__error');
}

