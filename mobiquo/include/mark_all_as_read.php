<?php

defined('IN_MOBIQUO') or exit;

function mark_all_as_read_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forum_cache;
			
	$input = Tapatalk_Input::filterXmlInput(array(
		'forum_id' => Tapatalk_Input::INT
	), $xmlrpc_params);
	
	if(!empty($input['forum_id'])){        
		
		$validforum = get_forum($input['forum_id']);
		if(!$validforum)
		{
			return xmlrespfalse('Invalid forum');
		}

		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_forum_read($input['forum_id']);
		
	} else {
		
		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_all_forums_read();
		
	}
	
	return xmlresptrue();
}
