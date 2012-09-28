<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_modcp.php";


function get_subscribed_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("usercp");
	
	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'start_num' => Tapatalk_Input::INT,
		'last_num'  => Tapatalk_Input::INT,
	), $xmlrpc_params);
		

	if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == 0)
	{
		return tt_no_permission();
	}
	
	$query = $db->simple_select("forumpermissions", "*", "gid='".$db->escape_string($mybb->user['usergroup'])."'");
	while($permissions = $db->fetch_array($query))
	{
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
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
	
	require_once MYBB_ROOT."inc/functions_forumlist.php";
	
	$fpermissions = forum_permissions();
		
	
	list($start, $limit) = process_page($input['start_num'], $input['last_num']);
	
	// Thread visiblity
	$visible = "AND t.visible != 0";
	if(is_moderator() == true)
	{
		$visible = '';
	}

	// Do Multi Pages
	$query = $db->query("
		SELECT COUNT(ts.tid) as threads
		FROM ".TABLE_PREFIX."threadsubscriptions ts
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = ts.tid)
		WHERE ts.uid = '".$mybb->user['uid']."' {$visible}
	");
	$threadcount = $db->fetch_field($query, "threads");


	// Fetch subscriptions
	$query = $db->query("
		SELECT s.*, t.*, t.username AS threadusername, u.username, u.username, u.avatar, if({$mybb->user['uid']} > 0 and s.uid = {$mybb->user['uid']}, 1, 0) as subscribed, po.message, f.name as forumname, IF(b.lifted > UNIX_TIMESTAMP() OR b.lifted = 0, 1, 0) as isbanned
		FROM ".TABLE_PREFIX."threadsubscriptions s
		LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
		LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = t.uid)
		LEFT JOIN ".TABLE_PREFIX."posts po ON (po.pid = t.firstpost)
		left join ".TABLE_PREFIX."forums f on f.fid = t.fid
		WHERE s.uid='".$mybb->user['uid']."' {$visible}
		ORDER BY t.lastpost DESC
		LIMIT $start, $limit
	");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];

		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			// Hmm, you don't have permission to view this thread - unsubscribe!
			$del_subscriptions[] = $subscription['tid'];
		}
		else if($subscription['tid'])
		{
			$subscriptions[$subscription['tid']] = $subscription;
		}
	}

	if(is_array($del_subscriptions))
	{
		$tids = implode(',', $del_subscriptions);
		if($tids)
		{
			$db->delete_query("threadsubscriptions", "tid IN ({$tids}) AND uid='{$mybb->user['uid']}'");
		}
	}

	$topic_list = array();
	
	if(is_array($subscriptions))
	{
		$tids = implode(",", array_keys($subscriptions));
	
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
			if($mybb->user['uid'] == 0)
			{
				if($forumsread[$forum['fid']])
				{
					$forum['lastread'] = $forumsread[$forum['fid']];
				}
			}
			$readforums[$forum['fid']] = $forum['lastread'];
		}

		// Read threads
		if($mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($readthread = $db->fetch_array($query))
			{
				$subscriptions[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}
		
		// Now we can build our subscription list
		foreach($subscriptions as $thread)
		{
			$bgcolor = alt_trow();

			$folder = '';
			$prefix = '';
			
			// If this thread has a prefix, insert a space between prefix and subject
			if($thread['prefix'] != 0)
			{
				$thread['threadprefix'] .= '&nbsp;';
			}
			
			// Sanitize
			$thread['subject'] = $parser->parse_badwords($thread['subject']);


			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;
			$unreadpost = 0;

			if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
			{
				$forum_read = $readforums[$thread['fid']];
			
				$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				if($forum_read == 0 || $forum_read < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
			}
			else
			{
				$forum_read = $forumsread[$thread['fid']];
			}

			if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
			{
				$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
			}

			if($thread['lastpost'] > $cutoff)
			{
				if($thread['lastpost'] > $cutoff)
				{
					if($thread['lastread'])
					{
						$lastread = $thread['lastread'];
					}
					else
					{
						$lastread = 1;
					}
				}
			}

			if(!$lastread)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
				if($readcookie > $forum_read)
				{
					$lastread = $readcookie;
				}
				else
				{
					$lastread = $forum_read;
				}
			}

			if($thread['lastpost'] > $lastread && $lastread)
			{
				$unreadpost = 1;
			}

			
			$topic_list[] = new xmlrpcval(array(
				'forum_id'          => new xmlrpcval($thread['fid'], 'string'),
				'forum_name'        => new xmlrpcval(basic_clean($thread['forumname']), 'base64'),
				'topic_id'          => new xmlrpcval($thread['tid'], 'string'),
				'topic_title'       => new xmlrpcval($thread['subject'], 'base64'),
				'topic_author_id'   => new xmlrpcval($thread['uid'], 'string'),
				'post_author_name'  => new xmlrpcval($thread['username'], 'base64'),
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
	}
		
	$result = new xmlrpcval(array(
		'total_topic_num' => new xmlrpcval($threadcount, 'int'),
		'topics'          => new xmlrpcval($topic_list, 'array')
	), 'struct');

	return new xmlrpcresp($result);
}

