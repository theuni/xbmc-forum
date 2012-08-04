<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";


function get_subscribed_forum_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("usercp");

	if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == 0)
	{
		return tt_no_permission();
	}
	
	$query = $db->simple_select("forumpermissions", "*", "gid='".$db->escape_string($mybb->user['usergroup'])."'");
	while($permissions = $db->fetch_array($query))
	{
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	
	// Build a forum cache.
	$query = $db->query("
		SELECT f.fid, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forums f
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");

	while($forum = $db->fetch_array($query))
	{
		if($mybb->user['uid'] == 0)
		{
			if($forumsread[$forum['fid']])
			{
				$forum['lastread'] = $forumsread[$forum['fid']];
			}
		}
		$readforums[$forum['fid']] = $forum['lastread'];
	}
	
	require_once MYBB_ROOT."inc/functions_forumlist.php";
	
	$fpermissions = forum_permissions();
	$query = $db->query("
		SELECT fs.*, f.*, t.subject AS lastpostsubject, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forumsubscriptions fs
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE f.type='f' AND fs.uid='".$mybb->user['uid']."'
		ORDER BY f.name ASC
	");
	$forums = '';
	$forum_list = array();
	while($forum = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$forum['fid']];
		if($forumpermissions['canview'] != 0)
		{
			$lightbulb = get_forum_lightbulb(array('open' => $forum['open'], 'lastread' => $forum['lastread']), array('lastpost' => $forum['lastpost']));
			
			$forum_list[] = new xmlrpcval(array(
				'forum_id'                      => new xmlrpcval($forum['fid'], 'string'),
				'forum_name'                    => new xmlrpcval(basic_clean($forum['name']), 'base64'),
				//'logo_url'                    => new xmlrpcval($forum[''], 'string'),
				'is_protected'                  => new xmlrpcval(!empty($forum['password']), 'boolean'),
				'new_post'                      => new xmlrpcval($lightbulb['folder'] == 'on', 'boolean'),
			), 'struct');
		}
	}
	
	
	$result = new xmlrpcval(array(
		'total_forums_num' => new xmlrpcval(count($forum_list), 'int'),
		'forums'           => new xmlrpcval($forum_list, 'array')
	), 'struct');

	return new xmlrpcresp($result);
}
