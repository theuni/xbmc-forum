<?php

defined('IN_MOBIQUO') or exit;

require_once MYBB_ROOT."inc/class_parser.php";

class Tapatalk_Parser extends postParser {


	/**
	* Parses quote MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_quotes($message, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		// Assign pattern and replace values.
		$pattern = array(
			"#\[quote=(?:&quot;|\"|')?(.*?)[\"']?(?:&quot;|\"|')?\](.*?)\[\/quote\](\r\n?|\n?)#esi",
			//"#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si"
		);

		if($text_only == false)
		{
			$replace = array(
				"\$this->mycode_parse_post_quotes('$2','$1')",
				"[quote]$1[/quote]\n"
			);
		}
		else
		{
			$replace = array(
				"\$this->mycode_parse_post_quotes('$2', '$1', true)",
				"\n{$lang->quote}\n--\n$1\n--\n"
			);
		}

		while(preg_match($pattern[0], $message)/* || preg_match($pattern[1], $message)*/)
		{
			$message = preg_replace($pattern, $replace, $message);
		}

		if($text_only == false)
		{
			$find = array(
				"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
				"#(\r\n*|\n*)<\/blockquote>#"
			);

			$replace = array(
				"</cite><br />",
				"</blockquote>"
			);
			$message = preg_replace($find, $replace, $message);
		}
		return $message;
	}



	function mycode_parse_video($video, $url)
	{
		return "[url]{$url}[/url]";
	}



	/**
	* Parses quotes with post id and/or dateline.
	*
	* @param string The message to be parsed
	* @param string The username to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes($message, $username, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		$linkback = $date = "";

		$message = trim($message);
		$message = preg_replace("#(^<br(\s?)(\/?)>|<br(\s?)(\/?)>$)#i", "", $message);

		if(!$message) return '';

		$message = str_replace('\"', '"', $message);
		$username = str_replace('\"', '"', $username)."'";
		$delete_quote = true;

		preg_match("#pid=(?:&quot;|\"|')?([0-9]+)[\"']?(?:&quot;|\"|')?#i", $username, $match);
		if(intval($match[1]))
		{
			$pid = intval($match[1]);
			$url = $mybb->settings['bburl']."/".get_post_link($pid)."#pid$pid";
			if(defined("IN_ARCHIVE"))
			{
				$linkback = " <a href=\"{$url}\">[ -> ]</a>";
			}
			else
			{
				eval("\$linkback = \" ".$templates->get("postbit_gotopost", 1, 0)."\";");
			}

			$username = preg_replace("#(?:&quot;|\"|')? pid=(?:&quot;|\"|')?[0-9]+[\"']?(?:&quot;|\"|')?#i", '', $username);
			$delete_quote = false;
		}

		unset($match);
		preg_match("#dateline=(?:&quot;|\"|')?([0-9]+)(?:&quot;|\"|')?#i", $username, $match);
		if(intval($match[1]))
		{
			if($match[1] < TIME_NOW)
			{
				$postdate = my_date($mybb->settings['dateformat'], intval($match[1]));
				$posttime = my_date($mybb->settings['timeformat'], intval($match[1]));
				$date = " ({$postdate} {$posttime})";
			}
			$username = preg_replace("#(?:&quot;|\"|')? dateline=(?:&quot;|\"|')?[0-9]+(?:&quot;|\"|')?#i", '', $username);
			$delete_quote = false;
		}

		if($delete_quote)
		{
			$username = my_substr($username, 0, my_strlen($username)-1);
		}

		if($text_only)
		{
			return "\n".htmlspecialchars_uni($username)." $lang->wrote{$date}\n--\n{$message}\n--\n";
		}
		else
		{
			$span = "";
			if(!$delete_quote)
			{
				$span = "<span>{$date}</span>";
			}

			return "[quote]{$username} $lang->wrote\n{$message}[/quote]\n";
		}
	}


	/**
	 * Generates a cache of MyCode, both standard and custom.
	 *
	 * @access private
	 */
	function cache_mycode()
	{
		global $cache, $lang;
		$this->mycode_cache = array();

		$standard_mycode['b']['regex'] = "#\[b\](.*?)\[/b\]#si";
		$standard_mycode['b']['replacement'] = '<b>$1</b>';

		$standard_mycode['u']['regex'] = "#\[u\](.*?)\[/u\]#si";
		$standard_mycode['u']['replacement'] = '<u>$1</u>';

		$standard_mycode['i']['regex'] = "#\[i\](.*?)\[/i\]#si";
		$standard_mycode['i']['replacement'] = '<i>$1</i>';

		$standard_mycode['url_simple']['regex'] = "#\[url\]([a-z]+?://)([^\r\n\"<]+?)\[/url\]#sei";
		$standard_mycode['url_simple']['replacement'] = "\$this->mycode_parse_url(\"$1$2\")";

		$standard_mycode['url_simple2']['regex'] = "#\[url\]([^\r\n\"<]+?)\[/url\]#ei";
		$standard_mycode['url_simple2']['replacement'] = "\$this->mycode_parse_url(\"$1\")";

		$standard_mycode['url_complex']['regex'] = "#\[url=([a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex']['replacement'] = "\$this->mycode_parse_url(\"$1$2\", \"$3\")";

		$standard_mycode['url_complex2']['regex'] = "#\[url=([^\r\n\"<&\(\)]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex2']['replacement'] = "\$this->mycode_parse_url(\"$1\", \"$2\")";

		$standard_mycode['email_simple']['regex'] = "#\[email\](.*?)\[/email\]#ei";
		$standard_mycode['email_simple']['replacement'] = "\$this->mycode_parse_email(\"$1\")";

		$standard_mycode['email_complex']['regex'] = "#\[email=(.*?)\](.*?)\[/email\]#ei";
		$standard_mycode['email_complex']['replacement'] = "\$this->mycode_parse_email(\"$1\", \"$2\")";

		$nestable_mycode['color']['regex'] = "#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#si";
		$nestable_mycode['color']['replacement'] = "<font color=\"$1\">$2</font>";

		$nestable_mycode['size']['regex'] = "#\[size=(.*?)\](.*?)\[/size\]#si";
		$nestable_mycode['size']['replacement'] = '$2';

		//$standard_mycode['size_int']['regex'] = "#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#esi";
		//$standard_mycode['size_int']['replacement'] = '$2';

		$nestable_mycode['font']['regex'] = "#\[font=([a-z ]+?)\](.+?)\[/font\]#si";
		$nestable_mycode['font']['replacement'] = '$2';

		$nestable_mycode['align']['regex'] = "#\[align=(left|center|right|justify)\](.*?)\[/align\]#si";
		$nestable_mycode['align']['replacement'] = '$2';

		// Assign the nestable MyCode to the cache.
		foreach($nestable_mycode as $code)
		{
			$this->mycode_cache['nestable'][] = array('find' => $code['regex'], 'replacement' => $code['replacement']);
		}

		// Assign the MyCode to the cache.
		foreach($standard_mycode as $code)
		{
			$this->mycode_cache['standard']['find'][] = $code['regex'];
			$this->mycode_cache['standard']['replacement'][] = $code['replacement'];
		}
	}

	/**
	* Parses code MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_code($code, $text_only=false)
	{
		return $code;
	}

	/**
	* Parses PHP code MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Whether or not it should return it as pre-wrapped in a div or not.
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_php($str, $bare_return = false, $text_only = false)
	{
		return $str;
	}

	/**
	 * Parses MyCode tags in a specific message with the specified options.
	 *
	 * @param string The message to be parsed.
	 * @param array Array of options in yes/no format. Options are allow_imgcode.
	 * @return string The parsed message.
	 */
	function parse_mycode($message, $options=array())
	{
		global $lang;

		// Cache the MyCode globally if needed.
		if($this->mycode_cache == 0)
		{
			$this->cache_mycode();
		}

		// Parse quotes first
		$message = $this->mycode_parse_quotes($message);

		$message = $this->mycode_auto_url($message);

		$message = str_replace('$', '&#36;', $message);

		// Replace the rest
		$message = preg_replace($this->mycode_cache['standard']['find'], $this->mycode_cache['standard']['replacement'], $message);

		// Replace the nestable mycode's
		foreach($this->mycode_cache['nestable'] as $mycode)
		{
			while(preg_match($mycode['find'], $message))
			{
				$message = preg_replace($mycode['find'], $mycode['replacement'], $message);
			}
		}

		// Special code requiring special attention
		while(preg_match("#\[list\](.*?)\[/list\]#esi", $message))
		{
			$message = preg_replace("#\s?\[list\](.*?)\[/list\](\r\n?|\n?)#esi", "\$this->mycode_parse_list('$1')\n", $message);
		}

		// Replace lists.
		while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\](\r\n?|\n?)#esi", $message))
		{
			$message = preg_replace("#\s?\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "\$this->mycode_parse_list('$2', '$1')\n", $message);
		}

		// Convert images when allowed.
		if($options['allow_imgcode'] != 0)
		{
			$message = preg_replace("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$2')\n", $message);
			$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3})\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$4', array('$1', '$2'));", $message);
			$message = preg_replace("#\[img align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$3', array(), '$1');", $message);
			$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3}) align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$5', array('$1', '$2'), '$3');", $message);
		}

		// Convert videos when allow.
		if($options['allow_videocode'] != 0)
		{
			$message = preg_replace("#\[video=(.*?)\](.*?)\[/video\]#ei", "\$this->mycode_parse_video('$1', '$2');", $message);
		}

		return $message;
	}



	/**
	* Parses list MyCode.
	*
	* @param string The message to be parsed
	* @param string The list type
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_list($message, $type="")
	{
		$message = str_replace('\"', '"', $message);
		$message = preg_replace("#\s*\[\*\]\s*#", "\n - ", $message);
/*		$message .= "</li>";

		if($type)
		{
			$list = "\n<ol type=\"$type\">$message</ol>\n";
		}
		else
		{
			$list = "<ul>$message</ul>\n";
		}
		$list = preg_replace("#<(ol type=\"$type\"|ul)>\s*</li>#", "<$1>", $list);*/
		$list = $message;
		return $list;
	}


	/**
	* Parses URL MyCode.
	*
	* @param string The URL to link to.
	* @param string The name of the link.
	* @return string The built-up link.
	*/
	function mycode_parse_url($url, $name="")
	{
		$url = str_replace("'", "", $url);
		$url = str_replace("\\", "", $url);
		if(!preg_match("#^[a-z0-9]+://#i", $url))
		{
			$url = "http://".$url;
		}
		$fullurl = $url;

		$url = str_replace('&amp;', '&', $url);
		$name = str_replace('&amp;', '&', $name);

		if(!$name)
		{
			$name = $url;
		}

		$name = str_replace("\'", "'", $name);
		$url = str_replace("\'", "'", $url);
		$fullurl = str_replace("\'", "'", $fullurl);

		if($name == $url && (!isset($this->options['shorten_urls']) || $this->options['shorten_urls'] != 0))
		{
			if(my_strlen($url) > 55)
			{
				$name = my_substr($url, 0, 40)."...".my_substr($url, -10);
			}
		}

		// Fix some entities in URLs
		$entities = array('$' => '%24', '&#36;' => '%24', '^' => '%5E', '`' => '%60', '[' => '%5B', ']' => '%5D', '{' => '%7B', '}' => '%7D', '"' => '%22', '<' => '%3C', '>' => '%3E', ' ' => '%20');
		$fullurl = str_replace(array_keys($entities), array_values($entities), $fullurl);

		$name = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $name); // Fix & but allow unicode
	   // $link = "<a href=\"$fullurl\" target=\"_blank\">$name</a>";
		$link = "[url={$fullurl}]{$name}[/url]";
		return $link;
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param string The URL to the image
	 * @param array Optional array of dimensions
	 */
	function mycode_parse_img($url, $dimensions=array(), $align='')
	{
		global $lang;
		$url = trim($url);
		$url = str_replace("\n", "", $url);
		$url = str_replace("\r", "", $url);

		return "[img]{$url}[/img]";
	}
}
