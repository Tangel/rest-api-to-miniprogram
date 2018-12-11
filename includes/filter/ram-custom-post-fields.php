<?php 
//禁止直接访问
if ( ! defined( 'ABSPATH' ) ) exit;

function content_format($str) {
    $str = preg_replace('/<!-- wp:paragraph -->\s*<p>/i', '', $str);
    $str = preg_replace('/<\/p>\s*<!-- \/wp:paragraph -->/i', '', $str);
    $str = preg_replace('/<!-- wp:separator -->/i', '', $str);
    $str = preg_replace('/<!-- \/wp:separator -->/i', '', $str);
    $str = preg_replace('/<!-- wp:list -->/i', '', $str);
    $str = preg_replace('/<!-- \/wp:list -->/i', '', $str);
    $str = preg_replace('/<\/li>/i', "</li>\n", $str);
    $str = preg_replace('/\n<hr \S*\/>\n/i', '<hr />', $str);
    return $str;
}

function custom_post_fields( $data, $post, $request) { 
    global $wpdb,$is_chrome;
    $_data = $data->data; 
    $post_id =$post->ID;
    $content =get_the_content();
    $siteurl = get_option('siteurl');
    $upload_dir = wp_upload_dir();
    $content = str_replace( 'http:'.strstr($siteurl, '//'), 'https:'.strstr($siteurl, '//'), $content);
    $content = str_replace( 'http:'.strstr($upload_dir['baseurl'], '//'), 'https:'.strstr($upload_dir['baseurl'], '//'), $content);
    $images =getPostImages($content, $post_id); 
    $_data['post_thumbnail_image']=$images['post_thumbnail_image'];
    $_data['content_first_image']=$images['content_first_image'];
    $_data['post_medium_image_300']=$images['post_medium_image_300'];
    $_data['post_thumbnail_image_624']=$images['post_thumbnail_image_624'];
    $_data['post_frist_image']=$images['post_frist_image']; 
    $_data['post_medium_image']=$images['post_medium_image'];
    $_data['post_large_image']=$images['post_large_image'];
    $_data['post_full_image']=$images['post_full_image'];
    $_data['post_all_images']=$images['post_all_images'];
    $comments_count = wp_count_comments($post_id);    
    $_data['total_comments']=$comments_count->total_comments;
    $category =get_the_category($post_id);
    if(!empty($category))
    {
      $_data['category_name'] =$category[0]->cat_name; 
    }
    $_content['rendered'] = $content;
    $_content = cdn_images_url_replace($_content);
    $_data['content'] = content_format($_content);  
    $post_date =$post->post_date;
    //$_data['date'] =time_tran($post_date);
    $_data['post_date'] =time_tran($post_date);
    $sql =$wpdb->prepare("SELECT COUNT(1) FROM ".$wpdb->postmeta." where meta_value='like' and post_id=%d",$post_id);
    $like_count = $wpdb->get_var($sql);
    $_data['like_count']= $like_count; 
    $post_views = (int)get_post_meta($post_id, 'views', true);     
    $params = $request->get_params();
     if ( isset( $params['id'] ) ) {
        $sql=$wpdb->prepare("SELECT meta_key , (SELECT display_name from ".$wpdb->users." WHERE user_login=substring(meta_key,2)) as avatarurl FROM ".$wpdb->postmeta." where meta_value='like' and post_id=%d",$post_id);
        $likes = $wpdb->get_results($sql);
        $avatarurls =array();
        foreach ($likes as $like) {
            $_avatarurl['avatarurl']  =$like->avatarurl;   
            $avatarurls[] = $_avatarurl;        
        }
      $post_views =$post_views+1;  
      if(!update_post_meta($post_id, 'views', $post_views))   
      {  
        add_post_meta($post_id, 'views', 1, true);  
      } 
      $_data['avatarurls']= $avatarurls;
      date_default_timezone_set('Asia/Shanghai');
      $fristday= date("Y-m-d H:i:s", strtotime("-5 year"));
      $today = date("Y-m-d H:i:s"); //获取今天日期时间
      $tags= $_data["tags"];
        if(count($tags)>0)
        {
          $tags=implode(",",$tags);
          $sql="
          SELECT DISTINCT ID, post_title
          FROM ".$wpdb->posts." , ".$wpdb->term_relationships.", ".$wpdb->term_taxonomy."
          WHERE ".$wpdb->term_taxonomy.".term_taxonomy_id =  ".$wpdb->term_relationships.".term_taxonomy_id
          AND ID = object_id
          AND taxonomy = 'post_tag'
          AND post_status = 'publish'
          AND post_type = 'post'
          AND term_id IN (" . $tags . ")
          AND ID != '" . $post_id . "'
          AND post_date BETWEEN '".$fristday."' AND '".$today."' 
          ORDER BY  RAND()
          LIMIT 5";
          $related_posts = $wpdb->get_results($sql);
          $_data['related_posts'] = $related_posts;
        }
        else{
          $_data['related_posts']=null;
        }
    }
    else 
    {
        unset($_data['content'] );   
    }
    $pageviews =$post_views ;   
    $_data['pageviews'] = $pageviews;
    if(!empty($category))
    {
      $category_id=$category[0]->term_id;
      $next_post = get_next_post($category_id, '', 'category');
      $previous_post = get_previous_post($category_id, '', 'category');
      $_data['next_post_id'] = !empty($next_post->ID)?$next_post->ID:null;
      $_data['next_post_title'] = !empty($next_post->post_title)?$next_post->post_title:null;
      $_data['previous_post_id'] = !empty($previous_post->ID)?$previous_post->ID:null;
      $_data['previous_post_title'] = !empty($previous_post->post_title)?$previous_post->post_title:null;
      $init_np_images_url = "~([\s\S]+)".BLOG_URL."wp-content/uploads/([0-9]{4}/[0-9]{2}/(\w*\-?\w*)\.(jpg|png|jpeg))([\s\S]+)~i";
      if( $is_chrome ) {
          $cdn_np_images_url = esc_attr(get_option('wf_cdn_url'))."wp-content/uploads/$2.webp";
      } else {
          $cdn_np_images_url = esc_attr(get_option('wf_cdn_url'))."wp-content/uploads/$2";
      }
      $next_post_thumbnail_image = preg_replace( $init_np_images_url, $cdn_np_images_url, get_the_post_thumbnail($next_post->ID, 'thumbnail'));
      $previous_post_thumbnail_image = preg_replace( $init_np_images_url, $cdn_np_images_url, get_the_post_thumbnail($previous_post->ID, 'thumbnail'));
      $_data['next_post_thumbnail_image'] = !empty($next_post->ID)?$next_post_thumbnail_image:null;
      $_data['previous_post_thumbnail_image'] = !empty($previous_post->ID)?$previous_post_thumbnail_image:null; 
    }   
    $data->data = $_data;     
    return $data; 
}
