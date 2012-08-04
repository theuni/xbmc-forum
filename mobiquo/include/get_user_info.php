<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

function addCustomField($name, $value, &$list){
	$list[] = new xmlrpcval(array(
		'name'  => new xmlrpcval($name, 'base64'),
		'value' => new xmlrpcval($value, 'base64')
	), 'struct');
}
	
function get_user_info_func($xmlrpc_params)
{	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$lang->load("member");
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'user_name' => Tapatalk_Input::STRING,
		'user_id' => Tapatalk_Input::INT,
	), $xmlrpc_params);
	
	if($mybb->usergroup['canviewprofiles'] == 0){
		return tt_no_permission();
	}
	
	$uid = $input['user_id'];
	
	if(!empty($input['user_name'])){
		$query = $db->simple_select("users", "uid", "username='{$input['user_name_esc']}'");
		$uid = $db->fetch_field($query, "uid");
	}
	
	if($uid == 0)
		return xmlrespfalse('User not found');
		
		
	if($mybb->user['uid'] != $uid)
	{
		$query = $db->simple_select("users", "*", "uid='$uid'");
		$memprofile = $db->fetch_array($query);
	}
	else
	{
		$memprofile = $mybb->user;
	}
	
	
	if(!$memprofile['uid'])
	{
		return xmlrespfalse($lang->error_nomember);
	}
	
	
	
	if(!$memprofile['displaygroup'])
	{
		$memprofile['displaygroup'] = $memprofile['usergroup'];
	}

	// Grab the following fields from the user's displaygroup
	$displaygroupfields = array(
		"title",
		"usertitle",
		"stars",
		"starimage",
		"image",
		"usereputationsystem"
	);
	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);

	// Get the user title for this user
	unset($usertitle);
	unset($stars);
	if(trim($memprofile['usertitle']) != '')
	{
		// User has custom user title
		$usertitle = $memprofile['usertitle'];
	}
	elseif(trim($displaygroup['usertitle']) != '')
	{
		// User has group title
		$usertitle = $displaygroup['usertitle'];
	}
	else
	{
		// No usergroup title so get a default one
		$query = $db->simple_select("usertitles", "*", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		while($title = $db->fetch_array($query))
		{
			if($memprofile['postnum'] >= $title['posts'])
			{
				$usertitle = $title['title'];
				$stars = $title['stars'];
				$starimage = $title['starimage'];
				break;
			}
		}
	}
	
	
	// User is currently online and this user has permissions to view the user on the WOL
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
	$query = $db->simple_select("sessions", "location,nopermission", "uid='$uid' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
	$session = $db->fetch_array($query);
	
	if(($memprofile['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $memprofile['uid'] == $mybb->user['uid']) && !empty($session))
	{
		// Fetch their current location
		$lang->load("online");
		require_once MYBB_ROOT."inc/functions_online.php";
		$activity = fetch_wol_activity($session['location'], $session['nopermission']);
		
		unset($activity['tid']);
		unset($activity['fid']);
		unset($activity['pid']);
		unset($activity['eid']);
		unset($activity['aid']);
		
		$location = strip_tags(build_friendly_wol_location($activity));
		$location_time = my_date($mybb->settings['timeformat'], $memprofile['lastactive']);

		$online = true;
	}
	// User is offline
	else
	{
		$online = false;
	}
	
	$custom_fields_list = array();
	
	$query = $db->simple_select("banned", "uid", "uid='{$uid}'");
	$isbanned = !!$db->fetch_field($query, "uid");
		
	$xmlrpc_user_info = new xmlrpcval(array(
		'post_count'         => new xmlrpcval($memprofile['postnum'], 'int'),
		'reg_time'           => new xmlrpcval(mobiquo_iso8601_encode($memprofile['regdate']), 'dateTime.iso8601'),
		'user_name'          => new xmlrpcval($memprofile['username'], 'base64'),
		'user_id'            => new xmlrpcval($memprofile['uid'], 'string'),
		'display_name'       => new xmlrpcval($memprofile['username'], 'base64'),
		'last_activity_time' => new xmlrpcval(mobiquo_iso8601_encode($memprofile['lastactive']), 'dateTime.iso8601'),
		'is_online'          => new xmlrpcval($online, 'boolean'),
		'accept_pm'          => new xmlrpcval($memprofile['receivepms'], 'boolean'),
		'i_follow_u'         => new xmlrpcval(false, 'boolean'), // not available in MyBB
		'u_follow_me'        => new xmlrpcval(false, 'boolean'), // not available in MyBB
		'accept_follow'      => new xmlrpcval(false, 'boolean'), // not available in MyBB
		'following_count'    => new xmlrpcval(0, 'int'), // not available in MyBB
		'follower'           => new xmlrpcval(0, 'int'), // not available in MyBB
		'display_text'       => new xmlrpcval($usertitle, 'base64'),
		'icon_url'           => new xmlrpcval(absolute_url($memprofile['avatar']), 'string'),
		'current_activity'   => new xmlrpcval($location, 'base64'), 
		'custom_fields_list' => new xmlrpcval($custom_fields_list, 'array'),
		
		'can_ban'            => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, 'boolean'),
		'is_ban'             => new xmlrpcval($isbanned, 'boolean'),
	), 'struct');

	return new xmlrpcresp($xmlrpc_user_info);
	
	
}
