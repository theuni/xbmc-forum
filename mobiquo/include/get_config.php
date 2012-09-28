<?php

defined('IN_MOBIQUO') or exit;

function get_config_func()
{
    global $mobiquo_config, $mybb, $cache;
    
    $config_list = array(
        'sys_version'   => new xmlrpcval($mybb->version, 'string'),
        'version'       => new xmlrpcval($mobiquo_config['version'], 'string'),
        'is_open'       => new xmlrpcval(isset($cache->cache['plugins']['active']['tapatalk']) && $mybb->settings['tapatalk_enable'], 'boolean'),
        'guest_okay'    => new xmlrpcval($mybb->usergroup['canview'] && $mybb->settings['boardclosed'] == 0, 'boolean'),
    );
    
    if ($mybb->settings['boardclosed'])
    {
        $config_list['result_text'] = new xmlrpcval(basic_clean($mybb->settings['boardclosed_reason']), 'base64');
    }
    
    if ($mybb->settings['tapatalk_push'])
    {
        $config_list['push'] = new xmlrpcval(1, 'string');
    }
    
    if ($mybb->settings['tapatalk_reg_url'])
    {
        $config_list['reg_url'] = new xmlrpcval(basic_clean($mybb->settings['tapatalk_reg_url']), 'string');
    }
    
    
    foreach($mobiquo_config as $key => $value){
        if(!array_key_exists($key, $config_list) && $key != 'thlprefix'){
            $config_list[$key] = new xmlrpcval($value, 'string');
        }
    }

    if (!$mybb->user['uid'])
    {
        if($mybb->usergroup['cansearch']) {
            $config_list['guest_search'] = new xmlrpcval('1', 'string');
        }
        
        if($mybb->usergroup['canviewonline']) {
            $config_list['guest_whosonline'] = new xmlrpcval('1', 'string');
        }
    }
    
    if($mybb->settings['minsearchword'] < 1)
    {
        $mybb->settings['minsearchword'] = 3;
    }
    
    $config_list['min_search_length'] = new xmlrpcval(intval($mybb->settings['minsearchword']), 'int');
    
    $response = new xmlrpcval($config_list, 'struct');
    
    return new xmlrpcresp($response);
}

