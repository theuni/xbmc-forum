<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_forumlist.php";
require_once MYBB_ROOT."inc/class_parser.php";

function get_forum_func()
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions, $fcache, $forum_cache;

    $lang->load("index");

    $inactiveforums = get_inactive_forums();

    if($mybb->user['uid'] == 0)
    {
        // Build a forum cache.
        $query = $db->query("
            SELECT *, threads as unread_count
            FROM ".TABLE_PREFIX."forums
            WHERE active != 0 " . ($inactiveforums ? " AND fid NOT IN ($inactiveforums)" : '') . "
            ORDER BY pid, disporder
        ");

        $forumsread = unserialize($mybb->cookies['mybb']['forumread']);
    }
    else
    {
        // Build a forum cache.
        $query = $db->query("
            SELECT f.*, fr.dateline AS lastread, fs.fsid, (
                select count(*) from ".TABLE_PREFIX."threads where fid=f.fid and lastpost > fr.dateline
            ) as unread_count
            FROM ".TABLE_PREFIX."forums f
            LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
            LEFT JOIN ".TABLE_PREFIX."forumsubscriptions fs ON (fs.fid=f.fid AND fs.uid='{$mybb->user['uid']}')
            WHERE f.active != 0 " . ($inactiveforums ? " AND f.fid NOT IN ($inactiveforums)" : '') . "
            ORDER BY pid, disporder
        ");
    }

    while($forum = $db->fetch_array($query))
    {
        if($mybb->user['uid'] == 0)
        {
            if($forumsread[$forum['fid']])
            {
                $forum['lastread'] = $forumsread[$forum['fid']];
            }
        }
        $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
    }
    $forumpermissions = forum_permissions();

    $excols = "index";
    $permissioncache['-1'] = "1";

    $showdepth = 10;

    $xml_nodes = new xmlrpcval(array(), 'array');
    $done=array();
    $xml_tree = treeBuild(0, $fcache, $xml_nodes, $done);
    $xml_nodes->addArray($xml_tree);

    return new xmlrpcresp($xml_nodes);
}

function processForum($forum)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions, $fcache;

    static $private_forums;

    $permissions = $forumpermissions[$forum['fid']];

    $hideinfo = false;
    $showlockicon = 0;
    if($permissions['canviewthreads'] != 1)
    {
        $hideinfo = true;
    }

    if($permissions['canonlyviewownthreads'] == 1)
    {
        $hideinfo = true;

        // If we only see our own threads, find out if there's a new post in one of them so the lightbulb shows
        if(!is_array($private_forums))
        {
            $private_forums = $fids = array();
            foreach($fcache[$forum['pid']] as $parent_p)
            {
                foreach($parent_p as $forum_p)
                {
                    if($forumpermissions[$forum_p['fid']]['canonlyviewownthreads'])
                    {
                        $fids[] = $forum_p['fid'];
                    }
                }
            }

            if(!empty($fids))
            {
                $fids = implode(',', $fids);
                $query = $db->simple_select("threads", "tid, fid, lastpost", "uid = '{$mybb->user['uid']}' AND fid IN ({$fids})", array("order_by" => "lastpost", "order_dir" => "desc"));

                while($thread = $db->fetch_array($query))
                {
                    if(!$private_forums[$thread['fid']])
                    {
                        $private_forums[$thread['fid']] = $thread;
                    }
                }
            }
        }

        if($private_forums[$forum['fid']]['lastpost'])
        {
            $forum['lastpost'] = $private_forums[$forum['fid']]['lastpost'];

            $lastpost_data = array(
                "lastpost" => $private_forums[$forum['fid']]['lastpost']
            );
        }
    }
    else
    {
        $lastpost_data = array(
            "lastpost" => $forum['lastpost'],
            "lastpostsubject" => $forum['lastpostsubject'],
            "lastposter" => $forum['lastposter'],
            "lastposttid" => $forum['lastposttid'],
            "lastposteruid" => $forum['lastposteruid']
        );
    }

    if($forum['password'] != '' && $mybb->cookies['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password'])){
        $hideinfo = true;
        $showlockicon = 1;
    }

    // If we are hiding information (lastpost) because we aren't authenticated against the password for this forum, remove them
    if($hideinfo == true)
    {
        unset($lastpost_data);
    }

    $lightbulb = get_forum_lightbulb($forum, $lastpost_data, $showlockicon);

    $new_post = $lightbulb['folder'] == 'on';
	$logo_url = '';
	//@todo
	$tapatalk_forum_icon_dir = './forum_icons/';
	$tapatalk_forum_icon_url = MYBB_ROOT . $mybb->settings['tapatalkdir'];
	if($forum['type'] == c)
	{
		$forum_type = 'category';
	}
	else if(!empty($forum['linkto']))
	{
		$forum_type = 'link';
	}
	else 
	{
		$forum_type = 'forum';
    }
	if(empty($forum['forum_image']))
	{
		$logo_url = get_forum_icon(2,$forum_type);
	}
	else if (!empty($forum['password']))
	{
		$logo_url = get_forum_icon(2,$forum_type,true);
	}
	else if(!empty($forum['unread_count']))
	{
		$logo_url = get_forum_icon(2,$forum_type,false,true);
	}
    else if ($forum['forum_image'])
    {
        $logo_url = MYBB_ROOT . $forum['forum_image'];
    }
    $xmlrpc_forum = new xmlrpcval(array(
        'forum_id'      => new xmlrpcval($forum['fid'], 'string'),
        'forum_name'    => new xmlrpcval(basic_clean($forum['name']), 'base64'),
        'description'   => new xmlrpcval($forum['description'], 'base64'),
        'parent_id'     => new xmlrpcval($forum['pid'], 'string'),
        'logo_url'      => new xmlrpcval($logo_url, 'string'),
        'new_post'      => new xmlrpcval($new_post, 'boolean'),
        'unread_count'  => new xmlrpcval($forum['unread_count'], 'int'),
        'is_protected'  => new xmlrpcval(!empty($forum['password']), 'boolean'),
        'url'           => new xmlrpcval($forum['linkto'], 'string'),
        'sub_only'      => new xmlrpcval($forum['type'] == 'c', 'boolean'),
        'can_subscribe' => new xmlrpcval($forumpermissions[$forum['fid']]['canviewthreads'] == 1, 'boolean'),
        'is_subscribed' => new xmlrpcval(!empty($forum['fsid']), 'boolean'),
    ), 'struct');

    return array($xmlrpc_forum, $new_post);
}

