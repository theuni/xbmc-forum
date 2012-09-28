<?php

// This file should NOT be included in the release package!

$startTime = microtime(true);

define('IN_MOBIQUO', true);
define('MOBIQUO_DEBUG', true);
define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');

require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
require_once './xmlrpcs.php';

require_once './server_define.php';
require_once './mobiquo_common.php';
require_once './input.php';

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'mobiquo/mobiquo.php');
/*
function error($error="", $title="")
{
	if(!empty($title))
		$error = "{$title} :: {$error}";
	$error = strip_tags($error);

	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror($error)->serialize('UTF-8'));
	exit;
}
*/
require_once "../global.php";

require_once './parser.php';

error_reporting(E_ALL & ~8096 & ~E_NOTICE);


function shutdown(){
	$error = error_get_last();
	if(!empty($error)){
		switch($error['type']){
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_PARSE:
				echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror("Server error occurred: '{$error['message']} (".basename($error['file']).":{$error['line']})'")->serialize('UTF-8'));
				break;
		}
	}
}
register_shutdown_function('shutdown');

$mobiquo_config = get_mobiquo_config();

$request_method_name = get_method_name();

$error = "";

function errorHandler($errno, $errstr, $errfile, $errline) {
	global $error;

	// ignore notices...
	if($errno == 8) return;

	$trace = '';
	$e = new Exception("($errfile:$errline) $errstr", $errno);
	foreach ($e->getTrace() AS $entry)
	{
		$function = (isset($entry['class']) ? $entry['class'] . $entry['type'] : '') . $entry['function'];
		$file = '';
		if(isset($entry['file']))
			$file = $entry['file'];
		$trace .= "\t<li><b class=\"function\">" . htmlspecialchars($function) . "()</b>" . (isset($entry['file']) && isset($entry['line']) ? ' <span class="shade">in</span> <b class="file">' . $file . "</b> <span class=\"shade\">at line</span> <b class=\"line\">$entry[line]</b>" : '') . "</li>\n";
	}


	$error = "<p>An exception ($errno) occurred: $errstr in $errfile on line $errline</p><ol>$trace</ol>";

	//$error = "($errfile:$errline) $errstr - $errno";
	//XenForo_Error::getExceptionTrace(new Exception("($errfile:$errline) $errstr", $errno));
}
set_error_handler('errorHandler');

echo "<pre>";


if ($request_method_name && isset($server_param[$request_method_name]))
{
	if (strpos($request_method_name, 'm_') === 0)
		require('./include/moderation.php');
	else
		if(file_exists('./include/'.$request_method_name.'.php'))
			include('./include/'.$request_method_name.'.php');
}


if(MOBIQUO_DEBUG){

	if(!empty($_GET['dbg'])){

		require('./include/get_user_reply_post.php');
		try {
			var_dump(get_user_reply_post_func(new xmlrpcval(array(
				new xmlrpcval("Darkimmortal", "string"),
				//new xmlrpcval("test", "string"),
				//new xmlrpcval("64", "int"),
				//new xmlrpcval("2", "int"),
				//new xmlrpcval("10", "int"),
				//new xmlrpcval("25", "int"),
				//new xmlrpcval("TOP", "string"),
				//new xmlrpcval("1", "int"),
			), "array")));
		} catch (Exception $e){
			echo xmlresperror("Server error occurred [{$e->getMessage()}; darkxmlrpcs]");
			exit;
		}

	} elseif(!empty($_GET['test'])){

		$func = $_GET['test'];
		if(!array_key_exists($func, $server_param)){
			$func = 'all';
		}

		if($func == 'all'){
			$functions = array_keys($server_param);
			foreach($functions as $function){
				testApiFunction($function, empty($_GET['silent']), true);
			}
		} else {
			testApiFunction($func, false);
		}
		exit;

	}
}


