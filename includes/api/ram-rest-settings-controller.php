<?php

if (!defined('ABSPATH')) {
    exit;
}

class RAM_REST_Options_Controller  extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace     = 'minazukisaki-lite/v1';
        $this->resource_name = 'options';
    }

    // Register our routes.
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->resource_name . '/enableComment', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getEnableComment'),
                'permission_callback' => array($this, 'get_item_permissions_check')
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    public function getEnableComment($request)
    {
        if (preg_match('/MicroMessenger/', $_SERVER['HTTP_USER_AGENT'])) {
            $option  = get_option('wf_enable_comment_option_wechat');
            if (empty($option)) {
                $result["code"] = "success";
                $result["message"] = "获取是否开启评论成功";
                $result["status"] = "200";
                $result["enableComment"] = "0";
            } else {
                $result["code"] = "success";
                $result["message"] = "获取是否开启评论成功";
                $result["status"] = "200";
                $result["enableComment"] = "1";
            }
        } else if (preg_match('/(QQ\/MiniApp|qqdevtools)/', $_SERVER['HTTP_USER_AGENT'])) {
            $option  = get_option('wf_enable_comment_option_qq');
            if (empty($option)) {
                $result["code"] = "success";
                $result["message"] = "获取是否开启评论成功";
                $result["status"] = "200";
                $result["enableComment"] = "0";
            } else {
                $result["code"] = "success";
                $result["message"] = "获取是否开启评论成功";
                $result["status"] = "200";
                $result["enableComment"] = "1";
            }
        } else {
            $result["code"] = "error";
            $result["message"] = "User-Agent 异常";
            $result["status"] = "200";
            $result["enableComment"] = "0";
        }
        $response = rest_ensure_response($result);
        return $response;
    }

    public function get_item_permissions_check($request)
    {
        return true;
    }
}
