<?php

defined('IN_MOBIQUO') or exit;

$server_param = array(

    'login' => array(
        'function'  => 'login_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcString)),
    ),

    'get_forum' => array(
        'function'  => 'get_forum_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_forum.',
    ),

    'get_board_stat' => array(
        'function'  => 'get_board_stat_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_board_stat.',
    ),

    'get_topic' => array(
        'function'  => 'get_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(forum id(string), start topic num(int), end topic number(int), topic type(string, "TOP" for sticky topics, "ANN" for announcement topics)',
    ),

    'get_thread' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(topic id(string), start post number(int), end post number(int), bbcode enable(boolean))',
    ),
    'get_thread_by_unread' => array(
        'function'  => 'get_thread_by_unread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be)',
    ),  
    'get_thread_by_post' => array(
        'function'  => 'get_thread_by_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be)',
    ),
    

    'get_raw_post' => array(
        'function'  => 'get_raw_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(post id(string))',
    ),

    'save_raw_post' => array(
        'function'  => 'save_raw_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(post id(string), post title(base64), post content(base64), bbcode enable(boolean))',
    ),

    'get_quote_post' => array(
        'function'  => 'get_quote_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(post id(string))',
    ),


    'get_user_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'parameter should be array(username(string))',
    ),

    'get_user_reply_post' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'parameter should be array(username(string))',
    ),

    'get_latest_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct)),
        'docstring' => 'parameter should be array(start number(int), end bumber(int)) or no parameter',
    ),

    'get_unread_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct)),
        'docstring' => 'parameter should be array(start number(int), end bumber(int)) or no parameter',
    ),

    'get_subscribed_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'no need parameters for get_subscribed_topic, return first 20',
    ),

    'get_subscribed_forum' => array(
        'function'  => 'get_subscribed_forum_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_subscribed_forum',
    ),

    'get_user_info' => array(
        'function'  => 'get_user_info_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'parameter should be array(username(string))',
    ),

    'get_config' => array(
        'function'  => 'get_config_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_config',
    ),

    'logout_user' => array(
        'function'  => 'logout_user_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for logout_user',
    ),

    'new_topic' => array(
        'function'  => 'new_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(forum id(string), topic title(base64), topic content(base64), topic type id(string), attachments id(array), attachments group id(string))',
    ),

    'reply_post' => array(
        'function'  => 'reply_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(forum id(string), topic id(string), post title(base64), post content(base64), attachments id(array), attachment group id(string), bbcode enable(boolean))',
    ),
    
    'subscribe_topic' => array(
        'function'  => 'subscribe_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(topic id(string))',
    ),

    'unsubscribe_topic' => array(
        'function'  => 'unsubscribe_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(topic id(string))',
    ),

    'subscribe_forum' => array(
        'function'  => 'subscribe_forum_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(forum id(string))',
    ),

    'unsubscribe_forum' => array(
        'function'  => 'unsubscribe_forum_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(forum id(string))',
    ),

    'get_inbox_stat' => array(
        'function'  => 'get_inbox_stat_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter for get_inbox_stat, but need login first',
    ),

    'get_conversations' => array(
        'function'  => 'get_conversations_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'parameter should be array(start conv number(int), end conv number(int))',
    ),

    'get_conversation' => array(
        'function'  => 'get_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(conv id(string), bbcode enable(boolean))'
    ),

    'get_online_users' => array(
        'function'  => 'get_online_users_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter',
    ),

    'mark_all_as_read' => array(
        'function'  => 'mark_all_as_read_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(forum id(string)) or null',
    ),

    'search_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64)),
        'docstring' => 'parameter should be array(search key words(base64),start number(int), end number(int), search id(string))',
    ),

    'search_post' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64)),
        'docstring' => 'parameter should be array(search key words(base64),start number(int), end number(int), search id(string))',
    ),
    
    'search' => array(
        'function' => 'search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcStruct)),
    ),

    'get_participated_topic' => array(
        'function'  => 'search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'parameter should be array(username(base64), start number(int), end number(int))',
    ),

    'login_forum' => array(
        'function'  => 'login_forum_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'parameter should be arrsy(forum id(string), password(base64))',
    ),

    'invite_participant' => array(
        'function'  => 'invite_participant_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'new_conversation' => array(
        'function'  => 'new_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'reply_conversation' => array(
        'function'  => 'reply_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'get_quote_conversation' => array(
        'function'  => 'get_quote_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
        'docstring' => '',
    ),

    
    'delete_message' => array(
        'function'  => 'delete_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'get_message need one parameter as message id'
    ),
    
    'mark_pm_unread' => array(
        'function'  => 'mark_pm_unread_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'message id, box id',
    ),
    
    'get_quote_pm' => array(
        'function'  => 'get_quote_pm_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),
    
    'create_message' => array(
        'function'  => 'create_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'parameter should be array(array,string,string,[int, string])',
    ),
    
    'get_message' => array(
        'function'  => 'get_message_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBoolean)),
        'docstring' => 'get_message need one parameter as message id'
    ),
        
    'get_box_info' => array(
        'function'  => 'get_box_info_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter but need login first',
    ),
    
    'get_box' => array(
        'function'  => 'get_box_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcDateTime),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,int,int,date)',
    ),

    'get_dashboard' => array(
        'function'  => 'get_dashboard_func',
        'signature' => array(array($xmlrpcArray),
                            array($xmlrpcArray, $xmlrpcBoolean)),
        'docstring' => 'no parameters or boolean mark alerts read',
    ),

    'like_post' => array(
        'function'  => 'thl_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'int post_id to like',
    ),
    
    'unlike_post' => array(
        'function'  => 'thl_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'int post_id to unlike',
    ),
    
    "thank_post" => array(
        "function" => "thl_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
    ),
    
    "remove_thank_post" => array(
        "function" => "thl_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
    ),
    
    'report_post' => array(
        'function'  => 'report_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'int post_id to unlike, base64 optional reason',
    ),
    'report_pm' => array(
        'function'  => 'report_pm_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'int msg_id to unlike, base64 optional reason',
    ),


    'upload_attach' => array(
        'function' => 'upload_attach_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),
    
    'set_avatar' => array(
        'function' => 'upload_avatar_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),
    
    'upload_avatar' => array(
        'function' => 'upload_avatar_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),

    'get_id_by_url' => array(
        'function'  => 'get_id_by_url_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'string url',
    ),
    
    'authorize_user' => array(
        'function'  => 'authorize_user_func',
        'signature' => array(
            array($xmlrpcArray, $xmlrpcBase64, $xmlrpcString),
            array($xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64),
        ),
        'docstring' => 'b64 username, b64 password',
    ),
    
    'remove_attachment' => array(
        'function'  => 'remove_attachment_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'parameter should be',
    ),

    // below part is for moderation functions
    'm_stick_topic' => array(
        'function'  => 'm_stick_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_close_topic' => array(
        'function'  => 'm_close_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_delete_topic' => array(
        'function'  => 'm_delete_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_delete_post' => array(
        'function'  => 'm_delete_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_undelete_topic' => array(
        'function'  => 'm_undelete_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_undelete_post' => array(
        'function'  => 'm_undelete_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_delete_post_by_user' => array(
        'function'  => 'm_delete_post_by_user_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_move_topic' => array(
        'function'  => 'm_move_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
        'docstring' => '',
    ),
    
    'm_rename_topic' => array(
        'function'  => 'm_rename_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
        'docstring' => '',
    ),

    'm_move_post' => array(
        'function'  => 'm_move_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => '',
    ),

    'm_merge_topic' => array(
        'function'  => 'm_merge_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
        'docstring' => '',
    ),

    'm_get_moderate_topic' => array(
        'function'  => 'm_get_moderate_topic_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_get_moderate_post' => array(
        'function'  => 'm_get_moderate_post_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_approve_topic' => array(
        'function'  => 'm_approve_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_approve_post' => array(
        'function'  => 'm_approve_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
        'docstring' => '',
    ),

    'm_ban_user' => array(
        'function'  => 'm_ban_user_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => '',
    ),

    'm_get_report_post' => array(
        'function'  => 'm_get_report_post_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => '',
    ),
    
    'get_alert' => array(
    	'function' => 'get_alert_func',
    	'signature' => array(array($xmlrpcStruct),
    						 array($xmlrpcStruct, $xmlrpcInt),
    						 array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
    ),
    //**********************************************
    // Puch related functions
    //**********************************************
    
    'update_push_status' => array(
        'function' => 'update_push_status_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64)),
    ),
);
