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
    public function generate()
    {
        return md5(uniqid());
    }

    public function refresh()
    {
        $my_options = get_option('watchtower');
        $my_options['access_token'] = 'dupaaaaaaaa';
        update_option('watchtower', $my_options);

        return $this->generate();
    }
}