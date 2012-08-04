<?php

defined('IN_MOBIQUO') or exit;

function get_board_stat_func()
{
    global $mybb, $cache, $db;
    
    // Get the online users.
    $timesearch = TIME_NOW - $mybb->settings['wolcutoff'];
    $query = $db->query("
        SELECT s.sid, s.uid, s.time
        FROM ".TABLE_PREFIX."sessions s
        WHERE s.time>'$timesearch'
        ORDER BY s.time DESC
    ");

    $membercount = 0;
    $guestcount = 0;
    $doneusers = array();

    // Fetch spiders
    $spiders = $cache->read("spiders");

    // Loop through all users.
    while($user = $db->fetch_array($query))
    {
        // Create a key to test if this user is a search bot.
        $botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

        // Decide what type of user we are dealing with.
        if($user['uid'] > 0)
        {
            // The user is registered.
            if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
            {
                ++$membercount;
                $doneusers[$user['uid']] = $user['time'];
            }
        }
        elseif(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
        {
        }
        else
        {
            ++$guestcount;
        }
    }

    $onlinecount = $membercount + $guestcount;
    
    $stats = $cache->read("stats");
    
    $board_stat = array(
        'total_threads' => new xmlrpcval($stats['numthreads'], 'int'),
        'total_posts'   => new xmlrpcval($stats['numposts'], 'int'),
        'total_members' => new xmlrpcval($stats['numusers'], 'int'),
        'guest_online'  => new xmlrpcval($guestcount, 'int'),
        'total_online'  => new xmlrpcval($onlinecount, 'int')
    );
    
    $response = new xmlrpcval($board_stat, 'struct');
    
    return new xmlrpcresp($response);
}
