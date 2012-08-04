<?php
/**
 * Copyright 2012 Team XBMC, All Rights Reserved
 *
 * Website: http://xbmc.org
 * Author: da-anda
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// register hooks
$plugins->add_hook("global_start", "xbmc_InitializeStart");
$plugins->add_hook("pre_output_page", "xbmc_AddToFooter");
$plugins->add_hook("private_send_end", "xbmc_SendPrivateMessageFormEnd");
$plugins->add_hook("postbit", "xbmc_RenderPost");
$plugins->add_hook("pre_output_page", "xbmc_PreOutputPage");
$plugins->add_hook("forumdisplay_start", "xbmc_ForumDisplayStart");

$plugins->add_hook("showteam_start", "xbmc_DenyAccessToSectionIfNoValidUser");
$plugins->add_hook("memberlist_start", "xbmc_DenyAccessToSectionIfNoValidUser");

$plugins->add_hook("text_parse_message", "xbmc_ParseMessage");



/**
 * Returns the meta information about this plugin
 *
 * @return array
 */
function xbmc_info()
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
		"name"			=> "XBMC addons",
		"description"	=> "This plugins contains XBMC specific changes and features for this forum",
		"website"		=> "http://xbmc.org",
		"author"			=> "Team XBMC",
		"authorsite"	=> "http://xbmc.or",
		"version"		=> "0.1beta",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function hello_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function hello_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function hello_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function hello_deactivate()
 * {
 * }
 */


function xbmc_InitializeStart() {
	global $mybb, $templates, $templatelist, $lang;

	// initialize xbmc namespace
	$mybb->xbmc = array();
	$mybb->xbmc['isLoginUser'] = $mybb->user['uid'] != 0 ? TRUE : FALSE;

	// load XBMC labels
	$lang->load('xbmc');

	// set debug mode for templates during development
	$mybb->dev_mode = 0;

	$mybb->settings['xbmc_url'] = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	$mybb->settings['xbmc_referer'] = (strpos($_SERVER['HTTP_REFERER'], $mybb->settings['bburl']) ? $_SERVER['HTTP_REFERER'] : '');

	// override the template loader with our custom implementation
	require_once(MYBB_ROOT . 'xbmc/xclass/class_templates.php');
	$templates = new xbmc_templates;
	$templates->setTemplateFile(MYBB_ROOT . 'xbmc/theme/xbmc_theme.xml');
	$templates->setTheme(3);
	$templates->setTemplateSet(2);
	
	// add custom templates that should be globally available
	$templatelist .= (strlen($templatelist) ? ',' : '') . 'headerbit_links_' . ($mybb->xbmc['isLoginUser'] ? 'user' : 'guest');
}

function xbmc_ForumDisplayStart() {
	xbmc_OverrideLanguage();
}

function xbmc_RenderPost($post) {
	global $thread;

	// if subject is empty, copy it from the thread
	if (!$post['subject'] && $post['icon']) {
		$post['subject'] = $thread['subject'];
	} else if (!$post['icon'] && $post['subject']) {
		$post['subject'] = '';	
	}
	if ($post['fid2']) $post['fid2'] = 'Location: ' . $post['fid2'];
	return $post;
}

function xbmc_SendPrivateMessageFormEnd() {
	global $options, $optionschecked;
	// convert the "checked=checked" flag to a boolean value in order to be used as hidden field in the templates
	$options['readreceipt'] = $optionschecked['readreceipt'] ? 1 : 0;
}


/**
 * This method allows to manipulate the page output
 */
function xbmc_PreOutputPage($content) {
	global $lang, $templates, $mybb, $privatemessage_text, $theme, $modcplink, $admincplink;

	// add conditional HTML5 compaint <head>-Tag
	$tagAttributes = '';
	$replaceTag = '<html';

	if ($lang->settings['htmllang']) {
		$replaceTag .= ' xml:lang="' . $lang->settings['htmllang'] . '"';
		$tagAttributes .= ' lang="' . $lang->settings['htmllang'] . '"';	
	}
	if ($lang->settings['rtl'] == 1) {
		$tagAttributes .= ' dir="rtl"';
	}
	$replaceTag .= $tagAttributes . ' xmlns="http://www.w3.org/1999/xhtml">';
	
	$htmlTag = '<!--[if lt IE 7]> <html class="no-js lt-ie9 lt-ie8 lt-ie7"' . $tagAttributes . '> <![endif]-->
<!--[if IE 7]>    <html class="no-js lt-ie9 lt-ie8 ie7"' . $tagAttributes . '> <![endif]-->
<!--[if IE 8]>    <html class="no-js lt-ie9 ie8"' . $tagAttributes . '> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"' . $tagAttributes . '> <!--<![endif]-->';

	$content = str_replace($replaceTag, $htmlTag, $content);
	//--end <head>-Tag



	// Add custom user menu items
	if ($mybb->xbmc['isLoginUser']) {
		if (isset($privatemessage_text) && strlen($privatemessage_text)) {
			$mybb->xbmc['user']['pmStatus'] = 'new';
		} else {
			$mybb->xbmc['user']['pmStatus'] = $mybb->user['pms_unread'] ? 'unread' : 'read';
		}
		eval("\$headerlinks = \"".$templates->get("headerbit_links_user")."\";");
	} else {
		eval("\$headerlinks = \"".$templates->get("headerbit_links_guest")."\";");
	}
	$content = str_replace('###HEADERLINKS###', $headerlinks, $content);



	return $content;	
}

// allows to parse a Message after the default parser has parsed it
function xbmc_ParseMessage($message) {
	global $mybb;

	if ($mybb->input['action'] == 'do_editsig' && $mybb->input['signature'] && strlen($message)) {
		$message = preg_replace('!\(https?:\/\/[^[:space:]]*\)!i', '', $message);
	}
	$message = preg_replace('![size=[a-z_-]+\](.+?)\[/size\]!is', '$1', $message);
	return $message;
}

function xbmc_AddToFooter($page) {
	// test if hooks run
	#$page .= 'fooo bar';

	// add analytics code
	$page = str_replace('</body>', "
<script type=\"text/javascript\">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-3066672-3']);
  _gaq.push(['_setDomainName', '.xbmc.org']);
  _gaq.push(['_gat._anonymizeIp']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
</body>", $page);

	return $page;
}

function xbmc_DenyAccessToSectionIfNoValidUser() {
	global $mybb;
	if (!$mybb->user || $mybb->user['uid'] <= 0) {
		header('HTTP/1.1 403 Forbidden');
		error('Sorry, only logged in users are allowed to access this page.');
		exit;
	}
}

function xbmc_OverrideLanguage() {
	global $lang;
	$lfile = $lang->path."/".$lang->language."/xbmc.lang.php";
	include($lfile);

	if (is_array($l)) {
		foreach ($l as $k => $v) {
			$lang->$k = $v;	
		}
	}
}

function xbmc_debug($message, $title='') {
	global $mybb;
	if ($mybb->user['uid'] == 49419) {
		echo '<pre>';
		var_dump($message);
		echo '</pre>';
	}
}

?>