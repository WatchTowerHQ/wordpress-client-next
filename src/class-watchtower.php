<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 15:16
 */

namespace WhatArmy\Watchtower;


use WhatArmy\Watchtower\Files\File_Backup;
use WhatArmy\Watchtower\Mysql\Mysql_Backup;

/**
 * Class Watchtower
 * @package WhatArmy\Watchtower
 */
class Watchtower
{
    /**
     * Watchtower constructor.
     */
    public function __construct()
    {
        $this->load_wp_plugin_class();
        add_filter('action_scheduler_queue_runner_batch_size', [$this, 'batch_size']);
        add_filter('action_scheduler_queue_runner_concurrent_batches', [$this, 'concurrent_batches']);
        new Password_Less_Access();
        new Download();
        new Api();
        new File_Backup();
        new Mysql_Backup();
        new Updates_Monitor();

        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_init', [$this, 'page_init']);
        add_action('plugins_loaded', [$this, 'check_db']);
        add_action('admin_enqueue_scripts', [$this, 'whthq_admin_scripts']);

        if (function_exists('is_multisite') && is_multisite()) {
            register_activation_hook(WHTHQ_MAIN, [$this, 'install_hook_multisite']);
            add_action('init', [$this, 'new_blog']);
            add_action('wp_delete_site', [$this, 'delete_blog']);
        } else {
            register_activation_hook(WHTHQ_MAIN, [$this, 'install_hook']);
        }

        register_activation_hook(WHTHQ_MAIN, [$this, 'check_db']);
        add_action('admin_notices', [$this, 'wht_activation_notice']);

        add_filter('plugin_action_links_'.plugin_basename(WHTHQ_MAIN), array($this, 'plugin_action_links'));

    }

    public function whthq_admin_scripts($hook)
    {
        if ('settings_page_watchtower-setting-admin' != $hook) {
            return;
        }
        wp_enqueue_style('whthq_admin_css', WHTHQ_MAIN_URI.'assets/css/wht_dashboard.css');
        wp_enqueue_script('whthq_admin_script', WHTHQ_MAIN_URI.'assets/js/whthq_admin.js', array('jquery','clipboard'), '1.0',
            true);

    }

    public static function plugin_action_links($links)
    {
        $action_links = array(
            'settings' => '<a href="'.admin_url('options-general.php?page=watchtower-setting-admin').'">Settings</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * @param $concurrent_batches
     * @return int
     */
    public function concurrent_batches($concurrent_batches)
    {
        return 1;
    }

    /**
     * @param $batch_size
     * @return int
     */
    public function batch_size($batch_size)
    {
        return 1;
    }

    public function delete_blog($blog)
    {
        global $wpdb;
        if (is_int($blog)) {
            $blog_id = $blog;
        } else {
            $blog_id = $blog->id;
        }
        switch_to_blog($blog_id);
        $table_name = $wpdb->prefix.'watchtower_logs';
        $wpdb->query("DROP TABLE IF EXISTS ".$table_name);
        restore_current_blog();

    }

    public function new_blog()
    {
        if (!get_option('watchtower')) {
            $this->install_hook();
        }
    }

    public function install_hook_multisite()
    {
        global $wpdb;

        $old_blog = $wpdb->blogid;
        $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ($blogs as $blog_id) {
            switch_to_blog($blog_id);
            $this->install_hook();
        }
        switch_to_blog($old_blog);
        return;
    }

    /**
     *
     */
    public function install_hook()
    {
        $token = new Token;
        add_option('watchtower', [
            'access_token' => $token->generate(),
        ]);
        flush_rewrite_rules();
        set_transient('wht-activation-notice-message', true, 5);
    }


    /**
     * Admin Notice on Activation.
     * @since 0.1.0
     */
    public function wht_activation_notice()
    {
        if (get_transient('wht-activation-notice-message')) {
            ?>
            <div class="updated notice is-dismissible"
                 style="padding-top:15px;padding-bottom:15px;display:flex;flex-direction:row">
                <div>
                    <img src="<?php echo WHTHQ_MAIN_URI.'assets/images/logo1x.png'; ?>"
                         style="height:100px;padding-right:15px;" alt="">
                </div>
                <div>
                    <h2>Thank you for installing the WatchTower HQ Monitoring Agent!</h2>
                    <h4 style="margin-bottom:0;"><a
                                href="<?php echo admin_url('options-general.php?page=watchtower-setting-admin'); ?>">Click here</a> to view your Access Token.</h4>
                </div>
            </div>
            <?php
            delete_transient('wht-activation-notice-message');
        }
    }

    /**
     *
     */
    public function load_wp_plugin_class()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }
    }

