<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function search_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("search");

	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'search_string' => Tapatalk_Input::STRING,
		'start_num' => Tapatalk_Input::INT,
		'last_num'  => Tapatalk_Input::INT,
		'search_id'   => Tapatalk_Input::STRING,
	), $xmlrpc_params);
		

	if($mybb->usergroup['cansearch'] == 0)
	{
		return tt_no_permission();
	}

	$now = TIME_NOW;
	$mybb->input['keywords'] = trim($mybb->input['keywords']);

	$limitsql = "";
	if(intval($mybb->settings['searchhardlimit']) > 0)
	{
		$limitsql = "ORDER BY t.dateline DESC LIMIT ".intval($mybb->settings['searchhardlimit']);
	}

	if(!empty($input['search_id'])){
		
		$query = $db->simple_select("searchlog", "*", "sid='{$input['search_id_esc']}'");
		$search = $db->fetch_array($query);

		if(!$search['sid'])
		{
			$input['search_id'] = null;
		}			
	}
	
	if(empty($input['search_id'])){
		
		// Check if search flood checking is enabled and user is not admin
		if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
		{
			// Fetch the time this user last searched
			if($mybb->user['uid'])
			{
				$conditions = "uid='{$mybb->user['uid']}'";
			}
			else
			{
				$conditions = "uid='0' AND ipaddress='".$db->escape_string($session->ipaddress)."'";
			}
			$timecut = TIME_NOW-$mybb->settings['searchfloodtime'];
			$query = $db->simple_select("searchlog", "*", "$conditions AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
			$last_search = $db->fetch_array($query);
			// Users last search was within the flood time, show the error
			if($last_search['sid'])
			{
				$remaining_time = $mybb->settings['searchfloodtime']-(TIME_NOW-$last_search['dateline']);
				if($remaining_time == 1)
				{
					$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
				}
				else
				{
					$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
				}
				return xmlrespfalse($lang->error_searchflooding);
			}
		}
		
		$resulttype = "threads";
		
		$search_data = array(
			"keywords" => $input['search_string']
		);
		
		if($db->can_search == true)
		{
			if($mybb->settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->is_fulltext("posts"))
			{
				$search_results = perform_search_mysql_ft($search_data);
			}
			else
			{
				$search_results = perform_search_mysql($search_data);
			}
		}
		else
		{
			return xmlrespfalse($lang->error_no_search_support);
		}
		$sid = md5(uniqid(microtime(), 1));
		$searcharray = array(
			"sid" => $db->escape_string($sid),
			"uid" => $mybb->user['uid'],
			"dateline" => $now,
			"ipaddress" => $db->escape_string($session->ipaddress),
			"threads" => $search_results['threads'],
			"posts" => $search_results['posts'],
			"resulttype" => $resulttype,
			"querycache" => $search_results['querycache'],
			"keywords" => $input['search_string_esc'],
		);

		$db->insert_query("searchlog", $searcharray);

		$sortorder = "desc";
	
	}
	
	$query = $db->simple_select("searchlog", "*", "sid='{$sid}'");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		return xmlrespfalse("Internal search error");
	}            
	
	list($start, $limit) = process_page($input['start_num'], $input['last_num']);
	
	if($search['resulttype'] == "threads")
	{
		$sortfield = "t.lastpost";
		$sortby = "lastpost";
	}
	else
	{
		$sortfield = "p.dateline";
		$sortby = "dateline";
	}	
	$order = "desc";
	
	
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
		$readforums[$forum['fid']] = $forum['lastread'];
	}
	$fpermissions = forum_permissions();
	
	$threadcount = 0;
		
	
	// Moderators can view unapproved threads
	$query = $db->simple_select("moderators", "fid", "(id='{$mybb->user['uid']}' AND isgroup='0') OR (id='{$mybb->user['usergroup']}' AND isgroup='1')");
	if($mybb->usergroup['issupermod'] == 1)
	{
		// Super moderators (and admins)
		$unapproved_where = "t.visible>-1";
	}
	elseif($db->num_rows($query))
	{
		// Normal moderators
		$moderated_forums = '0';
		while($forum = $db->fetch_array($query))
		{
			$moderated_forums .= ','.$forum['fid'];
		}
		$unapproved_where = "(t.visible>0 OR (t.visible=0 AND t.fid IN ({$moderated_forums})))";
	}
	else
	{
		// Normal users
		$unapproved_where = 't.visible>0';
	}
	
	// If we have saved WHERE conditions, execute them
	if($search['querycache'] != "")
	{
		$where_conditions = $search['querycache'];
		$query = $db->simple_select("threads t", "t.tid", $where_conditions. " AND {$unapproved_where} AND t.closed NOT LIKE 'moved|%' {$limitsql}");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = $thread['tid'];
			$threadcount++;
		}
		// Build our list of threads.
		if($threadcount > 0)
		{
			$search['threads'] = implode(",", $threads);
		}
		// No results.
		else
		{
			return xmlrespfalse($lang->error_nosearchresults);
		}
		$where_conditions = "t.tid IN (".$search['threads'].")";
	}
	// This search doesn't use a query cache, results stored in search table.
	else
	{
		$where_conditions = "t.tid IN (".$search['threads'].")";
		$query = $db->simple_select("threads t", "COUNT(t.tid) AS resultcount", $where_conditions. " AND {$unapproved_where} AND t.closed NOT LIKE 'moved|%' {$limitsql}");
		$count = $db->fetch_array($query);

		if(!$count['resultcount'])
		{
			return xmlrespfalse($lang->error_nosearchresults);
		}
		$threadcount = $count['resultcount'];
	}
		
	$sqlarray = array(
		'order_by' => $sortfield,
		'order_dir' => $order,
		'limit_start' => $start,
		'limit' => $limit
	);
	$query = $db->query("
		SELECT t.*, u.username AS userusername, u.username, u.avatar, if({$mybb->user['uid']} > 1 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, po.message, f.name as forumname, IF(b.lifted > UNIX_TIMESTAMP(), 1, 0) as isbanned
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = t.uid)		
		LEFT JOIN ".TABLE_PREFIX."threadsubscriptions s ON (s.tid = t.tid)
		LEFT JOIN ".TABLE_PREFIX."posts po ON (po.pid = t.firstpost)
		left join ".TABLE_PREFIX."forums f on f.fid = t.fid
		
		WHERE $where_conditions AND {$unapproved_where} AND t.closed NOT LIKE 'moved|%'
		ORDER BY $sortfield $order
		LIMIT $start, $limit
	");
	$thread_cache = array();
	while($thread = $db->fetch_array($query))
	{
		$thread_cache[$thread['tid']] = $thread;
	}
	$thread_ids = implode(",", array_keys($thread_cache));
	
	if(empty($thread_ids))
	{
		return xmlrespfalse($lang->error_nosearchresults);
	}

	// Fetch dot icons if enabled
	if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] && $thread_cache)
	{
		$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='".$mybb->user['uid']."' AND tid IN(".$thread_ids.")");
		while($thread = $db->fetch_array($query))
		{
			$thread_cache[$thread['tid']]['dot_icon'] = 1;
		}
	}

	// Fetch the read threads.
	if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
	{
		$query = $db->simple_select("threadsread", "tid,dateline", "uid='".$mybb->user['uid']."' AND tid IN(".$thread_ids.")");
		while($readthread = $db->fetch_array($query))
		{
			$thread_cache[$readthread['tid']]['lastread'] = $readthread['dateline'];
		}
	}
	
	$topic_list = array();

	foreach($thread_cache as $thread)
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
		), 'struct');
		
	}
		
			
	$result = new xmlrpcval(array(
		'total_topic_num' => new xmlrpcval($threadcount, 'int'),
		'search_id'       => new xmlrpcval($sid, 'string'),
		'topics'          => new xmlrpcval($topic_list, 'array')
	), 'struct');

	return new xmlrpcresp($result);
}
