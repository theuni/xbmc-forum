<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";
require_once TT_ROOT . 'parser.php';

function get_message_func($xmlrpc_params)
{    
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
		
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'message_id' => Tapatalk_Input::INT,
		'box_id' => Tapatalk_Input::INT,
		'return_html' => Tapatalk_Input::INT
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
	
	$pmid = $input['message_id'];

	$query = $db->query("
		SELECT pm.*, u.*, f.*, g.title AS grouptitle, g.usertitle AS groupusertitle, g.stars AS groupstars, g.starimage AS groupstarimage, g.image AS groupimage, g.namestyle
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup)
		WHERE pm.pmid='{$pmid}' AND pm.uid='".$mybb->user['uid']."'
	");
	$pm = $db->fetch_array($query);
	if($pm['folder'] == 3)
	{
		return xmlrespfalse("Draft PMs are not supported by Tapatalk");
	}

	if(!$pm['pmid'])
	{
		return xmlrespfalse($lang->error_invalidpm);
	}
	
	$parser = new Tapatalk_Parser;
	$parser_options = array();
	$parser_options['allow_html'] = false;
	$parser_options['allow_mycode'] = true;
	$parser_options['allow_smilies'] = false;
	$parser_options['allow_imgcode'] = true;
	$parser_options['allow_videocode'] = true;
	$parser_options['nl2br'] = (boolean)$input['return_html'];
	$parser_options['filter_badwords'] = 1;
	$pm['message'] = post_bbcode_clean($pm['message']);
	$pm['message'] = $parser->parse_message($pm['message'], $parser_options);

	if($pm['receipt'] == 1)
	{
		if($mybb->usergroup['cantrackpms'] == 1 && $mybb->usergroup['candenypmreceipts'] == 1 && $mybb->input['denyreceipt'] == 1)
		{
			$receiptadd = 0;
		}
		else
		{
			$receiptadd = 2;
		}
	}

	if($pm['status'] == 0)
	{
		$time = TIME_NOW;
		$updatearray = array(
			'status' => 1,
			'readtime' => $time
		);

		if(isset($receiptadd))
		{
			$updatearray['receipt'] = $receiptadd;
		}

		$db->update_query('privatemessages', $updatearray, "pmid='{$pmid}'");

		// Update the unread count - it has now changed.
		update_pm_count($mybb->user['uid'], 6);

		// Update PM notice value if this is our last unread PM
		if($mybb->user['unreadpms']-1 <= 0 && $mybb->user['pmnotice'] == 2)
		{
			$updated_user = array(
				"pmnotice" => 1
			);
			$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");
		}
	}

	$pm['subject'] = $parser->parse_badwords($pm['subject']);
	if($pm['fromid'] == 0)
	{
		$pm['username'] = $lang->mybb_engine;
	}
	
	if(!$pm['username'])
	{
		$pm['username'] = $lang->na;
	}

	// Fetch the recipients for this message
	$pm['recipients'] = @unserialize($pm['recipients']);
    
	if(is_array($pm['recipients']['to']))
	{
		$uid_sql = implode(',', $pm['recipients']['to']);
		foreach($pm['recipients']['to'] as $uid)
		{
		    $user = get_user($uid);
		    $msg_to_list[] = new xmlrpcval(array(
                'user_id'   => new xmlrpcval($user['uid'], 'string'),
                'username'  => new xmlrpcval(basic_clean($user['username']), 'base64'),
				'user_type' => check_return_user_type($user['username']),
            ), 'struct');
            
            if (($pm['folder'] == 2 or $pm['folder'] == 3) && !$avatar)
            {
                $avatar = $user['avatar'];
            }
		}
	}
	else
	{
		$user = get_user($pm['toid']);
		$msg_to_list[] = new xmlrpcval(array(
            'user_id'   => new xmlrpcval($user['uid'], 'string'),
            'username'  => new xmlrpcval(basic_clean($user['username']), 'base64'),
			'user_type' => check_return_user_type($user['username']),
        ), 'struct');
        
        if (($pm['folder'] == 2 or $pm['folder'] == 3))
        {
            $avatar = $user['avatar'];
        }
	}

	$show_bcc = 0;

	// If we have any BCC recipients and this user is an Administrator, add them on to the query
	if(count($pm['recipients']['bcc']) > 0 && $mybb->usergroup['cancp'] == 1)
	{
		foreach($pm['recipients']['bcc'] as $uid)
		{
		    $user = get_user($uid);
		    $msg_to_list[] = new xmlrpcval(array(
                'user_id'   => new xmlrpcval($user['uid'], 'string'),
                'username'  => new xmlrpcval(basic_clean($user['username']), 'base64'),
				'user_type' => check_return_user_type($user['username']),
            ), 'struct');
		}
	}
	
	if (!$avatar) $avatar = $pm['avatar'];
	
	//$display_user = ($box_id == 'inbox') ? $message['from'] : $msg_to[0];
	$is_online = false;
	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];
	if($pm['lastactive'] > $timecut && ($pm['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1) && $pm['lastvisit'] != $pm['lastactive'])
	{
		$is_online = true;
	}
	
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'msg_from'      => new xmlrpcval($pm['username'], 'base64'),
		'msg_to'        => new xmlrpcval($msg_to_list, 'array'),
		'icon_url'      => new xmlrpcval(absolute_url($avatar), 'string'),
		'sent_date'     => new xmlrpcval(mobiquo_iso8601_encode($pm['dateline']), 'dateTime.iso8601'),
		'msg_subject'   => new xmlrpcval($pm['subject'], 'base64'),
		'text_body'     => new xmlrpcval(process_post($pm['message'], $input['return_html']), 'base64'),
		'is_online'     => new xmlrpcval($is_online, 'boolean'),
		'allow_smilies' => new xmlrpcval(true, 'boolean'), 
	), 'struct');

	return new xmlrpcresp($result);
}
