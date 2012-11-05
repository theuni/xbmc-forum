<?php
define('IN_MYBB', 1);
require_once '../global.php';
error_reporting(E_ALL & ~E_NOTICE);

$return_status = tt_do_post_request(array('test' => 1 , 'key' => $mybb->settings['tapatalk_push_key']),true);
$return_ip = tt_do_post_request(array('ip' => 1),true);
$board_url = $mybb->settings['bburl'];
if(isset($mybb->settings['tapatalk_push']) && $mybb->settings['tapatalk_push'] == 1)
{
	$option_status = 'On';
}
elseif (isset($mybb->settings['tapatalk_push']) && $mybb->settings['tapatalk_push'] == 0)
{
	$option_status = 'Off';
}
else 
{
	$option_status = 'Unset';
}	
echo '<b>Tapatalk Push Notification Status Monitor</b><br/>';
echo '<br/>Push notification test: ' . (($return_status === '1') ? '<b>Success</b>' : '<font color="red">Failed('.$return_status.')</font>');
echo '<br/>Current server IP: ' . $return_ip;
echo '<br/>Current forum url: ' . $board_url;
echo '<br/>Tapatalk user table existence: ' . (($mybb->settings['tapatalk_push']) ? 'Yes' : 'On');
echo '<br/>Push Notification Option status: ' . $option_status;
echo '<br/><br/><a href="http://tapatalk.com/api/api.php" target="_blank">Tapatalk API for Universal Forum Access</a> | <a href="http://tapatalk.com/mobile.php" target="_blank">Tapatalk Mobile Applications</a><br>
    For more details, please visit <a href="http://tapatalk.com" target="_blank">http://tapatalk.com</a>';

