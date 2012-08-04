<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function get_quote_pm_func($xmlrpc_params)
{	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;		
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'message_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);
			
	$lang->load("private");

	$parser = new postParser;

	if($mybb->settings['enablepms'] == 0)
	{
		return xmlrespfalse($lang->pms_disabled);
	}

	if($mybb->user['uid'] == '/' || $mybb->user['uid'] == 0 || $mybb->usergroup['canusepms'] == 0)
	{
		return tt_no_permission();
	}

	if(!$mybb->user['pmfolders'])
	{
		$mybb->user['pmfolders'] = "1**$%%$2**$%%$3**$%%$4**";

		$sql_array = array(
			 "pmfolders" => $mybb->user['pmfolders']
		);
		$db->update_query("users", $sql_array, "uid = ".$mybb->user['uid']);
	}

	$rand = my_rand(0, 9);
	if($rand == 5)
	{
		update_pm_count();
	}        
			
	$foldernames = array();
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$foldernames[$folderinfo[0]] = $folderinfo[1];
	}
	
	
	if($mybb->usergroup['cansendpms'] == 0)
	{
		return tt_no_permission();
	}
	
	$query = $db->query("
		SELECT pm.*, u.username AS quotename
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
		WHERE pm.pmid='{$input['message_id']}' AND pm.uid='".$mybb->user['uid']."'
	");
	$pm = $db->fetch_array($query);

	$message = $pm['message'];
	$subject = $pm['subject'];

	$subject = preg_replace("#(FW|RE):( *)#is", '', $subject);
	$message = "[quote={$pm['quotename']}]\n$message\n[/quote]";
	$message = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $message);
	$subject = "Re: $subject";
			
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'msg_id'      => new xmlrpcval($pm['pmid'], 'string'),
		'msg_subject'   => new xmlrpcval($subject, 'base64'),
		'text_body'     => new xmlrpcval($message, 'base64'),
	), 'struct');

	return new xmlrpcresp($result);
	
}
