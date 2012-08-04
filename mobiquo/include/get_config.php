<?php

defined('IN_MOBIQUO') or exit;

function get_config_func()
{
	global $mobiquo_config, $mybb, $cache;
	
	$config_list = array(
		'sys_version'   => new xmlrpcval($mybb->version, 'string'),
		'version'       => new xmlrpcval($mobiquo_config['version'], 'string'),
		'is_open'       => new xmlrpcval(isset($cache->cache['plugins']['active']['tapatalk']) && $mybb->settings['boardclosed_original'] == 0, 'boolean'),
		'guest_okay'    => new xmlrpcval($mobiquo_config['guest_okay'] && $mybb->usergroup['canview'] != 0, 'boolean'),
		'api_level'      => new xmlrpcval($mobiquo_config['api_level'], 'string'),
		'max_attachment' => new xmlrpcval($mobiquo_config['max_attachment'], "int"),
	);
	
	foreach($mobiquo_config as $key => $value){
		if(!array_key_exists($key, $config_list)){
			$config_list[$key] = new xmlrpcval($value, 'string');
		}
	}

	$response = new xmlrpcval($config_list, 'struct');
	
	return new xmlrpcresp($response);
}

