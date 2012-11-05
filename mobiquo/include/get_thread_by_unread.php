<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_indicators.php";
require_once MYBB_ROOT."inc/functions_user.php";

require_once TT_ROOT."include/get_thread_by_post.php";

function get_thread_by_unread_func($xmlrpc_params)
{
    global $db, $mybb;

    $input = Tapatalk_Input::filterXmlInput(array(
        'topic_id'          => Tapatalk_Input::INT,
        'posts_per_request' => Tapatalk_Input::INT,
        'return_html'       => Tapatalk_Input::INT
    ), $xmlrpc_params);

    $thread = get_thread($input['topic_id']);

    if(is_moderator($thread['fid']))
    {
        $visible = "AND (p.visible='0' OR p.visible='1')";
    }
    else
    {
        $visible = "AND p.visible='1'";
    }

    $query = $db->query("select p.pid from ".TABLE_PREFIX."posts p
        LEFT JOIN ".TABLE_PREFIX."threadsread tr on p.tid = tr.tid and tr.uid = '{$mybb->user['uid']}'
        where p.tid='{$thread['tid']}' and tr.dateline < p.dateline $visible
        order by p.dateline desc
        limit 1");
    $pid = $db->fetch_field($query, 'pid');
    if(!$pid)
    {
        $query = $db->query("select p.pid from ".TABLE_PREFIX."posts p
                             where p.tid='{$thread['tid']}' $visible
                             order by p.dateline desc
                             limit 1");
        $pid = $db->fetch_field($query, 'pid');
    }

    return get_thread_by_post_func(new xmlrpcval(array(
        new xmlrpcval($pid, "string"),
        new xmlrpcval($input['posts_per_request'], 'int'),
        new xmlrpcval(!!$input['return_html'], 'boolean'),
    ), 'array'));
}