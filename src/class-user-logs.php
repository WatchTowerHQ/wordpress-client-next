<?php
/**
 * Author: Code2Prog
 * Date: 2019-06-10
 * Time: 18:43
 */

namespace WhatArmy\Watchtower;

/**
 * Class User_Logs
 * @package WhatArmy\Watchtower
 */
class User_Logs
{
    public function get(): array
    {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}watchtower_logs ORDER BY id DESC LIMIT 100",
            OBJECT);
        $to_ret = [];
        foreach ($results as $result) {
            $user_info = ($result->who != 0) ? get_userdata($result->who)->user_login : 'Auto Update';
            $result->user_login = $user_info;
            $to_ret[] = $result;
        }

        return $to_ret;
    }
}
