<?php

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('error', 'tapatalk_error');
$plugins->add_hook('redirect', 'tapatalk_redirect');
$plugins->add_hook('global_start', 'tapatalk_global_start');
$plugins->add_hook('fetch_wol_activity_end', 'tapatalk_fetch_wol_activity_end');
$plugins->add_hook('build_friendly_wol_location_end', 'tapatalk_build_friendly_wol_location_end');
$plugins->add_hook('pre_output_page', 'tapatalk_pre_output_page');

// hook for push
$plugins->add_hook('newreply_do_newreply_end', 'tapatalk_push_reply');
$plugins->add_hook('private_do_send_end', 'tapatalk_push_pm');

function tapatalk_info()
{
    /**
     * Array of information about the plugin.
     * name: The name of the plugin
     * description: Description of what the plugin does
     * website: The website the plugin is maintained at (Optional)
     * author: The name of the author of the plugin
     * authorsite: The URL to the website of the author (Optional)
     * version: The version number of the plugin
     * guid: Unique ID issued by the MyBB Mods site for version checking
     * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
     */
    return array(
        "name"          => "Tapatalk",
        "description"   => "Tapatalk MyBB Plugin",
        "website"       => "http://tapatalk.com",
        "author"        => "Quoord Systems Limited",
        "authorsite"    => "http://tapatalk.com",
        "version"       => "3.1.0",
        "guid"          => "e7695283efec9a38b54d8656710bf92e",
        "compatibility" => "16*"
    );
}

