<?php

if (!defined('ABSPATH')) {
    exit;
}

class RAM_REST_Comments_Controller  extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace     = 'minazukisaki-lite/v1';
        $this->resource_name = 'comment';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->resource_name . '/getcomments', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'get_comments'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args'               => array(
                    'postid' => array(
                        'required' => true
                    )
                )

            ),
            // Register our schema callback.
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->resource_name . '/add', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array($this, 'add_comment'),
                'permission_callback' => array($this, 'add_comment_permissions_check'),
                'args'               => array(
                    'post' => array(
                        'required' => true
                    ),
                    'author_name' => array(
                        'required' => true
                    ),
                    'author_email' => array(
                        'required' => true
                    ),
                    'content' => array(
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

        register_rest_route($this->namespace, '/' . $this->resource_name . '/get', array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array($this, 'getcomment'),
                'permission_callback' => array($this, 'get_comment_permissions_check'),
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

    function  getcomment($request)
    {
        global $wpdb;
        $openid = $request['openid'];
        $user_id = 0;
        $user = get_user_by('login', $openid);
        if ($user) {
            $user_id = $user->ID;
            if ($user_id == 0) {
                $result["code"] = "success";
                $result["message"] = "用户参数错误";
                $result["status"] = "500";
            } else {

                $sql = "SELECT * from " . $wpdb->posts . "  where ID in
        (SELECT comment_post_ID from " . $wpdb->comments . " where user_id=" . $user_id . "   GROUP BY comment_post_ID order by comment_date ) LIMIT 20";
                $_posts = $wpdb->get_results($sql);
                $posts = array();
                foreach ($_posts as $post) {
                    $post_id = $post->ID;
                    $post_date = $post->post_date;
                    $comment_total = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->comments . " where  comment_approved = '1' and comment_post_ID=" . $post_id);
                    $like_count = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->postmeta . " where meta_value='like' and post_id=" . $post_id);
                    $images = getPostImages($post->post_content, $post_id);
                    $_data["post_id"]  = $post_id;
                    $_data["post_title"]  = $post->post_title;
                    $_data["post_date"] = $post_date;
                    $_data["comment_total"] = $comment_total;
                    $_data['like_count'] = $like_count;
                    $_data['post_thumbnail_image'] = $images['post_thumbnail_image'];
                    // $_data['content_first_image'] = $images['content_first_image'];
                    // $_data['post_medium_image_300'] = $images['post_medium_image_300'];
                    // $_data['post_thumbnail_image_624'] = $images['post_thumbnail_image_624'];
                    $_data['post_frist_image'] = $images['post_frist_image'];
                    $_data['post_medium_image'] = $images['post_medium_image'];
                    $_data['post_large_image'] = $images['post_large_image'];
                    $_data['post_full_image'] = $images['post_full_image'];
                    // $_data['post_all_images'] = $images['post_all_images'];
                    $posts[] = $_data;
                }
                $result["code"] = "success";
                $result["message"] = "get  comments success";
                $result["status"] = "200";
                $result["data"] = $posts;
            }
        } else {
            $result["code"] = "success";
            $result["message"] = "用户参数错误";
            $result["status"] = "500";
        }

        $response = rest_ensure_response($result);
        return $response;
    }
    function add_comment($request)
    {

        $post = isset($request['post']) ? (int)$request['post'] : 0;
        $author_name = $request['author_name'];
        $author_email = $request['author_email'];
        $content = $request['content'];
        $author_url = $request['author_url'];
        $openid = $request['openid'];
        $parent = $request['parent'];
        $userid = isset($request['userid']) ? (int)$request['userid'] : 0; //被回复者
        $formId = isset($request['formId']) ? $request['formId'] : "";

        $authorIp = get_client_ip();
        $authorIp = empty($authorIp) ? '' : $authorIp;

        $wf_enable_comment_check = get_option('wf_enable_comment_check');
        $comment_approved = "1";
        if (!empty($wf_enable_comment_check)) {
            $comment_approved = "0";
        }

        global $wpdb;
        $user_id = 0;
        $useropenid = "";
        $sql = "SELECT ID FROM " . $wpdb->users . " WHERE user_login='" . $openid . "'";
        $user_id = (int)$wpdb->get_var($sql); //评论者id

        $commentdata = array(
            'comment_post_ID' => $post, // to which post the comment will show up
            'comment_author' => $author_name, //fixed value - can be dynamic
            'comment_author_email' => $author_email, //fixed value - can be dynamic
            'comment_author_url' => $author_url, //fixed value - can be dynamic
            'comment_content' => $content, //fixed value - can be dynamic
            'comment_type' => '', //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
            'comment_parent' => $parent, //0 if it's not a reply to another comment; if it's a reply, mention the parent comment ID here
            'user_id' => $user_id, //passing current user ID or any predefined as per the demand
            'comment_author_IP' => $authorIp,
            'comment_approved' => $comment_approved
        );

        $comment_id = wp_insert_comment(wp_filter_comment($commentdata));

        if (empty($comment_id)) {
            return new WP_Error('error', '添加评论失败', array('status' => 500));
        } else {
            $useropenid = "";
            if (!empty($userid)) {
                $sql = "SELECT user_login FROM " . $wpdb->users . " WHERE ID=" . $userid;
                $useropenid = $wpdb->get_var($sql);
            }
            $addcommentmetaflag = false;
            if (!empty($formId)) {
                $addcommentmetaflag = add_comment_meta($comment_id, 'formId', $formId, false);
            }
            if (strpos($author_url, "thirdqq.qlogo.cn") === false) {
                update_comment_meta($comment_id, 'wxapp_user', 'true');
            } else {
                update_comment_meta($comment_id, 'qqapp_user', 'true');
            }
            $result["code"] = "success";
            $message = '留言成功';

            if (!empty($wf_enable_comment_check)) {
                $message = '留言已提交,需管理员审核方可显示。';
            }

            if ($addcommentmetaflag) {
                $result["formId"] = "添加评论和formId成功";
            } else {
                $result["formId"] = "添加评论成功,添加formId失败";
            }
            $result["status"] = "200";
            $result["message"] = $message;
            $result["useropenid"] = $useropenid;
            $response = rest_ensure_response($result);
            return $response;
        }
    }

    function get_comments($request)
    {
        global $wpdb;
        $postid = isset($request['postid']) ? (int)$request['postid'] : 0;
        $limit = isset($request['limit']) ? (int)$request['limit'] : 0;
        $page = isset($request['page']) ? (int)$request['page'] : 0;
        $order = isset($request['order']) ? $request['order'] : '';
        if (empty($order)) {
            $order = "asc";
        }
        $page = ($page - 1) * $limit;
        $sql = $wpdb->prepare("SELECT t.*,(SELECT t2.meta_value  from " . $wpdb->commentmeta . "  t2 where  t.comment_ID = t2.comment_id  AND t2.meta_key = 'formId')  AS formId FROM " . $wpdb->comments . " t WHERE t.comment_post_ID =%d and t.comment_parent=0 and t.comment_approved='1' order by t.comment_date " . $order . " limit %d,%d", $postid, $page, $limit);

        $comments = $wpdb->get_results($sql);
        $commentslist  = array();
        foreach ($comments as $comment) {
            if ($comment->comment_parent == 0) {
                $data["id"] = $comment->comment_ID;
                $data["author_name"] = $comment->comment_author;
                $author_url = $comment->comment_author_url;
                $author_gravatar_urls = esc_url(get_avatar_url($comment->comment_author_email));
                $gravatar_avatar_url = "~https://secure\.gravatar\.com/avatar/(\S+)~i";
                $cdn_gravatar_url = esc_attr(get_option('wf_avatar_cdn_url')) == "" ? esc_attr(get_option('wf_cdn_url')) . "avatar/$1" : esc_attr(get_option('wf_avatar_cdn_url')) . "avatar/$1";
                $gravatar_url_replace = preg_replace($gravatar_avatar_url, $cdn_gravatar_url, $author_gravatar_urls);
                if (get_comment_meta($comment->comment_ID, 'qq_avatar', true)) {
                    $qq_number =  get_comment_meta($comment->comment_ID, 'qq_avatar', true);
                    $author_url = 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $qq_number . '&spec=100';
                    $data["author_avatar_urls"] = $author_url;
                } else {
                    $data["author_avatar_urls"] = strpos($author_url, "wx.qlogo.cn") ? $author_url : $gravatar_url_replace;
                }
                $data["date"] = time_tran($comment->comment_date);
                $data["content"] = $comment->comment_content;
                $data["formId"] = $comment->formId;
                $data["userid"] = $comment->user_id;
                $order = "asc";
                $data["child"] = $this->getchildcomment($postid, $comment->comment_ID, 5, $order);
                $commentslist[] = $data;
            }
        }
        $result["code"] = "success";
        $result["message"] = "获取评论成功";
        $result["status"] = "200";
        $result["data"] = $commentslist;


        $response = rest_ensure_response($result);
        return $response;
    }

    function getchildcomment($postid, $comment_id, $limit, $order)
    {
        global $wpdb;
        if ($limit > 0) {
            $commentslist  = array();
            $sql = $wpdb->prepare("SELECT t.*,(SELECT t2.meta_value  from " . $wpdb->commentmeta . "  t2 where  t.comment_ID = t2.comment_id  AND t2.meta_key = 'formId')  AS formId FROM " . $wpdb->comments . " t WHERE t.comment_post_ID =%d and t.comment_parent=%d and t.comment_approved='1' order by comment_date " . $order, $postid, $comment_id);

            $comments = $wpdb->get_results($sql);
            foreach ($comments as $comment) {
                $data["id"] = $comment->comment_ID;
                $data["author_name"] = $comment->comment_author;
                $author_url = $comment->comment_author_url;
                $author_gravatar_urls = esc_url(get_avatar_url($comment->comment_author_email));
                $gravatar_avatar_url = "~https://secure\.gravatar\.com/avatar/(\S+)~i";
                $cdn_gravatar_url = esc_attr(get_option('wf_avatar_cdn_url')) == "" ? esc_attr(get_option('wf_cdn_url')) . "avatar/$1" : esc_attr(get_option('wf_avatar_cdn_url')) . "avatar/$1";
                $gravatar_url_replace = preg_replace($gravatar_avatar_url, $cdn_gravatar_url, $author_gravatar_urls);
                if (get_comment_meta($comment->comment_ID, 'qq_avatar', true)) {
                    $qq_number =  get_comment_meta($comment->comment_ID, 'qq_avatar', true);
                    $author_url = 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $qq_number . '&spec=100';
                    $data["author_avatar_urls"] = $author_url;
                } else {
                    $data["author_avatar_urls"] = strpos($author_url, "wx.qlogo.cn") ? $author_url : $gravatar_url_replace;
                }
                $data["date"] = time_tran($comment->comment_date);
                $data["content"] = $comment->comment_content;
                $data["formId"] = $comment->formId;
                $data["userid"] = $comment->user_id;
                $data["child"] = $this->getchildcomment($postid, $comment->comment_ID, $limit - 1, $order);
                //$data["sql"]=$sql;
                $commentslist[] = $data;
            }
        }
        return $commentslist;
    }

    public function get_item_permissions_check($request)
    {
        $postid = isset($request['postid']) ? (int)$request['postid'] : 0;
        $limit = isset($request['limit']) ? (int)$request['limit'] : 0;
        $page = isset($request['page']) ? (int)$request['page'] : 0;
        $order = isset($request['order']) ? $request['order'] : '';
        if (empty($order)) {
            $order = "asc";
        }

        if (empty($postid) || empty($limit) || empty($page) || get_post($postid) == null) {
            return new WP_Error('error', ' 参数不能为空：postid,limit,page', array('status' => 500));
        } elseif (!is_numeric($limit) || !is_numeric($page) ||  !is_numeric($postid)) {
            return new WP_Error('error', ' 参数错误', array('status' => 500));
        }
        return true;
    }

    function get_comment_permissions_check($request)
    {
        $openid = $request['openid'];
        if (empty($openid)) {
            return new WP_Error('error', '参数错误', array('status' => 500));
        } else {

            if (!username_exists($openid)) {
                return new WP_Error('error', '不允许提交', array('status' => 500));
            }
        }

        return true;
    }

    function add_comment_permissions_check($request)
    {
        $post = (int)$request['post'];
        $author_name = $request['author_name'];
        $author_email = $request['author_email'];
        $content = $request['content'];
        $author_url = $request['author_url'];
        $openid = $request['openid'];
        $reqparent = '0';
        $userid = 0;
        $formId = '';

        if (isset($request['userid'])) {
            $userid = (int)$request['userid'];
        }

        if (isset($request['formId'])) {
            $formId = $request['formId'];
        }

        if (isset($request['parent'])) {
            $reqparent = $request['parent'];
        }
        $parent = 0;
        if (is_numeric($reqparent)) {
            $parent = (int)$reqparent;
            if ($parent < 0) {
                $parent = 0;
            }
        }

        if ($parent != 0) {
            $comment = get_comment($parent);
            if (empty($comment)) { {
                    return new WP_Error('error', 'parentId参数错误', array('status' => 500));
                }
            }
        }

        if (empty($openid) || empty($post)  || empty($author_url)  || empty($author_email)  || empty($content) || empty($author_name)) {
            return new WP_Error('error', '参数错误', array('status' => 500));
        }

        if (get_post($post) == null || $post == 0 || !is_int($post)) {
            return new WP_Error('error', 'postId 参数错误', array('status' => 500));
        } else {
            if ((!comments_open($post)) || has_tag('excerpt', $post)) {
                return new WP_Error('error', '该文章禁止吐槽', array('status' => 500));
            }
            global $wpdb;
            $status = $wpdb->get_row($wpdb->prepare("SELECT post_status, comment_status FROM $wpdb->posts WHERE ID = %d", $post));

            if (in_array($status->post_status, array('draft', 'pending'))) {
                return new WP_Error('error', '文章尚未发布', array('status' => 500));
            }
        }

        // if(!empty($formId) && strlen($formId>50))
        // {
        //     return new WP_Error( 'error', 'fromId参数错误', array( 'status' => 500 ) );
        // }

        if (!username_exists($openid)) {
            return new WP_Error('error', '不允许提交', array('status' => 500));
        } else if (is_wp_error(get_post($post))) {
            return new WP_Error('error', 'postId 参数错误', array('status' => 500));
        }

        return  true;
    }
}
