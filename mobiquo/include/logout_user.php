<?php

defined('IN_MOBIQUO') or exit;

function logout_user_func()
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forum_cache;
	
	if(!$mybb->user['uid'])
	{
		return xmlrespfalse('Already logged out');
	}

	my_unsetcookie("mybbuser");
	my_unsetcookie("sid");
	if($mybb->user['uid'])
	{
		$time = TIME_NOW;
		$lastvisit = array(
			"lastactive" => $time-900,
			"lastvisit" => $time,
		);
		$db->update_query("users", $lastvisit, "uid='".$mybb->user['uid']."'");
		$db->delete_query("sessions", "sid='".$session->sid."'");
	}
	
	return xmlresptrue();
}
