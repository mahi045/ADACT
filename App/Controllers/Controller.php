<?php
/**
 * Created by PhpStorm.
 * User: Hp User
 * Date: 4/19/2017
 * Time: 9:15 PM
 */

namespace AWorDS\App\Controllers;

use AWorDS\App\HttpStatusCode;
use AWorDS\App\Views\Template;
use AWorDS\App\Route;
use AWorDS\Config;

class Controller
{
    /**
     * @var int $response_code Any constant form HttpStatusCode
     */
    private $response_code = HttpStatusCode::OK;

    protected $_model;
    protected $_controller;
    protected $_action;
    protected $_method;
    protected $_params;
    protected $_url_params;
    protected $_template;

    /**
     * Output types (GUI, Redirect, JSON)
     *
     * @var bool $_redirect Redirect to the redirect location
     * @var bool $_HTML     Output to user
     * @var bool $_JSON     JSON output
     */
    protected $_redirect = false;
    protected $_HTML     = true;
    protected $_JSON     = false;

    protected $_redirect_location = Config::WEB_DIRECTORY;
    protected $_JSON_contents     = [];
    protected $_HTML_load_view     = true;

    private $_post_process = false;
    private $_post_process_info = [
        'object' => null,
        'method' => null,
        'arguments' => []
    ];

    /**
     * Controller constructor.
     * @param string $controller
     * @param string $action
     * @param string $method
     * @param array $params
     * @param array $url_params
     */
    function __construct($controller, $action, $method, $params, $url_params) {
        $this->_controller  = $controller;
        $this->_action      = $action;
        $this->_method      = $method;
        $this->_url_params  = $url_params;

        // FIXME: only GET and POST is implemented
        if(in_array($method, [Route::GET, Route::POST])){
            if($method == Route::GET){
                $parameters = $_GET;
                $input_method = INPUT_GET;
            }else{
                $parameters = $_POST;
                $input_method = INPUT_POST;
            }
            foreach($params as $param => &$value){
                $filter_type = $value;
                switch($filter_type){
                    case Route::BOOLEAN:
                        $value = isset($parameters[$param]) ?
                            (preg_match(Route::BOOLEAN, $parameters[$param]) ? true : false) : false;
                        break;
                    case Route::EMAIL:
                    case Route::FLOAT:
                    case Route::INTEGER:
                    case Route::STRING:
                    case Route::HTML:
                        $value = isset($parameters[$param]) ? filter_input($input_method, $param, $filter_type) : null; break;
                    default:
                        // FIXME: needed to be implemented upon required
                }
            }
        }
        $this->_params= $params;

        if($this->_HTML AND $this->_HTML_load_view) $this->_template = new Template($controller, $action);
    }

    /**
     * Get request parameters
     * @return array
     */
    function get_params(){
        return array_merge($this->_url_params, $this->_params);
    }

    /**
     * Call a particular model class
     *
     * @param null|string $model
     */
    function set_model($model = null){
        if($model == null) $model = $this->_controller;
        $model = '\\AWorDS\\App\\Models\\' . $model;
        $this->_model = $model;
        $this->$model = new $model;
        return $this->$model;
    }

    /**
     * Set a value for a variable
     *
     * This variable is accessible in various ways depends on user actions
     * - For a redirection, it does nothing and isn't accessible
     * - For an HTML request, it is accessible in the Template class
     * - For a JSON request, it's not accessible but sent as part of JSON output
     *
     * @param null|string $name  variable name
     * @param mixed       $value value of the variable
     */
    function set($name, $value){
        if($this->_HTML AND $this->_HTML_load_view) $this->_template->set($name, $value);
        elseif($this->_JSON){
            if($name == null){
                array_push($this->_JSON_contents, $value);
            }else{
                $this->_JSON_contents[$name] = $value;
            }
        }
    }

    /**
     * Response code of the current request
     *
     * @var int $code Any constants from HttpStatusCode
     */
    function response($code){
        $this->response_code = $code;
    }

    /**
     * Redirect to a certain page
     *
     * @param string $location
     */
    function redirect($location = Config::WEB_DIRECTORY){
        $this->_redirect = true;
        $this->_redirect_location = $location;
        $this->_HTML = false;
        $this->_JSON = false;
    }

    /**
     * Whether to load view or not
     *
     * Only applies when _HTML = true
     *
     * @param bool $isItOk
     */
    function load_view($isItOk){
        $this->_HTML_load_view = $isItOk;
    }

    /**
     * Output as JSON instead of HTML
     *
     * @var array|null $content An optional array which is to be outputted (also can be set by $this->set)
     */
    function json($content = null){
        $this->_JSON = true;
        $this->_HTML = false;
        $this->_redirect = false;
        if(is_array($content)) $this->_JSON_contents = $content;
    }

    function post_process($object, $method, $args = []){
        $this->_post_process = true;
        $this->_post_process_info['object'] = $object;
        $this->_post_process_info['method'] = $method;
        $this->_post_process_info['arguments'] = $args;
    }

    function __destruct(){
        if($this->_post_process) $this->__post_process();
        else $this->__send_response();
    }

    private function __post_process(){
        // at php.ini output_buffering = off
        // Disable gzip compression
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        // Disable apache compression
        //header( 'Content-Encoding: none; ' );

        ignore_user_abort(true);
        set_time_limit(0);

        ob_end_flush();
        ob_start();
        // do initial processing here
        // send the response: only text response
        $this->__send_response();
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
        // Close current session writing to prevent session locking
        if(session_id()) session_write_close();
        //error_log("Session Status: " . (session_status() == 1 ? "NONE" : "ACTIVE"));

        // Run post process functions
        if($this->_post_process_info['method'] != null){
            if($this->_post_process_info['object'] != null){
                call_user_func([$this->_post_process_info['object'], $this->_post_process_info['method']], $this->_post_process_info['arguments']);
            }else{
                call_user_func($this->_post_process_info['method'], $this->_post_process_info['arguments']);
            }
        }
    }

    private function __send_response(){
        //error_log("Session Status: " . (session_status() == 1 ? "NONE" : "ACTIVE"));
        http_response_code($this->response_code);
        if($this->_redirect) header("Location: {$this->_redirect_location}");
        elseif($this->_JSON) print json_encode($this->_JSON_contents, JSON_PRETTY_PRINT);
        elseif($this->_HTML AND $this->_HTML_load_view) $this->_template->render();
    }
}
