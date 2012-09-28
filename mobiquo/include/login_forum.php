<?php

defined('IN_MOBIQUO') or exit;

function login_forum_func($xmlrpc_params)
{
    global $lang;

    $lang->load("forumdisplay");

    $input = Tapatalk_Input::filterXmlInput(array(
        'forum_id' => Tapatalk_Input::INT,
        'password' => Tapatalk_Input::STRING,
    ), $xmlrpc_params);

    tt_check_forum_password($input['forum_id'], 0, $input['password']);

    return xmlresptrue();
}

