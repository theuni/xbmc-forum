<?php

defined('IN_MOBIQUO') or exit;

function get_inbox_stat_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'pm_last_checked_time' => Tapatalk_Input::STRING,
		'subscribed_topic_last_checked_time' => Tapatalk_Input::STRING,
	), $xmlrpc_params);
	
	
	// PMs	
	$query = $db->simple_select("privatemessages", "COUNT(*) AS pms_unread", "uid='".$mybb->user['uid']."' AND status = '0' AND folder = '1'");
	$pmcount = $db->fetch_field($query, "pms_unread");

	
	// Subscribed threads
	$visible = "AND t.visible != 0";
	if(is_moderator() == true)
	{
		$visible = '';
	}

	$query = $db->query("
		SELECT COUNT(ts.tid) as threads
		FROM ".TABLE_PREFIX."threadsubscriptions ts
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = ts.tid)
		left join ".TABLE_PREFIX."threadsread tr on t.tid = tr.tid and tr.uid = '{$mybb->user['uid']}'
		WHERE ts.uid = '".$mybb->user['uid']."' and (tr.dateline < t.lastpost or tr.dateline is null) {$visible}
	");
	$threadcount = $db->fetch_field($query, "threads");
	
	
	
	
	$result = new xmlrpcval(array(
		'inbox_unread_count' => new xmlrpcval($pmcount, 'int'),
		'subscribed_topic_unread_count' => new xmlrpcval($threadcount, 'int'),
	), 'struct');

	return new xmlrpcresp($result);
}
