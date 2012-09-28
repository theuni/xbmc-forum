<?

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";

function update_push_status_func($xmlrpc_params)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $mobiquo_config;

    $lang->load("member");

    $input = Tapatalk_Input::filterXmlInput(array(
        'settings'  => Tapatalk_Input::RAW,
        'username'  => Tapatalk_Input::STRING,
        'password'  => Tapatalk_Input::STRING,
    ), $xmlrpc_params);
    
    $userid = $mybb->user['uid'];
    $status = false;
    
    if ($db->table_exists('tapatalk_users'))
    {
        if (empty($uid) && $input['username'] && $input['password'])
        {
            $logins = login_attempt_check(1);
            
            if(!username_exists($input['username']))
            {
                my_setcookie('loginattempts', $logins + 1);
                error($lang->error_invalidpworusername);
            }
            
            $user = validate_password_from_username($input['username'], $input['password']);
            if(!$user['uid'])
            {
                $db->update_query("users", array('loginattempts' => 'loginattempts+1'), "LOWER(username) = '".my_strtolower($input['username_esc'])."'", 1, true);
        
                if($mybb->settings['failedlogincount'] != 0 && $mybb->settings['failedlogintext'] == 1)
                {
                    $login_text = $lang->sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
                }
                
                error($lang->error_invalidpworusername.$login_text);
            }
            
            $userid = $user['uid'];
        }
        
        if ($userid)
        {
            $update_params = array();
            if (isset($input['settings']['all']))
            {
                $update_params[] = 'announcement='.($input['settings']['all'] ? 1 : 0);
                $update_params[] = 'pm='.($input['settings']['all'] ? 1 : 0);
                $update_params[] = 'subscribe='.($input['settings']['all'] ? 1 : 0);
            }
            else
            {
                if (isset($input['settings']['ann']))
                    $update_params[] = 'announcement='.($input['settings']['ann'] ? 1 : 0);
                
                if (isset($input['settings']['pm']))
                    $update_params[] = 'pm='.($input['settings']['pm'] ? 1 : 0);
                
                if (isset($input['settings']['sub']))
                    $update_params[] = 'subscribe='.($input['settings']['sub'] ? 1 : 0);
            }
            
            if ($update_params)
            {
                $update_params_str = implode(', ', $update_params);
                $db->write_query("
                    UPDATE " . TABLE_PREFIX . "tapatalk_users
                    SET $update_params_str
                    WHERE userid = '$userid'
                ");
            }
            
            $status = true;
        }
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval($status, 'boolean'),
    ), 'struct'));
}