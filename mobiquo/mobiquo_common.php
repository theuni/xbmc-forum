<?php

defined('IN_MOBIQUO') or exit;

function get_error($error_key, $params = array())
{
	$error_message = (string)new XenForo_Phrase($error_key, $params);
	
	$r = new xmlrpcresp(
			new xmlrpcval(array(
				'result'        => new xmlrpcval(false, 'boolean'),
				'result_text'   => new xmlrpcval($error_message, 'base64'),
			),'struct')
	);
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$r->serialize('UTF-8');
	exit;
}


function errors(array $errors){
	if(empty($errors))
		return;
		
	$error = "";
	foreach($errors as $err){
		$error .= "$err\r\n";
	}
	error($error);
}


function get_method_name()
{
	$ver = phpversion();
	if ($ver[0] >= 5) {
		$data = file_get_contents('php://input');
	} else {
		$data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
	}
	$parsers = php_xmlrpc_decode_xml($data);
	if(is_object($parsers))
		return trim($parsers->methodname);
	else
	{
		if(!empty($_POST['method_name']))
			return $_POST['method_name'];
		else
			return null;
	}
}

function get_mobiquo_config() 
{
	$config_file = './config/config.txt';
	file_exists($config_file) or exit('config.txt does not exists');
	
	if(function_exists('file_get_contents')){
		$tmp = file_get_contents($config_file);
	}else{
		$handle = fopen($config_file, 'rb');
		$tmp = fread($handle, filesize($config_file));
		fclose($handle);
	}
	
	// remove comments by /*xxxx*/ or //xxxx
	$tmp = preg_replace('/\/\*.*?\*\/|\/\/.*?(\n)/si','$1',$tmp);
	$tmpData = preg_split("/\s*\n/", $tmp, -1, PREG_SPLIT_NO_EMPTY);
	
	$mobiquo_config = array();
	foreach ($tmpData as $d){
		list($key, $value) = preg_split("/=/", $d, 2); // value string may also have '='
		$key = trim($key);
		$value = trim($value);
		if ($key == 'hide_forum_id')
		{
			$value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
			count($value) and $mobiquo_config[$key] = $value;
		}
		else
		{
			strlen($value) and $mobiquo_config[$key] = $value;
		}
	}
	
	return $mobiquo_config;
}

function xmlresptrue()
{
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64')
	), 'struct');
	
	return new xmlrpcresp($result);
}

function xmlrespfalse($error_message)
{
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(false, 'boolean'),
		'result_text'   => new xmlrpcval(strip_tags($error_message), 'base64')
	), 'struct');
	
	return new xmlrpcresp($result);
}

function tt_error($error="", $title="")
{
	if(!empty($title))
		$error = "{$title} :: {$error}";
	$error = strip_tags($error);    
	
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror($error)->serialize('UTF-8'));
	exit;
}

/**
* For use via preg_replace_callback; makes urls absolute before wrapping them in [url]
*/
function parse_local_link($input){
	return "[URL=".XenForo_Link::convertUriToAbsoluteUri($input[1], true)."]{$input[2]}[/URL]";
}

function xmlresperror($error_message)
{
	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(false, 'boolean'),
		'result_text'   => new xmlrpcval($error_message, 'base64')
	), 'struct');

	return new xmlrpcresp($result/*, 98, $error_message*/);
}



function tt_check_forum_password($fid, $pid=0, $pass=""){
	
	global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forum_cache;
	
	
	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
		if(!$forum_cache)
		{
			return false;
		}
	}
	
	// Loop through each of parent forums to ensure we have a password for them too
	$parents = explode(',', $forum_cache[$fid]['parentlist']);
	rsort($parents);
	if(!empty($parents))
	{
		foreach($parents as $parent_id)
		{
			if($parent_id == $fid || $parent_id == $pid)
			{
				continue;
			}
			
			if($forum_cache[$parent_id]['password'] != "")
			{
				tt_check_forum_password($pass, $parent_id, $fid);
			}
		}
	}
	
	$password = $forum_cache[$fid]['password'];
	if($password)
	{
		if($pass && $pid == 0)
		{
			if($password == $pass)
			{
				my_setcookie("forumpass[$fid]", md5($mybb->user['uid'].$pass), null, true);
				$showform = false;
			}
			else
			{
				$showform = true;
			}
		}
		else
		{
			if(!$mybb->cookies['forumpass'][$fid] || ($mybb->cookies['forumpass'][$fid] && md5($mybb->user['uid'].$password) != $mybb->cookies['forumpass'][$fid]))
			{
				$showform = true;
			}
			else
			{
				$showform = false;
			}
		}
	}
	else
	{
		$showform = false;
	}

	if($showform)
	{
		return false;
	}	
	return true;
}



