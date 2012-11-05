<?php
define('IN_MOBIQUO', true);
define('MOBIQUO_DEBUG', 0);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);
define('TT_PATH', basename(TT_ROOT));
@ob_start();
error_reporting(MOBIQUO_DEBUG);

require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
require_once './xmlrpcs.php';
require_once './server_define.php';
require_once './mobiquo_common.php';
require_once './input.php';
require_once './xmlrpcresp.php';
require_once './env_setting.php';
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	require 'web.php';
	exit;
}
$rpcServer = new Tapatalk_xmlrpcs($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';

if(!empty($_POST['method_name'])){
    $xml = new xmlrpcmsg($_POST['method_name']);
    $request = $xml->serialize();
    $response = $rpcServer->service($request);
} else {
    $response = $rpcServer->service();
}

exit;