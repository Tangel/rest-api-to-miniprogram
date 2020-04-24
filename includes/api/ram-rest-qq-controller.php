<?php

if (!defined('ABSPATH')) {
    exit;
}

class RAM_REST_QQ_Controller  extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace     = 'minazukisaki-lite/v1';
        $this->resource_name = 'qq';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->resource_name . '/getopenid', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array($this, 'getOpenid'),
                'permission_callback' => array($this, 'get_openid_permissions_check'),
                'args'               => array(
                    'js_code' => array(
                        'required' => true
                    ),
                    'encryptedData' => array(
                        'required' => true
                    ),
                    'iv' => array(
                        'required' => true
                    ),
                    'avatarUrl' => array(
                        'required' => true
                    ),
                    'nickname' => array(
                        'required' => true
                    )
                )
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/getuserinfo', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getUserInfo'),
                'permission_callback' => array($this, 'get_userInfo_permissions_check'),
                'args'               => array(
                    'openid' => array(
                        'required' => true
                    )
                )
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/updateuserinfo', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array($this, 'updateUserInfo'),
                'permission_callback' => array($this, 'update_userInfo_permissions_check'),
                'args'               => array(
                    'openid' => array(
                        'required' => true
                    ),
                    'avatarUrl' => array(
                        'required' => true
                    ),
                    'nickname' => array(
                        'required' => true
                    )
                )
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    function updateUserInfo($request)
    {
        $openId = $request['openid'];
        $nickname = empty($request['nickname']) ? '' : $request['nickname'];
        $nickname = filterEmoji($nickname);
        $_nickname = base64_encode($nickname);
        $_nickname = strlen($_nickname) > 49 ? substr($_nickname, 49) : $_nickname;
        $avatarUrl = empty($request['avatarUrl']) ? '' : $request['avatarUrl'];
        $user = get_user_by('login', $openId);
        if (empty($user)) {
            return new WP_Error('error', '此用户不存在', array('status' => 500));
        }
        $userdata = array(
            'ID'            => $user->ID,
            'first_name'    => $nickname,
            'nickname'      => $nickname,
            'user_nicename' => $_nickname,
            'display_name'  => $nickname,
            'user_email'    => $openId . '@qq.com'
        );
        $userId = wp_update_user($userdata);
        if (is_wp_error($userId)) {
            return new WP_Error('error', '更新wp用户错误：', array('status' => 500));
        }
        update_user_meta($userId, 'avatar', $avatarUrl);
        update_user_meta($userId, 'usertype', "qq", "qq");
        $userLevel = getUserLevel($userId);
        $result["code"] = "success";
        $result["message"] = "更新成功";
        $result["status"] = "200";
        $result["openid"] = $openId;
        $result["userLevel"] = $userLevel;
        $response = rest_ensure_response($result);
        return $response;
    }

    function getUserInfo($request)
    {
        $openId = $request['openid'];
        $_user = get_user_by('login', $openId);
        if (empty($_user)) {
            return new WP_Error('error', '无此用户信息', array('status' => 500));
        } else {
            $user['nickname'] = $_user->display_name;
            $avatar = get_user_meta($_user->ID, 'avatar', true);
            if (empty($avatar)) {
                $avatar = plugins_url() . "/" . REST_API_TO_MINIPROGRAM_PLUGIN_NAME . "/includes/images/gravatar.png";
            }
            $userLevel = getUserLevel($_user->ID);
            $user['userLevel'] = $userLevel;
            $user['avatar'] = $avatar;
            $result["code"] = "success";
            $result["message"] = "获取用户信息成功";
            $result["status"] = "200";
            $result["user"] = $user;
            $response = rest_ensure_response($result);
            return $response;
        }
    }
    function getOpenid($request)
    {
        $js_code = $request['js_code'];
        $encryptedData = $request['encryptedData'];
        $iv = $request['iv'];
        $avatarUrl = $request['avatarUrl'];
        $nickname = empty($request['nickname']) ? '' : $request['nickname'];
        $appid = get_option('qq_appid');
        $appsecret = get_option('qq_secret');
        if (empty($appid) || empty($appsecret)) {
            return new WP_Error('error', 'appid或appsecret为空', array('status' => 500));
        } else {
            $access_url = "https://api.q.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $appsecret . "&js_code=" . $js_code . "&grant_type=authorization_code";
            $access_result = https_request($access_url);
            if ($access_result == 'ERROR') {
                return new WP_Error('error', 'API错误：' . json_encode($access_result), array('status' => 501));
            }
            $api_result  = json_decode($access_result, true);
            if (empty($api_result['openid']) || empty($api_result['session_key'])) {
                return new WP_Error('error', 'API错误：' . json_encode($api_result), array('status' => 502));
            }
            $openId = $api_result['openid'];
            $userId = 0;
            $sessionKey = $api_result['session_key'];
            $data = '';
            $access_result = decrypt_data($appid, $sessionKey, $encryptedData, $iv, $data);
            if ($access_result != 0) {
                return new WP_Error('error', '解密错误：' . $access_result, array('status' => 503));
            } else {
                $data = json_decode($data, true);
                $watermark_appid = $data['watermark']['appid'];
                if ($watermark_appid !== $appid) {
                    return new WP_Error('error', 'AppID 不一致', ['status' => 502]);
                }
            }
            $nickname = filterEmoji($nickname);
            $_nickname = base64_encode($nickname);
            $_nickname = strlen($_nickname) > 49 ? substr($_nickname, 49) : $_nickname;
            // $avatarUrl= $data['avatarUrl'];
            if (!username_exists($openId)) {
                $new_user_data = apply_filters('new_user_data', array(
                    'user_login'    => $openId,
                    'first_name'    => $nickname,
                    'nickname'      => $nickname,
                    'user_nicename' => $_nickname,
                    'display_name'  => $nickname,
                    'user_pass'     => $openId,
                    'user_email'    => $openId . '@qq.com'
                ));
                $userId = wp_insert_user($new_user_data);
                if (is_wp_error($userId) || empty($userId) ||  $userId == 0) {
                    return new WP_Error('error', '插入wordpress用户错误：', array('status' => 500));
                }
                update_user_meta($userId, 'avatar', $avatarUrl);
                update_user_meta($userId, 'usertype', "qq");
            } else {
                $user = get_user_by('login', $openId);
                $userdata = array(
                    'ID'            => $user->ID,
                    'first_name'    => $nickname,
                    'nickname'      => $nickname,
                    'user_nicename' => $_nickname,
                    'display_name'  => $nickname,
                    'user_email'    => $openId . '@qq.com'
                );
                $userId = wp_update_user($userdata);
                if (is_wp_error($userId)) {
                    return new WP_Error('error', '更新wp用户错误：', array('status' => 500));
                }
                update_user_meta($userId, 'avatar', $avatarUrl);
                update_user_meta($userId, 'usertype', "qq", "qq");
            }
            $userLevel = getUserLevel($userId);
            $result["code"] = "success";
            $result["message"] = "获取用户信息成功";
            $result["status"] = "200";
            $result["openid"] = $openId;
            // $result["data"] = $data;
            $result["userLevel"] = $userLevel;
            $response = rest_ensure_response($result);
            return $response;
        }
    }

    function https_curl_post($url, $data, $type)
    {
        if ($type == 'json') {
            //$headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
            $data = json_encode($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return 'ERROR';
        }
        curl_close($curl);
        return $data;
    }

    function  get_userInfo_permissions_check($request)
    {
        return true;
    }

    function  update_userInfo_permissions_check($request)
    {
        return true;
    }

    function get_openid_permissions_check($request)
    {
        $js_code = $request['js_code'];
        // $encryptedData = $request['encryptedData'];
        // $iv = $request['iv'];
        // $avatarUrl = $request['avatarUrl'];
        // $nickname = empty($request['nickname']) ? '' : $request['nickname'];
        if (empty($js_code)) {
            return new WP_Error('error', 'js_code是空值', array('status' => 500));
        } else if (!function_exists('curl_init')) {
            return new WP_Error('error', 'php  curl扩展没有启用', array('status' => 500));
        }
        return true;
    }
}
