<?php

//禁止直接访问
if (!defined('ABSPATH')) exit;

class RAW_Weixin_API
{
    //获取Access Token
    private static function get_access_token()
    {
        $appid = get_option('wx_appid');
        $secret = get_option('wx_secret');
        if (!$appid || !$secret) {
            return;
        }
        if (($access_token = get_option('weixin_access_token')) !== false && !empty($access_token) && time() < $access_token['expire_time']) {
            return $access_token['access_token'];
        }
        $api_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $appid . '&secret=' . $secret;
        $response = wp_remote_get($api_url);
        if (!is_wp_error($response) && is_array($response) && isset($response['body'])) {
            $result = json_decode($response['body'], true);
            if (!isset($result['errcode']) || $result['errcode'] === 0) {
                $access_token = array(
                    'access_token' => $result['access_token'],
                    'expire_time' => time() + intval($result['expires_in'])
                );
                update_option('weixin_access_token', $access_token);
                return $access_token['access_token'];
            }
        }
        return;
    }

    // 获取微信公众平台API地址
    private static function API($key)
    {
        $api_urls = [
            'send_subscribe' => 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send'
        ];
        return $api_urls[$key];
    }

    // 发起API请求
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
                case 40003:
                    $message = 'openid 不正确';
                    break;
                case 40037:
                    $message = '订阅模板 id 不正确';
                    break;
                case 43101:
                    $message = '用户拒绝接受消息';
                    break;
                case 47003:
                    $message = '模板参数不正确';
                    break;
                case 41030:
                    $message = 'page 路径不正确';
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
