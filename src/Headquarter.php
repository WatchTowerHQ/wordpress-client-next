<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-12
 * Time: 22:34
 */

namespace WhatArmy\Watchtower;

/**
 * Class Headquarter
 * @package WhatArmy\Watchtower
 */
class Headquarter
{
    public $headquarterUrl;
    private int $curlTimeoutMs = 10000;
    private int $retryTimes = 1;
    private int $retryDelaySeconds = 600;

    public function setRetryTimes(int $retryTimes): void
    {
        $this->retryTimes = $retryTimes;
    }

    public function setRetryDelaySeconds(int $retryDelaySeconds): void
    {
        $this->retryDelaySeconds = $retryDelaySeconds;
    }

    public function setRetryDelayMinutes(int $retryDelayMinutes): void
    {
        $this->retryDelaySeconds = $retryDelayMinutes * 60;
    }

    public function setCurlTimeoutMs(int $curlTimeoutMs): void
    {
        $this->curlTimeoutMs = $curlTimeoutMs;
    }

    /**
     * Headquarter constructor.
     * @param $headquarterUrl
     */
    public function __construct($headquarterUrl)
    {
        // Ensure URL has a protocol (database stores only FQDN)
        if (!preg_match('/^https?:\/\//', $headquarterUrl)) {
            $headquarterUrl = 'https://' . $headquarterUrl;
        }
        $this->headquarterUrl = $headquarterUrl;
    }
    /**
     * @param string $endpoint
     * @param array $data
     * @return bool
     */
    public function call(string $endpoint = '/', array $data = []): bool
    {
        try {
            // Add access_token to data
            $data['access_token'] = get_option('watchtower')['access_token'];

            // Build URL with query parameters (matching original cURL GET behavior)
            $url = $this->headquarterUrl . $endpoint;
            if (!empty($data)) {
                $url = add_query_arg($data, $url);
            }

            // Convert timeout from milliseconds to seconds
            $timeout_seconds = max(1, ceil($this->curlTimeoutMs / 1000));

            // Use WordPress HTTP API instead of cURL
            $args = [
                'method' => 'GET',
                'timeout' => $timeout_seconds,
                'redirection' => 5,
                'httpversion' => '1.0',
                'sslverify' => !(defined('WHTHQ_DEV_MODE') && WHTHQ_DEV_MODE),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ];

            $response = wp_remote_get($url, $args);

            // Check for errors
            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Headquarter call error: ' . $response->get_error_message());
                }
                return false;
            }

            // Get response code
            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code === 200) {
                return true;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response_body = wp_remote_retrieve_body($response);
                error_log($response_body);
            }

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Headquarter call exception: ' . $e->getMessage());
            }
        }

        return false;
    }

    public function retryOnFailure(string $endpoint = '/', array $data = [])
    {
        $this->retryTimes--;

        // Call the initial endpoint
        $success = $this->call($endpoint, $data);

        // If the call failed, schedule a retry
        if (!$success) {
            if ($this->retryTimes > 0) {
                if (!wp_next_scheduled('retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $this->retryTimes, $this->retryDelaySeconds, $this->curlTimeoutMs])) {
                    wp_schedule_single_event(time() + $this->retryDelaySeconds, 'retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $this->retryTimes, $this->retryDelaySeconds, $this->curlTimeoutMs]);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Contacting WHTHQ failed using endpoint: ' . $endpoint);
                }
            }
        }
    }

    public function setCurlTimeoutInSeconds(int $seconds): void
    {
        $this->curlTimeoutMs = $seconds * 1000;
    }
}
