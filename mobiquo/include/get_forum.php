<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";

function get_forum_func()
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions;
	
	$lang->load("index");
		
	if($mybb->user['uid'] == 0)
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT *, threads as unread_count
			FROM ".TABLE_PREFIX."forums
			WHERE active != 0
			ORDER BY pid, disporder
		");
		
		$forumsread = unserialize($mybb->cookies['mybb']['forumread']);
	}
	else
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT f.*, fr.dateline AS lastread, (
				select count(*) from ".TABLE_PREFIX."threads where fid=f.fid and lastpost > fr.dateline
			) as unread_count
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
			LEFT JOIN ".TABLE_PREFIX."forumsubscriptions fs ON (fs.fid=f.fid AND fs.uid='{$mybb->user['uid']}')
			WHERE f.active != 0
			ORDER BY pid, disporder
		");
	}
	
	while($forum = $db->fetch_array($query))
	{
		if($mybb->user['uid'] == 0)
		{
			if($forumsread[$forum['fid']])
			{
				$forum['lastread'] = $forumsread[$forum['fid']];
			}
		}
		$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
	}
	$forumpermissions = forum_permissions();

	$excols = "index";
	$permissioncache['-1'] = "1";

	$showdepth = 10;
	
	$xml_nodes = new xmlrpcval(array(), 'array');
	$done=array();
	$xml_tree = treeBuild(0, $fcache, $xml_nodes, $done);
	$xml_nodes->addArray($xml_tree);
	
	return new xmlrpcresp($xml_nodes);
}

function processForum($forum){
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions;

	if($forum['password'] != '' && $mybb->cookies['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password'])){
		$hideinfo = true;
		$showlockicon = 1;
	}
	
	$lightbulb = get_forum_lightbulb($forum, $lastpost_data, $showlockicon);
	
	$xmlrpc_forum = new xmlrpcval(array(
		'forum_id'      => new xmlrpcval($forum['fid'], 'string'),
		'forum_name'    => new xmlrpcval(basic_clean($forum['name']), 'base64'),
		'description'   => new xmlrpcval($forum['description'], 'base64'),
		'parent_id'     => new xmlrpcval($forum['pid'], 'string'),
		//'logo_url'      => new xmlrpcval($icon, 'string'),
		'new_post'      => new xmlrpcval($lightbulb['folder'] == 'on', 'boolean'),
		'unread_count'  => new xmlrpcval(!empty($node['hasNew']) ? $node['hasNew'] : 0, 'int'),
		'is_protected'  => new xmlrpcval(!empty($forum['password']), 'boolean'),
		'url'           => new xmlrpcval($forum['linkto'], 'string'),
		'sub_only'      => new xmlrpcval($forum['type'] == 'c', 'boolean'),
		'can_subscribe' => new xmlrpcval($forumpermissions[$forum['fid']]['canviewthreads'] == 1, 'boolean'),
		'is_subscribed' => new xmlrpcval(!empty($forum['fsid']), 'boolean'),
	), 'struct');
	
	return $xmlrpc_forum;
}

function treeBuild($pid, &$fcache, &$xml_nodes, &$done){
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions;
	
	$newForums = array();
	
	if(!empty($fcache[$pid]))
	{
		foreach($fcache[$pid] as $parent)
		{
			foreach($parent as $forum)
			{
    			// Get the permissions for this forum
    			$permissions = $forumpermissions[$forum['fid']];

    			// If this user doesnt have permission to view this forum and we're hiding private forums, skip this forum
    			if($permissions['canview'] != 1 && $mybb->settings['hideprivateforums'] == 1)
    			{
    				continue;
    			}
    			
				$forum2 = processForum($forum);
				$done[$id] = true;
				
				$forum2->addStruct(array('child' => new xmlrpcval(treeBuild($forum['fid'], $fcache, $xml_nodes, $done), 'array')/*, 'array'*/));

				$newForums[]=$forum2;
				
			}
		}
	}
	
	return $newForums;	
}
