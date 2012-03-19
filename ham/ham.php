<?php

class Ham {
    private $_compiled_routes;
    public $routes;
    public $config;
    public $template_paths = array('./templates/');
    public function route($uri, $callback, $request_methods=array('GET')) {
        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods
        );
    }
    /**
     * Makes sure the routes are compiled then scans through them
     * and calls whichever one is approprate.
     */
    public function run() {
        echo $this->_route();
    }

    protected function _route() {
        $uri = str_replace($this->config['APP_URI'], '', $_SERVER['REQUEST_URI']);
        $compiled = $this->_get_compiled_routes();
        foreach($compiled as $route) {
            if(preg_match($route['compiled'], $uri, $args)) {
                $args[0] = $this;
                return call_user_func_array($route['callback'], $args);
            }
        }
        return abort(404);
    }

    protected function _get_compiled_routes() {
        $_k = 'compiled_routes';
        $compiled = Cache::get($_k);
        if($compiled)
            return $compiled;

        $compiled = array();
        foreach($this->routes as $route) {
            $route['compiled'] = $this->_compile_route($route['uri']);
            $compiled[] = $route;
        }
        Cache::set($_k, $compiled);
        return $compiled;
    }

    /**
     * Takes a route in simple syntax and makes it into a regular expression.
     */
    protected function _compile_route($uri) {
        $uri = str_replace('/', '\/', preg_quote($uri));
        $types = array(
            '<int>' => '(\d+)',
            '<string>' => '([a-zA-Z0-9\-_]+)',
            '<path>' => '([a-zA-Z0-9\-_\/])'
        );
        foreach($types as $k => $v) {
            $route = '/^' . str_replace(preg_quote($k), $v, $uri) . '$/';
        }
        return $route;
    }
    /**
     * Returns the contents of a template, populated with the data given to it.
     */
    public function render($name, $data) {
        $path = $this->_get_template_path($name);
        if(!$path)
            return abort(500, 'Template not found');
        ob_start();
        extract($data);
        require $path;
        return ob_get_clean();
    }

    public function config_from_file($filename) {
        require($filename);
        $conf = get_defined_vars();
        unset($conf['filename']);
        foreach($conf as $k => $v) {
            $this->config[$k] = $v;
        }
    }

    public function config_from_env($var) {
        return $this->config_from_file($_ENV[$var]);
    }

    protected function _get_template_path($name) {
        $_k = "template_path:{$name}";
        $path = Cache::get($_k);
        if($path)
            return $path;
        foreach($this->template_paths as $dir) {
            $path = $dir . $name;
            if(file_exists($path)) {
                Cache::set($_k, $path);
                return $path;
            }
        }
        return False;
    }
}

function abort($code, $message='') {
    return "<h1>{$code}</h1><p>{$message}</p>";
}



class XCache implements HamCompatibleCache {
    public function get($key) {
        return False;
        return xcache_get($key);
    }
    public function set($key, $value, $ttl) {
        return xcache_set($key, $value, $ttl);
    }
    public function inc($key, $interval=1) {
        return xcache_inc($key, $interval);
    }
    public function dec($key, $interval=1) {
        return xcache_dec($key, $interval);
    }
}

class APC implements HamCompatibleCache {
    public function get($key) {
        return apc_fetch($key);
    }
    public function set($key, $value, $ttl) {
        return apc_store($key, $value, $ttl);
    }
    public function inc($key, $interval=1) {
        return apc_inc($key, $interval);
    }
    public function dec($key, $interval=1) {
        return apc_dec($key, $interval);
    }
}

interface HamCompatibleCache {
    public function set($key, $value, $ttl);
    public function get($key);
    public function inc($key, $interval=1);
    public function dec($key, $interval=1);
}


class Cache {
    private static $_cache;
    /**
     * Making sure we have a cache loaded (APC or XCache), so we can provide
     * it as a singleton.
     */
    public static function init() {
        if(function_exists('xcache_set')) {
            static::$_cache = new XCache();
        } else if(function_exists('apc_add')) {
            static::$_cache = new APC();
        }
    }

    public static function set($key, $value, $ttl=1) {
        return static::$_cache->set($key, $value, $ttl);
    }
    public static function get($key) {
        return static::$_cache->get($key);
    }
    public static function inc($key, $interval=1) {
        return static::$_cache->inc($key, $interval);
    }
    public static function dec($key, $interval=1) {
        return static::$_cache->dec($key, $interval);
    }
}

Cache::init();