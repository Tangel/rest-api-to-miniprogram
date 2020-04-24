<?php

function comment_approved_action($comment)
{
    $email = trim($comment->comment_author_email);
    if (strpos($email, '@open.id') === false) {
        return;
    }
    $touser = explode('@open.id', $email)[0];
    if (!$touser) {
        return;
    }
    $template_id = trim(get_option('approve_template_id'));
    if (!$template_id) {
        return;
    }
    $page = 'pages/detail/detail?id=' . $comment->comment_post_ID;
    $data = [
        // 审核内容
        'thing1' => [
            'value' => mb_strimwidth($comment->comment_content, 0, 36, '...', 'utf-8')
        ],
        // 审核结果
        'phrase2' => [
            'value' => '审核已通过'
        ],
        // 提示
        'thing3' => [
            'value' => '您在「' . mb_strimwidth(get_the_title($comment->comment_post_ID), 0, 24, '...', 'utf-8') . '」的评论'
        ]
    ];
    RAW_Weixin_API::send_subscribe($touser, $template_id, $page, $data);
}

function comment_notify_action($comment_id)
{
    $comment = get_comment($comment_id);
    $parent_id = $comment->comment_parent ? $comment->comment_parent : '';
    $spam_confirmed = $comment->comment_approved;
    if (($parent_id != '') && ($spam_confirmed == 'approve' || $spam_confirmed == '1')) {
        $email = trim(get_comment($parent_id)->comment_author_email);
        if (strpos($email, '@open.id') === false) {
            return;
        }
        $touser = explode('@open.id', $email)[0];
        if (!$touser) {
            return;
        }
        $template_id = trim(get_option('notify_template_id'));
        if (!$template_id) {
            return;
        }
        $page = 'pages/detail/detail?id=' . $comment->comment_post_ID;
        $data = [
            // 回复人
            'name2' => [
                'value' => mb_strimwidth($comment->comment_author, 0, 18, '...', 'utf-8')
            ],
            // 回复内容
            'thing3' => [
                'value' => mb_strimwidth($comment->comment_content, 0, 36, '...', 'utf-8')
            ],
            // 回复时间
            'date4' => [
                'value' => date('Y-m-d H:i', time() + 8 * 3600)
            ],
            // 提示
            'thing6' => [
                'value' => '您在「' . mb_strimwidth(get_the_title($comment->comment_post_ID), 0, 24, '...', 'utf-8') . '」的评论'
            ]
        ];
        RAW_Weixin_API::send_subscribe($touser, $template_id, $page, $data);
    }
}
