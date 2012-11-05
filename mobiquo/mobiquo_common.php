<?php

defined('IN_MOBIQUO') or exit;

function get_error($error_message)
{
    $r = new xmlrpcresp(
            new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval($error_message, 'base64'),
            ),'struct')
    );
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$r->serialize('UTF-8');
    exit;
}


function errors(array $errors)
{
    if(empty($errors)) return;

    $error = implode("\n", $errors);

    error($error);
}


function mobi_parse_requrest()
{
    global $request_method, $request_params, $params_num;
    
    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    
    if (count($_SERVER) == 0)
    {
        $r = new xmlrpcresp('', 15, 'XML-RPC: '.__METHOD__.': cannot parse request headers as $_SERVER is not populated');
        echo $r->serialize('UTF-8');
        exit;
    }
    
    if(isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $content_encoding = str_replace('x-', '', $_SERVER['HTTP_CONTENT_ENCODING']);
    } else {
        $content_encoding = '';
    }
    
    if($content_encoding != '' && strlen($data)) {
        if($content_encoding == 'deflate' || $content_encoding == 'gzip') {
            // if decoding works, use it. else assume data wasn't gzencoded
            if(function_exists('gzinflate')) {
                if ($content_encoding == 'deflate' && $degzdata = @gzuncompress($data)) {
                    $data = $degzdata;
                } elseif ($degzdata = @gzinflate(substr($data, 10))) {
                    $data = $degzdata;
                }
            } else {
                $r = new xmlrpcresp('', 106, 'Received from client compressed HTTP request and cannot decompress');
                echo $r->serialize('UTF-8');
                exit;
            }
        }
    }
    
    $parsers = php_xmlrpc_decode_xml($data);
    $request_method = $parsers->methodname;
    $request_params = php_xmlrpc_decode(new xmlrpcval($parsers->params, 'array'));
    $params_num = count($request_params);
}

function get_mobiquo_config()
{
    $config_file = './config/config.txt';
    file_exists($config_file) or exit('config.txt does not exists');

    if(function_exists('file_get_contents')){
        $tmp = file_get_contents($config_file);
    }else{
        $handle = fopen($config_file, 'rb');
        $tmp = fread($handle, filesize($config_file));
        fclose($handle);
    }

    // remove comments by /*xxxx*/ or //xxxx
    $tmp = preg_replace('/\/\*.*?\*\/|\/\/.*?(\n)/si','$1',$tmp);
    $tmpData = preg_split("/\s*\n/", $tmp, -1, PREG_SPLIT_NO_EMPTY);

    $mobiquo_config = array();
    foreach ($tmpData as $d){
        list($key, $value) = preg_split("/=/", $d, 2); // value string may also have '='
        $key = trim($key);
        $value = trim($value);
        if ($key == 'hide_forum_id')
        {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            count($value) and $mobiquo_config[$key] = $value;
        }
        else
        {
            strlen($value) and $mobiquo_config[$key] = $value;
        }
    }

    return $mobiquo_config;
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64')
    ), 'struct');

    return new xmlrpcresp($result);
}

function xmlrespfalse($error_message)
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval(strip_tags($error_message), 'base64')
    ), 'struct');

    return new xmlrpcresp($result);
}

function tt_error($error="", $title="")
{
    if(!empty($title))
        $error = "{$title} :: {$error}";
    $error = strip_tags($error);

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror($error)->serialize('UTF-8'));
    exit;
}

/**
* For use via preg_replace_callback; makes urls absolute before wrapping them in [url]
*/
function parse_local_link($input){
    return "[URL=".XenForo_Link::convertUriToAbsoluteUri($input[1], true)."]{$input[2]}[/URL]";
}

function xmlresperror($error_message)
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval($error_message, 'base64')
    ), 'struct');

    return new xmlrpcresp($result/*, 98, $error_message*/);
}