function tapatalk_install()
{
    global $db;
    
    tapatalk_uninstall();
    
    if(!$db->table_exists('tapatalk_users'))
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "tapatalk_users (
              userid int(10) NOT NULL,
              announcement smallint(5) NOT NULL DEFAULT '1',
              pm smallint(5) NOT NULL DEFAULT '1',
              subscribe smallint(5) NOT NULL DEFAULT '1',
              updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (userid)
            )
        ");
    }
    if(!$db->table_exists("tapatalk_push_data"))
    {
    	$db->query("
    		CREATE TABLE " . TABLE_PREFIX . "tapatalk_push_data (
			  push_id int(10) NOT NULL AUTO_INCREMENT,
			  author varchar(100) NOT NULL,
			  user_id int(10) NOT NULL DEFAULT '0',
			  data_type char(20) NOT NULL DEFAULT '',
			  title varchar(200) NOT NULL DEFAULT '',
			  data_id int(10) NOT NULL DEFAULT '0',
			  create_time int(11) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (push_id),
			  KEY user_id (user_id)
			)
    	");
    }
    // Insert settings in to the database
    $query = $db->query("SELECT disporder FROM ".TABLE_PREFIX."settinggroups ORDER BY `disporder` DESC LIMIT 1");
    $disporder = $db->fetch_field($query, 'disporder')+1;

    $setting_group = array(
        'name'          =>    'tapatalk',
        'title'         =>    'Tapatalk Options',
        'description'   =>    'Tapatalk enables your forum to be accessed by the Tapatalk app',
        'disporder'     =>    0,
        'isdefault'     =>    0
    );
    $db->insert_query('settinggroups', $setting_group);
    $gid = $db->insert_id();

    $settings = array(
        'enable' => array(
            'title'         => 'Enable/Disable',
            'description'   => 'Enable/Disable the Tapatalk',
            'optionscode'   => 'onoff',
            'value'         => '1'
        ),
        'chrome_notifier' => array(
            'title'         => 'Enable Tapatalk Notifier in Chrome',
            'description'   => "Users of your forum on Chome will be notified with 'Tapatalk Notifier'. Tapatalk Notifier for Chrome is a web browser extension that notify you with a small alert when you received a new Private Message from your forum members.",
            'optionscode'   => 'onoff',
            'value'         => '1'
        ),
        'hide_forum' => array(
            'title'         => 'Hide Forums',
            'description'   => "Hide forum you don't want them to be listed in Tapatalk app with its ID. Separate multiple entries with a coma",
            'optionscode'   => 'text',
            'value'         => ''
        ),
        'reg_url' => array(
            'title'         => 'Register page url',
            'description'   => "Set the forum register page url here for tapatalk app based on forum url. Normally it should be the default 'member.php?action=register'",
            'optionscode'   => 'text',
            'value'         => 'member.php?action=register'
        ),
        'directory' => array(
            'title'         => 'Tapatalk plugin directory',
            'description'   => 'Never change it if you did not rename the Tapatalk plugin directory. And the default value is \'mobiquo\'. If you renamed the Tapatalk plugin directory, you also need to update the same setting for this forum in tapatalk forum owner area.£¨http://tapatalk.com/forum_owner.php£©',
            'optionscode'   => 'text',
            'value'         => 'mobiquo'
        ),
        'push' => array(
            'title'         => 'Enable Tapatalk Push Notification',
            'description'   => 'Tapatalk users on your forum can get instant notification with new reply of subscribed topic and new pm if this setting was enabled.',
            'optionscode'   => 'onoff',
            'value'         => '1'
        ),
        'datakeep' => array(
            'title'         => 'Keep Data When Uninsall',
            'description'   => "Tapatalk users records and push options will be kept in table 'tapatalk_users'. Please keep the data if you'll reinstall tapatalk later.",
            'optionscode'   => "radio\nkeep=Keep Data\ndelete=Delete all data and table",
            'value'         => 'keep'
        ),
        'push_key' => array(
        	'title'         => 'Tapatalk push key',
        	'description'   => 'A push_key to verify your forum push certification, you can fill here with the push key you registered in Tapatalk.com. This is not mandatory but if you enter this key, it will make push feature perfect .',
        	'optionscode'   => 'text',
            'value'         => ''
        ),
    );

    $s_index = 0;
    foreach($settings as $name => $setting)
    {
        $s_index++;
        $insert_settings = array(
            'name'        => $db->escape_string('tapatalk_'.$name),
            'title'       => $db->escape_string($setting['title']),
            'description' => $db->escape_string($setting['description']),
            'optionscode' => $db->escape_string($setting['optionscode']),
            'value'       => $db->escape_string($setting['value']),
            'disporder'   => $s_index,
            'gid'         => $gid,
            'isdefault'   => 0
        );
        $db->insert_query('settings', $insert_settings);
    }
    rebuild_settings();
}

function tapatalk_is_installed()
{
    global $mybb, $db;

    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk'", array('limit' => 1));
    $group = $db->fetch_array($result);

    return !empty($group['gid']) && $db->table_exists('tapatalk_users');
}

function tapatalk_uninstall()
{
    global $mybb, $db;

    if($mybb->settings['tapatalk_datakeep'] == 'delete')
    {
        if($db->table_exists('tapatalk_users'))
        {
            $db->drop_table('tapatalk_users');
        }
    	if($db->table_exists('tapatalk_push_data'))
        {
            $db->drop_table('tapatalk_push_data');
        }
    }

    // Remove settings
    $result = $db->simple_select('settinggroups', 'gid', "name = 'tapatalk'", array('limit' => 1));
    $group = $db->fetch_array($result);

    if(!empty($group['gid']))
    {
        $db->delete_query('settinggroups', "gid='{$group['gid']}'");
        $db->delete_query('settings', "gid='{$group['gid']}'");
        rebuild_settings();
    }
}
/*
function tapatalk_activate()
{
    global $mybb, $db;

}

function tapatalk_deactivate()
{
    global $db;
}
*/
/* ============================================================================================ */

