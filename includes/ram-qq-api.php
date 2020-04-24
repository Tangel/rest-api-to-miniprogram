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

    private static function API($key)
    {
        $api_url = [
            'send_subscribe' => 'https://api.q.qq.com/api/json/subscribe/SendSubscriptionMessage'
        ];
        return $api_url[$key];
    }

    private static function request($url, $method, $body)
    {
        $response = wp_remote_request($url, [
            'method' => $method,
            'body' => json_encode($body)
        ]);
        return !is_wp_error($response) ? json_decode($response['body'], true) : false;
    }

    public static function send_subscribe($touser, $template_id, $page, $data)
    {
        $access_token = self::get_access_token();
        if (!$access_token) {
            return;
        }
        $api = self::API('send_subscribe') . '?access_token=' . $access_token;
        $request = self::request($api, 'POST', [
            'touser' => $touser,
            'template_id' => $template_id,
            'page' => $page,
            'data' => $data
        ]);
        $errCode = $request['errcode'];
        $message = 'success';
        if ($errCode !== 0) {
            switch ($errCode) {
                case 40014:
                    $message = 'token 过期';
                    break;
                case 40037:
                    $message = '订阅模板 id 不正确';
                    break;
                case 46001:
                    $message = '用户未订阅';
                    break;
                case 46002:
                    $message = '当日超过推送限额';
                    break;
                case 41030:
                    $message = '对同一用户推送请求太快';
                    break;
                default:
                    $message = $request['errmsg'];
            }
        }
        $response = [
            'code' => $errCode,
            'msg' => $message
        ];
        return json_encode($response);
    }
}
