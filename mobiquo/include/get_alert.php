<?php
defined('IN_MOBIQUO') or exit;
$alertData = getAlert();
function getAlert()
{
	global $db,$mybb;
	$push_table = TABLE_PREFIX . "tapatalk_push_data";
	$lang = array(
		'reply_to_you' => "%s replied to \"%s\"",
		'quote_to_you' => '%s quoted your post in thread "%s"',
	    'tag_to_you' => '%s mentioned you in thread "%s"',
	    'post_new_topic' => '%s started a new thread "%s"',
	    'like_your_thread' => '%s liked your post in thread "%s"',
		'pm_to_you' => '%s sent you a message "%s"',
	);
	$alertData = array();
	if (!$mybb->user['uid']) error('No auth to get alert data');
	if(!$db->table_exists("tapatalk_push_data")) error('Push data table not exist');
	$page = !empty($request_params[0]) ? intval($request_params[0]) : 1;
	$per_page = !empty($request_params[1]) ? intval($request_params[1]) : 20;
	$nowtime = time();
    $monthtime = 30*24*60*60;
    $preMonthtime = $nowtime-$monthtime;
    $startNum = ($page-1) * $per_page; 
    $sql = 'DELETE FROM ' . $push_table . ' WHERE create_time < ' . $preMonthtime . ' and user_id = ' . $mybb->user['uid'];
    $db->query($sql);
    $sql_select = "SELECT p.*,u.uid as author_id FROM ". $push_table . " p 
    LEFT JOIN " . TABLE_PREFIX . "users u ON p.author = u.username WHERE p.user_id = " . $mybb->user['uid'] . "
    ORDER BY create_time DESC LIMIT $startNum,$per_page ";
    $query = $db->query($sql_select);
    while($data = $db->fetch_array($query))
    {
    	switch ($data['data_type'])
		{
			case 'sub':
				$data['message'] = sprintf($lang['reply_to_you'],$data['author'],$data['title']);
				break;
			case 'tag':
				$data['message'] = sprintf($lang['tag_to_you'],$data['author'],$data['title']);
				break;
			case 'newtopic':
				$data['message'] = sprintf($lang['post_new_topic'],$data['author'],$data['title']);
				break;
			case 'quote':
				$data['message'] = sprintf($lang['quote_to_you'],$data['author'],$data['title']);
				break;
			case 'pm':
			case 'conv':
				$data['message'] = sprintf($lang['pm_to_you'],$data['author'],$data['title']);
				break;
		}
    	$alertData[] = $data; 
    }
    return $alertData;
}