    /**
     * @param $version
     */
    public function create_db($version)
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix.'watchtower_logs';


        $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		action  VARCHAR(255) NOT NULL,
		who smallint(5) NOT NULL,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

        require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('watchtower_db_version', $version);
    }

    /**
     *
     */
    public function check_db()
    {
        if (get_option('watchtower_db_version') != WHTHQ_DB_VERSION) {
            $this->create_db(WHTHQ_DB_VERSION);
        }
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_options_page(
            'Settings Watchtower',
            'Watchtower Settings',
            'manage_options',
            'watchtower-setting-admin',
            [$this, 'create_admin_page']
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        $this->options = get_option('watchtower');
        ?>
        <div class="wrap">
            <div class="wht-wrap">
                <img src="<?php echo plugin_dir_url(__FILE__).'../assets/images/logo.png'; ?>" alt="">
                <form method="post" action="options.php" id="wht-form">
                    <?php
                    settings_fields('watchtower');
                    ?>
                    <?php
                    do_settings_sections('watchtower-settings');
                    ?>

                    <hr style="margin-top:40px;">
                    <div class="wht-info-paragraph">
                        <h4>Need a new token?</h4>
                        Use the button below to generate a new access token.
                    </div>
                    <div class="wht-buttons">
                        <div>
                            <p class="submit">
                                <?php

                                $nonce = wp_create_nonce("wht_refresh_token_nonce");
                                ?>
                                <button type="button" data-nonce="<?php echo $nonce ?>" data-style="wht-refresh-token"
                                        id="wht-refresh-token"
                                        class="button button-primary">
                                    Refresh Token
                                </button>
                            </p>
                        </div>
                        <div>
                            <?php
                            submit_button('Save', 'primary', 'submit-save', true, array('data-style' => 'wht-save'));
                            ?>
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'watchtower',
            'watchtower',
            [$this, 'sanitize']
        );

        add_settings_section(
            'access_token_section',
            '',
            [$this, 'access_token_info'],
            'watchtower-settings'
        );

        add_settings_field(
            'access_token',
            'Refresh Token',
            [$this, 'access_token_callback'],
            'watchtower-settings',
            'access_token_section',
            []
        );

        add_settings_field(
            'use_beta',
            'Use Beta Plugin',
            [$this, 'use_beta_callback'],
            'watchtower-settings',
            'access_token_section',
            []
        );
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function sanitize($input)
    {
        $token = new Token;
        $new_input = array();
        if (isset($input['access_token']) && $input['access_token'] == 'true') {
            $new_input['access_token'] = $token->generate();
        } else {
            $new_input['access_token'] = get_option('watchtower')['access_token'];
        }

        if (isset($input['use_beta']) && $input['use_beta'] == 'true') {
            $new_input['use_beta'] = true;
        } else {
            $new_input['use_beta'] = false;
        }

        return $new_input;
    }

    /**
     *
     */
    public function access_token_info()
    {
        print '
<span class="watchtower_token_area">
<span class="watchtower_token_field clip" data-clipboard-text="'.get_option('watchtower')['access_token'].'">
<small>ACCESS TOKEN</small>
'.get_option('watchtower')['access_token'].'
<span id="wht-copied">Copied!</span>
<span id="wht-copy-info"><span class="dashicons dashicons-admin-page"></span></span>
</span>
</span>';
    }

    /**
     *
     */
    public function access_token_callback()
    {
        printf(
            '<input type="checkbox" value="true" name="watchtower[access_token]" />',
            isset($this->options['access_token']) ? esc_attr($this->options['access_token']) : ''
        );
    }

    public function use_beta_callback()
    {
        $is_checked = (get_option('watchtower')['use_beta'] == 1) ? "checked" : "";
        printf(
            '<input type="checkbox" value="true" name="watchtower[use_beta]" '.$is_checked.'/>',
            isset($this->options['use_beta']) ? esc_attr($this->options['use_beta']) : ''
        );
    }
}