function tt_no_permission(){
	return xmlrespfalse('You do not have permission to view this');
}

function absolute_url($url){
	global $mybb;
	if(empty($url))
		return "";
	if(substr($url, 0, 7) != 'http://'){
		$url = $mybb->settings['bburl'] . "/" . $url;
	}
	return $url;
}



function get_forum_icon_url($fid)
{
	$logo_url = '';
	if (file_exists("./forum_icons/$fid.png"))
	{
		$logo_url = FORUM_ROOT."mobiquo/forum_icons/$fid.png";
	}
	else if (file_exists("./forum_icons/$fid.jpg"))
	{
		$logo_url = FORUM_ROOT."mobiquo/forum_icons/$fid.jpg";
	}
	else if (file_exists("./forum_icons/default.png"))
	{
		$logo_url = FORUM_ROOT."mobiquo/forum_icons/default.png";
	}
	
	return $logo_url;
}

function mobiquo_iso8601_encode($timet, $offset = 0)
{
    global $mybb;
    
    if(!$offset)
    {
        if($mybb->user['uid'] != 0 && array_key_exists("timezone", $mybb->user))
        {
            $offset = $mybb->user['timezone'];
            $dstcorrection = $mybb->user['dst'];
        }
        elseif(defined("IN_ADMINCP"))
        {
            $offset =  $mybbadmin['timezone'];
            $dstcorrection = $mybbadmin['dst'];
        }
        else
        {
            $offset = $mybb->settings['timezoneoffset'];
            $dstcorrection = $mybb->settings['dstcorrection'];
        }
        
        // If DST correction is enabled, add an additional hour to the timezone.
        if($dstcorrection == 1)
        {
            ++$offset;
            if(my_substr($offset, 0, 1) != "-")
            {
                $offset = "+".$offset;
            }
        }
    }
    
    $t = gmdate("Ymd\TH:i:s", $timet + $offset * 3600);
    $t .= sprintf("%+03d:%02d", intval($offset), abs($offset - intval($offset)) * 60); 
    
    return $t;
}

function cutstr($string, $length)
{
	if(strlen($string) <= $length) {
		return $string;
	}

	$string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

	$strcut = '';

	$n = $tn = $noc = 0;
	while($n < strlen($string)) {

		$t = ord($string[$n]);
		if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
			$tn = 1; $n++; $noc++;
		} elseif(194 <= $t && $t <= 223) {
			$tn = 2; $n += 2; $noc += 2;
		} elseif(224 <= $t && $t <= 239) {
			$tn = 3; $n += 3; $noc += 2;
		} elseif(240 <= $t && $t <= 247) {
			$tn = 4; $n += 4; $noc += 2;
		} elseif(248 <= $t && $t <= 251) {
			$tn = 5; $n += 5; $noc += 2;
		} elseif($t == 252 || $t == 253) {
			$tn = 6; $n += 6; $noc += 2;
		} else {
			$n++;
		}

		if($noc >= $length) {
			break;
		}

	}
	if($noc > $length) {
		$n -= $tn;
	}

	$strcut = substr($string, 0, $n);
	
	return $strcut;
}

function process_short_content($post_text, $parser = null, $length = 200)
{
	if($parser === null){
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
	}
	
	$post_text = $parser->parse_badwords($post_text);
	$post_text = preg_replace('/\[url.*?\].*?\[\/url.*?\]/', '[url]', $post_text);
	$post_text = preg_replace('/\[img.*?\].*?\[\/img.*?\]/', '[img]', $post_text);
	$post_text = preg_replace('/[\n\r\t]+/', ' ', $post_text);
	
	// strip bbcode
	//$post_text = preg_replace('/\[[^]]*]([^[]*)\[\/[^]]*]/s', '', $post_text);
	//$post_text = preg_replace('/\[[^]]*=.*?]([^[]*)\[\/[^]]*]/s', '', $post_text);
	$post_text = preg_replace('/\[\/?.*?]/s', '', $post_text);

	$post_text = html_entity_decode($post_text, ENT_QUOTES, 'UTF-8');
	$post_text = function_exists('mb_substr') ? mb_substr($post_text, 0, $length) : substr($post_text, 0, $length);
	return $post_text;
}

