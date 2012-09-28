<?php
	
defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";

function remove_attachment_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	chdir("../");
	
	$lang->load("member");
	
	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
			'attachment_id' => Tapatalk_Input::INT,
			'forum_id' => Tapatalk_Input::INT,
			'group_id' => Tapatalk_Input::STRING,
			'post_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);    
	
	$fid = $input['forum_id'];
	
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

	tt_check_forum_password($forum['fid']);
   
	$posthash = $input['group_id'];
	if(empty($posthash)){
		$posthash = md5($mybb->user['uid'].random_str());
	}
	$mybb->input['posthash'] = $posthash;    
	
	// If we're removing an attachment that belongs to an existing post, some security checks...
	$query = $db->simple_select("attachments", "pid", "aid='{$input['attachment_id_esc']}'");
	$attachment = $db->fetch_array($query);
	$pid = $attachment['pid'];
	if($pid > 0){
		
		if($pid != $input['post_id']){
			return xmlrespfalse("The attachment you are trying to remove does not belong to this post");
		}
		
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
		}    
		
	} else {
		$pid = 0;
	}
	
	require_once MYBB_ROOT."inc/functions_upload.php";
	remove_attachment($pid, $mybb->input['posthash'], $input['attachment_id']);
		
	return xmlresptrue();
	
}