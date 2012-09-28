<?php

defined('IN_MOBIQUO') or exit;

class Tapatalk_Input {

    const INT = 'INT';
    const STRING = 'STRING';
    const ALPHASTRING = 'ALPHASTRING';
    const RAW = 'RAW';

    static public function filterXmlInput(array $filters, $xmlrpc_params){
        global $db, $mybb;

        $params = php_xmlrpc_decode($xmlrpc_params);

        // handle upload requests etc.
        if(empty($params) && !empty($_POST['method_name'])){
            $params = array();
            foreach($filters as $name => $type){
                if(isset($_POST[$name])){
                    $params[]=$_POST[$name];
                }
            }
        }

        $data = array();
        $i = 0;
        foreach($filters as $name => $type){
            switch($type){
                case self::INT:
                    if(isset($params[$i]))
                        $data[$name] = intval($params[$i]);
                    else
                        $data[$name] = 0;
                    break;
                case self::ALPHASTRING:
                    if(isset($params[$i]))
                        $data[$name] = preg_replace("#[^a-z\.\-_]#i", "", $params[$i]);
                    else
                        $data[$name] = '';
                    $data[$name.'_esc'] = $db->escape_string($data[$name]);
                    break;
                case self::STRING:
                    if(isset($params[$i]))
                        $data[$name] = $params[$i];
                    else
                        $data[$name] = '';
                    $data[$name.'_esc'] = $db->escape_string($data[$name]);
                    break;
                case self::RAW:
                    $data[$name] = $params[$i];
                    break;
            }
            $i ++;
        }

        return $data;
    }

}