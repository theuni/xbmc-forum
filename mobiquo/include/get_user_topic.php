<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function get_user_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("search");

	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'user_name' => Tapatalk_Input::STRING,
	), $xmlrpc_params);
		
	$start = 0;
	$limit = 49;
			 
	$uid = $mybb->user['uid'];
	if(!empty($input['user_name'])){
		$query = $db->simple_select("users", "uid", "username='{$input['user_name_esc']}'");
		$uid = $db->fetch_field($query, "uid");
	}
	
	if($uid == 0)
		return xmlrespfalse('User not found');

	$threads = array();

	if($mybb->user['uid'] == 0)
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT fid
			FROM ".TABLE_PREFIX."forums
			WHERE active != 0
			ORDER BY pid, disporder
		");
		
		$forumsread = unserialize($mybb->cookies['mybb']['forumread']);
	}
	else
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT f.fid, fr.dateline AS lastread
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$uid}')
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
		$readforums[$forum['fid']] = $forum['lastread'];
	}
	$fpermissions = forum_permissions();
	
	///////
	
	$where_sql = "t.uid = '{$uid}'";

	if($mybb->input['fid'])
	{
		$where_sql .= " AND t.fid='".intval($mybb->input['fid'])."'";
	}
	else if($mybb->input['fids'])
	{
		$fids = explode(',', $mybb->input['fids']);
		foreach($fids as $key => $fid)
		{
			$fids[$key] = intval($fid);
		}
		
		if(!empty($fids))
		{
			$where_sql .= " AND t.fid IN (".implode(',', $fids).")";
		}
	}
	
	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}
	
	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if($forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$where_sql .= "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
	}
					
					
		// Start Getting Threads
		$query = $db->query("
			SELECT t.*, {$ratingadd}{$select_rating_user}t.username AS threadusername, u.username, u.avatar, if({$mybb->user['uid']} > 1 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, po.message, f.name as forumname, lp.lastusername, lp.lastavatar, IF(b.lifted > UNIX_TIMESTAMP(), 1, 0) as isbanned
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid){$select_voting}
			LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = t.uid)
			LEFT JOIN ".TABLE_PREFIX."threadsubscriptions s ON (s.tid = t.tid)
			LEFT JOIN ".TABLE_PREFIX."posts po ON (po.pid = t.firstpost)
			left join ".TABLE_PREFIX."forums f on f.fid = t.fid
			left join ( 
				select ipo.tid, ipo.username as lastusername, iu.avatar as lastavatar
				from mybb_posts ipo
				left join mybb_users iu on ipo.uid = iu.uid
				order by ipo.dateline desc
			) lp on lp.tid = t.tid
			WHERE $where_sql
			GROUP BY t.tid
			ORDER BY t.dateline desc
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
				$thread['threadprefix'] .= '&nbsp;';
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
			
			$topic_list[] = new xmlrpcval(array(
				'forum_id'          => new xmlrpcval($thread['fid'], 'string'),
				'forum_name'        => new xmlrpcval(basic_clean($thread['forumname']), 'base64'),
				'topic_id'          => new xmlrpcval($thread['tid'], 'string'),
				'topic_title'       => new xmlrpcval($thread['subject'], 'base64'),
				'last_reply_author_name'  => new xmlrpcval($thread['lastusername'], 'base64'),
		'last_reply_author_display_name'  => new xmlrpcval($thread['lastusername'], 'base64'),
				'icon_url'          => new xmlrpcval(absolute_url($thread['lastavatar']), 'string'),
				'last_reply_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['lastpost']), 'dateTime.iso8601'),
				'reply_number'      => new xmlrpcval($thread['replies'], 'int'),
				'view_number'       => new xmlrpcval($thread['views'], 'int'),
				'new_post'          => new xmlrpcval($unreadpost, 'boolean'),
				'short_content'     => new xmlrpcval(process_short_content($thread['message'], $parser), 'base64'),
				'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
				'is_subscribed'     => new xmlrpcval((boolean)$thread['subscribed'], 'boolean'),
				'is_closed'         => new xmlrpcval((boolean)$thread['closed'], 'boolean'),
				
				'can_delete'        => new xmlrpcval(is_moderator($thread['fid'], "candeleteposts"), 'boolean'),
				'can_close'         => new xmlrpcval(is_moderator($thread['fid'], "canopenclosethreads"), 'boolean'),
				'can_approve'       => new xmlrpcval(is_moderator($thread['fid'], "canopenclosethreads"), 'boolean'),
				'can_stick'         => new xmlrpcval(is_moderator($thread['fid'], "canmanagethreads"), 'boolean'),
				'can_move'          => new xmlrpcval(is_moderator($thread['fid'], "canmovetononmodforum"), 'boolean'),
				'can_ban'           => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, 'boolean'),
				'can_rename'        => new xmlrpcval(false, 'boolean'), // based on first post title, separate rename not needed
				'is_ban'            => new xmlrpcval($thread['isbanned'], 'boolean'),
				'is_sticky'         => new xmlrpcval($thread['sticky'], 'boolean'),
				'is_approved'       => new xmlrpcval(!!$thread['visible'], 'boolean'),
				'is_deleted'        => new xmlrpcval(false, 'boolean'),
			), 'struct');

		}

		$customthreadtools = '';
	}
	


	$result = new xmlrpcval($topic_list, 'array');

	return new xmlrpcresp($result);
}
