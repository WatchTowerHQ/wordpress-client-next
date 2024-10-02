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
           // $curl->options['CURLOPT_FOLLOWLOCATION'] = true;
            $data['access_token'] = get_option('watchtower')['access_token'];
            $response = $curl->get($this->headquarterUrl.$endpoint, $data);

            error_log($this->headquarterUrl.$endpoint);
            error_log(serialize($response->headers));
            $test = $response->body;
            error_log($test);

         //   if (strpos()) {
                // Request succeeded
         //       return true;
         //   }
        } catch (\Exception $e) {

        }


        return false;
    }

    public function retryOnFailure(string $endpoint = '/', array $data = [], int $retryDelay = 600)
    {
        // Call the initial endpoint
        $success = $this->call($endpoint, $data);
        error_log('here');
        if($success)
        {
            error_log('ok');
        }
        else
        {
            error_log('error');
        }
        // If the call failed, schedule a retry
        if (!$success) {
            // Schedule the retry using wp_schedule_single_event
            if (!wp_next_scheduled('retry_headquarter_call', [$endpoint, $data])) {
             //   wp_schedule_single_event(time() + $retryDelay, 'retry_headquarter_call', [$endpoint, $data]);
            }
        }
    }

    public function setCurlTimeoutInSeconds(int $seconds): void
    {
        $this->curlTimeoutMs = $seconds*1000;
    }
}