function tt_check_forum_password($fid, $pid=0, $pass='')
{
    global $mybb, $header, $footer, $headerinclude, $theme, $templates, $lang, $forum_cache;

    $mybb->input['pwverify'] = $pass;

    $showform = true;

    if(!is_array($forum_cache))
    {
        $forum_cache = cache_forums();
        if(!$forum_cache)
        {
            return false;
        }
    }

    // Loop through each of parent forums to ensure we have a password for them too
    $parents = explode(',', $forum_cache[$fid]['parentlist']);
    rsort($parents);
    if(!empty($parents))
    {
        foreach($parents as $parent_id)
        {
            if($parent_id == $fid || $parent_id == $pid)
            {
                continue;
            }

            if($forum_cache[$parent_id]['password'] != "")
            {
                tt_check_forum_password($parent_id, $fid);
            }
        }
    }

    $password = $forum_cache[$fid]['password'];
    if($password)
    {
        if($mybb->input['pwverify'] && $pid == 0)
        {
            if($password == $mybb->input['pwverify'])
            {
                my_setcookie("forumpass[$fid]", md5($mybb->user['uid'].$mybb->input['pwverify']), null, true);
                $showform = false;
            }
            else
            {
                eval("\$pwnote = \"".$templates->get("forumdisplay_password_wrongpass")."\";");
                $showform = true;
            }
        }
        else
        {
            if(!$mybb->cookies['forumpass'][$fid] || ($mybb->cookies['forumpass'][$fid] && md5($mybb->user['uid'].$password) != $mybb->cookies['forumpass'][$fid]))
            {
                $showform = true;
            }
            else
            {
                $showform = false;
            }
        }
    }
    else
    {
        $showform = false;
    }

    if($showform)
    {
        if(empty($pwnote))
        {
            global $lang;
            $pwnote = $lang->forum_password_note;
        }

        error($pwnote);
    }
}



function tt_no_permission(){
    return xmlrespfalse('You do not have permission to view this');
}

function absolute_url($url)
{
    global $mybb;
    
    $url = trim($url);
    
    if(empty($url)) return "";
    
    $url = preg_replace('#^\.?/#', '', $url);
    
    if(!preg_match('#^https?://#', $url)) {
        $url = $mybb->settings['bburl'] . "/" . $url;
    }
    
    return $url;
}


function mobiquo_iso8601_encode($timet, $offset = 0)
{
    global $mybb;

    if(!$offset)
    {
        if($mybb->user['uid'] != 0 && array_key_exists("timezone", $mybb->user))
        {
            $offset = $mybb->user['timezone'];
            $dstcorrection = $mybb->user['dst'];
        }
        elseif(defined("IN_ADMINCP"))
        {
            $offset =  $mybbadmin['timezone'];
            $dstcorrection = $mybbadmin['dst'];
        }
        else
        {
            $offset = $mybb->settings['timezoneoffset'];
            $dstcorrection = $mybb->settings['dstcorrection'];
        }

        // If DST correction is enabled, add an additional hour to the timezone.
        if($dstcorrection == 1)
        {
            ++$offset;
            if(my_substr($offset, 0, 1) != "-")
            {
                $offset = "+".$offset;
            }
        }
    }

    $t = gmdate("Ymd\TH:i:s", $timet + $offset * 3600);
    $t .= sprintf("%+03d:%02d", intval($offset), abs($offset - intval($offset)) * 60);

    return $t;
}

function cutstr($string, $length)
{
    if(strlen($string) <= $length) {
        return $string;
    }

    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

    $strcut = '';

    $n = $tn = $noc = 0;
    while($n < strlen($string)) {

        $t = ord($string[$n]);
        if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1; $n++; $noc++;
        } elseif(194 <= $t && $t <= 223) {
            $tn = 2; $n += 2; $noc += 2;
        } elseif(224 <= $t && $t <= 239) {
            $tn = 3; $n += 3; $noc += 2;
        } elseif(240 <= $t && $t <= 247) {
            $tn = 4; $n += 4; $noc += 2;
        } elseif(248 <= $t && $t <= 251) {
            $tn = 5; $n += 5; $noc += 2;
        } elseif($t == 252 || $t == 253) {
            $tn = 6; $n += 6; $noc += 2;
        } else {
            $n++;
        }

        if($noc >= $length) {
            break;
        }

    }
    if($noc > $length) {
        $n -= $tn;
    }

    $strcut = substr($string, 0, $n);

    return $strcut;
}

function process_short_content($post_text, $parser = null, $length = 200)
{
    global $parser;
    
    if($parser === null) {
        require_once MYBB_ROOT."inc/class_parser.php";
        $parser = new postParser;
    }

    $parser_options = array(
        'allow_html' => 0,
        'allow_mycode' => 1,
        'allow_smilies' => 0,
        'allow_imgcode' => 0,
        'filter_badwords' => 1
    );
    $post_text = strip_tags($parser->parse_message($post_text, $parser_options));
    $post_text = preg_replace('/\s+/', ' ', $post_text);
    $post_text = html_entity_decode($post_text);
    
    if(my_strlen($post_text) > $length)
    {
        $post_text = my_substr(trim($post_text), 0, $length);
    }
    
    //$post_text = str_replace("\xC2\xA0", " ", $post_text);
    
    return $post_text;
}

