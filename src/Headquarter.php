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

            if (isset($response->headers['Status-Code']) && $response->headers['Status-Code'] === '200') {
                return true;
            }

        } catch (\Exception $e) {

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
            if($this->retryTimes > 0) {
                if (!wp_next_scheduled('retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $this->retryTimes, $this->retryDelaySeconds, $this->curlTimeoutMs])) {
                    wp_schedule_single_event(time() + $this->retryDelaySeconds, 'retry_headquarter_call', [$this->headquarterUrl, $endpoint, $data, $this->retryTimes, $this->retryDelaySeconds, $this->curlTimeoutMs]);
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
