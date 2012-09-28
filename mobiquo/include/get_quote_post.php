<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";

function get_quote_post_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$input = Tapatalk_Input::filterXmlInput(array(
			'post_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);       
	
	$lang->load("newreply");
	$parser = new postParser;
	
	$pid = $input['post_id'];
	
	$query = $db->simple_select("posts", "tid", "pid='".$pid."'");
	if($db->num_rows($query) == 0)
	{
		return xmlrespfalse("Invalid post");
	}
	$post = $db->fetch_array($query);
	
	$tid = $post['tid'];
		
	$options = array(
		"limit" => 1
	);
	$query = $db->simple_select("threads", "*", "tid='".$tid."'");
	if($db->num_rows($query) == 0)
	{
		return xmlrespfalse($lang->error_invalidthread);
	}

	$thread = $db->fetch_array($query);
	$fid = $thread['fid'];

	// Get forum info
	$forum = get_forum($fid);
	if(!$forum)
	{
		return xmlrespfalse($lang->error_invalidforum);
	}
		
	$forumpermissions = forum_permissions($fid);
		
	if(($thread['visible'] == 0 && !is_moderator($fid)) || $thread['visible'] < 0)
	{
		return xmlrespfalse($lang->error_invalidthread);
	}
	if($forum['open'] == 0 || $forum['type'] != "f")
	{
		return xmlrespfalse($lang->error_closedinvalidforum);
	}
	if($mybb->user['uid'] < 1 || $forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0 || $mybb->user['suspendposting'] == 1)
	{
		return tt_no_permission();
	}

	if($forumpermissions['canonlyviewthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
	{
		return tt_no_permission();
	}
	
	tt_check_forum_password($forum['fid']);
	
	
	// Check to see if the thread is closed, and if the user is a mod.
	if(!is_moderator($fid, "caneditposts"))
	{
		if($thread['closed'] == 1)
		{
			return xmlrespfalse($lang->redirect_threadclosed);
		}
	}

	// Is the currently logged in user a moderator of this forum?
	if(is_moderator($fid))
	{
		$ismod = true;
	}
	else
	{
		$ismod = false;
	}
	
	$unviewable_forums = get_unviewable_forums();
	if($unviewable_forums)
	{
		$unviewable_forums = "AND t.fid NOT IN ({$unviewable_forums})";
	}
	if(is_moderator($fid))
	{
		$visible_where = "AND p.visible != 2";
	}
	else
	{
		$visible_where = "AND p.visible > 0";
	}
	
	require_once MYBB_ROOT."inc/functions_posting.php";
	$query = $db->query("
		SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE p.pid = {$pid} {$unviewable_forums} {$visible_where}
	");
	$load_all = intval($mybb->input['load_all_quotes']);
	
	if($db->num_rows($query) == 0)
	{
		return xmlrespfalse("Invalid post");
	}
	
	$quoted_post = $db->fetch_array($query);

	// Only show messages for the current thread
	if($quoted_post['tid'] == $tid || $load_all == 1)
	{
		// If this post was the post for which a quote button was clicked, set the subject
		if($pid == $quoted_post['pid'])
		{
			$subject = preg_replace('#RE:\s?#i', '', $quoted_post['subject']);
			$subject = "RE: ".$subject;
		}
		$message .= parse_quoted_message($quoted_post);
		$quoted_ids[] = $quoted_post['pid'];
	}
	// Count the rest
	else
	{
		++$external_quotes;
	}

	if($mybb->settings['maxquotedepth'] != '0')
	{
		$message = remove_message_quotes($message);
	}	
	
	$result = new xmlrpcval(array(
		'post_id'       => new xmlrpcval($pid),
		'post_title'    => new xmlrpcval($subject, 'base64'),
		'post_content'  => new xmlrpcval($message, 'base64'),
	), 'struct');

	return new xmlrpcresp($result);
}
