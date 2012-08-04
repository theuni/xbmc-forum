<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by 
 * the Free Software Foundation, either version 3 of the License, 
 * or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, 
 * but WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License 
 * along with this program.  
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * $Id: regsecureq.php 24 2011-07-27 08:27:17Z - G33K - $
 */
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("member_register_end", "regsecureq_register");
$plugins->add_hook("xmlhttp", "regsecureq_xmlhttp");
$plugins->add_hook("datahandler_user_validate", "regsecureq_user_validate");

$plugins->add_hook("admin_config_menu", "regsecureq_confmenu");
$plugins->add_hook("admin_config_action_handler", "regsecureq_confactions");

function regsecureq_info()
{	
	global $plugins_cache, $db, $mybb;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
		
    $info = array(
        "name"				=> "Registration Security Question",
        "description"		=> "Adds a randomly selected security question on registration page.",
        "website"			=> "http://www.geekplugins.com/mybb/regsecureq",
        "author"			=> "- G33K -",
        "authorsite"		=> "http://community.mybboard.net/user-19236.html",
        "version"			=> "1.2",
		"intver"			=> "120",
		"guid" 				=> "aea79c4b3548a91d9e0282a67505099e",
		"compatibility" 	=> "16*"
    );
	
    if(is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active'][$codename])
    {
		$info['description'] = "<i><small>[<a href=\"index.php?module=config-regsecureq\">Manage Questions</a>]</small></i><br />".$info['description'];
		$info['description'] .= '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float: right;" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="JMYHPMTH7QFYU">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
	}
    return $info;
}

function regsecureq_install()
{
	global $db;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	// Create table
	if(!$db->table_exists($prefix.'questions'))
	{
		$db->query("CREATE TABLE ".TABLE_PREFIX.$prefix."questions (
				qid int unsigned NOT NULL auto_increment,
  				question varchar(200) NOT NULL default '',
				answer varchar(100) NOT NULL default '',
				shown int unsigned NOT NULL default 0,
				correct int unsigned NOT NULL default 0,
				incorrect int unsigned NOT NULL default 0,
  				PRIMARY KEY (qid)
				) ENGINE=MyISAM
				".$db->build_create_table_collation().";");
		
		// Enter default entries
		$regq_inserts = array(
					'1' => array(
							'question' => "What comes after the letter D and before the letter F",
							'answer' => "E"
						),
					'2' => array(
							'question' => "Complete this sentence: Unites States of -------",
							'answer' => "America"
						),
					'3' => array(
							'question' => "How many months are there in an year?",
							'answer' => "12|Twelve"
						),
					'4' => array(
							'question' => "Is fire hot or cold?",
							'answer' => "hot"
						),
					'5' => array(
							'question' => "How many letters are there in 'MyBB'?",
							'answer' => "4|Four"
						)
				);
		foreach($regq_inserts as $regq_id => $regq_data)
		{
			$inserts = array(
				'question' => $db->escape_string($regq_data['question']),
				'answer' => $db->escape_string($regq_data['answer'])
				);
			$db->insert_query($prefix.'questions', $inserts);
		}	
	}
}

function regsecureq_is_installed()
{
	global $db;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	if($db->table_exists($prefix.'questions'))
	{
		return true;
	}
	return false;
}

function regsecureq_upgrade()
{
	global $db;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	// Begining v1.2 we need to add some fields to the questions table if they don't already exist.
	if($db->table_exists($prefix.'questions') && !$db->field_exists('shown', $prefix.'questions'))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX.$prefix."questions ADD `shown` int unsigned NOT NULL default '0' AFTER `answer`");
	}
	if($db->table_exists($prefix.'questions') && !$db->field_exists('correct', $prefix.'questions'))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX.$prefix."questions ADD `correct` int unsigned NOT NULL default '0' AFTER `shown`");
	}
	if($db->table_exists($prefix.'questions') && !$db->field_exists('incorrect', $prefix.'questions'))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX.$prefix."questions ADD `incorrect` int unsigned NOT NULL default '0' AFTER `correct`");
	}
}
	
