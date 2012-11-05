<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";


function reply_post_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups,$tid, $pid, $visible, $thread;

	$input = Tapatalk_Input::filterXmlInput(array(
			'forum_id' => Tapatalk_Input::INT,
			'topic_id' => Tapatalk_Input::INT,
			'subject' => Tapatalk_Input::STRING,
			'text_body' => Tapatalk_Input::STRING,
			'attachment_id_array' => Tapatalk_Input::RAW,
			'group_id' => Tapatalk_Input::STRING,
			'return_html' => Tapatalk_Input::INT,
	), $xmlrpc_params);

	$lang->load("newreply");
	$parser = new postParser;

	$tid = $input['topic_id'];

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

	if(!empty($input['group_id']))
		$posthash = $input['group_id'];
	else
		$posthash = md5($thread['tid'].$mybb->user['uid'].random_str());

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

		$user_check = "p.uid='{$uid}'";
		$query = $db->simple_select("posts p", "p.pid, p.visible", "{$user_check} AND p.tid='{$thread['tid']}' AND p.subject='".$db->escape_string($mybb->input['subject'])."' AND p.message='".$db->escape_string($mybb->input['message'])."' AND p.posthash='".$db->escape_string($mybb->input['posthash'])."' AND p.visible != '-2'");
		$duplicate_check = $db->fetch_field($query, "pid");
		if($duplicate_check)
		{
			return xmlrespfalse($lang->error_post_already_submitted);
		}


	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("insert");

	$post = array(
		"tid" => $input['topic_id'],
		"replyto" => 0,
		"fid" => $thread['fid'],
		"subject" => $input['subject'],
		"icon" => 0,
		"uid" => $uid,
		"username" => $username,
		"message" => $input['text_body'],
		"ipaddress" => get_ip(),
		"posthash" => $posthash
	);

	if($mybb->input['pid'])
	{
		$post['pid'] = $mybb->input['pid'];
	}

	$post['savedraft'] = 0;

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => 1,
		"subscriptionmethod" => $mybb->user['subscriptionmethod'] == 0 ? '':$mybb->user['subscriptionmethod'],
		"disablesmilies" => 0
	);

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	$valid_post = $posthandler->validate_post();

	$post_errors = array();
	// Fetch friendly error messages if this is an invalid post
	if(!$valid_post)
	{
		$post_errors = $posthandler->get_friendly_errors();
	}

	// Mark thread as read
	require_once MYBB_ROOT."inc/functions_indicators.php";
	mark_thread_read($tid, $fid);

	// One or more errors returned, fetch error list and throw to newreply page
	if(count($post_errors) > 0)
	{
		return xmlrespfalse(implode(" :: ", $post_errors));
	}
	else
	{
		$postinfo = $posthandler->insert_post();
		$pid = $postinfo['pid'];
		$visible = $postinfo['visible'];
        $plugins->run_hooks("newreply_do_newreply_end");
		// Deciding the fate
		if($visible == -2)
		{
			$state = 1;
		}
		elseif($visible == 1)
		{
			$state = 0;
		}
		else
		{
			$state = 1;
		}
	}

	$pid = intval($pid);
	$db->update_query("attachments", array("pid" => $pid), "posthash='{$input['group_id_esc']}'");

	// update thread attachment account
	if (count($input['attachment_id_array']) > 0)
	    update_thread_counters($tid, array("attachmentcount" => "+".count($input['attachment_id_array'])));

	$post = get_post($pid);

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

	$post['message'] = $parser->parse_message($post['message'], $parser_options);

	global $attachcache;
	$attachcache = array();
	if($thread['attachmentcount'] > 0)
	{
		// Now lets fetch all of the attachments for these posts.
		$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
		}
	}

	$attachment_list = process_post_attachments($post['pid'], $post);

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


	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'post_id'       => new xmlrpcval($postinfo['pid'], 'string'),
		'state'         => new xmlrpcval($state, 'int'),
	'post_author_id'    => new xmlrpcval($mybb->user['uid'], 'string'),
	'post_author_name'  => new xmlrpcval(basic_clean($mybb->user['username']), 'base64'),
	'icon_url'          => new xmlrpcval(absolute_url($mybb->user['avatar']), 'string'),
		'post_content'  => new xmlrpcval(process_post($post['message'], $input['return_html']), 'base64'),
		'can_edit'      => new xmlrpcval(is_moderator($fid, "caneditposts") || $thread['closed'] == 0 && $forumpermissions['caneditposts'] == 1, 'boolean'),
		'can_delete'    => new xmlrpcval($can_delete, 'boolean'),
		'post_time'     => new xmlrpcval(mobiquo_iso8601_encode(TIME_NOW), 'dateTime.iso8601'),
		'attachments'   => new xmlrpcval($attachment_list, 'array'),
	), 'struct');

	return new xmlrpcresp($result);
}
