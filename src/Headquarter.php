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
     * @return $this
     */
    public function call(string $endpoint = '/', array $data = []): Headquarter
    {
        try {
            $curl = new \Curl();
            $curl->options['CURLOPT_SSL_VERIFYPEER'] = false;
            $curl->options['CURLOPT_SSL_VERIFYHOST'] = false;
            $curl->options['CURLOPT_TIMEOUT_MS'] = 10000;
            $curl->options['CURLOPT_NOSIGNAL'] = 1;
            $data['access_token'] = get_option('watchtower')['access_token'];
            $curl->get($this->headquarterUrl.$endpoint, $data);
        } catch (\Exception $e) {

        }


        return $this;
    }
}
