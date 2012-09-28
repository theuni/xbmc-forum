<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_upload.php";
require_once MYBB_ROOT."inc/class_parser.php";

function save_raw_post_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
		
	$lang->load("editpost");

	$input = Tapatalk_Input::filterXmlInput(array(
		'post_id' => Tapatalk_Input::INT,
		'post_title' => Tapatalk_Input::STRING,
		'post_content' => Tapatalk_Input::STRING,
		'return_html' => Tapatalk_Input::INT,
	), $xmlrpc_params);  

	$parser = new postParser;
	
	// No permission for guests
	if(!$mybb->user['uid'])
	{
		return tt_no_permission();
	}

	// Get post info
	$pid = $input['post_id'];
	
	$query = $db->simple_select("posts", "*", "pid='$pid'");
	$post = $db->fetch_array($query);
		
	if(!$post['pid'])
	{
		return xmlrespfalse($lang->error_invalidpost);
	}

	// Get thread info
	$tid = $post['tid'];
	$thread = get_thread($tid);

	if(!$thread['tid'])
	{
		return xmlrespfalse($lang->error_invalidthread);
	}

	$thread['subject'] = htmlspecialchars_uni($thread['subject']);

	// Get forum info
	$fid = $post['fid'];
	$forum = get_forum($fid);
	if(!$forum || $forum['type'] != "f")
	{
		return xmlrespfalse($lang->error_closedinvalidforum);
	}
	if($forum['open'] == 0 || $mybb->user['suspendposting'] == 1)
	{
		return tt_no_permission();
	}

	$forumpermissions = forum_permissions($fid);

	if(!is_moderator($fid, "caneditposts"))
	{
		if($thread['closed'] == 1)
		{
			return xmlrespfalse($lang->redirect_threadclosed);
		}
		if($forumpermissions['caneditposts'] == 0)
		{
			return tt_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			return tt_no_permission();
		}
		// Edit time limit
		$time = TIME_NOW;
		if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < ($time-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			return xmlrespfalse($lang->edit_time_limit);
		}
	}
		
	// Check if this forum is password protected and we have a valid password
	tt_check_forum_password($forum['fid']);

	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("update");
	$posthandler->action = "post";

	// Set the post data that came from the input to the $post array.
	$post = array(
		"pid" => $pid,
		"prefix" => 0,
		"subject" => $input['post_title'],
		"icon" => 0,
		"uid" => $mybb->user['uid'],
		"username" => $mybb->user['username'],
		"edit_uid" => $mybb->user['uid'],
		"message" => $input['post_content'],
	);
	
	// get subscription status
	$query = $db->simple_select("threadsubscriptions", 'notification', "uid='".intval($mybb->user['uid'])."' AND tid='".intval($tid)."'");
	$substatus = $db->fetch_array($query);

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => 1,
		"subscriptionmethod" => isset($substatus['notification']) ? ($substatus['notification'] == 1 ? 'instant' : 'none') : '',
		"disablesmilies" => 0
	);

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	if(!$posthandler->validate_post())
	{
		$post_errors = $posthandler->get_friendly_errors();		
		return xmlrespfalse(implode(" :: ", $post_errors));
	}
	// No errors were found, we can call the update method.
	else
	{
		$postinfo = $posthandler->update_post();
		$visible = $postinfo['visible'];
		$first_post = $postinfo['first_post'];

		// Help keep our attachments table clean.
		$db->delete_query("attachments", "filename='' OR filesize<1");

		if($visible == 0 && $first_post && !is_moderator($fid, "", $mybb->user['uid']))
		{
			$state = 1;
		}
		else if($visible == 0 && !is_moderator($fid, "", $mybb->user['uid']))
		{
			$state = 1;
		}
		// Otherwise, send them back to their post
		else
		{
			$state = 0;
		}
	}
	
	
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
			   
	$post['subject'] = $parser->parse_badwords($post['subject']); 
	
	
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'state'         => new xmlrpcval($state, 'int'),
		'post_title'    => new xmlrpcval($post['subject'], 'base64'),
		'post_content'  => new xmlrpcval(process_post($post['message'], $input['return_html']), 'base64'),
	), 'struct');
		
	return new xmlrpcresp($result);
}
