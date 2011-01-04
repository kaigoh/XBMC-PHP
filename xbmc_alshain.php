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

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions
        are met:

        Redistributions of source code must retain the above copyright
        notice, this list of conditions and the following disclaimer.

        Redistributions in binary form must reproduce the above copyright
        notice, this list of conditions and the following disclaimer in the
        documentation and/or other materials provided with the distribution.

        Neither the name of Kai Gohegan nor the names of its contributors may
        be used to endorse or promote products derived from this software
        without specific prior written permission.

        THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
        "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
        LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
        A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
        HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
        INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
        BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
        OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
        AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
        LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
        WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.

        +-----------------------------------------------+
        |                     Usage                     |
        +-----------------------------------------------+


        Clases:
            XbmcHost
            XbmcJson
            XbmcHttp

        The XbmcHost-Class represents a computer running an instance of XBMC
        An instance thereof must be passed to the constructor.  API-Classes
        XbmcJson and XbmcHttp.  The XbmcHost-Class accepts two parameter
        formats:

            * A string:
                'username:password@host:port'
            * an array with the keys:
                user, pass, host, port

        Usage:

        $xbmcJson->NAMESPACE->COMMAND(
            array(PARAM1 => VALUE1, PARAM2 => VALUE2, etc..)
        );
        $xbmcJson->VideoLibrary->GetRecentlyAddedMovies();

        $xbmcHttp->COMMAND('arg1,arg2');
        $xbmcHttp->ExecBuiltIn("Notification(XBMC-PHP, Hello!)");

        The arguments may also be passed as array. The values will be joined
        recursively and wrapped with parenthenses.  All parameters will be
        urlencoded properly, regardless of the of the way the arguments are
        being passed.

        $xbmcHttp->ExecBuiltIn("Notification", array("XBMC-PHP", "Hello!"));

        References:
        http://wiki.xbmc.org/index.php?title=JSON_RPC
        http://wiki.xbmc.org/index.php?title=Web_Server_HTTP_API

**/

class XbmcException extends Exception{

}

class XbmcHost{
    private $_url = null;

    public function __construct() {
        // we con't know how many arguments we will get.
        $config = call_user_func_array(
            array($this, 'parseConfig'),
            func_get_args()
        );
        $config = $this->validateAndCleanConfig($config);
        $this->_url = $this->buildUrl($config);
        $this->assertReachableXbmc();
    }

    /**
     * Throw XbmcException if XBMC cannot be reached.
     */
    protected function assertReachableXbmc(){
        if(!$this->isXbmcReachable()){
            throw new XbmcException("Host could not be reached.");
        }
    }

    /**
     * Build an URL(string) from a config array
     */
    protected function buildUrl($config){
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
     *
     * Added 01/01/2011 - Suggestion from robweber
     *     http://forum.xbmc.org/showpost.php?p=678465&postcount=11
     *     Check how the URL, port, username and password is being passed in,
     *     via array or string.
     */
    protected function parseConfig($config){
        if(is_string($config)) {
            $config = parse_url($config);

            if($config === false) {
                throw new XbmcException('Bad URL parameters!');
            }
        }
        return $config;
    }

    /**
     * Check whether the XBMC with specified config can be
     * reached.
     */
    public function isXbmcReachable() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        return ($info['http_code'] == "200" || $info['http_code'] == "401");
    }

    /**
     * Implements url property.
     */
    public function __get($name){
        if($name == 'url'){
            return $this->_url;
        }
    }

    public function __set($name, $value){
        if($name == "url"){
            throw new XbmcException("Property is read-only!");
        }
        throw new XbmcException("Undefined property");
    }
}

class XbmcJson{
    protected $_xbmc;
    public function __construct($xbmc) {
        $this->_xbmc = $xbmc;
        $this->populateCommands($this->rpc("JSONRPC.Introspect")->commands);
    }

    private function populateCommands($remoteCommands) {
        foreach($remoteCommands as $remoteCommand) {
            $rpcCommand = explode(".", $remoteCommand->command);
            if(!class_exists($rpcCommand[0])) {
                $this->$rpcCommand[0] = new XbmcJsonCommand(
                    $rpcCommand[0],
                    $this);
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

        if (property_exists($response, 'error')){
            throw new XbmcException(
                $response->error->message,
                $response->error->code
            );
        }
        else if (property_exists($response, 'result')){
            return $response->result;
        }
        else{
            throw new XbmcException('Bad JSON-RPC response');
        }
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

    public function __construct(XbmcHost $xbmc) {
        $this->_xbmc = $xbmc;
    }

    public function __call($command, $args = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->buildUrl($command, $args));
        $response = curl_exec($ch);
        return strip_tags($response);
    }

    public function buildUrl($command, $args = array(), $params = array()){
        $args = (array) $args;
        $command .= $this->arrayToParamString($args); 
        $params['command'] = $command;
        $result = "http://";
        $result .= $this->_xbmc->url;
        $result .= '/xbmcCmds/xbmcHttp?';
        $result .= http_build_query($params);
        return $result;
    }

    protected function arrayToParamString($array){
        $result = array();
        foreach($array as $arg){
            if(is_array($arg)){
                $arg = $this->arrayToParamString($arg);
            }
            $result[] = $arg;
        }
        return "(".implode(",", $result).")";
    }
}
?>
