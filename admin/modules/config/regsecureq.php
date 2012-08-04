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
 * $Id: regsecureq.php 14 2011-07-27 06:43:58Z - G33K - $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->regsecureq, "index.php?module=config-regsecureq");

$prefix = "g33k_regsecureq_";

$sub_tabs['regsecureq'] = array(
	'title' => $lang->regsecureq,
	'link' => "index.php?module=config-regsecureq",
	'description' => $lang->regsecureq_desc
);

$sub_tabs['addregsecureq'] = array(
	'title' => $lang->regsecureq_add,
	'link' => "index.php?module=config-regsecureq&amp;action=add",
	'description' => $lang->regsecureq_add_desc
);

if($mybb->input['action'] == 'add')
{
	if($mybb->request_method == "post")
	{
		if(trim($mybb->input['regsecureq_question']) == '')
		{
			$errors[] = $lang->regsecureq_missing_question;
		}
		
		if(trim($mybb->input['regsecureq_answer']) == '')
		{
			$errors[] = $lang->regsecureq_missing_answer;
		}
		
		if(strlen(trim($mybb->input['regsecureq_question'])) > 200)
		{
			$errors[] = $lang->regsecureq_question_long;
		}
		
		if(strlen(trim($mybb->input['regsecureq_answer'])) > 100)
		{
			$errors[] = $lang->regsecureq_answer_long;
		}
		
		if(!$errors)
		{
			$new_q = array(
				'question'		=> $db->escape_string(trim($mybb->input['regsecureq_question'])),
				'answer'		=> $db->escape_string(trim($mybb->input['regsecureq_answer']))
			);
			
			$qid = $db->insert_query($prefix.'questions', $new_q);
			flash_message($lang->regsecureq_success_question_added, 'success');
			admin_redirect("index.php?module=config-regsecureq");
		}
	}
	$page->add_breadcrumb_item($lang->regsecureq_add);
	$page->output_header($lang->regsecureq.' - '.$lang->regsecureq_add);
	$page->output_nav_tabs($sub_tabs, "addregsecureq");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=config-regsecureq&amp;action=add", "post");
	$form_container = new FormContainer($lang->regsecureq_add);
	$form_container->output_row($lang->regsecureq_question, "", $form->generate_text_area("regsecureq_question", $mybb->input['regsecureq_question'], array("class" => "text_input align_left", "style" => "width: 60%;")), 'regsecureq_question');	
	$form_container->output_row($lang->regsecureq_answer, "", $form->generate_text_box("regsecureq_answer", $mybb->input['regsecureq_answer'], array("class" => "text_input align_left", "style" => "width: 60%;")), 'regsecureq_answer');	
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->regsecureq_add);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == 'edit')
{
	if(!$mybb->input['qid'])
	{
		flash_message($lang->regsecureq_error_deleting, 'error');
		admin_redirect('index.php?module=config-regsecureq');
	}
	
	$qid = intval($mybb->input['qid']);
	
	if($mybb->request_method == "post")
	{
		if(trim($mybb->input['regsecureq_question']) == '')
		{
			$errors[] = $lang->regsecureq_missing_question;
		}
		
		if(trim($mybb->input['regsecureq_answer']) == '')
		{
			$errors[] = $lang->regsecureq_missing_answer;
		}
		
		if(strlen(trim($mybb->input['regsecureq_question'])) > 200)
		{
			$errors[] = $lang->regsecureq_question_long;
		}
		
		if(strlen(trim($mybb->input['regsecureq_answer'])) > 100)
		{
			$errors[] = $lang->regsecureq_answer_long;
		}
		
		if(!$errors)
		{
			$update_q = array(
				'question'		=> $db->escape_string(trim($mybb->input['regsecureq_question'])),
				'answer'		=> $db->escape_string(trim($mybb->input['regsecureq_answer']))
			);
			
			$db->update_query($prefix.'questions', $update_q, "qid='{$qid}'");
			
			flash_message($lang->regsecureq_success_question_edited, 'success');
			admin_redirect("index.php?module=config-regsecureq");
		}
	}
	$sub_tabs['editregsecureq'] = array(
		'title' => $lang->regsecureq_edit_q,
		'link' => "index.php?module=config-regsecureq",
		'description' => $lang->regsecureq_edit_desc
	);
	$page->add_breadcrumb_item($lang->regsecureq_edit_q);
	$page->output_header($lang->regsecureq.' - '.$lang->regsecureq_edit_q);
	$page->output_nav_tabs($sub_tabs, "editregsecureq");
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select($prefix."questions", "*", "qid={$qid}", array('limit' => 1));
		$q = $db->fetch_array($query);
		$mybb->input['regsecureq_question'] = $q['question'];
		$mybb->input['regsecureq_answer'] = $q['answer'];
	}
	
	$form = new Form("index.php?module=config-regsecureq&amp;action=edit&amp;qid={$qid}", "post");
	$form_container = new FormContainer($lang->regsecureq_edit);
	$form_container->output_row($lang->regsecureq_question, "", $form->generate_text_area("regsecureq_question", $mybb->input['regsecureq_question'], array("class" => "text_input align_left", "style" => "width: 60%;")), 'regsecureq_question');	
	$form_container->output_row($lang->regsecureq_answer, "", $form->generate_text_box("regsecureq_answer", $mybb->input['regsecureq_answer'], array("class" => "text_input align_left", "style" => "width: 60%;")), 'regsecureq_answer');	
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->regsecureq_edit_q);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == 'delete')
{
	if(!$mybb->input['qid'])
	{
		flash_message($lang->regsecureq_error_deleting, 'error');
		admin_redirect('index.php?module=config-regsecureq');
	}
	
	$qid = intval($mybb->input['qid']);
	
	$query = $db->simple_select($prefix.'questions', '*', "qid='{$qid}'");
	$q = $db->fetch_array($query);
	
	if(!$q['qid'])
	{
		flash_message($lang->regsecureq_error_deleting, 'error');
		admin_redirect('index.php?module=config-regsecureq');
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect('index.php?module=config-regsecureq');
	}
	
	if($mybb->request_method == 'post')
	{
		// Delete question
		$db->delete_query($prefix.'questions', "qid='{$q['qid']}'");
		
		flash_message($lang->regsecureq_success_question_deleted, 'success');
		admin_redirect('index.php?module=config-regsecureq');
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-regsecureq&amp;action=delete&amp;qid={$qid}", $lang->regsecureq_confirm_delete);
	}
}

