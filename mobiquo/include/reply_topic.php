<?php
	
defined('IN_MOBIQUO') or exit;

require_once "include/reply_post.php";

function reply_topic_func($xmlrpc_params)
{	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	return reply_post_func($xmlrpc_params);	
}