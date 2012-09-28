<?php
  
defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function get_box_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'box_id' => Tapatalk_Input::INT,
		'start_num' => Tapatalk_Input::INT,
		'last_num' => Tapatalk_Input::INT,
	), $xmlrpc_params);
		
	list($start, $limit) = process_page($input['start_num'], $input['last_num']);
	
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

	if(!$input['box_id'] || !array_key_exists($input['box_id'], $foldernames))
	{
		$input['box_id'] = 1;
	}
		
	$folder = $input['box_id'];
	
	$foldername = $foldernames[$folder];

	$lang->pms_in_folder = $lang->sprintf($lang->pms_in_folder, $foldername);
	if($folder == 2 || $folder == 3)
	{
		$sender = $lang->sentto;
	}
	else
	{
		$sender = $lang->sender;
	}

	// Do Multi Pages
	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."' AND folder='$folder'");
	$count_total = $db->fetch_field($query, 'total');
	$query = $db->simple_select("privatemessages", "COUNT(*) AS unread", "uid='".$mybb->user['uid']."' AND folder='$folder' AND readtime = 0");
	$count_unread = $db->fetch_field($query, 'unread');
	
	// Cache users in multiple recipients for sent & drafts folder
	//if($folder == 2 || $folder == 3)
	{
		// Get all recipients into an array
		$cached_users = $get_users = array();
		$users_query = $db->simple_select("privatemessages", "recipients", "folder='$folder' AND uid='{$mybb->user['uid']}'", array('limit_start' => $start, 'limit' => $limit, 'order_by' => 'dateline', 'order_dir' => 'DESC'));
		while($row = $db->fetch_array($users_query))
		{
			$recipients = unserialize($row['recipients']);
			if(is_array($recipients['to']) && count($recipients['to']))
			{
				$get_users = array_merge($get_users, $recipients['to']);
			}
			
			if(is_array($recipients['bcc']) && count($recipients['bcc']))
			{
				$get_users = array_merge($get_users, $recipients['bcc']);
			}
		}
		
		$get_users = implode(',', array_unique($get_users));
		
		// Grab info
		if($get_users)
		{
			$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "uid IN ({$get_users})");
			while($user = $db->fetch_array($users_query))
			{
				$cached_users[$user['uid']] = $user;
			}
		}
	}
	
	$user_online = $folder == 1 ? ', fu.lastactive, fu.invisible, fu.lastvisit ' : ', tu.lastactive, tu.invisible, tu.lastvisit ';
	
	$query = $db->query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername, fu.avatar as favatar, tu.avatar as tavatar $user_online
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
		WHERE pm.folder='$folder' AND pm.uid='".$mybb->user['uid']."'
		ORDER BY pm.dateline DESC
		LIMIT $start, $limit
	");
		
		
	$message_list = array();
	if($db->num_rows($query) > 0)
	{
		while($message = $db->fetch_array($query))
		{
			
			$status = 1;
			if($message['status'] == 0)
			{
				$msgalt = $lang->new_pm;
			}
			elseif($message['status'] == 1)
			{
				$msgalt = $lang->old_pm;
				$status = 2;
			}
			elseif($message['status'] == 3)
			{
				$msgalt = $lang->reply_pm;
				$status = 3;
			}
			elseif($message['status'] == 4)
			{
				$msgalt = $lang->fwd_pm;
				$status = 4;
			}
			
			$msg_from = null;
			$msg_to = array();
			$avatar = "";
			$outboxdisplayuserid = 0;
		//	if($folder == 2 || $folder == 3)
			{ // Sent Items or Drafts Folder Check
				$recipients = unserialize($message['recipients']);
				if(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && count($recipients['bcc']) > 0))
				{
					foreach($recipients['to'] as $uid)
					{
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$msg_to[]=new xmlrpcval($user['username'], "base64");
						
						if (($folder == 2 or $folder == 3) && !$outboxdisplayuserid)
						{
						    $outboxdisplayuserid = $uid;
						}
						
					}
					/*if(is_array($recipients['bcc']) && count($recipients['bcc']))
					{
						foreach($recipients['bcc'] as $uid)
						{
							$profilelink = get_profile_link($uid);
							$user = $cached_users[$uid];
							$msg_to[]=new xmlrpcval($user['username'], "base64");
						}
					}*/
				}
				else if($message['toid'])
				{
					$tofromusername = $message['tousername'];
					$tofromuid = $message['toid'];
					$msg_to[]=new xmlrpcval(array("username" => new xmlrpcval($tofromusername, "base64")), "struct");
				}
				else
				{
					$tofromusername = $lang->not_sent;
					$msg_to[]=new xmlrpcval($tofromusername, "base64");
				}
				$avatar = $message['tavatar'];
			}
			
			if($folder != 2 && $folder != 3)
			{
				$tofromusername = $message['fromusername'];
				$tofromuid = $message['fromid'];
				if($tofromuid == 0)
				{
					$tofromusername = $lang->mybb_engine;
				}
				
				if(!$tofromusername)
				{
					$tofromuid = 0;
					$tofromusername = $lang->na;
				}
				$msg_from = $tofromusername;
				$avatar = $message['favatar'];
			}
			else
			{
			    if ($outboxdisplayuserid)
			    {
			        $outboxdisplayuser = get_user($outboxdisplayuserid);
			        $avatar = $outboxdisplayuser['avatar'];
			    }
			}
			
			
			if(!trim($message['subject']))
			{
				$message['subject'] = $lang->pm_no_subject;
			}
				
        	$is_online = false;
        	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];
        	if($message['lastactive'] > $timecut && ($message['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1) && $message['lastvisit'] != $message['lastactive'])
        	{
        		$is_online = true;
        	}
			
			$new_message = array(
				'msg_id'          => new xmlrpcval($message['pmid'], 'string'),
				'msg_state'       => new xmlrpcval($status, 'int'),
				'sent_date'       => new xmlrpcval(mobiquo_iso8601_encode($message['dateline']), 'dateTime.iso8601'),
				'msg_to'          => new xmlrpcval($msg_to, 'array'),
				'icon_url'        => new xmlrpcval(absolute_url($avatar), 'string'),
				'msg_subject'     => new xmlrpcval($message['subject'], 'base64'),
				'short_content'   => new xmlrpcval(process_short_content($message['message'], $parser), 'base64'),
				'is_online'       => new xmlrpcval($is_online, 'boolean'),
			);
			
			if($msg_from !== null)
				$new_message['msg_from'] = new xmlrpcval($msg_from, 'base64');
				
			$message_list []= new xmlrpcval($new_message, "struct");
			
		}
	}
	
	$result = new xmlrpcval(array(
		'result'             => new xmlrpcval(true, 'boolean'),
		'result_text'        => new xmlrpcval('', 'base64'),
		'total_message_count'=> new xmlrpcval($count_total, 'int'),
		'total_unread_count' => new xmlrpcval($count_unread, 'int'),
		'list'               => new xmlrpcval($message_list, 'array'),
	), 'struct');
	
	return new xmlrpcresp($result);
	

}