function tapatalk_error($error)
{
    if(defined('IN_MOBIQUO'))
    {
        global $lang, $include_topic_num, $search, $function_file_name;

        if ($error == $lang->error_nosearchresults)
        {
            if ($include_topic_num) {
                if($search['resulttype'] != 'posts') {
                    $response = new xmlrpcresp(new xmlrpcval(array(
                        'result'            => new xmlrpcval(true, 'boolean'),
                        'total_topic_num'   => new xmlrpcval(0, 'int'),
                        'topics'            => new xmlrpcval(array(), 'array'),
                    ), 'struct'));
                } else {
                    $response = new xmlrpcresp(new xmlrpcval(array(
                        'result'            => new xmlrpcval(true, 'boolean'),
                        'total_post_num'    => new xmlrpcval(0, 'int'),
                        'posts'             => new xmlrpcval(array(), 'array'),
                    ), 'struct'));
                }
            } else {
                $response = new xmlrpcresp(new xmlrpcval(array(), 'array'));
            }
        }
        else if ($function_file_name == 'thankyoulike' && strpos($error, $lang->tyl_redirect_back))
        {
            $response = new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(true, 'boolean'),
            ), 'struct'));
        }
        else
        {
            $response = new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(trim(strip_tags($error)), 'base64'),
            ), 'struct'));
        }

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
        exit;
    }
}

function tapatalk_redirect($args)
{
    tapatalk_error($args['message']);
}

function tapatalk_global_start()
{
    global $mybb, $request_method, $function_file_name;

    header('Mobiquo_is_login: ' . ($mybb->user['uid'] > 0 ? 'true' : 'false'));

    if ($mybb->usergroup['canview'] != 1 && in_array($request_method, array('get_config', 'login')))
    {
        define("ALLOWABLE_PAGE", 1);
    }

    if (isset($mybb->settings['no_proxy_global']))
    {
        $mybb->settings['no_proxy_global'] = 0;
    }

    if ($function_file_name == 'thankyoulike')
    {
        $mybb->input['my_post_key'] = md5($mybb->user['loginkey'].$mybb->user['salt'].$mybb->user['regdate']);
    }
}

function tapatalk_fetch_wol_activity_end(&$user_activity)
{
    if($user_activity['activity'] == 'unknown' && strpos($user_activity['location'], 'mobiquo') !== false)
    {
        $user_activity['activity'] = 'tapatalk';
    }
}

function tapatalk_build_friendly_wol_location_end($plugin_array)
{
    if($plugin_array['user_activity']['activity'] == 'tapatalk')
    {
        $plugin_array['location_name'] = 'via Tapatalk';
    }
}

function tapatalk_pre_output_page(&$page)
{
    global $mybb;
    
    $tapatalk_detect_js_name = $mybb->settings['tapatalk_chrome_notifier'] == 1 ? 'tapatalkdetect.js' : 'tapatalkdetect-nochrome.js';
    
    $page = str_ireplace("</head>", "<script type='text/javascript' src='{$mybb->settings['bburl']}/{$mybb->settings['tapatalk_directory']}/{$tapatalk_detect_js_name}'></script></head>", $page);
}

