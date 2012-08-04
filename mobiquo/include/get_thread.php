<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_indicators.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/functions_modcp.php";

function get_thread_func($xmlrpc_params)
{	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'topic_id'    => Tapatalk_Input::INT,
		'start_num'   => Tapatalk_Input::INT,
		'last_num'    => Tapatalk_Input::INT,
		'return_html' => Tapatalk_Input::INT
	), $xmlrpc_params);
	
	$lang->load("showthread");

	$parser = new Tapatalk_Parser;

	// Get the thread details from the database.
	$thread = get_thread($input['topic_id']);
/*
	// Get thread prefix if there is one.
	$thread['threadprefix'] = '';
	$thread['displayprefix'] = '';
	if($thread['prefix'] != 0)
	{
		$query = $db->simple_select('threadprefixes', 'prefix, displaystyle', "pid='{$thread['prefix']}'");
		$threadprefix = $db->fetch_array($query);
		
		$thread['threadprefix'] = $threadprefix['prefix'].'&nbsp;';
		$thread['displayprefix'] = $threadprefix['displaystyle'].'&nbsp;';
	}*/

	if(substr($thread['closed'], 0, 6) == "moved|")
	{
		$thread['tid'] = 0;
	}

	$thread['subject'] = $parser->parse_badwords($thread['subject']);
	$tid = $thread['tid'];
	$fid = $thread['fid'];

	if(!$thread['username'])
	{
		$thread['username'] = $lang->guest;
	}

	$visibleonly = "AND visible='1'";

	// Is the currently logged in user a moderator of this forum?
	if(is_moderator($fid))
	{
		$visibleonly = " AND (visible='1' OR visible='0')";
		$ismod = true;
	}
	else
	{
		$ismod = false;
	}

	$forumpermissions = forum_permissions($thread['fid']);

	// Does the user have permission to view this thread?
	if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
	{
		tt_no_permission();
	}

	if($forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
	{
		tt_no_permission();
	}

	// Make sure we are looking at a real thread here.
	if(!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		return xmlrespfalse($lang->error_invalidthread);
	}
	
	
	// Does the thread belong to a valid forum?
	$forum = get_forum($fid);
	if(!$forum || $forum['type'] != "f")
	{
		return xmlrespfalse($lang->error_invalidforum);
	}

	check_forum_password($forum['fid']);
	

	if($thread['firstpost'] == 0)
	{
		update_first_post($tid);
	}
	
	// Mark this thread as read
	mark_thread_read($tid, $fid);
	
	// Increment the thread view.
	if($mybb->settings['delayedthreadviews'] == 1)
	{
		$db->shutdown_query("INSERT INTO ".TABLE_PREFIX."threadviews (tid) VALUES('{$tid}')");
	}
	else
	{
		$db->shutdown_query("UPDATE ".TABLE_PREFIX."threads SET views=views+1 WHERE tid='{$tid}'");
	}
	++$thread['views'];

	
	// Work out if we are showing unapproved posts as well (if the user is a moderator etc.)
	if($ismod)
	{
		$visible = "AND (p.visible='0' OR p.visible='1')";
	}
	else
	{
		$visible = "AND p.visible='1'";
	}
	
	
	// Fetch the ignore list for the current user if they have one
	$ignored_users = array();
	if($mybb->user['uid'] > 0 && $mybb->user['ignorelist'] != "")
	{
		$ignore_list = explode(',', $mybb->user['ignorelist']);
		foreach($ignore_list as $uid)
		{
			$ignored_users[$uid] = 1;
		}
	}
	
	list($start, $limit) = process_page($input['start_num'], $input['last_num']);
	
	// Recount replies if user is a moderator to take into account unapproved posts.
	if($ismod)
	{
		$query = $db->simple_select("posts p", "COUNT(*) AS replies", "p.tid='$tid' $visible");
		$thread['replies'] = $db->fetch_field($query, 'replies')-1;
	}
	$postcount = intval($thread['replies'])+1;
	
	
	$pids = "";
	$comma = '';
	$query = $db->simple_select("posts p", "p.pid", "p.tid='$tid' $visible", array('order_by' => 'p.dateline', 'limit_start' => $start, 'limit' => $limit));
	while($getid = $db->fetch_array($query))
	{
		// Set the ID of the first post on page to $pid if it doesn't hold any value
		// to allow this value to be used for Thread Mode/Linear Mode links
		// and ensure the user lands on the correct page after changing view mode
		if(!$pid)
		{
			$pid = $getid['pid'];
		}
		// Gather a comma separated list of post IDs
		$pids .= "$comma'{$getid['pid']}'";
		$comma = ",";
	}
	if($pids)
	{
		$pids = "pid IN($pids)";
		
		global $attachcache;
		$attachcache = array();
		if($thread['attachmentcount'] > 0)
		{
			// Now lets fetch all of the attachments for these posts.
			$query = $db->simple_select("attachments", "*", $pids);
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}
		}
	}
	else
	{
		// If there are no pid's the thread is probably awaiting approval.
		return xmlrespfalse($lang->error_invalidthread);
	}
		
	$post_list = array();
		
	// Get the actual posts from the database here.
	$posts = '';
	$query = $db->query("
		SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername, IF(b.lifted > UNIX_TIMESTAMP(), 1, 0) as isbanned
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
		LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid = p.uid)
		WHERE $pids
		ORDER BY p.dateline
	");
	
	while($post = $db->fetch_array($query))
	{		
		if($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0)
		{
			$post['visible'] = 0;
		}
		//$posts .= build_postbit($post);
		$parser_options = array();
		$parser_options['allow_html'] = false;
		$parser_options['allow_mycode'] = true;
		$parser_options['allow_smilies'] = false;
		$parser_options['allow_imgcode'] = true;
		$parser_options['allow_videocode'] = true;
		$parser_options['nl2br'] = (boolean)$input['return_html'];
		$parser_options['filter_badwords'] = 1;
		
		if(!$post['username'])
		{
			$post['username'] = $lang->guest;
		}
		
		if($post['userusername'])
		{
			$parser_options['me_username'] = $post['userusername'];
		}
		else
		{
			$parser_options['me_username'] = $post['username'];
		}	
		
		$post['subject'] = $parser->parse_badwords($post['subject']);
		$post['author'] = $post['uid'];
		
		if($post['userusername'])
		{ // This post was made by a registered user

			$post['username'] = $post['userusername'];
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
		
		$post['message'] = $parser->parse_message($post['message'], $parser_options);
		
		$attachment_list = process_post_attachments($post['pid'], $post);
	
		if(is_array($ignored_users) && $post['uid'] != 0 && $ignored_users[$post['uid']] == 1){
			$post['message'] = $lang->sprintf($lang->postbit_currently_ignoring_user, $post['username']);
		}
		
		$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
		$query2 = $db->simple_select("sessions", "location,nopermission", "uid='{$post['uid']}' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
		$session = $db->fetch_array($query2);
		
		$post_list[] = new xmlrpcval(array(
			'post_id'          => new xmlrpcval($post['pid'], 'string'),
			'post_title'       => new xmlrpcval($post['subject'], 'base64'), 
			'post_content'     => new xmlrpcval(process_post($post['message'], $input['return_html']), 'base64'),
			'post_author_name' => new xmlrpcval($post['username'], 'base64'),
	'post_author_display_name' => new   xmlrpcval($post['username'], 'base64'),
			'is_online'        => new xmlrpcval(($post['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $memprofile['uid'] == $mybb->user['uid']) && !empty($session), 'boolean'),
			'can_edit'         => new xmlrpcval(is_moderator($fid, "caneditposts") || $thread['closed'] == 0 && $forumpermissions['caneditposts'] == 1 && $mybb->user['uid'] == $post['uid'], 'boolean'),
			'icon_url'         => new xmlrpcval(absolute_url($post['avatar']), 'string'),
			'post_time'        => new xmlrpcval(mobiquo_iso8601_encode($post['dateline']), 'dateTime.iso8601'),
			'attachments'      => new xmlrpcval($attachment_list, 'array'),
			'can_upload'       => new xmlrpcval($forumpermissions['canpostattachments'] != 0, 'boolean'),
			'allow_smilies'    => new xmlrpcval(true, 'boolean'), // always true
			
			'can_delete'        => new xmlrpcval($can_delete, 'boolean'),
			'can_approve'       => new xmlrpcval(is_moderator($post['fid'], "canmanagethreads"), 'boolean'),
			'can_move'          => new xmlrpcval(is_moderator($post['fid'], "canmovetononmodforum"), 'boolean'),
			'can_ban'           => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, 'boolean'),
			'is_ban'            => new xmlrpcval($post['isbanned'], 'boolean'),
			'is_approved'       => new xmlrpcval(!!$post['visible'], 'boolean'),
			'is_deleted'        => new xmlrpcval(false, 'boolean'),
		), 'struct');
	}

	$query = $db->simple_select("threadsubscriptions", "tid", "tid='".intval($tid)."' AND uid='".intval($mybb->user['uid'])."'", array('limit' => 1));
	$subscribed = (boolean)$db->fetch_field($query, 'tid');
	
	
	$query = $db->simple_select("banned", "uid", "uid='{$thread['uid']}'");
	$isbanned = !!$db->fetch_field($query, "uid");
	
	$result = new xmlrpcval(array(
		'total_post_num'  => new xmlrpcval($postcount, 'int'),
		'forum_id'        => new xmlrpcval($thread['fid'], 'string'),
		'forum_name'      => new xmlrpcval(basic_clean($forum['name']), 'base64'),
		'topic_id'        => new xmlrpcval($thread['tid'], 'string'),
		'topic_title'     => new xmlrpcval($thread['subject'], 'base64'),
		'can_subscribe'   => new xmlrpcval(true, 'boolean'),
		'is_subscribed'   => new xmlrpcval($subscribed, 'boolean'),
		'is_closed'       => new xmlrpcval($thread['closed'] == 1, 'boolean'),
		'can_reply'       => new xmlrpcval($forumpermissions['canpostreplys'] != 0 && $mybb->user['suspendposting'] != 1 && ($thread['closed'] != 1 || is_moderator($fid)) && $forum['open'] != 0, 'boolean'),
		
		'can_delete'        => new xmlrpcval(is_moderator($thread['fid'], "candeleteposts"), 'boolean'),
		'can_close'         => new xmlrpcval(is_moderator($thread['fid'], "canopenclosethreads"), 'boolean'),
		'can_approve'       => new xmlrpcval(is_moderator($thread['fid'], "canopenclosethreads"), 'boolean'),
		'can_stick'         => new xmlrpcval(is_moderator($thread['fid'], "canmanagethreads"), 'boolean'),
		'can_move'          => new xmlrpcval(is_moderator($thread['fid'], "canmovetononmodforum"), 'boolean'),
		'can_rename'        => new xmlrpcval(false, 'boolean'), // based on first post title, separate rename not needed
		'can_ban'           => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, 'boolean'),
		'is_ban'            => new xmlrpcval($isbanned, 'boolean'),
		'is_approved'       => new xmlrpcval(!!$thread['visible'], 'boolean'),
		'is_deleted'        => new xmlrpcval(false, 'boolean'),
		
		'posts'           => new xmlrpcval($post_list, 'array'),
	), 'struct');

	return new xmlrpcresp($result);
}
