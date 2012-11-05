<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";

function new_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("newthread"); 
	
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'forum_id' => Tapatalk_Input::INT,
		'subject' => Tapatalk_Input::STRING,
		'message' => Tapatalk_Input::STRING,
		'prefix_id' => Tapatalk_Input::STRING,
		'attachment_id_array' => Tapatalk_Input::RAW,
		'group_id' => Tapatalk_Input::STRING,
	), $xmlrpc_params);

	$fid = $input['forum_id'];
		
	// Fetch forum information.
	$forum = get_forum($fid);
	if(!$forum)
	{
		return xmlrespfalse($lang->error_invalidforum);
	}	
	
	$forumpermissions = forum_permissions($fid);

	if($forum['open'] == 0 || $forum['type'] != "f")
	{
		return xmlrespfalse($lang->error_closedinvalidforum);
	}

	if($mybb->user['uid'] < 1 || $forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0 || $mybb->user['suspendposting'] == 1)
	{
		return tt_no_permission();
	}

	// Check if this forum is password protected and we have a valid password
	tt_check_forum_password($forum['fid']);

		
	// Check the maximum posts per day for this user
	if($mybb->settings['maxposts'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		$daycut = TIME_NOW-60*60*24;
		$query = $db->simple_select("posts", "COUNT(*) AS posts_today", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$daycut}");
		$post_count = $db->fetch_field($query, "posts_today");
		if($post_count >= $mybb->settings['maxposts'])
		{
			$lang->error_maxposts = $lang->sprintf($lang->error_maxposts, $mybb->settings['maxposts']);
			return xmlrespfalse($lang->error_maxposts);
		}
	}
	
	
		$username = $mybb->user['username'];
		$uid = $mybb->user['uid'];
	
	// Attempt to see if this post is a duplicate or not
	if($uid > 0)
	{
		$user_check = "p.uid='{$uid}'";
	}
	else
	{
		$user_check = "p.ipaddress='".$db->escape_string($session->ipaddress)."'";
	}
	if(!$mybb->input['savedraft'] && !$pid)
	{
		$query = $db->simple_select("posts p", "p.pid", "$user_check AND p.fid='{$forum['fid']}' AND p.subject='{$input['subject_esc']}' AND p.message='{$input['message_esc']}'");
		$duplicate_check = $db->fetch_field($query, "pid");
		if($duplicate_check)
		{
			return xmlrespfalse($lang->error_post_already_submitted);
		}
	}
	
	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("insert");
	$posthandler->action = "thread";

	// Set the thread data that came from the input to the $thread array.
	$new_thread = array(
		"fid" => $forum['fid'],
		"subject" => $input['subject'],
		"prefix" => $input['prefix_id'],
		"icon" => 0,
		"uid" => $uid,
		"username" => $username,
		"message" => $input['message'],
		"ipaddress" => get_ip(),
		"posthash" => $input['group_id_esc'],
	);
	
	$new_thread['savedraft'] = 0;
	
	// Set up the thread options from the input.
	$new_thread['options'] = array(
		"signature" => 1,
		"subscriptionmethod" => $mybb->user['subscriptionmethod'] == 0 ? '':$mybb->user['subscriptionmethod'],
		"disablesmilies" => 0
	);
	
	$posthandler->set_data($new_thread);
	
	// Now let the post handler do all the hard work.
	$valid_thread = $posthandler->validate_thread();
	
	$post_errors = array();
	// Fetch friendly error messages if this is an invalid thread
	if(!$valid_thread)
	{
		$post_errors = $posthandler->get_friendly_errors();
		return xmlrespfalse(implode(" :: ", $post_errors));
	}
		
	$thread_info = $posthandler->insert_thread();
	$tid = $thread_info['tid'];
	$pid = $thread_info['pid'];
	$visible = $thread_info['visible'];

	if($pid != '')
	{
		$db->update_query("attachments", array("pid" => intval($pid)), "posthash='{$input['group_id_esc']}'");
	}

	// Mark thread as read
	require_once MYBB_ROOT."inc/functions_indicators.php";
	mark_thread_read($tid, $fid);
	
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'topic_id'      => new xmlrpcval($tid, 'string'),
		'state'         => new xmlrpcval($visible ? 0 : 1, 'int'),
	), 'struct');
		
	return new xmlrpcresp($result);
}
