<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_user.php";

function subscribe_topic_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;    
		
	$lang->load("usercp");
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'topic_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);        
	
	$thread = get_thread($input['topic_id']);
	if(!$thread['tid'])
	{
		return xmlrespfalse($lang->error_invalidthread);
	}
	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
	{
		return tt_no_permission();
	}
	add_subscribed_thread($thread['tid'], 0);
	
	return xmlresptrue();        
}
