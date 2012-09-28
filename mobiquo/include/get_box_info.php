<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function get_box_info_func($xmlrpc_params)
{    
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
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

	$foldercache = array();
	$folderids = array();
	$folderlist = '';
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $foldername);
		
		$type = "";
		if($fid == 1)
			$type = "INBOX";
		else if($fid == 2)
			$type = "SENT";
		else
			continue; // return inbox and send box only
		
		$foldercache[$fid] = array(
			"fid" => $fid,
			"name" => $foldername,
			"total" => 0,
			"unread" => 0,
			"type" => $type,
		);
		$folderids[]=intval($fid);
	}
	
	$query = $db->simple_select("privatemessages", "folder, count(*) as total", "FIND_IN_SET(folder, '".implode(",", $folderids)."') AND uid='{$mybb->user['uid']}' group by folder");	
	while($folder = $db->fetch_array($query))
	{
		$foldercache[$folder['folder']]['total'] = $folder['total'];
	}
	$query = $db->simple_select("privatemessages", "folder, count(*) as unread", "FIND_IN_SET(folder, '".implode(",", $folderids)."') AND uid='{$mybb->user['uid']}' AND readtime = 0 group by folder");
	while($folder = $db->fetch_array($query))
	{
		$foldercache[$folder['folder']]['unread'] = $folder['unread'];
	}
	
	$folder_list = array();
	foreach($foldercache as $fid => $folder){
		$folder_list[]=  new xmlrpcval(array(
			'box_id'       => new xmlrpcval($fid, 'string'),
			'box_name'     => new xmlrpcval($folder['name'], 'base64'),
			'msg_count'    => new xmlrpcval($folder['total'], 'int'),
			'unread_count' => new xmlrpcval($folder['unread'], 'int'),
			'box_type'     => new xmlrpcval($folder['type'], 'string'),
		), 'struct');
	}
	
	$spaceused = 0;
	if($mybb->usergroup['pmquota'] != '0' && $mybb->usergroup['cancp'] != 1)
	{
		$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_array($query);
		if($pmscount['total'] > 0)
		{
			$spaceused = $mybb->usergroup['pmquota'] - $pmscount['total'];
		}
	}
	
	$result = new xmlrpcval(array(
		'result'             => new xmlrpcval(true, 'boolean'),
		'result_text'        => new xmlrpcval('', 'base64'),
		'message_room_count' => new xmlrpcval($mybb->usergroup['cancp'] == 1 ? 100 : $spaceused, 'int'),
		'list'               => new xmlrpcval($folder_list, 'array'),
	), 'struct');

	return $result;
}
