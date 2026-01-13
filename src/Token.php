<?php

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Author: Code2Prog
 * Date: 2019-06-07
 * Time: 18:16
 */

namespace WhatArmy\Watchtower;

/**
 * Class Token
 * @package WhatArmy\Watchtower
 */
class Token
{
    public function generate(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            error_log('Failed to generate WHT token: ' . $e->getMessage());
            throw new \RuntimeException('Could not generate WHT token.');
        }
    }
}
