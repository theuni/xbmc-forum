<?php
	
defined('IN_MOBIQUO') or exit;

function get_id_by_url_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	$input = Tapatalk_Input::filterXmlInput(array(
		'url' => Tapatalk_Input::STRING,
	), $xmlrpc_params);
	
	$url = trim($input['url']);
	
	$fid = $tid = $pid = "";
	
	// get forum id
	if (preg_match('/(?:\?|&|;)(?:f|fid|board)=(\d+)/', $url, $match)) {
		$fid = $match[1];
	}
	// get topic id
	if (preg_match('/(?:\?|&|;|\/)(?:t|tid|topic|thread)(?:=|-)(\d+)/', $url, $match)) {
		$tid = $match[1];
	}
	
	// get post id
	if (preg_match('/(?:\?|&|;|\/)(?:p|pid|post)(?:=|-)(\d+)/', $url, $match)) {
		$pid = $match[1];
	}

	$result = array();
	if ($fid) $result['forum_id'] = new xmlrpcval($fid, 'string');
	if ($tid) $result['topic_id'] = new xmlrpcval($tid, 'string');
	if ($pid) $result['post_id'] = new xmlrpcval($pid, 'string');
	
	$response = new xmlrpcval($result, 'struct');
	
	return new xmlrpcresp($response);
}
