<?php

namespace WhatArmy\Watchtower;

if (!defined('ABSPATH')) {
    exit;
}

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
