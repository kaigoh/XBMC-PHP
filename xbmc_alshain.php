<?php

/**

	Code does not run...yet. I havn't had a chance
	to go through and double check where the errors
	are and correct them, hope to do that tommorrow.


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
        
        Next, create the two classes in one of the below formats:
        
        1. Pass the URL parameters as an array
        
        $xbmcConfig = array('host' => '0.0.0.0', 'port' => '8080', 'user' => 'username', 'pass' => 'password');
        $xbmcJson = new xbmcJson($xbmcConfig);
        $xbmcHttp = new xbmcHttp($xbmcConfig);
        
        2. Pass the URL as a string:
        
        $xbmcJson = new xbmcJson('username:password@IP:Port');
        $xbmcHttp = new xbmcHttp('username:password@IP:Port');
        
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

class XbmcException extends Exception{

}
/** <JSON-RPC> **/

class Xbmc{
    private $_url = null;

    public function __construct() {
        $config = $this->parseConfig(func_get_args());
        $this->_config = $this->validateAndCleanConfig($config);
        $this->url = $this->buildUrl();
        $this->assertReachableXbmc();
    }

    /**
     * Throw XbmcException if XBMC cannot be reached.
     */
    protected function assertReachableXbmc($config = null){
        if(!$this->isXbmcReachable($config)){
            throw XbmcException("Host could not be reached.");
        }
    }

    /**
     * Build an URL(string) from a config array
     */
    protected function buildUrl($config){
        /**
         * Build a URL from a config array.
         */

        // First validate config
        $port = 8080;
        extract($config);
        $url = '';
        if(!empty($user)){
            $url = $user;
            $url .= (!empty($pass)) ? ":${pass}": "";
            $url .= '@';
        }
        return $url."${host}:${port}";
    }

    /**
     * Remove unnecessary pairs from array
     * and ensure the requierd host key exists.
     */
    protected function validateAndCleanConfig($config){
        $valid_keys = array("user", "pass", "host", "port");
        $required_keys = array("host");
		print_r($config);
        foreach($required_keys as $key) {
            if(!array_key_exists($key, $config)) {
                throw new XbmcException("Missing config key: ".$key);
            }
        }
        $subset = array_fill_keys($valid_keys, null);
        $cleaned = array_intersect_key($config, $subset);
        return $cleaned;
    }

    /**
    * Process the constructor argument and try returning
    * a well formed configuration array containing the
    * following indexes: user, password, host, port
    * Added 01/01/2011 - Suggestion from robweber (http://forum.xbmc.org/showpost.php?p=678465&postcount=11)
    * Check how the URL, port, username and password is being passed in, via array or string.
    */
    protected function parseConfig($config){
        if(is_string($config)) {
            $config = parse_url($config);

            if($config === false) {
                throw new XbmcException('Bad URL parameters!');         
            }
        } 
        // removing the if, I'd drop that process entirely.
        $config = array_change_key_case($config, CASE_LOWER);
        return $config;
    }
    
    /**
     * Check whether the XBMC with specified config can be
     * reached.
     */
    public function isXbmcReachable($config = null) {
        $config = (!is_null($config)) ? $config : $this->_config;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $tihs->buildUrl($config));
        curl_exec($ch);
        $info = curl_getinfo($ch);
        return ($info['http_code'] == "200" || $info['http_code'] == "401");
    }

    public function __get($name){
        $valid_getters = array("url");
        if(in_array($name, $valid_getters)){
            $getter_name = "get".ucwords($url);
            return $this->$getter_name; 
        }
    }

    public function __set($name, $value){
        $valid_setters = array("url");
        if(in_array($name, $valid_setters)){
            $getter_name = "get".ucwords($url);
            return $this->$getter_name; 
        }
    }

    public function setUrl($url) {
        $this->_url = $url;
    }

    public function getUrl() {
        return $this->_url;
    }
}

class XbmcJson{
    protected $_xbmc;
    public function __construct() {
        $this->_xbmc = new Xbmc(func_get_args());
        $this->populateCommands($this->rpc("JSONRPC.Introspect")->commands);
    }
    
    private function populateCommands($remoteCommands) {
        foreach($remoteCommands as $remoteCommand) {
            $rpcCommand = explode(".", $remoteCommand->command);
            if(!class_exists($rpcCommand[0])) {
                $this->$rpcCommand[0] = new XbmcJsonCommand($rpcCommand[0], $this);
            }
        }
    }

    public function rpc($method, $params = NULL) {
        
        $uid = rand(1, 9999999);

        $json = array(
        'jsonrpc' => '2.0',
        'method'  => $method,
        'params'  => $params,
        'id'      => $uid
        );

        $request = json_encode($json);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "http://".$this->_xbmc->url."/jsonrpc");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        $responseRaw = curl_exec($ch);
        
        $response = json_decode($responseRaw);

        if ($response->id != $uid) {
            throw new XbmcException('JSON-RPC ID Mismatch');
        }

        if (property_exists($response, 'error'))
        throw new XbmcException($response->error->message, $response->error->code);
        else if (property_exists($response, 'result'))
        return $response->result;
        else
        throw new XbmcException('Bad JSON-RPC response');
    }
}

class XbmcJsonCommand {
    private $_name;
    private $_xbmcJson;
    
    public function __construct($name, xbmcJson $xbmcJson) {
        $this->_name = $name;
        $this->_xbmcJson = $xbmcJson;
    }

    public function __call($method, $args = array()) {
        return $this->_xbmcJson->rpc($this->_name.".".$method, $args);
    }
    
}
/** </JSON-RPC> **/

/** <HTTP-API> **/
class XbmcHttp {
    protected $_xbmc;

    public function __construct() {
        $this->_xbmc = new Xbmc(func_get_args());
    }

    public function __call($method, $args = "") {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, "http://".$this->_xbmc->url."/xbmcCmds/xbmcHttp?command=".$method."(".urlencode($args[0]).")");
        $response = curl_exec($ch);
        return strip_tags($response);
    }
}
/** </HTTP-API> **/
?>