// push related functions
function tapatalk_push_reply()
{
    global $mybb, $db, $tid, $pid, $visible, $thread;
    
    if ($tid && $pid && $visible == 1 && $mybb->settings['tapatalk_push'] && $db->table_exists('tapatalk_users'))
    {
        $query = $db->query("
            SELECT ts.uid
            FROM ".TABLE_PREFIX."threadsubscriptions ts
            LEFT JOIN ".TABLE_PREFIX."tapatalk_users tu ON (ts.uid=tu.userid)
            WHERE ts.tid = '$tid' AND tu.subscribe=1
        ");
        
        $ttp_data = array();
        while($user = $db->fetch_array($query))
        {
            if ($user['uid'] == $mybb->user['uid']) continue;
            
            $ttp_data[] = array(
                'userid'    => $user['uid'],
                'type'      => 'sub',
                'id'        => $tid,
                'subid'     => $pid,
                'title'     => tt_push_clean($thread['subject']),
                'author'    => tt_push_clean($mybb->user['username']),
                'dateline'  => TIME_NOW,
            );
            tt_insert_push_data($ttp_data[count($ttp_data)-1]);
        }
        
        $ttp_post_data = array(
            'url'  => $mybb->settings['bburl'],
            'data' => base64_encode(serialize($ttp_data)),
        );
        
        $return_status = tt_do_post_request($ttp_post_data);
    }
}

function tapatalk_push_pm()
{
    global $mybb, $db, $pm, $pminfo;
    
    if ($pminfo['messagesent'] && $mybb->settings['tapatalk_push'] && $db->table_exists('tapatalk_users'))
    {
        $query = $db->query("
            SELECT p.pmid, p.toid
            FROM ".TABLE_PREFIX."privatemessages p
            LEFT JOIN ".TABLE_PREFIX."tapatalk_users tu ON (p.toid=tu.userid)
            WHERE p.fromid = '{$mybb->user['uid']}' and p.dateline = " . TIME_NOW . " AND p.folder = 1 AND tu.pm=1
        ");
        
        $ttp_data = array();
        while($user = $db->fetch_array($query))
        {
            if ($user['toid'] == $mybb->user['uid']) continue;
            
            $ttp_data[] = array(
                'userid'    => $user['toid'],
                'type'      => 'pm',
                'id'        => $user['pmid'],
                'title'     => tt_push_clean($pm['subject']),
                'author'    => tt_push_clean($mybb->user['username']),
                'dateline'  => TIME_NOW,
            );
            tt_insert_push_data($ttp_data[count($ttp_data)-1]);
        }
        $ttp_post_data = array(
            'url'  => $mybb->settings['bburl'],
            'data' => base64_encode(serialize($ttp_data)),
        );
        
        $return_status = tt_do_post_request($ttp_post_data);
    }
}

function tt_do_post_request($data,$pushTest = false)
{
	global $mybb;
	if(!empty($mybb->settings['tapatalk_push_key']) && !$pushTest)
	{
		$data['key'] = $mybb->settings['tapatalk_push_key'];
	}
	$push_url = 'http://push.tapatalk.com/push.php';
    $push_host = 'push.tapatalk.com';
    $response = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';

    if (@ini_get('allow_url_fopen'))
    {
        if(!$pushTest)
        {
            $fp = fsockopen($push_host, 80, $errno, $errstr, 5);
            
            if(!$fp)
                return false;
                
            $data =  http_build_query($data,'', '&');
            fputs($fp, "POST /push.php HTTP/1.1\r\n");
            fputs($fp, "Host: $push_host\r\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($fp, "Content-length: ". strlen($data) ."\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $data);
            fclose($fp);
        }
        else
        {
            $params = array('http' => array(
                'method' => 'POST',
                'content' => http_build_query($data, '', '&'),
            ));

            $ctx = stream_context_create($params);
            $timeout = 10;
            $old = ini_set('default_socket_timeout', $timeout);
            $fp = @fopen($push_url, 'rb', false, $ctx);

            if (!$fp) return false;

            ini_set('default_socket_timeout', $old);
            stream_set_timeout($fp, $timeout);
            stream_set_blocking($fp, 0); 
            

            $response = @stream_get_contents($fp);
        }
    }
    elseif (function_exists('curl_init'))
    {
        $ch = curl_init($push_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        $response = curl_exec($ch);
        curl_close($ch);
    }
    
	return $response;
}

function tt_insert_push_data($data)
{
	global $mybb,$db;
	if(!$db->table_exists("tapatalk_push_data"))
	{
		return ;
	}
	$sql_data = array(
        'author' => $data['author'],
		'user_id' => $data['userid'],
		'data_type' => $data['type'],
		'title' => $data['title'],
		'data_id' => $data['subid'],
		'create_time' => $data['dateline']		
    );
	$db->insert_query('tapatalk_push_data', $sql_data);
}
function tt_push_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}