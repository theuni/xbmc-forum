<?php
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	echo '<b>Attachment Upload Interface for Tapatalk Application</b><br/><br/>';
	echo '<br/><a href="http://tapatalk.com/api/api.php" target="_blank">Tapatalk API for Universal Forum Access</a> | <a href="http://tapatalk.com/mobile.php" target="_blank">Tapatalk Mobile Applications</a><br>
    For more details, please visit <a href="http://tapatalk.com" target="_blank">http://tapatalk.com</a>';
	exit;
}	
require "./mobiquo.php";