function regsecureq_activate()
{
	global $db, $mybb, $cache;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	// Lets run the udate if needed
	regsecureq_upgrade();
	
	// Insert Template elements
	// Remove first to clean up any template edits left from previous installs
	$db->delete_query("templates", "title='regsecureq'");			
	$db->delete_query("templates", "title='regsecureq_button'");
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_register", "#".preg_quote('{$regq}
')."#i", '', 0);
	
	// Now add
	$regq_templates = array(
				'regsecureq'			=> '<br />
<fieldset class="trow2">
<script type="text/javascript" src="jscripts/regsecureq.js?ver=100"></script>
<legend><strong>{$lang->regsecureq}</strong></legend>
<table cellspacing="0" cellpadding="{$theme[\'tablespace\']}">
<tr>
<td colspan="2"><span class="smalltext">{$lang->regq_explain}</span></td>
</tr>
<tr>
<td colspan="2"><br /><span class="smalltext" id="regsecureq" style="font-weight:bold;">{$regsecureq}</span></td>
</tr>
<tr>
<td width="60%"><br /><input type="text" class="textbox" name="regsecureans" value="" id="regsecureans" style="width: 100%;" /><input type="hidden" name="regsecureq_id" value="{$regsecureq_id}" id="regsecureq_id" /></td>
<td align="right" valign="bottom">
{$regq_button}
</td>
</tr>
<tr>
	<td id="regsecureans_status"  style="display: none;" colspan="2">&nbsp;</td>
</tr>
</table>
</fieldset>',
			'regsecureq_button'			=> '<script type="text/javascript">
<!--
	if(use_xmlhttprequest == "1")
	{
		document.write(\'<input type="button" class="button" tabindex="11000" name="regsecureq_change" value="{$lang->regq_change}" onclick="regsecureq.change();return false;" \/>\');
	}
// -->
</script>'
					);
	
	foreach($regq_templates as $template_title => $template_data)
	{
		$insert_templates = array(
			'title' => $db->escape_string($template_title),
			'template' => $db->escape_string($template_data),
			'sid' => "-1",
			'version' => $info['intver'],
			'dateline' => TIME_NOW
			);
		$db->insert_query('templates', $insert_templates);
	}
	find_replace_templatesets("member_register", "#".preg_quote('{$regimage}')."#i", '{$regimage}
{$regq}');
}

function regsecureq_deactivate()
{
	global $db, $mybb, $cache;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	// Remove template elements	
	$db->delete_query("templates", "title='regsecureq'");
	$db->delete_query("templates", "title='regsecureq_button'");
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_register", "#".preg_quote('
{$regq}')."#i", '', 0);

}

function regsecureq_uninstall()
{
	global $db;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	if($db->table_exists($prefix.'questions'))
	{
		$db->drop_table($prefix.'questions');
	}
}

function regsecureq_register()
{
	global $mybb, $db, $templates, $theme, $lang, $regq, $validator_extra;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	$lang->load("regsecureq");
	
	// Grab all questions from the database, then choose a random one from the array
	// Changed from the previous way to reduce sql calls, one for the question, another for the count
	$query = $db->query("
		SELECT q.*
		FROM ".TABLE_PREFIX.$prefix."questions q
		ORDER BY q.qid
	");
	$q = array();
	$count = 0;
	while($qdata = $db->fetch_array($query))
	{
		$q[$count]['qid'] = $qdata['qid'];
		$q[$count]['question'] = $qdata['question'];
		$q[$count]['answer'] = $qdata['answer'];
		$q[$count]['shown'] = $qdata['shown'];
		$count++;
	}
	
	$random = array_rand($q);
	
	$regsecureq_id = $q[$random]['qid'];
	$regsecureq = $q[$random]['question'];
	
	if ($count == 0)
	{
		// There are no questions, hide whole block
		$regq_button = '';
		$regq = '';
	}
	else if ($count == 1)
	{
		// Theres only one question, hide change button
		$regq_button = '';
		$validator_extra .= "\tregValidator.register('regsecureans', 'ajax', {url:'xmlhttp.php?action=validate_question', extra_body: 'regsecureq_id', loading_message:'{$lang->regq_checking}', failure_message:'{$lang->regq_wrong_answer}'});\n";
		eval("\$regq = \"".$templates->get("regsecureq")."\";");
	}
	else
	{
		// Normal, show all
		// JS validator extra
		$validator_extra .= "\tregValidator.register('regsecureans', 'ajax', {url:'xmlhttp.php?action=validate_question', extra_body: 'regsecureq_id', loading_message:'{$lang->regq_checking}', failure_message:'{$lang->regq_wrong_answer}'});\n";
		eval("\$regq_button = \"".$templates->get("regsecureq_button")."\";");
		eval("\$regq = \"".$templates->get("regsecureq")."\";");
	}
	
	// Update db question shown count
	if ($count > 0)
	{
		$update_q = array(
			'shown'		=> $q[$random]['shown']+1
		);
			
		$db->update_query($prefix.'questions', $update_q, "qid='{$regsecureq_id}'");
	}
}

function regsecureq_user_validate($data)
{
	global $mybb;
	
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	global $mybb, $db, $lang;
	
	// We only process this if we're registering. Otherwise we get errors on usercp and any other place user_validater is used
	if ($mybb->input['action'] == "do_register")
	{
		$lang->load("regsecureq");
	
		$regq_id = intval($mybb->input['regsecureq_id']);
		// Only if id is valid, else we assume the regq block is not visible.
		if($regq_id > 0)
		{
			// Get the answer using the qid above and check that answer
			$query = $db->simple_select($prefix."questions", "*", "qid='{$regq_id}'");
			if($db->num_rows($query) == 0)
			{
				$data->set_error($lang->regq_invalid);
			}
			$q = $db->fetch_array($query);
				
			$reg_ans = explode("|", $q['answer']);
			$validated = 0;
			foreach ($reg_ans AS $regans)
			{
				if(my_strtolower(trim($regans)) == my_strtolower(trim($mybb->input['regsecureans'])))
				{
					$validated = 1;
				}
			}
			
			if(!$validated)
			{
				$data->set_error($lang->regq_wrong_answer);
				// Update incorrect count of question
				// If ajax is on, we're going to assume this has already been counted via ajax
				// Should find a more fool proof way to check it before counting
				if(!$mybb->settings['use_xmlhttprequest'])
				{
					$update_q = array(
						'incorrect'		=> $q['incorrect']+1
					);
				
					$db->update_query($prefix.'questions', $update_q, "qid='{$regq_id}'");
				}
			}
			else
			{
				// Update correct count of question
				// If ajax is on, we're going to assume this has already been counted via ajax
				// Should find a more fool proof way to check it before counting
				if(!$mybb->settings['use_xmlhttprequest'])
				{
					$update_q = array(
						'correct'		=> $q['correct']+1
					);
				
					$db->update_query($prefix.'questions', $update_q, "qid='{$regq_id}'");
				}
			}
		}
	}
	return $data;
}

function regsecureq_xmlhttp()
{
	$codename = basename(__FILE__, ".php");
	$prefix = 'g33k_'.$codename.'_';
	
	$info = regsecureq_info();
	
	global $mybb, $db, $lang, $charset;
	
	$lang->load("regsecureq");
	
	if($mybb->input['action'] == "change_regq")
	{
		// Send headers.
		header("Content-type: application/json; charset={$charset}");
		
		$regq_id = intval($mybb->input['regq']);
		
		if (!$regq_id)
		{
			$regq_id = 0;
		}
		
		// Pick another question except the one that was currently shown
		$query = $db->query("
			SELECT q.*
			FROM ".TABLE_PREFIX.$prefix."questions q
			WHERE q.qid != {$regq_id}
			ORDER BY RAND()
			LIMIT 1
		");
		if($db->num_rows($query) == 0)
		{
			echo "<fail>{$lang->regq_invalid}</fail>";
			exit;
		}
		$q = $db->fetch_array($query);
		$regsecureq_id = $q['qid'];
		$regsecureq = $q['question'];
		
		// Update shown count
		$update_q = array(
			'shown'		=> $q['shown']+1
		);
			
		$db->update_query($prefix.'questions', $update_q, "qid='{$regsecureq_id}'");
		
		// Cleanup for JSON
		$regsecureq = cleanup_json($regsecureq);
	
		echo '{';
		echo '"qid":"'.$regsecureq_id.'",';
		echo '"q":"'.$regsecureq.'"';
		echo '}';
	}
	else if($mybb->input['action'] == "validate_question")
	{
		header("Content-type: text/xml; charset={$charset}");
		$regq_id = $db->escape_string(intval($mybb->input['regsecureq_id']));
		// Get the answer using the qid above and check that answer
		$query = $db->simple_select($prefix."questions", "*", "qid='{$regq_id}'");
		if($db->num_rows($query) == 0)
		{
			echo "<fail>{$lang->regq_invalid}</fail>";
			exit;
		}
		$q = $db->fetch_array($query);
				
		$reg_ans = explode("|", $q['answer']);
		$validated = 0;
		foreach ($reg_ans AS $regans)
		{
			if(my_strtolower(trim($regans)) == my_strtolower(trim($mybb->input['value'])))
			{
				$validated = 1;
			}
		}
		
		if($validated)
		{
			// Update correct count
			$update_q = array(
				'correct'		=> $q['correct']+1
			);
			
			$db->update_query($prefix.'questions', $update_q, "qid='{$regq_id}'");
			
			echo "<success>{$lang->regq_correct_answer}</success>";
			exit;
		}
		else
		{
			// Update shown count
			$update_q = array(
				'incorrect'		=> $q['incorrect']+1
			);
			
			$db->update_query($prefix.'questions', $update_q, "qid='{$regq_id}'");
			
			echo "<fail>{$lang->regq_wrong_answer}</fail>";
			exit;
		}
	}
}

function cleanup_json($data)
{
	return addcslashes($data, "\\\/\"\n\r\t/".chr(0).chr(8).chr(12));
}
		
function regsecureq_confmenu($sub_menu)
{
	$count = count($sub_menu);
	$item = ($count+1)*10;
	$sub_menu[$item] = array("id" => "regsecureq", "title" => "Security Questions", "link" => "index.php?module=config-regsecureq");
	return $sub_menu;
}

function regsecureq_confactions($actions)
{
	$actions['regsecureq'] = array('active' => 'regsecureq', 'file' => 'regsecureq.php');
	return $actions;
}		
?>