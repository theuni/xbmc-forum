<?php
	
defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once TT_ROOT.'parser.php';

function upload_attach_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("member");
	
	$parser = new postParser;
	
	$input = Tapatalk_Input::filterXmlInput(array(
			'forum_id' => Tapatalk_Input::INT,
			'group_id' => Tapatalk_Input::STRING,
			'content' => Tapatalk_Input::STRING,
	), $xmlrpc_params);	
	
	$fid = $input['forum_id'];
	
	//return xmlrespfalse(print_r($_FILES, true));
		
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
   
	$posthash = $input['group_id'];
	if(empty($posthash)){
		$posthash = md5($mybb->user['uid'].random_str());
	}
	$mybb->input['posthash'] = $posthash;	
	
	$attachwhere = "posthash='{$input['group_id_esc']}'";

	$query = $db->simple_select("attachments", "COUNT(aid) as numattachs", $attachwhere);
	$attachcount = $db->fetch_field($query, "numattachs");
	
	//if(is_array($_FILES['attachment']['name'])){
		foreach($_FILES['attachment'] as $k => $v){
			if(is_array($_FILES['attachment'][$k]))
				$_FILES['attachment'][$k] = $_FILES['attachment'][$k][0];
		}
	//}
	
	if ($_FILES['attachment']['type'] == 'image/jpg')
	    $_FILES['attachment']['type'] = 'image/jpeg';
	
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != 0 && ($mybb->settings['maxattachments'] == 0 || $attachcount < $mybb->settings['maxattachments']))
	{
		require_once MYBB_ROOT."inc/functions_upload.php";
		$attachedfile = upload_attachment($_FILES['attachment'], false);
	}
	
	if(empty($attachedfile)){
		return xmlrespfalse("No file uploaded");
	}
	//return xmlrespfalse(print_r($attachedfile, true));

	if($attachedfile['error'])
	{
		return xmlrespfalse(implode(" :: ", $attachedfile['error']));
	}
	
	$result = new xmlrpcval(array(
		'attachment_id'   => new xmlrpcval($attachedfile['aid'], 'string'),
		'group_id'        => new xmlrpcval($posthash, 'string'),
		'result'          => new xmlrpcval(true, 'boolean'),
		'result_text'     => new xmlrpcval('', 'base64'),
		'file_size'       => new xmlrpcval($attachedfile['filesize'], 'int'),
	), 'struct');

	return new xmlrpcresp($result);
	
}