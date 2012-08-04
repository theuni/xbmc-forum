<?php
  
defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_upload.php";
		
function upload_avatar_func($xmlrpc_params)
{
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups;
	
	chdir("../");
	
	$input = Tapatalk_Input::filterXmlInput(array(
			'content' => Tapatalk_Input::STRING,
	), $xmlrpc_params);
		
	if($mybb->usergroup['canuploadavatars'] == 0)
	{
		error_no_permission();
	}
	$avatar = upload_avatar($_FILES['upload']);
	if($avatar['error'])
	{
		return xmlrespfalse($avatar['error']);
	}
	else
	{
		if($avatar['width'] > 0 && $avatar['height'] > 0)
		{
			$avatar_dimensions = $avatar['width']."|".$avatar['height'];
		}
		$updated_avatar = array(
			"avatar" => $avatar['avatar'].'?dateline='.TIME_NOW,
			"avatardimensions" => $avatar_dimensions,
			"avatartype" => "upload"
		);
		$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
	}
	
	return xmlresptrue();
}