function treeBuild($pid, &$fcache, &$xml_nodes, &$done)
{
    global $db, $lang, $theme, $plugins, $mybb, $session, $settings, $cache, $time, $mybbgroups, $forumpermissions, $new_note;

    $newForums = array();

    if(!empty($fcache[$pid]))
    {
        foreach($fcache[$pid] as $parent)
        {
            foreach($parent as $forum)
            {
                // Get the permissions for this forum
                $permissions = $forumpermissions[$forum['fid']];
                // If this user doesnt have permission to view this forum and we're hiding private forums, skip this forum
                if($permissions['canview'] != 1 && $mybb->settings['hideprivateforums'] == 1)
                {
                    continue;
                }
                
                list($forum2, $new_post) = processForum($forum);
                $done[$id] = true;
                $child = treeBuild($forum['fid'], $fcache, $xml_nodes, $done);
                
                if ($new_note[$forum['fid']])
                    $forum2->addStruct(array('new_post' => new xmlrpcval(true, 'boolean')));
                
                if ($child)
                    $forum2->addStruct(array('child' => new xmlrpcval($child, 'array')));
                
                if ($new_post || $new_note[$forum['fid']]) $new_note[$pid] = true;
                
                $newForums[]=$forum2;

            }
        }
    }

    return $newForums;
}
