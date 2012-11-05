<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/datahandlers/pm.php";

function create_message_func($xmlrpc_params)
{	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups , $pminfo , $pm;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'user_name' => Tapatalk_Input::RAW,
		'subject' => Tapatalk_Input::STRING,
		'text_body' => Tapatalk_Input::STRING,
		'action' => Tapatalk_Input::INT,
		'pm_id' => Tapatalk_Input::INT,
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

	if($mybb->usergroup['cansendpms'] == 0)
	{
		return tt_no_permission();
	}

	$pmhandler = new PMDataHandler();

	$pm = array(
		"subject" => $input['subject'],
		"message" => $input['text_body'],
		"icon" => 0,
		"fromid" => $mybb->user['uid'],
		"do" => $input['action'] == 1 ? 'reply' : 'forward',
		"pmid" => $input['pm_id']
	);

	$pm['to'] = array_map("trim", $input['user_name']);

	$pm['options'] = array(
		"signature" => 0,
		"disablesmilies" => 0,
		"savecopy" => 1,
		"readreceipt" => 0
	);

	$pmhandler->set_data($pm);

	if(!$pmhandler->validate_pm())
	{
		$pm_errors = $pmhandler->get_friendly_errors();
		return xmlrespfalse(implode(" :: ", $pm_errors));
	}
	else
	{
		$pminfo = $pmhandler->insert_pm();
		$plugins->run_hooks("private_do_send_end");
	}
		
	return xmlresptrue();
}
