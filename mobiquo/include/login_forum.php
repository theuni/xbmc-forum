<?php

defined('IN_MOBIQUO') or exit;

function login_forum_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forum_cache;
			
	$lang->load("forumdisplay");

	$input = Tapatalk_Input::filterXmlInput(array(
		'forum_id' => Tapatalk_Input::INT,
		'password' => Tapatalk_Input::STRING,
	), $xmlrpc_params);
		
	if(tt_check_forum_password($input['forum_id'], null, $input['password']))
		return xmlresptrue();
	else
		return xmlrespfalse('Incorrect forum password entered');		
}

