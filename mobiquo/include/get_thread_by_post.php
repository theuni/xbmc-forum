<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_indicators.php";
require_once MYBB_ROOT."inc/functions_user.php";

require_once TT_ROOT."include/get_thread.php";

function get_thread_by_post_func($xmlrpc_params)
{
    global $db, $mybb, $position;

    $input = Tapatalk_Input::filterXmlInput(array(
        'post_id'           => Tapatalk_Input::INT,
        'posts_per_request' => Tapatalk_Input::INT,
        'return_html'       => Tapatalk_Input::INT
    ), $xmlrpc_params);

    $post = get_post($input['post_id']);

    if(!$input['posts_per_request'])
        $input['posts_per_request'] = 20;

    if(is_moderator($post['fid']))
    {
        $visible = "AND (visible='0' OR visible='1')";
    }
    else
    {
        $visible = "AND visible='1'";
    }

    $query = $db->simple_select("posts", "COUNT(*) AS position", "pid < '{$input['post_id']}' and tid='{$post['tid']}' $visible");
    $position = $db->fetch_field($query, 'position');

    $page = floor($position / $input['posts_per_request']) + 1;
    $position = $position + 1;
    
    $response = get_thread_func(new xmlrpcval(array(
        new xmlrpcval($post['tid'], "string"),
        new xmlrpcval(($page-1) * $input['posts_per_request'], 'int'),
        new xmlrpcval(($page-1) * $input['posts_per_request'] + $input['posts_per_request'], 'int'),
        new xmlrpcval(!!$input['return_html'], 'boolean'),
    ), 'array'));
    
    return $response;
}
