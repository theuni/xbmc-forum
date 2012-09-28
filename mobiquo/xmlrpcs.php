<?php


class Tapatalk_xmlrpcs extends xmlrpc_server {
	
	public function service($data=null, $return_payload=false){
		if ($data === null)
		{
			// workaround for a known bug in php ver. 5.2.2 that broke $HTTP_RAW_POST_DATA
			$ver = phpversion();
			if ($ver[0] >= 5)
			{
				$data = file_get_contents('php://input');
			}
			else
			{
				$data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
			}
		}
		$raw_data = $data;

		// reset internal debug info
		$this->debug_info = '';

		// Echo back what we received, before parsing it
		if($this->debug > 1)
		{
			$this->debugmsg("+++GOT+++\n" . $data . "\n+++END+++");
		}

		// Dark: HAAAX
		try { 
			$r = $this->parseRequestHeaders($data, $req_charset, $resp_charset, $resp_encoding);
			if (!$r)
			{
				$r=$this->parseRequest($data, $req_charset);
			}
		} catch (Exception $e){
			$error = $e->getMessage();
			if(!empty($error))
				$r = xmlresperror("Server error occurred [{$error}; darkxmlrpcs]");
		}
				
		// Dark: HAAAX x2
		if(!empty($GLOBALS['tapatalk_error'])){
			$r2 = xmlresperror($GLOBALS['tapatalk_error']);
		}
		if($r === null){
			$r = xmlresperror("Server error occurred [no response; darkxmlrpcs]");
		}
		
		// triple check
		if(!empty($r2) && $r2 instanceof xmlrpcresp)
			$r = $r2;
			
			
		// save full body of request into response, for more debugging usages
		$r->raw_data = $raw_data;

		if($this->debug > 2 && $GLOBALS['_xmlrpcs_occurred_errors'])
		{
			$this->debugmsg("+++PROCESSING ERRORS AND WARNINGS+++\n" .
				$GLOBALS['_xmlrpcs_occurred_errors'] . "+++END+++");
		}

		$payload=$this->xml_header($resp_charset);
		if($this->debug > 0)
		{
			$payload = $payload . $this->serializeDebug($resp_charset);
		}

		// G. Giunta 2006-01-27: do not create response serialization if it has
		// already happened. Helps building json magic
		if (empty($r->payload))
		{
			$r->serialize($resp_charset);
		}
		$payload = $payload . $r->payload;

		if ($return_payload)
		{
			return $payload;
		}

		// if we get a warning/error that has output some text before here, then we cannot
		// add a new header. We cannot say we are sending xml, either...
		if(!headers_sent())
		{
			header('Content-Type: '.$r->content_type);
			// we do not know if client actually told us an accepted charset, but if he did
			// we have to tell him what we did
			header("Vary: Accept-Charset");

			// http compression of output: only
			// if we can do it, and we want to do it, and client asked us to,
			// and php ini settings do not force it already
			$php_no_self_compress = ini_get('zlib.output_compression') == '' && (ini_get('output_handler') != 'ob_gzhandler');
			if($this->compress_response && function_exists('gzencode') && $resp_encoding != ''
				&& $php_no_self_compress)
			{
				if(strpos($resp_encoding, 'gzip') !== false)
				{
					$payload = gzencode($payload);
					header("Content-Encoding: gzip");
					header("Vary: Accept-Encoding");
				}
				elseif (strpos($resp_encoding, 'deflate') !== false)
				{
					$payload = gzcompress($payload);
					header("Content-Encoding: deflate");
					header("Vary: Accept-Encoding");
				}
			}

			// do not ouput content-length header if php is compressing output for us:
			// it will mess up measurements
			if($php_no_self_compress)
			{
				header('Content-Length: ' . (int)strlen($payload));
			}
		}
		else
		{
			error_log('XML-RPC: xmlrpc_server::service: http headers already sent before response is fully generated. Check for php warning or error messages');
		}
		
		@ob_end_clean();
		print $payload;

		// return request, in case subclasses want it
		return $r;
	}

}
