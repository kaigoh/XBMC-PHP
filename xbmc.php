<?php

/**

		+-----------------------------------------------+
		|       XBMC PHP Library - (C) Kai Gohegan, 2010   |
		+-----------------------------------------------+
		
		A PHP library for interacting with XBMC using
		JSON-RPC and XBMC's HTTP-API. Inspiration was
		drawn from Jason Bryant-Greene's php-json-rpc
		(https://bitbucket.org/jbg/php-json-rpc/src).
		
		+-----------------------------------------------+
		|                    License                    |
		+-----------------------------------------------+
				
		Copyright (c) 2010, Kai Gohegan
		All rights reserved.

		Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following
		conditions are met:

		Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
		
		Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer
		in the documentation and/or other materials provided with the distribution.
		
		Neither the name of Kai Gohegan nor the names of its contributors may be used to endorse or promote products derived
		from this software without specific prior written permission.
		
		THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING,
		BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
		SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
		DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
		INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
		OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

		+-----------------------------------------------+
		|                     Usage                     |
		+-----------------------------------------------+
		
		First, include the xbmc.php file:
		include("xbmc.php");
		
		Next, create the three classes in one of the below formats:
		
		1. Pass the URL parameters as an array
		
		$xbmcHost = new xbmcHost(array('host' => '0.0.0.0', 'port' => '8080', 'user' => 'username', 'pass' => 'password'));
		$xbmcJson = new xbmcJson($xbmcHost);
		$xbmcHttp = new xbmcHttp($xbmcHost);
		
		2. Pass the URL as a string:
		
		$xbmcHost = new xbmcHost('username:password@IP:Port');
		$xbmcJson = new xbmcJson($xbmcHost);
		$xbmcHttp = new xbmcHttp($xbmcHost);
		
		Then you can start making calls:
		
		JSON-RPC API (http://wiki.xbmc.org/index.php?title=JSON_RPC)
		Commands are sent using the following syntax:
		$xbmcJSON->NAMESPACE->COMMAND(array(PARAM1 => VALUE1, PARAM2 => VALUE2, etc..));
		eg: $xbmcJSON->VideoLibrary->GetRecentlyAddedMovies();
						
		HTTP-API API (http://wiki.xbmc.org/index.php?title=Web_Server_HTTP_API)
		Commands are sent using the following syntax:
		$xbmcHTTP->COMMAND("Parameter string in quotes. The string is automatically url-encoded.");
		eg: $xbmcHTTP->ExecBuiltIn("Notification(XBMC-PHP, Hello!)");

**/

/** <Exceptions> **/

class xbmcError extends Exception {

}

/** </Exceptions> **/

/** <Config> **/

class xbmcHost {
	/*
		Added 01/01/2011 - Suggestion from robweber (http://forum.xbmc.org/showpost.php?p=678465&postcount=11)
		Checks the config parameters (URL, port, username and password).
	*/
		
	private $_url = "";
	private $_host = "";
	private $_port = "8080";
	private $_user = "";
	private $_pass = "";

	public function __construct($config) {
	
		$url = "";
		
		if(is_string($config)) {
		
			/*
				The config has been passed in as a string. Clean and populate URL parameters...
			*/
			$config = parse_url($config);
			if($config === false) {
			/*
				Bad config recieved, throw exception...
			*/
				throw new xbmcError('Bad URL parameters');			
			}
		} else if(is_array($config)) {
			/*
				The config has been passed in as an array. Clean it up and populate the command list...
			*/
			$config = array_change_key_case($config, CASE_LOWER);
		} else {
			/*
				Bad config recieved, throw exception...
			*/
			throw new xbmcError('Bad URL parameters');
		}
		
		/*
			If a username and password have been specified, inject
			them into the URL string.
		*/
		if(array_key_exists('user', $config)) {
			$url .= $config['user'];
			$this->_user = $config['user'];
			if(array_key_exists('pass', $config)) {
				$this->_pass = $config['pass'];
				$url .= ":".$config['pass'];
			}
			$url .= "@";
		}		
		
		if(array_key_exists('host', $config)) {
			$this->_host = $config['host'];
			/*
				Check we have specified a port number. If not, use the default (8080).
			*/
			if(!array_key_exists('port', $config)) {
				$config['port'] = "8080";
			} else {
				$this->_port = $config['port'];
			}
			/*
				Complete the URL string
			*/
			$url .= $config['host'].":".$config['port'];
			/*
				Check the XBMC host is online.
			*/			
			if($this->isHostAlive($config['host'], $config['port'])) {
				$this->_url = $url;
			} else {
			/*
				XBMC host is offline or bad connection parameters
			*/
				throw new xbmcError('XBMC web server not detected - host offline, incorrect URL, bad username or password?');	
			}
		} else {
		/*
			Bad config recieved, throw exception...
		*/
			throw new xbmcError('Bad URL parameters');				
		}	
	}