function process_post($post, $returnHtml = false)
{
    if($returnHtml){
        //$post = str_replace("&", '&amp;', $post);
        //$post = str_replace("<", '&lt;', $post);
        //$post = str_replace(">", '&gt;', $post);
        // handled by post parser nl2br option
        //$post = str_replace("\r", '', $post);
        //$post = str_replace("\n", '<br />', $post);
        $post = str_replace('[hr]', '<br />____________________________________<br />', $post);
    } else {
        $post = strip_tags($post);
        $post = html_entity_decode($post, ENT_QUOTES, 'UTF-8');
        $post = str_replace('[hr]', "\n____________________________________\n", $post);
    }

    $post = trim($post);
    // remove link on img
    $post = preg_replace('/\[url=[^\]]*?\]\s*(\[img\].*?\[\/img\])\s*\[\/url\]/si', '$1', $post);

    return $post;
}

function process_page($start_num, $end)
{
    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    if (empty($end) || $end < $start)
    {
        $start = 0;
        $end = 19;
    }
    elseif ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    $page = intval($start/$limit) + 1;

    return array($start, $limit, $page);
}

// redundant? __toString ;)
function get_xf_lang($lang_key, $params = array())
{
    $phrase = new XenForo_Phrase($lang_key, $params);
    return $phrase->render();
}

function get_online_status($user_id)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $sessionModel = $bridge->getSessionModel();
    $userModel = $bridge->getUserModel();

    $bypassUserPrivacy = $userModel->canBypassUserPrivacy();

    $conditions = array(
        'cutOff'            => array('>', $sessionModel->getOnlineStatusTimeout()),
        'getInvisible'      => $bypassUserPrivacy,
        'getUnconfirmed'    => $bypassUserPrivacy,
        'user_id'           => XenForo_Visitor::getUserId(),
        'forceInclude'      => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
    );

    $onlineUsers = $sessionModel->getSessionActivityRecords($conditions);

    return empty($onlineUsers) ? false : true;
}

function basic_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}


function process_post_attachments($id, &$post)
{
    global $attachcache, $mybb, $theme, $templates, $forumpermissions, $lang;

    $validationcount = 0;
    $tcount = 0;

    $attachment_list = array();
    if(is_array($attachcache[$id]))
    { // This post has 1 or more attachments
        foreach($attachcache[$id] as $aid => $attachment)
        {
            if($attachment['visible'])
            { // There is an attachment thats visible!
                $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
                $attachment['filesize_b'] = $attachment['filesize'];
                $attachment['filesize'] = get_friendly_size($attachment['filesize']);
                $ext = get_extension($attachment['filename']);
                if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
                    $type = 'image';
                elseif($ext == "pdf")
                    $type = 'pdf';
                else
                    $type = 'other';

                $attachment['icon'] = get_attachment_icon($ext);
                // Support for [attachment=id] code
                if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
                {
                    if($type == 'image')
                        $replace = '[img]'.absolute_url("attachment.php?aid={$attachment['aid']}").'[/img]';
                    else
                        $replace = '[url='.absolute_url("attachment.php?aid={$attachment['aid']}").']'.$attachment['filename']."[/url]({$lang->postbit_attachment_size} {$attachment['filesize']} / {$lang->postbit_attachment_downloads} {$attachment['downloads']})";

                    $post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $replace, $post['message']);
                }
                else
                {
                    $url = absolute_url("attachment.php?aid={$attachment['aid']}");
                    $thumbnail_url = ($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != '') ? absolute_url("attachment.php?thumbnail={$attachment['aid']}") : $url;

                    $attachment_list[] = new xmlrpcval(array(
                        'filename'      => new xmlrpcval($attachment['filename'], 'base64'),
                        'filesize'      => new xmlrpcval($attachment['filesize_b'], 'int'),
                        'content_type'  => new xmlrpcval($type, 'string'),
                        'thumbnail_url' => new xmlrpcval($thumbnail_url, 'string'),
                        'url'           => new xmlrpcval($url, 'string'),
                    ), 'struct');
                }
            }
        }
    }

    return $attachment_list;
}

