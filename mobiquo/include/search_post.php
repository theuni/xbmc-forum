<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function search_post_func($xmlrpc_params)
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

	$limitsql = "";
	if(intval($mybb->settings['searchhardlimit']) > 0)
	{
		$limitsql = "ORDER BY t.dateline DESC LIMIT ".intval($mybb->settings['searchhardlimit']);
	}

	$forumcache = $cache->read("forums");
	
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
		
		$resulttype = "posts";
		
		$search_data = array(
			"keywords" => trim($input['search_string'])
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
			"keywords" => trim($input['search_string_esc']),
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
	
	if(!$search['posts'])
	{
		return xmlrespfalse($lang->error_nosearchresults);
	}
	
	$postcount = 0;
	
	// Moderators can view unapproved threads
	$query = $db->simple_select("moderators", "fid", "(id='{$mybb->user['uid']}' AND isgroup='0') OR (id='{$mybb->user['usergroup']}' AND isgroup='1')");
	if($mybb->usergroup['issupermod'] == 1)
	{
		// Super moderators (and admins)
		$p_unapproved_where = "visible >= 0";
		$t_unapproved_where = "visible < 0";
	}
	elseif($db->num_rows($query))
	{
		// Normal moderators
		$moderated_forums = '0';
		while($forum = $db->fetch_array($query))
		{
			$moderated_forums .= ','.$forum['fid'];
			$test_moderated_forums[$forum['fid']] = $forum['fid'];
		}
		$p_unapproved_where = "visible >= 0";
		$t_unapproved_where = "visible < 0 AND fid NOT IN ({$moderated_forums})";
	}
	else
	{
		// Normal users
		$p_unapproved_where = 'visible=1';
		$t_unapproved_where = 'visible < 1';
	}    
	
	$post_cache_options = array();
	if(intval($mybb->settings['searchhardlimit']) > 0)
	{
		$post_cache_options['limit'] = intval($mybb->settings['searchhardlimit']);
	}
	
	if(strpos($sortfield, 'p.') !== false)
	{
		$post_cache_options['order_by'] = str_replace('p.', '', $sortfield);
		$post_cache_options['order_dir'] = $order;
	}

	$tids = array();
	$pids = array();
	// Make sure the posts we're viewing we have permission to view.
	$query = $db->simple_select("posts", "pid, tid", "pid IN(".$db->escape_string($search['posts']).") AND {$p_unapproved_where}", $post_cache_options);
	while($post = $db->fetch_array($query))
	{
		$pids[$post['pid']] = $post['tid'];
		$tids[$post['tid']][$post['pid']] = $post['pid'];
	}
	
	if(!empty($pids))
	{
		$temp_pids = array();

		// Check the thread records as well. If we don't have permissions, remove them from the listing.
		$query = $db->simple_select("threads", "tid", "tid IN(".$db->escape_string(implode(',', $pids)).") AND ({$t_unapproved_where} OR closed LIKE 'moved|%')");
		while($thread = $db->fetch_array($query))
		{
			if(array_key_exists($thread['tid'], $tids) != false)
			{
				$temp_pids = $tids[$thread['tid']];
				foreach($temp_pids as $pid)
				{
					unset($pids[$pid]);
					unset($tids[$thread['tid']]);
				}
			}
		}
		unset($temp_pids);
	}

	// Declare our post count
	$postcount = count($pids);
	
	if(!$postcount)
	{
		return xmlrespfalse($lang->error_nosearchresults);
	}
	
	// And now we have our sanatized post list
	$search['posts'] = implode(',', array_keys($pids));
	
	$tids = implode(",", array_keys($tids));
	
	// Read threads
	if($mybb->user['uid'] && $mybb->settings['threadreadcut'] > 0)
	{
		$query = $db->simple_select("threadsread", "tid, dateline", "uid='".$mybb->user['uid']."' AND tid IN(".$db->escape_string($tids).")");
		while($readthread = $db->fetch_array($query))
		{
			$readthreads[$readthread['tid']] = $readthread['dateline'];
		}
	}

	$dot_icon = array();
	if($mybb->settings['dotfolders'] != 0 && $mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("posts", "DISTINCT tid,uid", "uid='".$mybb->user['uid']."' AND tid IN(".$db->escape_string($tids).")");
		while($post = $db->fetch_array($query))
		{
			$dot_icon[$post['tid']] = true;
		}
	}

	$query = $db->query("
		SELECT p.*, u.username AS userusername, t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views, t.lastpost AS thread_lastpost, t.closed AS thread_closed, t.uid as thread_uid, if({$mybb->user['uid']} > 1 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, u.avatar, IF(b.lifted > UNIX_TIMESTAMP(), 1, 0) as isbanned
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."threadsubscriptions s ON (s.tid = t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = p.uid)
		WHERE p.pid IN (".$db->escape_string($search['posts']).")
		ORDER BY $sortfield $order
		LIMIT $start, $limit
	");
	
	$post_list = array();
	
	
	while($post = $db->fetch_array($query))
	{
	
	
		if($post['userusername'])
		{
			$post['username'] = $post['userusername'];
		}
		$post['subject'] = $parser->parse_badwords($post['subject']);
		$post['thread_subject'] = $parser->parse_badwords($post['thread_subject']);
			
			
		$isnew = 0;
		$donenew = 0;
		$last_read = 0;
		$post['thread_lastread'] = $readthreads[$post['tid']];
		if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'] && $post['thread_lastpost'] > $forumread)
		{
			$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
			if($post['thread_lastpost'] > $cutoff)
			{
				if($post['thread_lastread'])
				{
					$last_read = $post['thread_lastread'];
				}
				else
				{
					$last_read = 1;
				}
			}
		}

		if(!$last_read)
		{
			$readcookie = $threadread = my_get_array_cookie("threadread", $post['tid']);
			if($readcookie > $forumread)
			{
				$last_read = $readcookie;
			}
			elseif($forumread > $mybb->user['lastvisit'])
			{
				$last_read = $forumread;
			}
			else
			{
				$last_read = $mybb->user['lastvisit'];
			}
		}

		if($post['thread_lastpost'] > $last_read && $last_read)
		{
			$unreadpost = 1;
		}
			
		$can_delete = 0;
		if($mybb->user['uid'] == $post['uid'])
		{
			if($forumpermissions['candeletethreads'] == 1 && $postcounter == 1)
			{
				$can_delete = 1;
			}
			else if($forumpermissions['candeleteposts'] == 1 && $postcounter != 1)
			{
				$can_delete = 1;
			}
		}
		$can_delete = (is_moderator($fid, "candeleteposts") || $can_delete == 1) && $mybb->user['uid'] != 0;
		
		
		$post_list[] = new xmlrpcval(array(
			'forum_id'                      => new xmlrpcval($post['fid'], 'string'),
			'forum_name'                    => new xmlrpcval(basic_clean($forumcache[$post['fid']]['name']), 'base64'),
			'topic_id'                      => new xmlrpcval($post['tid'], 'string'),
			'topic_title'                   => new xmlrpcval($post['thread_subject'], 'base64'),
			'post_id'                       => new xmlrpcval($post['pid'], 'string'),
			'post_title'                    => new xmlrpcval($post['subject'], 'base64'),
			'post_author_name'              => new xmlrpcval($post['username'], 'base64'),
			'is_subscribed'                 => new xmlrpcval((boolean)$post['subscribed'], 'boolean'),
			'can_subscribe'                 => new xmlrpcval(true, 'boolean'), // implied by view permissions
			'is_closed'                     => new xmlrpcval((boolean)$post['thread_closed'], 'boolean'),
			'icon_url'                      => new xmlrpcval(absolute_url($post['avatar']), 'string'),
			'post_time'                     => new xmlrpcval(mobiquo_iso8601_encode($post['dateline']), 'dateTime.iso8601'),
			'reply_number'                  => new xmlrpcval($post['thread_replies'], 'int'),
			'new_post'                      => new xmlrpcval((boolean)$unreadpost, 'boolean'),
			'view_number'                   => new xmlrpcval($post['thread_views'], 'int'),
			'short_content'                 => new xmlrpcval(process_short_content($post['message'], $parser), 'base64'),
			
			'can_delete'                    => new xmlrpcval($can_delete, 'boolean'),
			'can_approve'                   => new xmlrpcval(is_moderator($post['fid'], "canmanagethreads"), 'boolean'),
			'can_move'                      => new xmlrpcval(is_moderator($post['fid'], "canmovetononmodforum"), 'boolean'),
			'can_ban'                       => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, 'boolean'),
			'is_ban'                        => new xmlrpcval($post['isbanned'], 'boolean'),
			
			'is_approved'                   => new xmlrpcval(!!$post['visible'], 'boolean'),
			'is_deleted'                    => new xmlrpcval(false, 'boolean'),
		), 'struct');
	
	}
	
	$result = new xmlrpcval(array(
		'total_topic_num' => new xmlrpcval($postcount, 'int'),
		'search_id'       => new xmlrpcval($sid, 'string'),
		'posts'          => new xmlrpcval($post_list, 'array')
	), 'struct');

	return new xmlrpcresp($result);
}