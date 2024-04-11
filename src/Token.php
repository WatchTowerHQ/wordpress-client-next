<?php
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
        return md5(uniqid());
    }
}
