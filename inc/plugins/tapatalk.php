<?php
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("error", "tapatalk_error");
$plugins->add_hook("global_start", "tapatalk_global_start");
$plugins->add_hook("fetch_wol_activity_end", "tapatalk_fetch_wol_activity_end");
$plugins->add_hook("build_friendly_wol_location_end", "tapatalk_build_friendly_wol_location_end");
#$plugins->add_hook('pre_output_page','tapatalk_pre_output_page');

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
		"version"       => "2.0.0",
		"guid"          => "",
		"compatibility" => "14*,16*"
	);
}

function tapatalk_error(&$error)
{
	if(defined('IN_MOBIQUO')){
		$error = strip_tags($error);
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror($error)->serialize('UTF-8'));
		exit;
	}
}

function tapatalk_global_start()
{
    global $mybb, $request_method_name;
    
    $mybb->settings['boardclosed_original'] = $mybb->settings['boardclosed'];
    
    if (in_array($request_method_name, array('get_config', 'login')))
    {
        if ($mybb->settings['boardclosed'] == 1)
        {
            $mybb->settings['boardclosed'] = 0;
        }
        
        if($mybb->usergroup['canview'] != 1)
        {
            define("ALLOWABLE_PAGE", 1);
        }
    }
}

function tapatalk_fetch_wol_activity_end(&$user_activity){
	if($user_activity['activity'] == 'unknown' && strpos($user_activity['location'], 'mobiquo') !== false){
		$user_activity['activity'] = 'tapatalk';
	}
}

function tapatalk_build_friendly_wol_location_end($plugin_array){
	if($plugin_array['user_activity']['activity'] == 'tapatalk'){
		$plugin_array['location_name'] = 'In Tapatalk';
	}
}

function tapatalk_pre_output_page(&$page){
	global $mybb;
	$page = str_ireplace("</head>", "<script type='text/javascript' src='{$mybb->settings['bburl']}/mobiquo/tapatalkdetect.js'></script></head>", $page);
}
