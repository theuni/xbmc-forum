<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function get_topic_func($xmlrpc_params)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $time, $mybbgroups;
    
    $lang->load("member");
    
    $parser = new postParser;
    
    $input = Tapatalk_Input::filterXmlInput(array(
        'forum_id' => Tapatalk_Input::INT,
        'start_num' => Tapatalk_Input::INT,
        'last_num' => Tapatalk_Input::INT,
        'mode' => Tapatalk_Input::STRING,
    ), $xmlrpc_params);
    
    $lang->load("forumdisplay");
    
    $fid = $input['forum_id'];
    $foruminfo = get_forum($fid);
    if(!$foruminfo)
    {
        return xmlrespfalse($lang->error_invalidforum);
    }
    
    list($start, $limit) = process_page($input['start_num'], $input['last_num']);

    $forumpermissions = forum_permissions();
    $fpermissions = $forumpermissions[$fid];

    if($fpermissions['canview'] != 1)
    {
        return tt_no_permission();
    }

    switch($input['mode'])
    {
        case 'TOP':
            $stickyonly = " AND sticky=1 ";
            $tstickyonly = " AND t.sticky=1 ";
            break;
        case 'ANN':
            $stickyonly = " AND 0=1 ";
            $tstickyonly = " AND 0=1 ";
            break;
        default:
            $stickyonly = " AND sticky=0 ";
            $tstickyonly = " AND t.sticky=0 ";
            break;
    }

    if($mybb->user['uid'] == 0)
    {
        // Build a forum cache.
        $query = $db->query("
            SELECT *
            FROM ".TABLE_PREFIX."forums
            WHERE active != 0
            ORDER BY pid, disporder
        ");
        
        $forumsread = unserialize($mybb->cookies['mybb']['forumread']);

        if(!is_array($forumsread))
        {
            $forumsread = array();
        }
    }
    else
    {
        // Build a forum cache.
        $query = $db->query("
            SELECT f.*, fr.dateline AS lastread
            FROM ".TABLE_PREFIX."forums f
            LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
            WHERE f.active != 0
            ORDER BY pid, disporder
        ");
    }
    
    while($forum = $db->fetch_array($query))
    {
        if($mybb->user['uid'] == 0)
        {
            if($forumsread[$forum['fid']])
            {
                $forum['lastread'] = $forumsread[$forum['fid']];
            }
        }
        $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
    }

    tt_check_forum_password($foruminfo['fid']);

    if($foruminfo['linkto'])
    {
        return xmlrespfalse('This forum is a link');
    }

    $visibleonly = "AND visible='1'";
    $tvisibleonly = "AND t.visible='1'";

    // Check if the active user is a moderator and get the inline moderation tools.
    if(is_moderator($fid))
    {
        $ismod = true;
        $inlinecount = "0";
        $inlinecookie = "inlinemod_forum".$fid;
        $visibleonly = " AND (visible='1' OR visible='0')";
        $tvisibleonly = " AND (t.visible='1' OR t.visible='0')";
    }
    else
    {
        $inlinemod = '';
        $ismod = false;
    }

    if(is_moderator($fid, "caneditposts") || $fpermissions['caneditposts'] == 1)
    {
        $can_edit_titles = 1;
    }
    else
    {
        $can_edit_titles = 0;
    }
    
    $t = "t.";
    
    $sortby = "lastpost";
    $sortfield = "lastpost";
    $sortordernow = "desc";
    
    $threadcount = 0;
    $useronly = $tuseronly = "";
    if($fpermissions['canonlyviewownthreads'] == 1)
    {
        $useronly = "AND uid={$mybb->user['uid']}";
        $tuseronly = "AND t.uid={$mybb->user['uid']}";
    }

    if($fpermissions['canviewthreads'] != 0)
    {
        // How many posts are there?
        if($datecut > 0 || $fpermissions['canonlyviewownthreads'] == 1)
        {
            $query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $useronly $visibleonly $stickyonly");
            $threadcount = $db->fetch_field($query, "threads");
        }
        else
        {
            /*$query = $db->simple_select("forums", "threads, unapprovedthreads", "fid = '{$fid}'", array('limit' => 1));
            $forum_threads = $db->fetch_array($query);
            $threadcount = $forum_threads['threads'];
            if($ismod == true)
            {
                $threadcount += $forum_threads['unapprovedthreads'];
            }
            
            // If we have 0 threads double check there aren't any "moved" threads
            if($threadcount == 0)*/
            {
                $query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid = '$fid' $useronly $visibleonly $stickyonly", array('limit' => 1));
                $threadcount = $db->fetch_field($query, "threads");
            }
        }
    }

    // count unread stickies
    $query = $db->query("
        select COUNT(t.tid) AS threads
        from ".TABLE_PREFIX."threads t
        left join ".TABLE_PREFIX."threadsread tr on t.tid = tr.tid and tr.uid = '{$mybb->user['uid']}'
        where t.fid = '$fid' $tuseronly $tvisibleonly and t.sticky=1 and (tr.dateline < t.lastpost or tr.dateline is null)
    ");
    $unreadStickyCount = $db->fetch_field($query, "threads");

    $icon_urls_sql = "";
    if($_SERVER['HTTP_MOBIQUO_ID'] == 10)
    {
        $icon_urls_sql = ", (
            select group_concat(distinct u2.avatar separator '@@%#%@@')
            FROM ".TABLE_PREFIX."posts p2
            LEFT JOIN ".TABLE_PREFIX."users u2 ON (u2.uid = p2.uid)
            where p2.tid = t.tid
        ) as icon_urls";
    }

    if($fpermissions['canviewthreads'] != 0)
    {
        // Start Getting Threads
        $query = $db->query("
            SELECT t.*, {$ratingadd}{$select_rating_user}t.username AS threadusername, u.username, u.avatar, if({$mybb->user['uid']} > 0 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, po.message, IF(b.lifted > UNIX_TIMESTAMP() OR b.lifted = 0, 1, 0) as isbanned $icon_urls_sql
            FROM ".TABLE_PREFIX."threads t
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid){$select_voting}
            LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = t.uid)
            LEFT JOIN ".TABLE_PREFIX."threadsubscriptions s ON (s.tid = t.tid)
            LEFT JOIN ".TABLE_PREFIX."posts po ON (po.pid = t.firstpost)
            WHERE t.fid='$fid' $tuseronly $tvisibleonly $tstickyonly
            GROUP BY t.tid
            ORDER BY t.sticky DESC, {$t}{$sortfield} $sortordernow $sortfield2
            LIMIT $start, $limit
        ");
        
        while($thread = $db->fetch_array($query))
        {
            $threadcache[$thread['tid']] = $thread;

            // If this is a moved thread - set the tid for participation marking and thread read marking to that of the moved thread
            if(substr($thread['closed'], 0, 5) == "moved")
            {
                $tid = substr($thread['closed'], 6);
                if(!$tids[$tid])
                {
                    $moved_threads[$tid] = $thread['tid'];
                    $tids[$thread['tid']] = $tid;
                }
            }
            // Otherwise - set it to the plain thread ID
            else
            {
                $tids[$thread['tid']] = $thread['tid'];
                if($moved_threads[$tid])
                {
                    unset($moved_threads[$tid]);
                }
            }
        }
    }
    else
    {
        $threadcache = $tids = null;
    }


    if($tids)
    {
        $tids = implode(",", $tids);
    }

    if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] && $threadcache)
    {
        $query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
        while($post = $db->fetch_array($query))
        {
            if($moved_threads[$post['tid']])
            {
                $post['tid'] = $moved_threads[$post['tid']];
            }
            if($threadcache[$post['tid']])
            {
                $threadcache[$post['tid']]['doticon'] = 1;
            }
        }
    }

    if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0 && $threadcache)
    {
        $query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})"); 
        while($readthread = $db->fetch_array($query))
        {
            if($moved_threads[$readthread['tid']]) 
            { 
                 $readthread['tid'] = $moved_threads[$readthread['tid']]; 
             }
            if($threadcache[$readthread['tid']])
            {
                 $threadcache[$readthread['tid']]['lastread'] = $readthread['dateline']; 
            }
        }
    }

    if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
    {
        $query = $db->simple_select("forumsread", "dateline", "fid='{$fid}' AND uid='{$mybb->user['uid']}'");
        $forum_read = $db->fetch_field($query, "dateline");

        $read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
        if($forum_read == 0 || $forum_read < $read_cutoff)
        {
            $forum_read = $read_cutoff;
        }
    }
    else
    {
        $forum_read = my_get_array_cookie("forumread", $fid);
    }

    $threads = '';
    $load_inline_edit_js = 0;

    $topic_list = array();
    
    if(is_array($threadcache))
    {
        reset($threadcache);
        foreach($threadcache as $thread)
        {
            $unreadpost = false;

            $moved = explode("|", $thread['closed']);

            $thread['author'] = $thread['uid'];
            if(!$thread['username'])
            {
                $thread['username'] = $thread['threadusername'];
                $thread['profilelink'] = $thread['threadusername'];
            }
            else
            {
                $thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
            }
            
            // If this thread has a prefix, insert a space between prefix and subject
            if($thread['prefix'] != 0)
            {
                $threadprefix = build_prefixes($thread['prefix']);
                $thread['displayprefix'] = $threadprefix['displaystyle'];
            }

            $thread['subject'] = $parser->parse_badwords($thread['subject']);

            $prefix = '';
            if($thread['poll'])
            {
                $prefix = $lang->poll_prefix;
            }

            $thread['posts'] = $thread['replies'] + 1;

            if($moved[0] == "moved")
            {
                $prefix = $lang->moved_prefix;
                $thread['tid'] = $moved[1];
                $thread['replies'] = "-";
                $thread['views'] = "-";
            }

            $gotounread = '';
            $isnew = 0;
            $donenew = 0;

            if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $thread['lastpost'] > $forum_read)
            {
                if($thread['lastread'])
                {
                    $last_read = $thread['lastread'];
                }
                else
                {
                    $last_read = $read_cutoff;
                }
            }
            else
            {
                $last_read = my_get_array_cookie("threadread", $thread['tid']);
            }

            if($forum_read > $last_read)
            {
                $last_read = $forum_read;
            }

            if($thread['lastpost'] > $last_read && $moved[0] != "moved")
            {
                $folder .= "new";
                $folder_label .= $lang->icon_new;
                $new_class = "subject_new";
                $unreadpost = true;
            }
            else
            {
                $folder_label .= $lang->icon_no_new;
                $new_class = "subject_old";
            }
            
            $new_topic = array(
                'forum_id'          => new xmlrpcval($thread['fid'], 'string'),
                'topic_id'          => new xmlrpcval($thread['tid'], 'string'),
                'topic_title'       => new xmlrpcval(basic_clean($thread['subject']), 'base64'),
                'prefix'            => new xmlrpcval(basic_clean($thread['displayprefix']), 'base64'),
                'topic_author_id'   => new xmlrpcval($thread['uid'], 'string'),
                'topic_author_name' => new xmlrpcval(basic_clean($thread['username']), 'base64'),
                //'can_subscribe'     => new xmlrpcval(true, 'boolean'), // default as true

                'icon_url'          => new xmlrpcval(absolute_url($thread['avatar']), 'string'),
                'last_reply_time'   => new xmlrpcval(mobiquo_iso8601_encode($thread['lastpost']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($thread['lastpost'], 'string'),
                'short_content'     => new xmlrpcval(process_short_content($thread['message'], $parser), 'base64'),
                'reply_number'      => new xmlrpcval(intval($thread['replies']), 'int'),
                'view_number'       => new xmlrpcval(intval($thread['views']), 'int'),
                'is_approved'       => new xmlrpcval($thread['visible'], 'boolean'),
            );
        	$forumpermissions = forum_permissions($thread['fid']);
			if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
			{
				$new_topic['can_subscribe']  = new xmlrpcval(false, 'boolean');
			}
			else 
			{
				$new_topic['can_subscribe']  = new xmlrpcval(true, 'boolean');
			}
            if ($unreadpost)                                $new_topic['new_post']       = new xmlrpcval(true, 'boolean');
            if ($thread['sticky'])                          $new_topic['is_sticky']      = new xmlrpcval(true, 'boolean');
            if ($thread['subscribed'])                      $new_topic['is_subscribed']  = new xmlrpcval(true, 'boolean');
            else                                            $new_topic['is_subscribed']  = new xmlrpcval(false, 'boolean');
            if ($thread['closed'])                          $new_topic['is_closed']      = new xmlrpcval(true, 'boolean');
            if ($thread['isbanned'])                        $new_topic['is_ban']         = new xmlrpcval(true, 'boolean');
            if ($mybb->usergroup['canmodcp'] == 1)          $new_topic['can_ban']        = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canmanagethreads"))     $new_topic['can_move']       = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canopenclosethreads"))  $new_topic['can_close']      = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "candeleteposts"))       $new_topic['can_delete']     = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canmanagethreads"))     $new_topic['can_stick']      = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "canopenclosethreads"))  $new_topic['can_approve']    = new xmlrpcval(true, 'boolean');
            if (is_moderator($fid, "caneditposts"))         $new_topic['can_rename']     = new xmlrpcval(true, 'boolean');
            
            $topic_list[] = new xmlrpcval($new_topic, 'struct');
        }

        $customthreadtools = '';
    }

    // If there are no unread threads in this forum and no unread child forums - mark it as read
    require_once MYBB_ROOT."inc/functions_indicators.php";
    if(fetch_unread_count($fid) == 0 && $unread_forums == 0)
    {
        mark_forum_read($fid);
    }

    $prefix_list = array();
    
    // Does this user have additional groups?
    if($mybb->user['additionalgroups'])
    {
        $exp = explode(",", $mybb->user['additionalgroups']);

        // Because we like apostrophes...
        $imps = array();
        foreach($exp as $group)
        {
            $imps[] = "'{$group}'";
        }

        $additional_groups = implode(",", $imps);
        $extra_sql = "groups IN ({$additional_groups}) OR ";
    }
    else
    {
        $extra_sql = '';
    }

    if($mybb->version_code >= 1600 && $mybb->user['uid'])
    {
        $prefixes = get_prefix_list($fid);
        
        foreach($prefixes as $prefix)
        {
            $prefix_list[] = new xmlrpcval(array(
                'prefix_id' => new xmlrpcval($prefix['pid'], "string"),
                'prefix_display_name' => new xmlrpcval(basic_clean($prefix['prefix']), "base64"),
            ), "struct");
        }
    }

    $result = array(
        'total_topic_num' => new xmlrpcval($threadcount, 'int'),
        'forum_id'        => new xmlrpcval($fid, 'string'),
        'forum_name'      => new xmlrpcval(basic_clean($foruminfo['name']), 'base64'),
        'can_post'        => new xmlrpcval($foruminfo['type'] == "f" && $foruminfo['open'] != 0 && $mybb->user['uid'] > 0 && $mybb->usergroup['canpostthreads'], 'boolean'),
        //'require_prefix'  => new xmlrpcval(false, 'boolean'), default as false
        'prefixes'        => new xmlrpcval($prefix_list, 'array'),
        'can_upload'      => new xmlrpcval($fpermissions['canpostattachments'], 'boolean'),
    );
    
    if ($unreadStickyCount) $result['unread_sticky_count']  = new xmlrpcval($unreadStickyCount, 'int');
    
    if($mybb->user['uid'])
    {
        $query = $db->simple_select("forumsubscriptions", "fid", "fid='".$fid."' AND uid='{$mybb->user['uid']}'", array('limit' => 1));
        
        if($db->fetch_field($query, 'fid'))
        {
            $result['is_subscribed']  = new xmlrpcval(true, 'boolean');
        }
    }
    
    $result['topics']  = new xmlrpcval($topic_list, 'array');
    
    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function get_prefix_list($fid)
{
    global $db, $mybb;
    
    if($fid != 'all')
    {
        $fid = intval($fid);
    }
    
    if (defined('OLD_PREFIX'))
    {
        // Does this user have additional groups?
        if($mybb->user['additionalgroups'])
        {
            $exp = explode(",", $mybb->user['additionalgroups']);
    
            // Because we like apostrophes...
            $imps = array();
            foreach($exp as $group)
            {
                $imps[] = "'{$group}'";
            }
    
            $additional_groups = implode(",", $imps);
            $extra_sql = "groups IN ({$additional_groups}) OR ";
        }
        else
        {
            $extra_sql = '';
        }
    
        switch($db->type)
        {
            case "pgsql":
            case "sqlite":
                $whereforum = "";
                if($fid != 'all')
                {
                    $whereforum = " AND (','||forums||',' LIKE '%,{$fid},%' OR ','||forums||',' LIKE '%,-1,%' OR forums='')";
                }
                
                $query = $db->query("
                    SELECT pid, prefix
                    FROM ".TABLE_PREFIX."threadprefixes
                    WHERE ({$extra_sql}','||groups||',' LIKE '%,{$mybb->user['usergroup']},%' OR ','||groups||',' LIKE '%,-1,%' OR groups='')
                    {$whereforum}
                ");
                break;
            default:
                $whereforum = "";
                if($fid != 'all')
                {
                    $whereforum = " AND (CONCAT(',',forums,',') LIKE '%,{$fid},%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='')";
                }
                
                $query = $db->query("
                    SELECT pid, prefix
                    FROM ".TABLE_PREFIX."threadprefixes
                    WHERE ({$extra_sql}CONCAT(',',groups,',') LIKE '%,{$mybb->user['usergroup']},%' OR CONCAT(',',groups,',') LIKE '%,-1,%' OR groups='')
                    {$whereforum}
                ");
        }
        
        $prefixes = array();
        
        if($db->num_rows($query) > 0)
        {
            while($prefix = $db->fetch_array($query))
            {
                $prefixes[$prefix['pid']] = $prefix;
            }
        }
    }
    else
    {
        $prefix_cache = build_prefixes(0);
        if(!$prefix_cache)
        {
            return array(); // We've got no prefixes to show
        }
    
        $groups = array($mybb->user['usergroup']);
        if($mybb->user['additionalgroups'])
        {
            $exp = explode(",", $mybb->user['additionalgroups']);
    
            foreach($exp as $group)
            {
                $groups[] = $group;
            }
        }
    
        // Go through each of our prefixes and decide which ones we can use
        $prefixes = array();
        foreach($prefix_cache as $prefix)
        {
            if($fid != "all" && $prefix['forums'] != "-1")
            {
                // Decide whether this prefix can be used in our forum
                $forums = explode(",", $prefix['forums']);
    
                if(!in_array($fid, $forums))
                {
                    // This prefix is not in our forum list
                    continue;
                }
            }
    
            if($prefix['groups'] != "-1")
            {
                $prefix_groups = explode(",", $prefix['groups']);
    
                foreach($groups as $group)
                {
                    if(in_array($group, $prefix_groups) && !isset($prefixes[$prefix['pid']]))
                    {
                        // Our group can use this prefix!
                        $prefixes[$prefix['pid']] = $prefix;
                    }
                }
            }
            else
            {
                // This prefix is for anybody to use...
                $prefixes[$prefix['pid']] = $prefix;
            }
        }
    }

    return $prefixes;
}

if (!function_exists('build_prefixes'))
{
    define('OLD_PREFIX', 1);
    
    function build_prefixes($pid=0)
    {
        global $db;
        static $prefixes_cache;
        
        if (isset($prefixes_cache[$pid])) return $prefixes_cache[$pid];
        
        $query = $db->simple_select('threadprefixes', 'prefix, displaystyle', "pid='{$pid}'");
        $threadprefix = $db->fetch_array($query);
        
        $prefixes_cache[$pid] = $threadprefix;
        
        return $threadprefix;
    }
}