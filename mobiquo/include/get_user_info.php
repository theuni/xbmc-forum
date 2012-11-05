<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;


function get_user_info_func($xmlrpc_params)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $parser, $displaygroupfields;

    $lang->load("member");

    $input = Tapatalk_Input::filterXmlInput(array(
        'user_name' => Tapatalk_Input::STRING,
        'user_id' => Tapatalk_Input::INT,
    ), $xmlrpc_params);

    if($mybb->usergroup['canviewprofiles'] == 0){
        error_no_permission();
    }

    if (isset($input['user_id']) && !empty($input['user_id'])) {
        $uid = $input['user_id'];
    } elseif(!empty($input['user_name'])){
        $query = $db->simple_select("users", "uid", "username='{$input['user_name_esc']}'");
        $uid = $db->fetch_field($query, "uid");
    } else {
        $uid = $mybb->user['uid'];
    }

    if($mybb->user['uid'] != $uid)
    {
        $memprofile = get_user($uid);
    }
    else
    {
        $memprofile = $mybb->user;
    }

    if(!$memprofile['uid'])
    {
        error($lang->error_nomember);
    }

    // Get member's permissions
    $memperms = user_permissions($memprofile['uid']);

    if(!$memprofile['displaygroup'])
    {
        $memprofile['displaygroup'] = $memprofile['usergroup'];
    }

    // Grab the following fields from the user's displaygroup
    $displaygroupfields = array(
        "title",
        "usertitle",
        "stars",
        "starimage",
        "image",
        "usereputationsystem"
    );
    $displaygroup = usergroup_displaygroup($memprofile['displaygroup']);

    // Get the user title for this user
    unset($usertitle);
    unset($stars);
    if(trim($memprofile['usertitle']) != '')
    {
        // User has custom user title
        $usertitle = $memprofile['usertitle'];
    }
    elseif(trim($displaygroup['usertitle']) != '')
    {
        // User has group title
        $usertitle = $displaygroup['usertitle'];
    }
    else
    {
        // No usergroup title so get a default one
        $query = $db->simple_select("usertitles", "*", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
        while($title = $db->fetch_array($query))
        {
            if($memprofile['postnum'] >= $title['posts'])
            {
                $usertitle = $title['title'];
                $stars = $title['stars'];
                $starimage = $title['starimage'];
                break;
            }
        }
    }


    // User is currently online and this user has permissions to view the user on the WOL
    $timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
    $query = $db->simple_select("sessions", "location,nopermission", "uid='$uid' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
    $session = $db->fetch_array($query);

    if(($memprofile['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $memprofile['uid'] == $mybb->user['uid']) && !empty($session))
    {
        // Fetch their current location
        $lang->load("online");
        require_once MYBB_ROOT."inc/functions_online.php";
        $activity = fetch_wol_activity($session['location'], $session['nopermission']);

        unset($activity['tid']);
        unset($activity['fid']);
        unset($activity['pid']);
        unset($activity['eid']);
        unset($activity['aid']);

        $location = strip_tags(build_friendly_wol_location($activity));
        $location_time = my_date($mybb->settings['timeformat'], $memprofile['lastactive']);

        $online = true;
    }
    // User is offline
    else
    {
        $online = false;
    }

    // Get custom fields start
    $custom_fields_list = array();
    if($memprofile['birthday'])
    {
        $membday = explode("-", $memprofile['birthday']);

        if($memprofile['birthdayprivacy'] != 'none')
        {
            if($membday[0] && $membday[1] && $membday[2])
            {
                $lang->membdayage = $lang->sprintf($lang->membdayage, get_age($memprofile['birthday']));

                if($membday[2] >= 1970)
                {
                    $w_day = date("l", mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]));
                    $membday = format_bdays($mybb->settings['dateformat'], $membday[1], $membday[0], $membday[2], $w_day);
                }
                else
                {
                    $bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
                    $membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
                    $membday = date($bdayformat, $membday);
                }
                $membdayage = $lang->membdayage;
            }
            elseif($membday[2])
            {
                $membday = mktime(0, 0, 0, 1, 1, $membday[2]);
                $membday = date("Y", $membday);
                $membdayage = '';
            }
            else
            {
                $membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
                $membday = date("F j", $membday);
                $membdayage = '';
            }
        }

        if($memprofile['birthdayprivacy'] == 'age')
        {
            $membday = $lang->birthdayhidden;
        }
        else if($memprofile['birthdayprivacy'] == 'none')
        {
            $membday = $lang->birthdayhidden;
            $membdayage = '';
        }

        $custom_fields_list[] = new xmlrpcval(array(
            'name'  => new xmlrpcval(basic_clean($lang->date_of_birth), 'base64'),
            'value' => new xmlrpcval(basic_clean("{$membday} {$membdayage}"), 'base64')
        ), 'struct');
    }
    
    // thank you/like field
    global $mobiquo_config;
    $prefix = $mobiquo_config['thlprefix'];
    if ($mybb->settings[$prefix.'enabled'] == "1")
    {
        $lang->load("thankyoulike");
        
        if ($mybb->settings[$prefix.'thankslike'] == "like")
        {
            $lang->tyl_total_tyls_given = $lang->tyl_total_likes_given;
            $lang->tyl_total_tyls_rcvd = $lang->tyl_total_likes_rcvd;
        }
        else if ($mybb->settings[$prefix.'thankslike'] == "thanks")
        {
            $lang->tyl_total_tyls_given = $lang->tyl_total_thanks_given;
            $lang->tyl_total_tyls_rcvd = $lang->tyl_total_thanks_rcvd;
        }
        $daysreg = (TIME_NOW - $memprofile['regdate']) / (24*3600);
        $tylpd = $memprofile['tyl_unumtyls'] / $daysreg;
        $tylpd = round($tylpd, 2);
        if($tylpd > $memprofile['tyl_unumtyls'])
        {
            $tylpd = $memprofile['tyl_unumtyls'];
        }
        $tylrcvpd = $memprofile['tyl_unumrcvtyls'] / $daysreg;
        $tylrcvpd = round($tylrcvpd, 2);
        if($tylrcvpd > $memprofile['tyl_unumrcvtyls'])
        {
            $tylrcvpd = $memprofile['tyl_unumrcvtyls'];
        }
        // Get total tyl and percentage
        $options = array(
            "limit" => 1
        );
        $query = $db->simple_select($prefix."stats", "*", "title='total'", $options);
        $total = $db->fetch_array($query);
        if($total['value'] == 0)
        {
            $percent = "0";
            $percent_rcv = "0";
        }
        else
        {
            $percent = $memprofile['tyl_unumtyls']*100/$total['value'];
            $percent = round($percent, 2);
            $percent_rcv = $memprofile['tyl_unumrcvtyls']*100/$total['value'];
            $percent_rcv = round($percent_rcv, 2);
        }
        
        if($percent > 100)
        {
            $percent = 100;
        }
        if($percent_rcv > 100)
        {
            $percent_rcv = 100;
        }
        $memprofile['tyl_unumtyls'] = my_number_format($memprofile['tyl_unumtyls']);
        $memprofile['tyl_unumrcvtyls'] = my_number_format($memprofile['tyl_unumrcvtyls']);
        $tylpd_percent_total = $lang->sprintf($lang->tyl_tylpd_percent_total, my_number_format($tylpd), $tyl_thankslikes_given, $percent);
        $tylrcvpd_percent_total = $lang->sprintf($lang->tyl_tylpd_percent_total, my_number_format($tylrcvpd), $tyl_thankslikes_rcvd, $percent_rcv);
        
        addCustomField($lang->tyl_total_tyls_given, "{$memprofile['tyl_unumtyls']} ({$tylpd_percent_total})", $custom_fields_list);
        addCustomField($lang->tyl_total_tyls_rcvd, "{$memprofile['tyl_unumrcvtyls']} ({$tylrcvpd_percent_total})", $custom_fields_list);
    }
    
    if($memprofile['timeonline'] > 0)
    {
        $timeonline = nice_time($memprofile['timeonline']);
        addCustomField($lang->timeonline, $timeonline, $custom_fields_list);
    }
    if($mybb->settings['usereferrals'] == 1 && $memprofile['referrals'] > 0)
    {
        addCustomField($lang->members_referred, $memprofile['referrals'], $custom_fields_list);
    }
    if($memperms['usereputationsystem'] == 1 && $displaygroup['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1 && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']))
    {
        addCustomField($lang->reputation, $memprofile['reputation'], $custom_fields_list);
    }
    if($mybb->settings['enablewarningsystem'] != 0 && $memperms['canreceivewarnings'] != 0 && ($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $memprofile['uid'] && $mybb->settings['canviewownwarning'] != 0)))
    {
        $warning_level = round($memprofile['warningpoints']/$mybb->settings['maxwarningpoints']*100);
        if($warning_level > 100) $warning_level = 100;
        
        addCustomField($lang->warning_level, $warning_level.'%', $custom_fields_list);
    }
    if($memprofile['website'])
    {
        $memprofile['website'] = htmlspecialchars_uni($memprofile['website']);
        addCustomField($lang->homepage, $memprofile['website'], $custom_fields_list);
    }

    if ($memprofile['icq'])   addCustomField($lang->icq_number, $memprofile['icq'], $custom_fields_list);
    if ($memprofile['aim'])   addCustomField($lang->aim_screenname, $memprofile['aim'], $custom_fields_list);
    if ($memprofile['yahoo']) addCustomField($lang->yahoo_id, $memprofile['yahoo'], $custom_fields_list);
    if ($memprofile['msn'])   addCustomField($lang->msn, $memprofile['msn'], $custom_fields_list);

    $query = $db->simple_select("userfields", "*", "ufid='$uid'");
    $userfields = $db->fetch_array($query);
    if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['canmodcp'] == 1)
    {
        $field_hidden = '1=1';
    }
    else
    {
        $field_hidden = "hidden=0";
    }
    $query = $db->simple_select("profilefields", "*", "{$field_hidden}", array('order_by' => 'disporder'));
    while($customfield = $db->fetch_array($query))
    {
        $thing = explode("\n", $customfield['type'], "2");
        $type = trim($thing[0]);

        $field = "fid{$customfield['fid']}";
        $useropts = explode("\n", $userfields[$field]);
        $customfieldval = $comma = '';
        if(is_array($useropts) && ($type == "multiselect" || $type == "checkbox"))
        {
            $customfieldval = $userfields[$field];
        }
        else
        {
            $customfieldval = $parser->parse_badwords($userfields[$field]);
        }

        $customfield['name'] = htmlspecialchars_uni($customfield['name']);

        if ($customfieldval)
        {
            addCustomField($customfield['name'], $customfieldval, $custom_fields_list);
        }
    }

    if($memprofile['signature'] && ($memprofile['suspendsignature'] == 0 || $memprofile['suspendsigtime'] < TIME_NOW))
    {
        $sig_parser = array(
            "allow_html" => $mybb->settings['sightml'],
            "allow_mycode" => $mybb->settings['sigmycode'],
            "allow_smilies" => $mybb->settings['sigsmilies'],
            "allow_imgcode" => $mybb->settings['sigimgcode'],
            "me_username" => $memprofile['username'],
            "filter_badwords" => 1
        );

        $memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
        $lang->users_signature = $lang->sprintf($lang->users_signature, $memprofile['username']);
        addCustomField($lang->users_signature, $memprofile['signature'], $custom_fields_list);
    }
    // Get custom fields end

    $query = $db->simple_select("banned", "uid", "uid='{$uid}'");
    $isbanned = !!$db->fetch_field($query, "uid");

    $xmlrpc_user_info = array(
        'user_id'            => new xmlrpcval($memprofile['uid'], 'string'),
        'username'           => new xmlrpcval(basic_clean($memprofile['username']), 'base64'),
        'user_name'          => new xmlrpcval(basic_clean($memprofile['username']), 'base64'),
		'user_type'          => check_return_user_type($memprofile['username']),
        'post_count'         => new xmlrpcval($memprofile['postnum'], 'int'),
        'reg_time'           => new xmlrpcval(mobiquo_iso8601_encode($memprofile['regdate']), 'dateTime.iso8601'),
        'timestamp_reg'      => new xmlrpcval($memprofile['regdate'], 'string'),
        'last_activity_time' => new xmlrpcval(mobiquo_iso8601_encode($memprofile['lastactive']), 'dateTime.iso8601'),
        'timestamp'          => new xmlrpcval($memprofile['lastactive'], 'string'),
        'is_online'          => new xmlrpcval($online, 'boolean'),
        'accept_pm'          => new xmlrpcval($memprofile['receivepms'], 'boolean'),
        'display_text'       => new xmlrpcval($usertitle, 'base64'),
        'icon_url'           => new xmlrpcval(absolute_url($memprofile['avatar']), 'string'),
        'current_activity'   => new xmlrpcval($location, 'base64'),
    );

    if ($mybb->usergroup['canmodcp'] == 1 && $uid != $mybb->user['uid'])
        $xmlrpc_user_info['can_ban'] = new xmlrpcval(ture, 'boolean');

    if ($isbanned) $xmlrpc_user_info['is_ban'] = new xmlrpcval(ture, 'boolean');

    $xmlrpc_user_info['custom_fields_list'] = new xmlrpcval($custom_fields_list, 'array');

    return new xmlrpcresp(new xmlrpcval($xmlrpc_user_info, 'struct'));
}

function addCustomField($name, $value, &$list)
{
    $name = preg_replace('/:$/', '', $name);
    
    $list[] = new xmlrpcval(array(
        'name'  => new xmlrpcval(basic_clean($name), 'base64'),
        'value' => new xmlrpcval(basic_clean($value), 'base64')
    ), 'struct');
}