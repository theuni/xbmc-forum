<?php
/**
 * MyBB 1.6
 * Copyright � 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id: loginconvert.php 4435 2011-12-05 03:05:56Z ralgith $
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("member_do_login_start", "loginconvert_convert", 1);

function loginconvert_info()
{
	return array(
		"name"				=> "Login Password Conversion",
		"description"		=> "Converts passwords of the vB3 XBMC forum. To be used in conjunction with the MyBB Merge System.",
		"website"			=> "http://www.mybb.com",
		"author"				=> "MyBB Group, TeamXBMC",
		"authorsite"		=> "http://www.xbmc.org",
		"version"				=> "1.0",
		"guid"					=> "",
		"compatibility"	=> "16*",
	);
}

function loginconvert_activate()
{
}

function loginconvert_deactivate()
{
}

function loginconvert_convert()
{
	global $mybb, $db, $lang, $session, $plugins, $inline_errors, $errors;

	if($mybb->input['action'] != "do_login" || $mybb->request_method != "post")
	{
		return;
	}
/*
	// Checks to make sure the user can login; they haven't had too many tries at logging in.
	// Is a fatal call if user has had too many tries
	$logins = login_attempt_check();
	$login_text = '';
*/
	// Did we come from the quick login form?
	if($mybb->input['quick_login'] == "1" && $mybb->input['quick_password'] && $mybb->input['quick_username'])
	{
		$mybb->input['password'] = $mybb->input['quick_password'];
		$mybb->input['username'] = $mybb->input['quick_username'];
	}
/*
	if(!username_exists($mybb->input['username']))
	{
		my_setcookie('loginattempts', $logins + 1);
		error($lang->error_invalidpworusername.$login_text);
	}
	
	$query = $db->simple_select("users", "loginattempts", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
	$loginattempts = $db->fetch_field($query, "loginattempts");
	
	$errors = array();
*/
	$user = loginconvert_validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	// if validation suceeded and we get a user back, update the user-record in $mybb to prevent futher user lookups in the db
	if ($user && is_array($user) && $user['uid']) {
		#$mybb->user = $user;
	}
}

/**
 * Checks a password with a supplied username.
 *
 * @param string The username of the user.
 * @param string The md5()'ed password.
 * @return boolean|array False when no match, array with user info when match.
 */
function loginconvert_validate_password_from_username($username, $password)
{
	global $db;
	
	if($db->field_exists("passwordconvert", "users") && $db->field_exists("passwordconverttype", "users"))
	{
		$query = $db->simple_select("users", "uid,username,password,salt,loginkey,passwordconvert,passwordconvertsalt,passwordconverttype", "username='".$db->escape_string($username)."' AND passwordconverttype != ''", array('limit' => 1));

		$user = $db->fetch_array($query);
		$db->free_result($query);
	
		if(!$user['uid'])
		{
			return false;
		}
		else
		{
			return loginconvert_validate_password($user, $password);
		}
	} 
	return FALSE;
}

/**
 * Checks a password with a supplied uid.
 *
 * @param string An optional user data array.
 * @param string The md5()'ed password.
 * @return boolean|array False when not valid, user data array when valid.
 */
function loginconvert_validate_password($user, $password)
{
	global $db, $mybb;
	
	if($mybb->user['uid'] == $user['uid'])
	{
		$user = $mybb->user;
	}

	if(!isset($user['password']) && (!isset($user['passwordconvert']) || trim($user['passwordconvert']) == ''))
	{
		$query = $db->simple_select("users", "uid,username,password,salt,loginkey,passwordconvert,passwordconvertsalt,passwordconverttype", "uid='".intval($uid)."'", array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
	}

	if(isset($user['passwordconvert']) && trim($user['passwordconvert']) != '' && trim($user['passwordconverttype']) != ''/* && trim($user['password']) == ''*/)
	{
		$convert = new loginConvert($user);
		return $convert->login($user['passwordconverttype'], $user['uid'], $password);
	}
	return false;
}

/*
 * This class allows us to take the encryption algorithm used by the convertee bulletin board with the plain text password
 * the user just logged in with, and match it against the encrypted password stored in the passwordconvert column added by
 * the Merge System. If we have success then apply MyBB's encryption to the plain-text password.
 */
class loginConvert
{ 
	protected $user;
	
	public function __construct($user)
	{
		$user['passwordconvert'] = trim($user['passwordconvert']);
		$user['passwordconvertsalt'] = trim($user['passwordconvertsalt']);
		$user['passwordconverttype'] = trim($user['passwordconverttype']);
		$this->user = $user;
	}
	
	public function login($type, $uid, $password)
   {
		global $db;
		
		$password = trim($password);
		$return = false;
		$user = array();
		
		switch($type)
		{
			case 'vb3':
				 $return = $this->authenticate_vb3($password);
				 break;
			default:
				 return false;
		}

		if($return == true)
		{
			// Generate a salt for this user and assume the password stored in db is empty
			$user['salt'] = generate_salt();
			$this->user['salt'] = $user['salt'];
			$user['password'] = salt_password(md5($password), $user['salt']);
			$this->user['password'] = $user['password'];
			$user['loginkey'] = generate_loginkey();
			$this->user['loginkey'] = $user['loginkey'];
			$user['passwordconverttype'] = '';
			$this->user['passwordconverttype'] = '';
			$user['passwordconvert'] = '';
			$this->user['passwordconvert'] = '';
			$user['passwordconvertsalt'] = '';
			$this->user['passwordconvertsalt'] = '';

			$db->update_query("users", $user, "uid='{$uid}'", 1);
			
			return $this->user;
		}

		return false;
    }

    // Authentication for vB3
    function authenticate_vb3($password)
    {
		if(md5(md5($password).$this->user['passwordconvertsalt']) == $this->user['passwordconvert']
			|| md5($password.$this->user['passwordconvertsalt']) == $this->user['passwordconvert']
			|| md5(md5($password).stripslashes($this->user['passwordconvertsalt'])) == $this->user['passwordconvert']
			){
			return true;
		}
		
		return false;
    }
}

?>