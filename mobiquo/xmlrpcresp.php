<?php

defined('IN_MOBIQUO') or exit;

function search_func()
{
    global $search_data, $include_topic_num, $mybb;
    
    $return_list = array();
    
    foreach ($search_data['results'] as $item)
    {
        $fid = $item['fid'];
        
        if($search_data['type'] == 'threads')
        {
            $lastpost = $item['lastpost'];
            $isbanned = $lastpost['isbanned'];
            
            $return_thread = array(
                'forum_id'              => new xmlrpcval($item['fid'], 'string'),
                'forum_name'            => new xmlrpcval(basic_clean($item['forumname']), 'base64'),
                'topic_id'              => new xmlrpcval($item['tid'], 'string'),
                'topic_title'           => new xmlrpcval(basic_clean($item['subject']), 'base64'),
                
                'post_author_id'        => new xmlrpcval($lastpost ? $lastpost['uid'] : $item['lastposteruid'], 'string'),
                'post_author_name'      => new xmlrpcval(basic_clean($lastpost ? $lastpost['username'] : $item['lastposter']), 'base64'),
                'last_reply_time'       => new xmlrpcval(mobiquo_iso8601_encode($lastpost ? $lastpost['dateline'] : $item['lastpost']), 'dateTime.iso8601'),
                'timestamp'             => new xmlrpcval($lastpost ? $lastpost['dateline'] : $item['lastpost'], 'string'),
                'icon_url'              => new xmlrpcval(absolute_url($lastpost ? $lastpost['avatar'] : $item['avatar']) , 'string'),
                'short_content'         => new xmlrpcval(basic_clean($lastpost ? $lastpost['prev'] : ''), 'base64'),
                
                // compatibility data
                'last_reply_author_id'  => new xmlrpcval($lastpost ? $lastpost['uid'] : $item['lastposteruid'], 'string'),
              'last_reply_author_name'  => new xmlrpcval(basic_clean($lastpost ? $lastpost['username'] : $item['lastposter']), 'base64'),
                'post_time'             => new xmlrpcval(mobiquo_iso8601_encode($lastpost ? $lastpost['dateline'] : $item['lastpost']), 'dateTime.iso8601'),
                
                'reply_number'          => new xmlrpcval($item['replies'], 'int'),
                'view_number'           => new xmlrpcval($item['views'], 'int'),
                'attachment'            => new xmlrpcval($item['attachmentcount'], 'string'),
                'can_subscribe'         => new xmlrpcval(true, 'boolean'),
                'is_approved'           => new xmlrpcval($item['visible'], 'boolean'),
            );
            
            if ($item['threadprefix']) $return_thread['prefix'] = new xmlrpcval(basic_clean($item['threadprefix']), 'base64');
            
            if (is_moderator($fid, "canopenclosethreads"))  $return_thread['can_close']     = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "candeleteposts"))       $return_thread['can_delete']    = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canmanagethreads"))     $return_thread['can_stick']     = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canmanagethreads"))     $return_thread['can_move']      = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canopenclosethreads"))  $return_thread['can_approve']   = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "caneditposts"))         $return_thread['can_rename']    = new xmlrpcval(true, 'boolean');
            if ($mybb->usergroup['canmodcp'] == 1)          $return_thread['can_ban']       = new xmlrpcval(true, 'boolean');
            if ($isbanned)       $return_thread['is_ban']        = new xmlrpcval(true, 'boolean');
            if ($item['unread']) $return_thread['new_post']      = new xmlrpcval(true, 'boolean');
            if ($item['closed']) $return_thread['is_closed']     = new xmlrpcval(true, 'boolean');
            if ($item['sticky']) $return_thread['is_sticky']     = new xmlrpcval(true, 'boolean');
            if ($item['is_sub']) $return_thread['is_subscribed'] = new xmlrpcval(true, 'boolean');
            
            $xmlrpc_thread = new xmlrpcval($return_thread, 'struct');
            
            array_push($return_list, $xmlrpc_thread);
        }
        else
        {
            $return_post = array(
                'forum_id'          => new xmlrpcval($item['fid'], 'string'),
                'forum_name'        => new xmlrpcval(basic_clean($item['forumname']), 'base64'),
                'topic_id'          => new xmlrpcval($item['tid'], 'string'),
                'topic_title'       => new xmlrpcval(basic_clean($item['thread_subject']), 'base64'),
                'post_id'           => new xmlrpcval($item['pid'], 'string'),
                'post_title'        => new xmlrpcval(basic_clean($item['subject']), 'base64'),
                'post_author_id'    => new xmlrpcval($item['uid'], 'string'),
                'post_author_name'  => new xmlrpcval(basic_clean($item['username']), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($item['dateline']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($item['dateline'], 'string'),
                'reply_number'      => new xmlrpcval($item['thread_replies'], 'int'),
                'view_number'       => new xmlrpcval($item['thread_views'], 'int'),
                'icon_url'          => new xmlrpcval(absolute_url($item['avatar']), 'string'),
                'short_content'     => new xmlrpcval(basic_clean($item['prev']), 'base64'),
                'is_approved'       => new xmlrpcval($item['visible'], 'boolean'),
            );
            
            
            if (is_moderator($fid, "canmanagethreads"))     $return_post['can_approve'] = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "candeleteposts"))       $return_post['can_delete']  = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canmanagethreads"))     $return_post['can_move']    = new xmlrpcval(true, 'boolean');
            if ($mybb->usergroup['canmodcp'] == 1)          $return_post['can_ban']     = new xmlrpcval(true, 'boolean');
            if ($item['isbanned'])  $return_post['is_ban']      = new xmlrpcval(true, 'boolean');
            if ($item['unread'])    $return_post['new_post']    = new xmlrpcval(true, 'boolean');
            
            $xmlrpc_post = new xmlrpcval($return_post, 'struct');
            
            array_push($return_list, $xmlrpc_post);
        }
    }
    
    if ($include_topic_num) {
        if($search_data['type'] == 'threads') {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_data['sid'], 'string'),
                'total_topic_num'   => new xmlrpcval($search_data['total'], 'int'),
                'topics'            => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        } else {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_data['sid'], 'string'),
                'total_post_num'    => new xmlrpcval($search_data['total'], 'int'),
                'posts'             => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        }
    } else {
        return new xmlrpcresp(new xmlrpcval($return_list, 'array'));
    }
}

function thl_func()
{
    return new xmlrpcresp(new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_result' => new xmlrpcval('This feature is not supported', 'base64'),
    ), 'struct'));
}