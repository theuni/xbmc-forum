<?php

$startTime = microtime(true);

define('IN_MOBIQUO', true);
define('MOBIQUO_DEBUG', false);
define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');

require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
require_once './xmlrpcs.php';

require_once './server_define.php';
require_once './mobiquo_common.php';
require_once './input.php';

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'mobiquo/mobiquo.php');

$mobiquo_config = get_mobiquo_config();
$request_method_name = get_method_name();

chdir('../');
require_once './global.php';
chdir('./mobiquo/');
require_once './parser.php';

$errorReporting = ini_get('error_reporting') &~ 8096;
@error_reporting($errorReporting);
@ini_set('error_reporting', $errorReporting);
// Hide errors from normal display - will be cleanly output via shutdown function. 
ini_set('display_errors', 0);

restore_error_handler();

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

if ($request_method_name && isset($server_param[$request_method_name]))
{
	header('Mobiquo_is_login: ' . ($mybb->user['uid'] > 0 ? 'true' : 'false'));
	if (substr($request_method_name, 0, 2) == 'm_')
		include('./include/moderation.php');
	else
		if(file_exists('./include/'.$request_method_name.'.php'))
			include('./include/'.$request_method_name.'.php');
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
