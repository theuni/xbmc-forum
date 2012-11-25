<?php
/**
 * Don't reveal any of the credentials or other
 * sensible configurations in GIT!!!!
 * Thus include 'em from an external file but
 * keep the dummy config.php for myBB
 */
$private_path = '/etc/xbmc/php-include';
set_include_path(get_include_path().PATH_SEPARATOR.$private_path);
require_once('forum/private/configuration.php');
 
?>