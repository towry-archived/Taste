<?php

namespace Taste;

class Taste
{
    private $settings = array();
    private $routes = array();
    private $stash = array();

    public function __construct($config = null)
    {
        $this->checkVersion();

        $this->settings = array(
            'app_root'    => null,
            'baseurl'     => '/dispatch', /* base url for the app */
            'templates'   => 'templates', /* the templates dir */
            'controllers' => 'controllers', /* the controllers dir */
            'database'    => array(
                'adapter' => 'mysql',
                'host'    => 'localhost',
                'user'    => 'root',
                'password'=> 'root',
                'charset' => 'utf8',
                'dbname'=> 'test',
            ), /* database configure */
            'helpers'     => 'helpers',
        );

        $this->routes = array(
            'errors' => [], /** error callbacks */
            'any'    => [], /** any methods */
            'get'    => [], /** get method */
            'post'   => [], /** post method */
            'put'    => [], /** put method */
            'delete' => [], /** delete method */
        );

        $this->stash = array(
            'env' => array(
            ),
            'context' => array(
                'self' => $this,
            ),
            'helpers' => array(
            ),
        ); /** others */
        $this->setupEnv($this->stash['env']);
        
        if ($config !== null) {
            $this->configSetup($config);
        }

        // guard
        define('ROOT_PATH', $this->stash['env']['app_root']);
    }

    private function checkVersion()
    {
        $required = "5.4.0";

        if (version_compare(phpversion(), $required, '<')) {
            throw new \Exception("Taste framework requires PHP Version greater than {$required}.", 500);
        }
    }

    private function configSetup($config)
    {
        if (!is_array($config) && is_string($config)) {
            if (file_exists($config)) {
                /** current only support php file */
                $config = @include($config);
                if (!$config) return false;
            }
        } else if (!is_array($config)) {
            return false;
        }

        $settings = isset($config['settings']) ? $config['settings'] : array();
        $routes = isset($config['routes']) ? $config['routes'] : array();
        
        $this->arrayMerge($this->settings, $settings);
        $this->arrayMerge($this->routes, $routes);
    }

    protected function setupEnv(&$env)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $requestUri = $_SERVER['REQUEST_URI'];
        $queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

        if (strpos($requestUri, $scriptName) !== false) {
            // /app/index.php?q=2 | /app?q=2
            // /app/index.php
            // without rewrite
            $physicalPath = $scriptName;
            $basePath = dirname($physicalPath);
        } else {
            $physicalPath = str_replace('\\', '', dirname($scriptName));
            $basePath = $physicalPath;
        }
        $env['base_path'] = $basePath;

        $env['script_name'] = rtrim($physicalPath, '/');

        $env['path_info'] = substr_replace($requestUri, '', 0, strlen($physicalPath));
        $env['path_info'] = str_replace('?' . $queryString, '', $env['path_info']);
        $env['path_info'] = '/' . ltrim($env['path_info'], '/');

        $env['query_string'] = $queryString;
        $env['url_scheme'] = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https';
        $env['raw_input'] = @file_get_contents('php://input');
        $env['raw_error'] = @fopen('php://stderr', 'w');

        $env['method'] = strtolower($_SERVER['REQUEST_METHOD']);
        $env['app_root'] = $_SERVER['DOCUMENT_ROOT'] . trim($basePath, '/');
    }

    /**
     * This may be not work
     */
    private function arrayMerge($dest, $src, $type = 0)
    {
        if (count($src) == 0) return false;

        switch ($type) {
            case 0:
                foreach ($src as $key => $value) {
                    if ($isset($dest[$key])) {
                        if (is_array($value)) {
                            $this->arrayMerge($dest[$key], $value);
                        } else {
                            $dest[$key] = $value;
                        }
                    }
                }
                break;

            case 1:
                foreach ($src as $key => $value) {
                    if (is_array($value)) {
                        $this->arrayMerge($dest[$key], $value);
                    } else {
                        $dest[$key] = $value;
                    }
                }                
            
            default:
                throw new \BadMethodCallException("Invalid arguments.", 500);
                break;
        }
    }

    public function map(/* $method, $path, $handler */) 
    {
        $args = func_get_args();
        $this->routes;

        switch (count($args)) {
            case 3:
                /** ['post', 'get'], path, handler */
                foreach ((array) $args[0] as $method) {
                    $this->routes[strtolower($method)][] = [
                        '/' . trim($args[1], '/'),
                        $args[2]
                    ];
                }
                break;

            case 2:
                $this->routes['any'][] = [
                    '/' . trim($args[0], '/'),
                    $args[1],
                ];
                break;
            
            default:
                throw new \BadFunctionCallException("Invalid number of arguments.", 500);
                break;
        }
    }

    /**
     * Currently not support any.
     * 
     * @return boolean
     */
    public function mapRoute()
    {
        $env = $this->stash['env'];
        $method = $env['method'];
        $path = $env['path_info'];

        $routes = $this->routes;

        $handler = null;
        $params = [];

        $routesMatchMethod = $routes[$method];
        foreach ($routesMatchMethod as $route) {
            list($url, $handler) = $route;

            $urlRexp = preg_replace('#\{(\w+)\}#', '([^/]+)', $url);
            $urlRexp = '@^' . $urlRexp . '$@';

            if (preg_match($urlRexp, $path, $matched)) {
                $params = count($matched) > 1 ? $matched[1] : [];
                break;
            } else {
                continue;
            }
        }

        if ($handler) {
            
        } else {
            echo "404 Not found.";
        }
    }

    public function render($tpl, $context = array())
    {
        $file = $this->stash['env']['app_root'] . '/' . trim($this->settings['templates'], '/') . '/' . $tpl;

        ob_start();
        ob_implicit_flush();

        if (file_exists($file)) {
            $this->arrayMerge($this->stash['context'], $context, 1);
            extract($this->stash['context']);
            include $file;
        } else {
            throw new \RuntimeException("Template {{$file}} not found.", 500);
        }

        $content = ob_get_clean();
        return $content;
    }

    public function display($tpl, $context = array())
    {
        $content = $this->render($tpl, $context);

        echo $content;
    }

    public function partial($tpl, $context = array())
    {
        $file = $this->stash['env']['app_root'] . '/' . trim($this->settings['templates'], '/') . '/' . $tpl;

        if (file_exists($file)) {
            $this->arrayMerge($this->stash['context'], $context, 1);
            extract($this->stash['context']);
            include $file;
        } else {
            throw new \RuntimeException("Partial Template {{$file}} not found.", 500);
        }
    }

    public function session()
    {
        $args = func_get_args();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        switch (count($args)) {
            case 1:
                return (isset($_SESSION[$args[0]]) ? $_SESSION[$args[0]] : null);
                break;
            
            case 2:
                $key = $args[0];
                $value = $args[1];

                if ($value === null) {
                    if (isset($_SESSION[$key])) {
                        unset($_SESSION[$key]);
                    }
                } else {
                    $_SESSION[$key] = $value;
                }
                break;

            default:
                throw new \BadMethodCallException("Invalid arguments.", 500);
                break;
        }
    }

    public function cookie($key, $value = null, $expire = 31536000, $path = null)
    {
        if ($path === null) {
            $path = $this->stash['env']['base_path'];
        }

        if (func_num_args() === 1) {
            return (isset($_COOKIE[$key]) ? $_COOKIE[$key] : null);
        }

        setcookie($key, $value, time() + $expire, $path);
    }
}