function shutdown()
{
    if (!headers_sent())
    {
        header("HTTP/1.0 200 OK");
    }
    
    $error = error_get_last();
    if(!empty($error)){
        switch($error['type']){
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_PARSE:
                echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror("Server error occurred: '{$error['message']} (".basename($error['file']).":{$error['line']})'")->serialize('UTF-8'));
                break;
        }
    }
}

function get_forum_icon($id, $type = 'forum', $lock = false, $new = false)
{
    if (!in_array($type, array('link', 'category', 'forum')))
        $type = 'forum';
    
    $icon_name = $type;
    if ($type != 'link')
    {
        if ($lock) $icon_name .= '_lock';
        if ($new) $icon_name .= '_new';
    }
    
    $icon_map = array(
        'category_lock_new' => array('category_lock', 'category_new', 'lock_new', 'category', 'lock', 'new'),
        'category_lock'     => array('category', 'lock'),
        'category_new'      => array('category', 'new'),
        'lock_new'          => array('lock', 'new'),
        'forum_lock_new'    => array('forum_lock', 'forum_new', 'lock_new', 'forum', 'lock', 'new'),
        'forum_lock'        => array('forum', 'lock'),
        'forum_new'         => array('forum', 'new'),
        'category'          => array(),
        'forum'             => array(),
        'lock'              => array(),
        'new'               => array(),
        'link'              => array(),
    );
    
    $final = empty($icon_map[$icon_name]);
    
    if ($url = get_forum_icon_by_name($id, $icon_name, $final))
        return $url;
    
    foreach ($icon_map[$icon_name] as $sub_name)
    {
        $final = empty($icon_map[$sub_name]);
        if ($url = get_forum_icon_by_name($id, $sub_name, $final))
            return $url;
    }
    
    return '';
}

function get_forum_icon_by_name($id, $name, $final)
{
    global $tapatalk_forum_icon_dir, $tapatalk_forum_icon_url;
    
    $filename_array = array(
        $name.'_'.$id.'.png',
        $name.'_'.$id.'.jpg',
        $id.'.png', $id.'.jpg',
        $name.'.png',
        $name.'.jpg',
    );
    
    foreach ($filename_array as $filename)
    {
        if (file_exists($tapatalk_forum_icon_dir.$filename))
        {
            return $tapatalk_forum_icon_url.$filename;
        }
    }
    
    if ($final) {
        if (file_exists($tapatalk_forum_icon_dir.'default.png'))
            return $tapatalk_forum_icon_url.'default.png';
        else if (file_exists($tapatalk_forum_icon_dir.'default.jpg'))
            return $tapatalk_forum_icon_url.'default.jpg';
    }
    
    return false;
}

function post_bbcode_clean($str)
{
	global $board_url;
	$array_reg = array(
		array('reg' => '/\[color=(.*?)\](.*?)\[\/color\]/sei','replace' => "mobi_color_convert('$1','$2' ,false)"),
		array('reg' => '/\[php\](.*?)\[\/php\]/si','replace' => '[quote]$1[/quote]'),
		array('reg' => '/\[code\](.*?)\[\/code\]/si','replace' => '[quote]$1[/quote]'),
		array('reg' => '/\[align=(.*?)\](.*?)\[\/align\]/si',replace=>" $2 "),
		array('reg' => '/\[email\](.*?)\[\/email\]/si',replace=>"[url]$1[/url]"),
		
	);
	foreach ($array_reg as $arr)
	{
		$str = preg_replace($arr['reg'], $arr['replace'], $str);
	}
	$str = tt_covert_list($str, '/\[list=1\](.*?)\[\/list\]/si', '2');
	$str = tt_covert_list($str, '/\[list\](.*?)\[\/list\]/si', '1');
	return $str;
}

