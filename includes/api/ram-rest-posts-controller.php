<?php

if (!defined('ABSPATH')) {
    exit;
}

class RAM_REST_Posts_Controller  extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace     = 'minazukisaki-lite/v1';
        $this->resource_name = 'post';
    }

    // Register our routes.
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->resource_name . '/swipe', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getPostSwipe'),
                'permission_callback' => array($this, 'get_item_permissions_check')
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/like', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array($this, 'postLike'),
                'permission_callback' => array($this, 'post_like_permissions_check'),
                'args'               => array(
                    'postid' => array(
                        'required' => true
                    ),
                    'openid' => array(
                        'required' => true
                    )
                )
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/islike', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array($this, 'getIsLike'),
                'permission_callback' => array($this, 'post_like_permissions_check'),
                'args'               => array(
                    'postid' => array(
                        'required' => true
                    ),
                    'openid' => array(
                        'required' => true
                    )
                )
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/mylike', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getmyLike'),
                'permission_callback' => array($this, 'get_mylike_permissions_check'),
                'args'               => array(
                    'openid' => array(
                        'required' => true
                    )
                )
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));
        register_rest_route($this->namespace, '/' . $this->resource_name . '/mydetail', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getMyDetail'),
                'permission_callback' => array($this, 'get_mydetail_permissions_check'),
                'args'               => array(
                    'openid' => array(
                        'required' => true
                    )
                )
            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    function getPostSwipe($request)
    {
        global $wpdb;
        $postSwipeIDs = get_option('wf_swipe');
        $posts = array();
        if (!empty($postSwipeIDs)) {
            $sql = "SELECT *  from " . $wpdb->posts . " where id in(" . $postSwipeIDs . ") ORDER BY find_in_set(id,'" . $postSwipeIDs . "')";
            $_posts = $wpdb->get_results($sql);
            foreach ($_posts as $post) {
                $post_id = (int) $post->ID;
                $post_title = stripslashes($post->post_title);
                $post_date = $post->post_date;
                $post_permalink = get_permalink($post->ID);
                $_data["id"]  = $post_id;
                $_data["post_title"] = $post_title;
                $_data["post_date"] = $post_date;
                $_data["post_permalink"] = $post_permalink;
                $_data['type'] = "detailpage";
                $pageviews = (int) get_post_meta($post_id, 'views', true);
                $_data['pageviews'] = $pageviews;
                $comment_total = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->comments . " where  comment_approved = '1' and comment_post_ID=" . $post_id);
                $_data['comment_total'] = $comment_total;
                $images = getPostImages($post->post_content, $post_id);
                $_data['post_thumbnail_image'] = $images['post_thumbnail_image'];
                $_data['post_frist_image'] = $images['post_frist_image'];
                $_data['post_medium_image'] = $images['post_medium_image'];
                $_data['post_large_image'] = $images['post_large_image'];
                $_data['post_full_image'] = $images['post_full_image'];
                $posts[] = $_data;
            }
            $result["code"] = "success";
            $result["message"] = "获取轮播图成功";
            $result["status"] = "200";
            $result["posts"] = $posts;
        } else {
            return new WP_Error('error', '没有设置轮播图的文章id', array('status' => "500"));
        }
        $response = rest_ensure_response($result);
        return $response;
    }

    public function getmyLike($request)
    {
        global $wpdb;
        $openid = $request['openid'];
        $sql = "SELECT * from " . $wpdb->posts . "  where ID in
(SELECT post_id from " . $wpdb->postmeta . " where meta_value='like' and meta_key='_" . $openid . "') ORDER BY post_date desc LIMIT 20";
        $_posts = $wpdb->get_results($sql);
        $posts = array();
        foreach ($_posts as $post) {
            $post_id = $post->ID;
            $post_date = $post->post_date;
            $comment_total = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->comments . " where  comment_approved = '1' and comment_post_ID=" . $post_id);
            $like_count = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->postmeta . " where meta_value='like' and post_id=" . $post_id);
            $images = getPostImages($post->post_content, $post_id);
            $_data["post_id"] = $post_id;
            $_data["post_title"] = $post->post_title;
            $_data["post_date"] = $post_date;
            $_data["comment_total"] = $comment_total;
            $_data['like_count'] = $like_count;
            $_data['post_thumbnail_image'] = $images['post_thumbnail_image'];
            $_data['post_frist_image'] = $images['post_frist_image'];
            $_data['post_medium_image'] = $images['post_medium_image'];
            $_data['post_large_image'] = $images['post_large_image'];
            $_data['post_full_image'] = $images['post_full_image'];
            $posts[] = $_data;
        }
        $result["code"] = "success";
        $result["message"] = "获取我点赞的文章成功";
        $result["status"] = "200";
        $result["data"] = $posts;
        $response = rest_ensure_response($result);
        return $response;
    }

    public function getIsLike($request)
    {
        $openid = $request['openid'];
        $postid = $request['postid'];
        $openid = "_" . $openid;
        $postmeta = get_post_meta($postid, $openid, true);
        if (!empty($postmeta)) {
            $result["code"] = "success";
            $result["message"] = "you have  posted like ";
            $result["status"] = "200";
        } else {
            $result["code"] = "success";
            $result["message"] = "you have not  posted like ";
            $result["status"] = "501";
        }
        $response = rest_ensure_response($result);
        return $response;
    }

    public function postLike($request)
    {
        $openid = $request['openid'];
        $openid = "_" . $openid;
        $postid = $request['postid'];
        $postmeta = get_post_meta($postid, $openid, true);
        if (empty($postmeta)) {
            if (add_post_meta($postid, $openid, 'like', true)) {
                $result["code"] = "success";
                $result["message"] = "点赞成功 ";
                $result["status"] = "200";
            } else {
                return new WP_Error('error', '点赞失败', array('status' => "500"));
            }
        } else {
            $result["code"] = "success";
            $result["message"] = "已点赞 ";
            $result["status"] = "501";
        }
        $response = rest_ensure_response($result);
        return $response;
    }

    public function getMyDetail($request)
    {
        global $wpdb;
        $openid = $request['openid'];
        $user_id = 0;
        $user = get_user_by('login', $openid);
        $like_sql = "SELECT count(*) from " . $wpdb->posts . "  where ID in
(SELECT post_id from " . $wpdb->postmeta . " where meta_value='like' and meta_key='_" . $openid . "') ORDER BY post_date desc LIMIT 20";
        $likeCount = $wpdb->get_var($like_sql);
        if ($user) {
            $user_id = $user->ID;
            if ($user_id == 0) {
                $result["status"] = "error";
                $result["message"] = "用户参数错误";
                $result["code"] = 500;
            } else {
                $comment_sql = "SELECT count(*) from " . $wpdb->posts . "  where ID in
        (SELECT comment_post_ID from " . $wpdb->comments . " where user_id=" . $user_id . "   GROUP BY comment_post_ID order by comment_date ) LIMIT 20";
                $commentCount = $wpdb->get_var($comment_sql);
                $result["status"] = "success";
                $result["message"] = "获取用户信息成功";
                $result["code"] = 200;
                $result["data"]["likeCount"] = $likeCount;
                $result["data"]["commentCount"] = $commentCount;
            }
        } else {
            $result["status"] = "error";
            $result["message"] = "用户参数错误";
            $result["code"] = 500;
        }
        $response = rest_ensure_response($result);
        return $response;
    }

    public function post_like_permissions_check($request)
    {
        $openid = $request['openid'];
        $postid = $request['postid'];
        if (empty($openid) || empty($postid)) {
            return new WP_Error('error', '参数错误', array('status' => 400));
        } else {
            if (!username_exists($openid)) {
                return new WP_Error('error', '不允许提交', array('status' => 400));
            }
            if (is_wp_error(get_post($postid))) {
                return new WP_Error('error', 'postId参数错误', array('status' => 400));
            }
        }
        return true;
    }

    public function get_mylike_permissions_check($request)
    {
        $openid = $request['openid'];
        if (empty($openid)) {
            return new WP_Error('error', 'openid is empty', array('status' => 500));
        } else {
            if (!username_exists($openid)) {
                return new WP_Error('error', '不允许提交', array('status' => 500));
            }
        }
        return true;
    }

    public function get_item_permissions_check($request)
    {
        return true;
    }

    public function get_mydetail_permissions_check($request)
    {
        $openid = $request['openid'];
        if (empty($openid)) {
            return new WP_Error('error', 'openid is empty', array('status' => 500));
        } else {
            if (!username_exists($openid)) {
                return new WP_Error('error', '不允许提交', array('status' => 500));
            }
        }
        return true;
    }
}