function process_post($post, $returnHtml = false)
{
	if($returnHtml){
		//$post = str_replace("&", '&amp;', $post);
		//$post = str_replace("<", '&lt;', $post);
		//$post = str_replace(">", '&gt;', $post);
		// handled by post parser nl2br option
		//$post = str_replace("\r", '', $post);
		//$post = str_replace("\n", '<br />', $post);
		$post = str_replace('[hr]', '<br />____________________________________<br />', $post);
	} else {
		$post = strip_tags($post);
		$post = html_entity_decode($post, ENT_QUOTES, 'UTF-8');
		$post = str_replace('[hr]', "\n____________________________________\n", $post);
	}
	
	$post = trim($post);
	// remove link on img
	$post = preg_replace('/\[url=[^\]]*?\]\s*(\[img\].*?\[\/img\])\s*\[\/url\]/si', '$1', $post);
	
	return $post;
}

function process_page($start_num, $end)
{
	$start = intval($start_num);
	$end = intval($end);
	$start = empty($start) ? 0 : max($start, 0);
	if (empty($end) || $end < $start)
	{
		$start = 0;
		$end = 19;
	}
	elseif ($end - $start >= 50) {
		$end = $start + 49;
	}
	$limit = $end - $start + 1;
	$page = intval($start/$limit) + 1;
	
	return array($start, $limit, $page);
}

// redundant? __toString ;)
function get_xf_lang($lang_key, $params = array())
{
	$phrase = new XenForo_Phrase($lang_key, $params);
	return $phrase->render();
}

function get_online_status($user_id)
{
	$bridge = Tapatalk_Bridge::getInstance();
	$sessionModel = $bridge->getSessionModel();
	$userModel = $bridge->getUserModel();
	
	$bypassUserPrivacy = $userModel->canBypassUserPrivacy();
	
	$conditions = array(
		'cutOff'            => array('>', $sessionModel->getOnlineStatusTimeout()),
		'getInvisible'      => $bypassUserPrivacy,
		'getUnconfirmed'    => $bypassUserPrivacy,
		'user_id'           => XenForo_Visitor::getUserId(),
		'forceInclude'      => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
	);
	
	$onlineUsers = $sessionModel->getSessionActivityRecords($conditions);
	
	return empty($onlineUsers) ? false : true;
}

function basic_clean($str)
{
	$str = strip_tags($str);
	$str = trim($str);
	return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}


function process_post_attachments($id, &$post)
{
	global $attachcache, $mybb, $theme, $templates, $forumpermissions, $lang;

	$validationcount = 0;
	$tcount = 0;
	
	$attachment_list = array();
	if(is_array($attachcache[$id]))
	{ // This post has 1 or more attachments
		foreach($attachcache[$id] as $aid => $attachment)
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
				$attachment['filesize_b'] = $attachment['filesize'];
				$attachment['filesize'] = get_friendly_size($attachment['filesize']);
				$ext = get_extension($attachment['filename']);
				if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
					$type = 'image';
				elseif($ext == "pdf")
					$type = 'pdf';
				else
					$type = 'other';
				
				$attachment['icon'] = get_attachment_icon($ext);
				// Support for [attachment=id] code
				if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					if($type == 'image')
					    $replace = '[img]'.absolute_url("attachment.php?aid={$attachment['aid']}").'[/img]';
					else
					    $replace = '[url='.absolute_url("attachment.php?aid={$attachment['aid']}").']'.$attachment['filename']."[/url]({$lang->postbit_attachment_size} {$attachment['filesize']} / {$lang->postbit_attachment_downloads} {$attachment['downloads']})";
					
					$post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $replace, $post['message']);
				}
				else
				{
					$url = absolute_url("attachment.php?aid={$attachment['aid']}");
    				$thumbnail_url = ($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != '') ? absolute_url("attachment.php?thumbnail={$attachment['aid']}") : $url;
    				
    				$attachment_list[] = new xmlrpcval(array(
    					'filename'      => new xmlrpcval($attachment['filename'], 'base64'),
						'filesize'      => new xmlrpcval($attachment['filesize_b'], 'int'),
    					'content_type'  => new xmlrpcval($type, 'string'),
    					'thumbnail_url' => new xmlrpcval($thumbnail_url, 'string'),
    					'url'           => new xmlrpcval($url, 'string'),
    				), 'struct');
				}
			}
		}
	}
	
	return $attachment_list;
}