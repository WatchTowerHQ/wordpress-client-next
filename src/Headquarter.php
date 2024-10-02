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

    /**
     * Headquarter constructor.
     * @param $headquarterUrl
     */
    public function __construct($headquarterUrl)
    {
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
            $curl = new \Curl();
            $curl->options['CURLOPT_SSL_VERIFYPEER'] = false;
            $curl->options['CURLOPT_SSL_VERIFYHOST'] = false;
            $curl->options['CURLOPT_TIMEOUT_MS'] = $this->curlTimeoutMs;
            $curl->options['CURLOPT_NOSIGNAL'] = 1;

            $curl->headers['Accept']= 'application/json';

            $data['access_token'] = get_option('watchtower')['access_token'];

            $response = $curl->get($this->headquarterUrl.$endpoint, $data);

            if (isset($response->headers['Status-Code']) && $response->headers['Status-Code'] === '201') {
                return true;
            }

        } catch (\Exception $e) {

        }

        return false;
    }

    public function retryOnFailure(string $endpoint = '/', array $data = [],int $retryTimes = 1,  int $retryDelay = 600)
    {
        $retryTimes--;

        // Call the initial endpoint
        $success = $this->call($endpoint, $data);

        // If the call failed, schedule a retry
        if (!$success) {
            if($retryTimes > 0) {
                if (!wp_next_scheduled('retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $retryTimes, $retryDelay])) {
                    wp_schedule_single_event(time() + $retryDelay, 'retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $retryTimes, $retryDelay]);
                }
            }
            else
            {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log('Contacting WHTHQ failed using endpoint: ' . $endpoint);
                }
            }
        }
    }

    public function setCurlTimeoutInSeconds(int $seconds): void
    {
        $this->curlTimeoutMs = $seconds*1000;
    }
}
