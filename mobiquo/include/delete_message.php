<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function delete_message_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;        
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'message_id' => Tapatalk_Input::INT,
		'box_id' => Tapatalk_Input::INT,
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
		
	$query = $db->simple_select("privatemessages", "*", "pmid='{$input['message_id']}' AND uid='".$mybb->user['uid']."' AND folder='4'", array('order_by' => 'pmid'));
	if($db->num_rows($query) == 1)
	{
		$db->delete_query("privatemessages", "pmid='{$input['message_id']}'");
	}
	else
	{
		$sql_array = array(
			"folder" => 4,
			"deletetime" => TIME_NOW
		);
		$db->update_query("privatemessages", $sql_array, "pmid='{$input['message_id']}' AND uid='".$mybb->user['uid']."'");
	}

	update_pm_count();
	
	return xmlresptrue();
}