	private function isHostAlive($host, $port = "8080") {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "http://".$host.":".$port);
		curl_exec($ch);
		$info = curl_getinfo($ch);
		if($info['http_code'] == "200" || $info['http_code'] == "401") {
			return true;
		} else {
			return false;
		}
	}
	
	public function url() {
		return $this->_url;
	}
	
	public function host() {
		return $this->_host;
	}
	
	public function port() {
		return $this->_port;
	}
	
	public function user() {
		return $this->_user;
	}

}

/** </Config> **/

/** <JSON-RPC> **/

class xbmcJsonRPC {

	private $_url;

    protected $_debug = false;

    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    public function getDebug()
    {
        return $this->_debug;
    }

	public function __construct() {
		
	}
	
	public function setUrl($url) {
		$this->_url = $url;
	}
	
	public function getUrl() {
		return $this->_url;
	}

    protected function debug($message) {
        if (!$this->getDebug()) {
            return;
        }
        error_log($message, 0);
    }

	public function rpc($method, $params = null) {
		$uid = rand(1, 9999999);

		$json = array(
			'jsonrpc' => '2.0',
			'method' => $method,
            'id' => $uid
        );

        if (!empty($params)) {
            $json['params'] = $params;
        }
		$request = json_encode($json);

        $url = "http://".$this->_url."/jsonrpc?" . $method;
        $this->debug(sprintf('Request to URL "%s": %s', $url, var_export($request, true)));

        $ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        $responseRaw = curl_exec($ch);

        if ($responseRaw === false) {
            throw new xbmcError('cURL Error: ' . curl_error($ch));
        }

        $response = json_decode($responseRaw);

        $this->debug('Response: ' . var_export($response,true));

        if ($response->id != $uid) {
			throw new xbmcError('JSON-RPC ID Mismatch');
		}

		if (property_exists($response, 'error')) {
			/*
				Instead of killing the script just because of one JSON-RPC error,
				lets throw an E_USER_NOTICE instead...
				old: throw new xbmcError($response->error->message, $response->error->code);
			*/
			trigger_error($response->error->message."(".$response->error->code.")");
		} else if(property_exists($response, 'result')) {
			return $response->result;
		} else {
			throw new xbmcError('Bad JSON-RPC response');
		}
	}
	
}

class xbmcJson extends xbmcJsonRPC {
	
	public function __construct(xbmcHost $xbmcHost, $debug = false) {
        $this->setDebug($debug);
		parent::setUrl($xbmcHost->url());
		$this->populateCommands($this->rpc("JSONRPC.Introspect")->methods);
	}
	
	private function populateCommands($remoteCommands) {
		foreach($remoteCommands as $command=>$remoteCommand) {
			$rpcCommand = explode(".", $command);
			if(!class_exists($rpcCommand[0])) {
				$this->$rpcCommand[0] = new xbmcJsonCommand($rpcCommand[0], parent::getUrl(), $this);
			}
		}
	}
	
}

class xbmcJsonCommand {

	private $_name;
	private $_url;
	private $_xbmcJson;
	
	public function __construct($name, $url, xbmcJson $xbmcJson) {
		$this->_name = $name;
		$this->_url = $url;
		$this->_xbmcJson = $xbmcJson;
	}

	public function __call($method, $args = null) {
		return $this->_xbmcJson->rpc($this->_name.".".$method, $args[0]);
	}
	
}
/** </JSON-RPC> **/

/** <HTTP-API> **/
class xbmcHttp {

	private $_url;
	
	public function __construct(xbmcHost $xbmcHost) {
		$this->_url = $xbmcHost->url();
	}
	
	public function setUrl($url) {
		$this->_url = $url;
	}
	
	public function getUrl() {
		return $_url;
	}

	public function __call($method, $args = "") {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "http://".$this->_url."/xbmcCmds/xbmcHttp?command=".$method."(".urlencode($args[0]).")");
		$response = curl_exec($ch);
		return strip_tags($response);
	}
	
	public function hostAlive($host, $port = "8080") {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "http://".$host.":".$port);
		curl_exec($ch);
		$info = curl_getinfo($ch);
		if($info['http_code'] == "200" || $info['http_code'] == "401") {
			return true;
		} else {
			return false;
		}
	}
}
/** </HTTP-API> **/
?>
