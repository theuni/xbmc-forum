<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function get_unread_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	if($mybb->user['uid'] == 0)
	{
		$result = new xmlrpcval(array(
			'result'          => new xmlrpcval(true, 'boolean'),
			'total_topic_num' => new xmlrpcval(0, 'int'),
			'topics'          => new xmlrpcval(array(), 'array'),
		), 'struct');
	
		return new xmlrpcresp($result);
	}
	
	$lang->load("search");

	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'start_num' => Tapatalk_Input::INT,
		'last_num'  => Tapatalk_Input::INT,
		'filters'   => Tapatalk_Input::RAW,
	), $xmlrpc_params);
		
	list($start, $limit) = process_page($input['start_num'], $input['last_num']);

	$threads = array();

	// Build a forum cache.
	$query = $db->query("
		SELECT f.fid, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forums f
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");

	while($forum = $db->fetch_array($query))
	{
		$readforums[$forum['fid']] = $forum['lastread'];
	}
	$fpermissions = forum_permissions();
	
	$where_sql = is_moderator() ? '(t.visible=1 OR t.visible=0) ' : 't.visible=1 ';
	
	$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
	switch($db->type)
	{
		case "pgsql":
			$where_sql .= " AND t.closed NOT LIKE 'moved|%' AND t.lastpost > COALESCE(tr.dateline,$cutoff) AND t.lastpost > COALESCE(fr.dateline,$cutoff) AND t.lastpost>$cutoff";
			break;
		default:
			$where_sql .= " AND t.closed NOT LIKE 'moved|%' AND t.lastpost > IFNULL(tr.dateline,$cutoff) AND t.lastpost > IFNULL(fr.dateline,$cutoff) AND t.lastpost>$cutoff";
	}

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
	
	if(!empty($input['filters'])){
		if(!empty($input['filters']['only_in'])){
			$input['filters']['only_in'] = array_map("intval", $input['filters']['only_in']);
			$where_sql .= " AND t.fid IN(".implode(',', $input['filters']['only_in']).")";		
		}
		if(!empty($input['filters']['not_in'])){
			$input['filters']['not_in'] = array_map("intval", $input['filters']['not_in']);
			$where_sql .= " AND t.fid NOT IN(".implode(',', $input['filters']['not_in']).")";
		}
	}
			
	$query = $db->query("
		SELECT COUNT(t.tid) AS unread_count
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."threadsread tr ON (tr.tid=t.tid AND tr.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE $where_sql
	");
	
	$resultcount = $db->fetch_field($query, "unread_count");
	
	if($resultcount > 0){
		// Start Getting Threads
	
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
	
		$query = $db->query("
			SELECT t.*, {$ratingadd}{$select_rating_user}t.username AS threadusername, u.username, u.avatar, if({$mybb->user['uid']} > 1 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, po.message, f.name as forumname, IF(b.lifted > UNIX_TIMESTAMP(), 1, 0) as isbanned $icon_urls_sql
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid){$select_voting}
			LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = t.uid)
			LEFT JOIN ".TABLE_PREFIX."threadsubscriptions s ON (s.tid = t.tid)
			LEFT JOIN ".TABLE_PREFIX."posts po ON (po.pid = t.firstpost)
			LEFT JOIN ".TABLE_PREFIX."threadsread tr on t.tid = tr.tid and tr.uid = '{$mybb->user['uid']}'
			LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
			LEFT JOIN ".TABLE_PREFIX."forums f on f.fid = t.fid
			WHERE $where_sql
			GROUP BY t.tid
			ORDER BY t.lastpost desc
			LIMIT $start, $limit
		");
		while($thread = $db->fetch_array($query))
		{
			$threadcache[$thread['tid']] = $thread;
			$tids[$thread['tid']] = $thread['tid'];
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
			
			$new_topic = array(
				'forum_id'          => new xmlrpcval($thread['fid'], 'string'),
				'forum_name'        => new xmlrpcval(basic_clean($thread['forumname']), 'base64'),
				'topic_id'          => new xmlrpcval($thread['tid'], 'string'),
				'topic_title'       => new xmlrpcval($thread['subject'], 'base64'),
				'topic_author_id'   => new xmlrpcval($thread['uid'], 'string'),
				'post_author_name'  => new xmlrpcval($thread['username'], 'base64'),
		'post_author_display_name'  => new xmlrpcval($thread['username'], 'base64'),
				'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
				'is_subscribed'     => new xmlrpcval((boolean)$thread['subscribed'], 'boolean'),
				'is_closed'         => new xmlrpcval((boolean)$thread['closed'], 'boolean'),
				'short_content'     => new xmlrpcval(process_short_content($thread['message'], $parser), 'base64'),
				'icon_url'          => new xmlrpcval(absolute_url($thread['avatar']), 'string'),
				'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['lastpost']), 'dateTime.iso8601'),
				'reply_number'      => new xmlrpcval($thread['replies'], 'int'),
				'view_number'       => new xmlrpcval($thread['views'], 'int'),
				'new_post'          => new xmlrpcval($unreadpost, 'boolean'),
				
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
			);

			if($_SERVER['HTTP_MOBIQUO_ID'] == 10)
			{
				$icon_urls_list = array();
				$icon_urls = explode('@@%#%@@', $thread['icon_urls']);
				foreach($icon_urls as $icon_url){
					$icon_urls_list []= new xmlrpcval(absolute_url($icon_url), "string");
				}
				
				$new_topic['icon_urls'] = new xmlrpcval($icon_urls_list, 'array');
			}
			
			$topic_list[] = new xmlrpcval($new_topic, 'struct');
		}

		$customthreadtools = '';
	}
	

	$result = new xmlrpcval(array(
		'result'          => new xmlrpcval(true, 'boolean'),
		'result_text'     => new xmlrpcval('', 'base64'),
		'total_topic_num' => new xmlrpcval($resultcount, 'int'),
		'topics'          => new xmlrpcval($topic_list, 'array'),
	), 'struct');

	return new xmlrpcresp($result);
}
