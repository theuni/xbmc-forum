<?php

error_reporting(E_ALL & ~E_NOTICE);

if (isset($_GET['checkip']))
{
    if (ini_get('allow_url_fopen'))
    {
        print do_post_request(array('ip' => 1));
    }
    else
        print '';
}
else
{
    echo 'Tapatalk push notification test: <b>';
    if (!ini_get('allow_url_fopen'))
        echo 'Failed<br />allow_url_fopen</b> is "off" in php.ini - Turning this on and try again!';
    else
    {
        $return_status = do_post_request(array('test' => 1));
        
        if ($return_status === '1')
            echo 'Success</b>';
        else
            echo 'Failed</b><br />'.$return_status;
    }
}

function do_post_request($data, $optional_headers = null)
{
    $url = 'http://push.tapatalk.com/push.php';
    
    $params = array('http' => array(
        'method' => 'POST',
        'content' => http_build_query($data, '', '&'),
    ));
    
    if ($optional_headers!== null) {
        $params['http']['header'] = $optional_headers;
    }
    
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) return false;
    $response = @stream_get_contents($fp);
    
    return $response;
}