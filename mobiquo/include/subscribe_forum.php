<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_user.php";

function subscribe_forum_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;	
		
	$lang->load("usercp");
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'forum_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);        
	
	$forum = get_forum($input['forum_id']);
	if(!$forum['fid'])
	{
		return xmlrespfalse($lang->error_invalidforum);
	}
	$forumpermissions = forum_permissions($forum['fid']);
	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
	{
		return tt_no_permission();
	}
	add_subscribed_forum($forum['fid']);
	
	return xmlresptrue();		
}
