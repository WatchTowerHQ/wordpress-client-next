<?php
/**
 * Passive monitoring of mail delivery status.
 */

namespace WhatArmy\Watchtower;

if (!defined('ABSPATH')) {
    exit;
}

class Mail_Monitor
{
    private const OPTION_KEY = 'whthq_mail_monitor';
    private const WP_MAIL_SMTP_SLUG = 'wp-mail-smtp';

    public function __construct(bool $register_hooks = true)
    {
        if ($register_hooks) {
            add_action('wp_mail_failed', [$this, 'record_failure']);
            add_action('wp_mail_succeeded', [$this, 'record_success']);
        }
    }

    public function record_failure($wp_error): void
    {
        $state = $this->get_state();
        $messages = [];

        if ($wp_error instanceof \WP_Error) {
            $messages = $wp_error->get_error_messages();
        } elseif (is_string($wp_error) && $wp_error !== '') {
            $messages = [$wp_error];
        }

        $state['last_failure_at'] = current_time('mysql');
        $state['last_failure_message'] = sanitize_text_field(implode(' | ', array_filter($messages)));
        $state['last_event'] = 'failure';

        update_option(self::OPTION_KEY, $state, false);
    }

    public function record_success($mail_data): void
    {
        $state = $this->get_state();
        $state['last_success_at'] = current_time('mysql');
        $state['last_event'] = 'success';

        update_option(self::OPTION_KEY, $state, false);
    }

    public function get_status(): array
    {
        $state = $this->get_state();
        $smtp_plugin = $this->get_wp_mail_smtp_status();

        return [
            'smtp_plugin' => $smtp_plugin,
            'status' => $this->resolve_status($state),
            'last_event' => $state['last_event'],
            'last_success_at' => $state['last_success_at'],
            'last_failure_at' => $state['last_failure_at'],
            'last_failure_message' => $state['last_failure_message'],
        ];
    }

    private function resolve_status(array $state): string
    {
        if ($state['last_event'] === 'failure') {
            return 'error';
        }

        if ($state['last_event'] === 'success') {
            return 'ok';
        }

        return 'unknown';
    }

    private function get_state(): array
    {
        $state = get_option(self::OPTION_KEY, []);

        return [
            'last_event' => isset($state['last_event']) ? (string) $state['last_event'] : null,
            'last_success_at' => isset($state['last_success_at']) ? (string) $state['last_success_at'] : null,
            'last_failure_at' => isset($state['last_failure_at']) ? (string) $state['last_failure_at'] : null,
            'last_failure_message' => isset($state['last_failure_message']) ? (string) $state['last_failure_message'] : null,
        ];
    }

    private function get_wp_mail_smtp_status(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        foreach ($plugins as $basename => $details) {
            $slug = $this->resolve_plugin_slug($basename, $details);

            if ($slug !== self::WP_MAIL_SMTP_SLUG) {
                continue;
            }

            return [
                'slug' => self::WP_MAIL_SMTP_SLUG,
                'installed' => true,
                'active' => is_plugin_active($basename),
                'version' => isset($details['Version']) ? $details['Version'] : null,
            ];
        }

        return [
            'slug' => self::WP_MAIL_SMTP_SLUG,
            'installed' => false,
            'active' => false,
            'version' => null,
        ];
    }

    private function resolve_plugin_slug(string $basename, array $details): string
    {
        $dirname = dirname($basename);

        if ($dirname !== '.' && $dirname !== '') {
            return sanitize_title($dirname);
        }

        if (!empty($details['TextDomain'])) {
            return sanitize_title($details['TextDomain']);
        }

        return sanitize_title(basename($basename, '.php'));
    }
}
