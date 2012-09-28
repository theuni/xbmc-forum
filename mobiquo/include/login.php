<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";

function login_func($xmlrpc_params)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $mobiquo_config;

    $lang->load("member");

    $input = Tapatalk_Input::filterXmlInput(array(
        'username'  => Tapatalk_Input::STRING,
        'password'  => Tapatalk_Input::STRING,
        'anonymous' => Tapatalk_Input::INT,
        'push'      => Tapatalk_Input::STRING,
    ), $xmlrpc_params);

    $logins = login_attempt_check(1);
    $login_text = '';

    if(!username_exists($input['username']))
    {
        my_setcookie('loginattempts', $logins + 1);
        return xmlrespfalse($lang->error_invalidpworusername.$login_text);
    }

    $query = $db->simple_select("users", "loginattempts", "LOWER(username)='".my_strtolower($input['username_esc'])."'", array('limit' => 1));
    $loginattempts = $db->fetch_field($query, "loginattempts");

    $errors = array();

    $user = validate_password_from_username($input['username'], $input['password']);
    if(!$user['uid'])
    {
        my_setcookie('loginattempts', $logins + 1);
        $db->update_query("users", array('loginattempts' => 'loginattempts+1'), "LOWER(username) = '".my_strtolower($input['username_esc'])."'", 1, true);

        if($mybb->settings['failedlogincount'] != 0 && $mybb->settings['failedlogintext'] == 1)
        {
            $login_text = $lang->sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
        }

        $errors[] = $lang->error_invalidpworusername.$login_text;
    }
    else
    {
        $correct = true;
    }

    if(!empty($errors))
    {
        return xmlrespfalse(implode(" :: ", $errors));
    }
    else if($correct)
    {
        if($user['coppauser'])
        {
            error($lang->error_awaitingcoppa);
        }

        my_setcookie('loginattempts', 1);
        $db->delete_query("sessions", "ip='".$db->escape_string($session->ipaddress)."' AND sid != '".$session->sid."'");
        $newsession = array(
            "uid" => $user['uid'],
        );
        $db->update_query("sessions", $newsession, "sid='".$session->sid."'");

        $db->update_query("users", array("loginattempts" => 1), "uid='{$user['uid']}'");

        my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], null, true);
        my_setcookie("sid", $session->sid, -1, true);

        $mybb->cookies['sid'] = $session->sid;
        $session = new session;
        $session->init();

        $mybbgroups = $mybb->user['usergroup'];
        if($mybb->user['additionalgroups'])
        {
            $mybbgroups .= ','.$mybb->user['additionalgroups'];
        }
        $groups = explode(",", $mybbgroups);
        $xmlgroups = array();
        foreach($groups as $group){
            $xmlgroups[] = new xmlrpcval($group, "string");
        }

        if ($settings['maxattachments'] == 0) $settings['maxattachments'] = 100;
        
        $result = array(
            'result'            => new xmlrpcval(true, 'boolean'),
            'result_text'       => new xmlrpcval('', 'base64'),
            'user_id'           => new xmlrpcval($mybb->user['uid'], 'string'),
            'username'          => new xmlrpcval(basic_clean($mybb->user['username']), 'base64'),
            'icon_url'          => new xmlrpcval(absolute_url($mybb->user['avatar']), 'string'),
            'post_count'        => new xmlrpcval(intval($mybb->user['postnum']), 'int'),
            'usergroup_id'      => new xmlrpcval($xmlgroups, 'array'),
            'max_png_size'      => new xmlrpcval(10000000, "int"),
            'max_jpg_size'      => new xmlrpcval(10000000, "int"),
            'max_attachment'    => new xmlrpcval($mybb->usergroup['canpostattachments'] == 1 ? $settings['maxattachments'] : 0, "int"),
            'can_upload_avatar' => new xmlrpcval($mybb->usergroup['canuploadavatars'] == 1, "boolean"),
            'can_pm'            => new xmlrpcval($mybb->usergroup['canusepms'] == 1 && !$mobiquo_config['disable_pm'], "boolean"),
            'can_send_pm'       => new xmlrpcval($mybb->usergroup['cansendpms'] == 1 && !$mobiquo_config['disable_pm'], "boolean"),
            'can_moderate'      => new xmlrpcval($mybb->usergroup['canmodcp'] == 1, "boolean"),
            'can_search'        => new xmlrpcval($mybb->usergroup['cansearch'] == 1, "boolean"),
            'can_whosonline'    => new xmlrpcval($mybb->usergroup['canviewonline'] == 1, "boolean"),
        );
        
        if ($input['push']) update_push();
        
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }

    return xmlrespfalse("Invalid login details");
}

function update_push()
{
    global $mybb, $db;
    
    $uid = $mybb->user['uid'];
    
    if ($uid && $db->table_exists('tapatalk_users'))
    {
        $db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "tapatalk_users (userid) VALUES ('$uid')", 1);
        
        if ($db->affected_rows() == 0)
        {
            $db->write_query("UPDATE " . TABLE_PREFIX . "tapatalk_users SET updated = CURRENT_TIMESTAMP WHERE userid = '$uid'", 1);
        }
    }
}