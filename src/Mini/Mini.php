<?php
/**
 * Created by PhpStorm.
 * User: Luca
 * Date: 16/8/6
 * Time: 16:39
 */
namespace Mini;

class App
{
    public $controller_path;
    public static $config = [];

    public function __construct($config_path, $env)
    {
        if (!file_exists($config_path)) {
            throw new \ErrorException('config file ' . $config_path . ' is not exist');
        }
        $config = parse_ini_file($config_path, true);
        self::$config = $config[$env];
        $this->controller_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/app/controllers';
    }

    public function run()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = substr($uri, 1);
        if ($uri == '') {
            $uri = 'index/index';
        }
        $routes = explode('/', $uri);
        $_controller_name = $routes[0] ? $routes[0] : 'index';
        $_action_name = (isset($routes[1]) && $routes[1]) ? $routes[1] : 'index';

        $controller_file = $this->controller_path . '/' . $_controller_name . '.php';
        if (file_exists($controller_file)) {
            require $controller_file;
            $controllerClass = ucfirst($_controller_name . 'Controller');
            $controller = new $controllerClass(['controllerName' => $_controller_name, 'actionName' => $_action_name]);
            $controller->{$_action_name . 'Action'}();
        } else {
            throw new \ErrorException('controller file ' . $controller_file . ' is not exist');
        }
    }
}


class Controller
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view()
    {
        return Singleton::getViewInstance('View', $this->data);
    }

    public function display($_display_name = '')
    {
        return Singleton::getViewInstance('View', $this->data)->display($_display_name);
    }

    public function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public function echoJson($data, $code, $message)
    {
        echo json_encode(['data' => $data, 'code' => $code, 'message' => $message]);
        exit;
    }

    public function getControllerName()
    {
        return $this->data['controllerName'];
    }

    public function getActionName()
    {
        return $this->data['actionName'];
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        throw new \ErrorException($name . ' is not defined');
    }
}

class View
{
    public $data = array();
    public $controller_name;
    public $action_name;
    public $views_path;

    public function __construct($params)
    {
        $this->views_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/app/views';
        $this->controller_name = $params['controllerName'];
        $this->action_name = $params['actionName'];
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function display($_display_name)
    {
        $_view_name = $_display_name ? $_display_name : $this->action_name;
        $_view_file = $this->views_path . '/' . $this->controller_name . '/' . $_view_name . '.php';
        if (file_exists($_view_file)) {
            extract($this->data);
            include $_view_file;
        } else {
            throw new \ErrorException('view file ' . $_view_file . ' is not exist');
        }
    }
}


class Singleton
{
    private static $_class_instances = array();
    private static $_model_instances = array();
    private static $_db_instances = array();
    private static $_redis_instances = array();
    private static $model_path = '';

    public static function getViewInstance($class_name, $params = array())
    {
        if (!isset(self::$_class_instances[$class_name])) {
            self::$_class_instances[$class_name] = new View($params);
        }
        return self::$_class_instances[$class_name];
    }

    public static function getModelInstance($model_name)
    {
        if (!isset(self::$_model_instances[$model_name])) {
            self::$model_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/app/models';
            $model_file = self::$model_path . '/' . $model_name . '.php';
            if (file_exists($model_file)) {
                require_once $model_file;
            } else {
                throw new \ErrorException('model file' . $model_file . ' is not exist');
            }
            $model = ucfirst($model_name . 'Model');
            self::$_model_instances[$model_name] = new $model($model_name);
        }
        return self::$_model_instances[$model_name];
    }

    public static function getDBInstance($db_name)
    {
        if (!isset(self::$_db_instances[$db_name])) {
            $db_file = self::$model_path . '/db/' . $db_name . '.php';
            if (file_exists($db_file)) {
                require_once $db_file;
            } else {
                throw new \ErrorException('db file ' . $db_file . ' is not exist');
            }
            $db = ucfirst($db_name . 'DB');
            self::$_db_instances[$db_name] = new $db();
        }
        return self::$_db_instances[$db_name];
    }

    public static function getRedisInstance($redis_name)
    {
        if (!isset(self::$_redis_instances[$redis_name])) {
            $redis_file = self::$model_path . '/redis/' . $redis_name . '.php';
            if (file_exists($redis_file)) {
                require_once $redis_file;
            } else {
                throw new \ErrorException('redis file ' . $redis_file . ' is not exist');
            }
            $redis = ucfirst($redis_name . 'Redis');
            self::$_redis_instances[$redis_name] = new $redis();
        }
        return self::$_redis_instances[$redis_name];
    }
}


Class Model
{


    public static function getInstance($model_name)
    {
        return Singleton::getModelInstance($model_name);
    }

    public function db()
    {
        $model_name = get_class($this);
        return Singleton::getDBInstance($model_name);
    }

    public function redis()
    {
        $model_name = get_class($this);
        return Singleton::getRedisInstance($model_name);
    }

    //重载__clone方法，不允许对象实例被克隆
    public function __clone()
    {
        throw new \ErrorException("Singleton Class Can Not Be Cloned");
    }
}


class DB
{
    private static $instances = [];
    public $db_key = 'db';

    public final function dbReader()
    {
        $config = App::$config;
        $key = md5($config[$this->db_key . '.host']);
        if (!isset(self::$instances[$key])) {
            try {
                $db = new \PDO('mysql:host='.$config[$this->db_key . '.host'].';dbname='.$config[$this->db_key . '.database'], $config[$this->db_key . '.user'], $config[$this->db_key . '.password']);
                $db->exec("SET NAMES utf8mb4");
            } catch (PDOException $e) {
                echo "数据库迷路了^_^";
                exit;
            }
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$instances[$key] = $db;
        }
        return self::$instances[$key];
    }

    public final function fetch($sql, $params)
    {
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public final function fetchAll($sql, $params)
    {
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public final function update($sql, $params)
    {
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert($sql, $params)
    {
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $this->dbReader()->lastInsertId();
    }

}


class Redis
{
    private static $instances = array();
    public $redis_key = 'redis';

    //类唯一实例的全局访问点
    public function redisReader()
    {
        $config = App::$config;
        $key = md5($config[$this->redis_key . '.host']);
        if (!isset(self::$instances[$key])) {
            $redis = new \Redis();
            $redis->connect($config[$this->redis_key . '.host'], $config[$this->redis_key . '.port'], 2);
            self::$instances[$key] = $redis;
        }
        return self::$instances[$key];
    }


    //重载__clone方法，不允许对象实例被克隆
    public function __clone()
    {
        throw new \Exception("Singleton Class Can Not Be Cloned");
    }

}

class Config
{
    public static function getAll()
    {
        return App::$config;
    }

    public static function getOne($name){
        if(!$name){
            throw new \Exception("config name can't be null");
        }
        if(!isset(App::$config[$name])){
            throw new \Exception("config name:".$name." is not existed");
        }
        return App::$config[$name];
    }
}