$rpcServer = new Tapatalk_xmlrpcs($server_param, false);
$rpcServer->setDebug(MOBIQUO_DEBUG ? 3 : 1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';


if(!empty($_POST['method_name'])){
	$xml = new xmlrpcmsg($_POST['method_name']);
	$request = $xml->serialize();
	$response = $rpcServer->service($request);
} else {
	$response = $rpcServer->service();
}

/*
if(MOBIQUO_DEBUG){
	$f= fopen('debug.log', 'a');
	fwrite($f, date("Y-m-d H:i:s")."\r\n".$rpcServer->debug_info."\r\n".print_r($response, true)."\r\n".print_r($_REQUEST, true)."\r\n----------------------===========================----------------------\r\n\r\n");
	fclose($f);
}*/

echo "</pre>";


function testApiFunction($func, $silent=true, $all=false){
	global $server_param, $error;
	echo "Testing $func... ";
	$time = microtime(true);
	if(/*substr($func, 0, 2) == 'm_' || */in_array($func, array('remove_attachment', 'upload_avatar', 'delete_conversation'))){
		echo "Skipped.\r\n";
		return;
	}
	$resp = false;
	$pass = 0;
	if(substr($func, 0, 2) == 'm_')
		require_once "include/moderation.php";
	else
		require_once "include/".str_replace("_func", "", $server_param[$func]['function']).".php";
	try {
		ob_start();
		$error = "";

		switch($func){
			case "authorize_user";
				$resp = authorize_user_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
					new xmlrpcval("9989", "string"),
				), "array"));
				break;

			case "get_board_stat";
				$resp = get_board_stat_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_box";
				$resp = get_box_func(new xmlrpcval(array(
					new xmlrpcval("0", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "get_box_info";
				$resp = get_box_info_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_config";
				$resp = get_config_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_conversation";
				$resp = get_conversation_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "get_conversations";
				$resp = get_conversations_func(new xmlrpcval(array(
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "m_stick_topic":
				$resp = m_stick_topic_func(new xmlrpcval(array(
					new xmlrpcval("2", "int"),
					new xmlrpcval("2", "int"),
				), "array"));
				break;

			case "m_ban_user":
				$resp = m_ban_user_func(new xmlrpcval(array(
					new xmlrpcval("another user", "string"),
					new xmlrpcval("1", "int"),
					new xmlrpcval("testing ban...", "string"),
				), "array"));
				break;

			case "get_dashboard";
				$resp = get_dashboard_func(new xmlrpcval(array(
					new xmlrpcval(false, "boolean"),
				), "array"));
				break;

			case "get_forum";
				$resp = get_forum_func(new xmlrpcval(array(
				), "array"));
				break;

			case "upload_attach";
				$resp = upload_attach_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("", "string"),
					new xmlrpcval("aaaa", "string"),
				), "array"));
				break;

			case "create_message";
				$resp = create_message_func(new xmlrpcval(array(
					new xmlrpcval(array(new xmlrpcval("Darkimmortal ", "string"), new xmlrpcval("another user", "string")), "array"),
					new xmlrpcval("test pm #".mt_rand(0,99999), "string"),
					new xmlrpcval("UNIT TEST PM #".mt_rand(0,99999)." [b]lol [i]test[/i][/b]", "string"),
					new xmlrpcval(1, "int"),
					new xmlrpcval("0", "string"),
				), "array"));
				break;

			case "get_box";
				$resp = get_box_func(new xmlrpcval(array(
					new xmlrpcval("1", "string"),
					new xmlrpcval("0", "string"),
					new xmlrpcval("25", "string"),
				), "array"));
				break;

			case "get_message";
				$resp = get_message_func(new xmlrpcval(array(
					new xmlrpcval("5", "string"),
					new xmlrpcval("1", "string"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "get_quote_pm";
				$resp = get_quote_pm_func(new xmlrpcval(array(
					new xmlrpcval("5", "string"),
				), "array"));
				break;

			case "delete_message";
				$resp = delete_message_func(new xmlrpcval(array(
					new xmlrpcval("8", "string"),
					new xmlrpcval("1", "string"),
				), "array"));
				break;

			case "mark_pm_unread";
				$resp = mark_pm_unread_func(new xmlrpcval(array(
					new xmlrpcval("5", "string"),
					new xmlrpcval("1", "string"),
				), "array"));
				break;

			case "get_box";
				$resp = get_box_info_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_id_by_url";
				$resp = get_id_by_url_func(new xmlrpcval(array(
					new xmlrpcval("http://kiririn/mybb/showthread.php?tid=3&pid=20#pid20", "string"),
				), "array"));
				break;

			case "get_inbox_stat";
				$resp = get_inbox_stat_func(new xmlrpcval(array(
					new xmlrpcval("0", "string"),
					new xmlrpcval("0", "string"),
				), "array"));
				break;

			case "get_latest_topic";
				$resp = get_latest_topic_func(new xmlrpcval(array(
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "get_new_topic";
				$resp = get_new_topic_func(new xmlrpcval(array(
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "get_online_users";
				$resp = get_online_users_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_participated_topic";
				$resp = get_participated_topic_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("10", "int"),
				), "array"));
				break;

			case "get_quote_conversation";
				$resp = get_quote_conversation_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("2", "string"),
				), "array"));
				break;

			case "get_quote_post";
				$resp = get_quote_post_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
				), "array"));
				break;

			case "get_raw_post";
				$resp = get_raw_post_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
				), "array"));
				break;

			case "get_subscribed_forum";
				$resp = get_subscribed_forum_func(new xmlrpcval(array(
				), "array"));
				break;

			case "get_subscribed_topic";
				$resp = get_subscribed_topic_func(new xmlrpcval(array(
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
				), "array"));
				break;

			case "get_thread";
				$resp = get_thread_func(new xmlrpcval(array(
					new xmlrpcval("5", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "get_thread_by_post";
				$resp = get_thread_by_post_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("20", "int"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "get_thread_by_unread";
				$resp = get_thread_by_unread_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("20", "int"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "get_topic";
				$resp = get_topic_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
					new xmlrpcval("", "string"),
				), "array"));
				break;

			case "get_unread_topic";
				$resp = get_unread_topic_func(new xmlrpcval(array(
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
					new xmlrpcval(array(
						"not_in" => new xmlrpcval(array(), "array"),
						"only_in" => new xmlrpcval(array(new xmlrpcval("2", "string")), "array"),
					), "struct"),
				), "array"));
				break;

			case "get_user_info";
				$resp = get_user_info_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
				), "array"));
				break;

			case "get_user_reply_post";
				$resp = get_user_reply_post_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
				), "array"));
				break;

			case "get_user_topic";
				$resp = get_user_topic_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
				), "array"));
				break;

			case "invite_participant";
				$resp = invite_participant_func(new xmlrpcval(array(
					new xmlrpcval(array(new xmlrpcval("another user", "string")), "array"),
					new xmlrpcval("2", "string"),
				), "array"));
				break;

			case "like_post";
				$resp = like_post_func(new xmlrpcval(array(
					new xmlrpcval("39", "string"),
				), "array"));
				break;

			case "login";
				$resp = login_func(new xmlrpcval(array(
					new xmlrpcval("Darkimmortal", "string"),
					new xmlrpcval("9989", "string"),
				), "array"));
				break;

			case "login_forum";
				$resp = login_forum_func(new xmlrpcval(array(
					new xmlrpcval("5", "int"),
					new xmlrpcval("1", "string"),
				), "array"));
				break;

			case "logout_user";
				if(!$all)
					$resp = logout_user_func(new xmlrpcval(array(
					), "array"));
				break;

			case "mark_all_as_read";
				$resp = mark_all_as_read_func(new xmlrpcval(array(
				), "array"));
				break;

			case "new_conversation";
				$resp = new_conversation_func(new xmlrpcval(array(
					new xmlrpcval(array(new xmlrpcval("another user", "string")), "array"),
					new xmlrpcval("unit test conversation", "string"),
					new xmlrpcval("[b]lol [i]test[/i][/b]", "string"),
				), "array"));
				break;

			case "new_topic";
				$resp = new_topic_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("unit test topic #".mt_rand(0,99999), "string"),
					new xmlrpcval("[b]lol [i]test[/i][/b]", "string"),
					new xmlrpcval("", "string"),
					new xmlrpcval(array(), "array"),
					new xmlrpcval("", "string"),
				), "array"));
				break;

			case "reply_conversation";
				$resp = reply_conversation_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("UNIT TEST REPLY [b]lol [i]test[/i][/b]", "string"),
				), "array"));
				break;

			case "reply_post";
				$resp = reply_post_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("2", "string"),
					new xmlrpcval("unit test reply #".mt_rand(0,99999), "string"),
					new xmlrpcval("UNIT TEST REPLY #".mt_rand(0,99999)." [b]lol [i]test[/i][/b]", "string"),
					new xmlrpcval(array(), "array"),
					new xmlrpcval("", "string"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "reply_topic";
				$resp = reply_topic_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("2", "string"),
					new xmlrpcval("unit test reply #".mt_rand(0,99999), "string"),
					new xmlrpcval("UNIT TEST REPLY #".mt_rand(0,99999)." [b]lol [i]test[/i][/b]", "string"),
					new xmlrpcval(array(), "array"),
					new xmlrpcval("", "string"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "report_pm";
				$resp = report_pm_func(new xmlrpcval(array(
				), "array"));
				break;

			case "report_post";
				$resp = report_post_func(new xmlrpcval(array(
					new xmlrpcval("2", "string"),
					new xmlrpcval("unit test report reason #".mt_rand(0,99999), "string"),
				), "array"));
				break;

			case "save_raw_post";
				$resp = save_raw_post_func(new xmlrpcval(array(
					new xmlrpcval("14", "string"),
					new xmlrpcval("unit test edit title #".mt_rand(0,99999), "string"),
					new xmlrpcval("[color=red]unit test edit #".mt_rand(0,99999)."[/color] :cool:", "string"),
					new xmlrpcval(true, "boolean"),
				), "array"));
				break;

			case "search_post";
				$resp = search_post_func(new xmlrpcval(array(
					new xmlrpcval("test", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
					new xmlrpcval("", "string"),
				), "array"));
				break;

			case "search_topic";
				$resp = search_topic_func(new xmlrpcval(array(
					new xmlrpcval("test", "string"),
					new xmlrpcval("0", "int"),
					new xmlrpcval("25", "int"),
					new xmlrpcval("", "string"),
				), "array"));
				break;

			case "subscribe_forum";
				$resp = subscribe_forum_func(new xmlrpcval(array(
				), "array"));
				break;

			case "subscribe_topic";
				$resp = subscribe_topic_func(new xmlrpcval(array(
					new xmlrpcval("23", "string"),
				), "array"));
				break;

			case "unlike_post";
				$resp = unlike_post_func(new xmlrpcval(array(
					new xmlrpcval("39", "string"),
				), "array"));
				break;

			case "unsubscribe_forum";
				$resp = unsubscribe_forum_func(new xmlrpcval(array(
				), "array"));
				break;

			case "unsubscribe_topic";
				$resp = unsubscribe_topic_func(new xmlrpcval(array(
					new xmlrpcval("23", "string"),
				), "array"));
				break;

			default:
				echo "Unknown function/no unit test implemented yet.";
				$pass = 2;
		}
		//$ob = ob_get_clean();

		if($pass < 2)
			$pass = 1;

		if(strlen($error) > 0)
			$pass = -1;

	}
	catch (Exception $e){
		$pass = 0;
		echo "*FAILED*:\r\n";
		throw $e;
		echo "\r\n";
	}
	if($pass === 1){
		echo "Passed! (";
		echo round(microtime(true) - $time, 4);
		echo ")";
	}

	if(($pass < 1 && $resp !== false && !$silent) || !$all ){
		echo "\r\n";
		var_dump($resp);
		//var_dump($ob);
	}

	if($pass == -1)
		echo "*FAILED*: ";

	echo $error;

	echo "\r\n";
}