function mobi_color_convert($color, $str , $is_background)
{
    static $colorlist;
    
    if (preg_match('/#[\da-fA-F]{6}/is', $color))
    {
        if (empty($colorlist))
        {
            $colorlist = array(
                '#000000' => 'Black',             '#708090' => 'SlateGray',       '#C71585' => 'MediumVioletRed', '#FF4500' => 'OrangeRed',
                '#000080' => 'Navy',              '#778899' => 'LightSlateGrey',  '#CD5C5C' => 'IndianRed',       '#FF6347' => 'Tomato',
                '#00008B' => 'DarkBlue',          '#778899' => 'LightSlateGray',  '#CD853F' => 'Peru',            '#FF69B4' => 'HotPink',
                '#0000CD' => 'MediumBlue',        '#7B68EE' => 'MediumSlateBlue', '#D2691E' => 'Chocolate',       '#FF7F50' => 'Coral',
                '#0000FF' => 'Blue',              '#7CFC00' => 'LawnGreen',       '#D2B48C' => 'Tan',             '#FF8C00' => 'Darkorange',
                '#006400' => 'DarkGreen',         '#7FFF00' => 'Chartreuse',      '#D3D3D3' => 'LightGrey',       '#FFA07A' => 'LightSalmon',
                '#008000' => 'Green',             '#7FFFD4' => 'Aquamarine',      '#D3D3D3' => 'LightGray',       '#FFA500' => 'Orange',
                '#008080' => 'Teal',              '#800000' => 'Maroon',          '#D87093' => 'PaleVioletRed',   '#FFB6C1' => 'LightPink',
                '#008B8B' => 'DarkCyan',          '#800080' => 'Purple',          '#D8BFD8' => 'Thistle',         '#FFC0CB' => 'Pink',
                '#00BFFF' => 'DeepSkyBlue',       '#808000' => 'Olive',           '#DA70D6' => 'Orchid',          '#FFD700' => 'Gold',
                '#00CED1' => 'DarkTurquoise',     '#808080' => 'Grey',            '#DAA520' => 'GoldenRod',       '#FFDAB9' => 'PeachPuff',
                '#00FA9A' => 'MediumSpringGreen', '#808080' => 'Gray',            '#DC143C' => 'Crimson',         '#FFDEAD' => 'NavajoWhite',
                '#00FF00' => 'Lime',              '#87CEEB' => 'SkyBlue',         '#DCDCDC' => 'Gainsboro',       '#FFE4B5' => 'Moccasin',
                '#00FF7F' => 'SpringGreen',       '#87CEFA' => 'LightSkyBlue',    '#DDA0DD' => 'Plum',            '#FFE4C4' => 'Bisque',
                '#00FFFF' => 'Aqua',              '#8A2BE2' => 'BlueViolet',      '#DEB887' => 'BurlyWood',       '#FFE4E1' => 'MistyRose',
                '#00FFFF' => 'Cyan',              '#8B0000' => 'DarkRed',         '#E0FFFF' => 'LightCyan',       '#FFEBCD' => 'BlanchedAlmond',
                '#191970' => 'MidnightBlue',      '#8B008B' => 'DarkMagenta',     '#E6E6FA' => 'Lavender',        '#FFEFD5' => 'PapayaWhip',
                '#1E90FF' => 'DodgerBlue',        '#8B4513' => 'SaddleBrown',     '#E9967A' => 'DarkSalmon',      '#FFF0F5' => 'LavenderBlush',
                '#20B2AA' => 'LightSeaGreen',     '#8FBC8F' => 'DarkSeaGreen',    '#EE82EE' => 'Violet',          '#FFF5EE' => 'SeaShell',
                '#228B22' => 'ForestGreen',       '#90EE90' => 'LightGreen',      '#EEE8AA' => 'PaleGoldenRod',   '#FFF8DC' => 'Cornsilk',
                '#2E8B57' => 'SeaGreen',          '#9370D8' => 'MediumPurple',    '#F08080' => 'LightCoral',      '#FFFACD' => 'LemonChiffon',
                '#2F4F4F' => 'DarkSlateGrey',     '#9400D3' => 'DarkViolet',      '#F0E68C' => 'Khaki',           '#FFFAF0' => 'FloralWhite',
                '#2F4F4F' => 'DarkSlateGray',     '#98FB98' => 'PaleGreen',       '#F0F8FF' => 'AliceBlue',       '#FFFAFA' => 'Snow',
                '#32CD32' => 'LimeGreen',         '#9932CC' => 'DarkOrchid',      '#F0FFF0' => 'HoneyDew',        '#FFFF00' => 'Yellow',
                '#3CB371' => 'MediumSeaGreen',    '#9ACD32' => 'YellowGreen',     '#F0FFFF' => 'Azure',           '#FFFFE0' => 'LightYellow',
                '#40E0D0' => 'Turquoise',         '#A0522D' => 'Sienna',          '#F4A460' => 'SandyBrown',      '#FFFFF0' => 'Ivory',
                '#4169E1' => 'RoyalBlue',         '#A52A2A' => 'Brown',           '#F5DEB3' => 'Wheat',           '#FFFFFF' => 'White',
                '#4682B4' => 'SteelBlue',         '#A9A9A9' => 'DarkGrey',        '#F5F5DC' => 'Beige',
                '#483D8B' => 'DarkSlateBlue',     '#A9A9A9' => 'DarkGray',        '#F5F5F5' => 'WhiteSmoke',
                '#48D1CC' => 'MediumTurquoise',   '#ADD8E6' => 'LightBlue',       '#F5FFFA' => 'MintCream',
                '#4B0082' => 'Indigo',            '#ADFF2F' => 'GreenYellow',     '#F8F8FF' => 'GhostWhite',
                '#556B2F' => 'DarkOliveGreen',    '#AFEEEE' => 'PaleTurquoise',   '#FA8072' => 'Salmon',
                '#5F9EA0' => 'CadetBlue',         '#B0C4DE' => 'LightSteelBlue',  '#FAEBD7' => 'AntiqueWhite',
                '#6495ED' => 'CornflowerBlue',    '#B0E0E6' => 'PowderBlue',      '#FAF0E6' => 'Linen',
                '#66CDAA' => 'MediumAquaMarine',  '#B22222' => 'FireBrick',       '#FAFAD2' => 'LightGoldenRodYellow',
                '#696969' => 'DimGrey',           '#B8860B' => 'DarkGoldenRod',   '#FDF5E6' => 'OldLace',
                '#696969' => 'DimGray',           '#BA55D3' => 'MediumOrchid',    '#FF0000' => 'Red',
                '#6A5ACD' => 'SlateBlue',         '#BC8F8F' => 'RosyBrown',       '#FF00FF' => 'Fuchsia',
                '#6B8E23' => 'OliveDrab',         '#BDB76B' => 'DarkKhaki',       '#FF00FF' => 'Magenta',
                '#708090' => 'SlateGrey',         '#C0C0C0' => 'Silver',          '#FF1493' => 'DeepPink',
            );
        }
        
        if (isset($colorlist[strtoupper($color)])) $color = $colorlist[strtoupper($color)];
    }
    if($is_background)
    	return "[color=$color][b]".$str.'[/b][/color]';
    else 
        return "[color=$color]".$str.'[/color]';
}
function tt_covert_list($message,$preg,$type)
{
	while(preg_match($preg, $message, $blocks))
    {
    	$list_str = "";
    	$list_arr = explode('[*]', $blocks[1]);
    	foreach ($list_arr as $key => $value)
    	{
    		$value = trim($value);
    		if(!empty($value) && $key != 0)
    		{
    			if($type == '1')
    			{
    				$key = ' * ';
    			}
    			else 
    			{
    				$key = $key.'.';
    			}
    			$list_str .= $key.$value ."\n";
    		}
    		else if(!empty($value))
    		{
    			$list_str .= $value ."\n";
    		}    		
    	}
    	$message = str_replace($blocks[0], $list_str, $message);
    }
    return $message;
}