if(!$mybb->input['action'])
{
	// Show a list of questions from the database
	$page->output_header($lang->regsecureq);

	$page->output_nav_tabs($sub_tabs, "regsecureq");
	
	$table = new Table;
	$table->construct_header($lang->regsecureq_question, array("class" => "align_left", 'width' => '55%'));
	$table->construct_header($lang->regsecureq_answer, array("class" => "align_right", 'width' => '25%'));
	$table->construct_header($lang->regsecureq_stats, array("class" => "align_center", 'width' => '10%'));
	$table->construct_header($lang->regsecureq_controls, array("class" => "align_center", 'width' => '10%', 'colspan' => 2));
	
	$query = $db->simple_select($prefix."questions", "*", "", array('order_by' => 'qid'));
	while($q = $db->fetch_array($query))
	{
		$trow = alt_trow();		
		$table->construct_cell("{$q['question']}", array("class" => "align_left"));
		$table->construct_cell("{$q['answer']}", array("class" => "align_right"));
		$table->construct_cell("".g33k_prog_bar($q['correct'], $q['incorrect'])."", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-regsecureq&amp;action=edit&amp;qid={$q['qid']}\">{$lang->regsecureq_edit}</a>", array('class' => 'align_center', 'width' => '5%'));
		$table->construct_cell("<a href=\"index.php?module=config-regsecureq&amp;action=delete&amp;qid={$q['qid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->regsecureq_confirm_delete}')\">{$lang->regsecureq_delete}</a>", array('class' => 'align_center', 'width' => '5%'));
		$table->construct_row();
	}
	
	$table->output($lang->regsecureq);
	
	$page->output_footer();
}

function g33k_prog_bar($c, $i)
{
	global $lang;
	
	$return = "<table style='border:0; border-spacing:0; width: 100%;'><tr>";
	
	if (($c+$i) == 0)
	{
		$corr = $lang->sprintf($lang->regsecureq_correct, '0');
		$incorr = $lang->sprintf($lang->regsecureq_incorrect, '0');
		$return .= "<td style='background-color: #C0C0C0; width:100%; height:2px; padding:0px; border:0;' title='{$corr} / {$incorr}'>&nbsp;</td>";
	}
	elseif($c == 0)
	{
		$corr = $lang->sprintf($lang->regsecureq_correct, '0');
		$incorr = $lang->sprintf($lang->regsecureq_incorrect, '100');
		$return .= "<td style='background-color: #FF0000; width:100%; height:2px; padding:0px; border:0;' title='{$corr} / {$incorr} ({$i})'>&nbsp;</td>";
	}
	elseif($i == 0)
	{
		$corr = $lang->sprintf($lang->regsecureq_correct, '100');
		$incorr = $lang->sprintf($lang->regsecureq_incorrect, '0');
		$return .= "<td style='background-color: #00FF00; width:100%; height:2px; padding:0px; border:0;' title='{$corr} ({$c}) / {$incorr}'>&nbsp;</td>";
	}
	else
	{
		$correct = round(($c/($c+$i))*100);
		$incorrect = round(($i/($c+$i))*100);
		$corr = $lang->sprintf($lang->regsecureq_correct, $correct);
		$incorr = $lang->sprintf($lang->regsecureq_incorrect, $incorrect);
		$return .= "<td style='background-color: #00FF00; width:{$correct}%; height:2px; padding:0px; border:0;' title='{$corr} ({$c}) / {$incorr} ({$i})'>&nbsp;</td>";
		$return .= "<td style='background-color: #FF0000; width:{$incorrect}%; height:2px; padding:0px; border:0;' title='{$corr} ({$c}) / {$incorr} ({$i})'>&nbsp;</td>";
	}
	
	$return .= "</tr></table>";
	
	return $return;
}
	
?>