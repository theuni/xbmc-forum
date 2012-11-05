<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_online.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

function get_online_users_func()
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("online");
	
	$user_lists = array();
	
	if($mybb->usergroup['canviewonline'] == 0){
		return tt_no_permission();
	}

	switch($db->type)
	{
		case "sqlite":
		case "pgsql":        
			$sql = "s.time DESC";
			break;
		default:
			$sql = "IF( s.uid >0, 1, 0 ) DESC, s.time DESC";
			break;
	}
	$refresh_string = '';
	
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
		
	// Query for active sessions
	$query = $db->query("
		SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup, u.avatar
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY $sql
	");

	// Fetch spiders
	$spiders = $cache->read("spiders");

	while($user = $db->fetch_array($query))
	{
		// Fetch the WOL activity
		$user['activity'] = fetch_wol_activity($user['location'], $user['nopermission']);
		
		// Stop links etc. 
		unset($user['activity']['tid']);
		unset($user['activity']['fid']);
		unset($user['activity']['pid']);
		unset($user['activity']['eid']);
		unset($user['activity']['aid']);

		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

		// Have a registered user
		if($user['uid'] > 0)
		{
			if($users[$user['uid']]['time'] < $user['time'] || !$users[$user['uid']])
			{
				$users[$user['uid']] = $user;
			}
		}
		// Otherwise this session is a bot
		else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
		{
			$user['bot'] = $spiders[$botkey]['name'];
			$user['usergroup'] = $spiders[$botkey]['usergroup'];
			$guests[] = $user;
		}
		// Or a guest
		else
		{
			$guests[] = $user;
		}
	}

	// Now we build the actual online rows - we do this separately because we need to query all of the specific activity and location information
	$online_rows = '';
	if(is_array($users))
	{
		reset($users);
		foreach($users as $user)
		{			
			// We have a registered user
			if($user['uid'] > 0)
			{
				// Only those with "canviewwolinvis" permissions can view invisible users
				if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
				{
					// Append an invisible mark if the user is invisible
					if($user['invisible'] == 1)
					{
						$invisible_mark = "*";
					}
					else
					{
						$invisible_mark = '';
					}

					//$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					//$online_name = build_profile_link($user['username'], $user['uid']).$invisible_mark;
					$online_name = $user['username'].$invisible_mark;
				}
			}
			// We have a bot
			elseif($user['bot'])
			{
				//$online_name = format_name($user['bot'], $user['usergroup']);
				continue;
			}
			// Otherwise we've got a plain old guest
			else
			{
				//$online_name = format_name($lang->guest, 1);
				continue;
			}
			
			// Fetch the location name for this users activity
			$location = strip_tags(build_friendly_wol_location($user['activity']));			
	
			$user_lists[] = new xmlrpcval(array(
				'user_name'     => new xmlrpcval($online_name, 'base64'),
				'user_type'     => check_return_user_type($online_name),
				'user_id'       => new xmlrpcval($user['uid'], 'string'),
				'display_text'  => new xmlrpcval($location, 'base64'),
				'icon_url'      => new xmlrpcval(absolute_url($user['avatar']), 'string'),
			), 'struct');
		
		}
	}
	
	$online_users = new xmlrpcval(array(
		'member_count' => new xmlrpcval(count($user_lists), 'int'),
		'guest_count'  => new xmlrpcval(count($guests), 'int'),
		'list'         => new xmlrpcval($user_lists, 'array'),
	), 'struct');

	return new xmlrpcresp($online_users);
}