function check_return_user_type($username)
{
	global $mybb, $db, $cache;
	$sql = "SELECT u.uid,g.gid 
		FROM ".TABLE_PREFIX."users u 
		LEFT JOIN ".TABLE_PREFIX . "usergroups g
		ON u.usergroup = g.gid
		WHERE u.username = '" . $username."'
		LIMIT 1";
	$query = $db->query($sql);
	$is_ban = false;
	// Read the banned cache
	$bannedcache = $cache->read("banned");	
	$user_groups = $db->fetch_array($query);
	if(empty($user_groups))
	{
		return new xmlrpcval(basic_clean('guest'), 'base64');;
	}	
	// If the banned cache doesn't exist, update it and re-read it
	if(!is_array($bannedcache))
	{
		$cache->update_banned();
		$bannedcache = $cache->read("banned");
	}
	if(!empty($bannedcache[$user_groups['uid']]) || ($user_groups['gid'] == 7))
	{
		$is_ban = true;
	}
	if($is_ban)
	{
		$user_type = 'banned';
	}
	else if($user_groups['gid'] == 4)
	{
		$user_type = 'admin';
	}
	else if($user_groups['gid'] == 6)
	{
		$user_type = 'mod';
	}
	else
    {
		$user_type = 'normal';
	}
	return new xmlrpcval(basic_clean($user_type), 'base64');
}