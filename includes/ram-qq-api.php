<?php

//禁止直接访问
if (!defined('ABSPATH')) exit;

class RAW_QQ_API
{
    //获取Access Token
    private static function get_access_token()
    {
        $appid = get_option('qq_appid');
        $secret = get_option('qq_secret');
        if (!$appid || !$secret) {
            return false;
        }
        if (($access_token = get_option('qq_access_token')) !== false && !empty($access_token) && time() < $access_token['expire_time']) {
            return $access_token['access_token'];
        }
        $api_url = 'https://api.q.qq.com/api/getToken?grant_type=client_credential&appid=' . $appid . '&secret=' . $secret;
        $response = wp_remote_get($api_url);
        if (!is_wp_error($response) && is_array($response) && isset($response['body'])) {
            $result = json_decode($response['body'], true);
            if (!isset($result['errcode']) || $result['errcode'] === 0) {
                $access_token = array(
                    'access_token' => $result['access_token'],
                    'expire_time' => time() + intval($result['expires_in'])
                );
                update_option('qq_access_token', $access_token);
                return $access_token['access_token'];
            }
        }
        return false;
    }
}
