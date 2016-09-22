<?php
/**
 * Created by PhpStorm.
 * User: Luca
 * Date: 16/8/6
 * Time: 16:39
 */
namespace Mini;

class App{
    public $controller_path;
    public static $config = [];

    public function __construct($config_path,$env){
        if(!file_exists($config_path)) {
            throw new ErrorException('config file '.$config_path . ' is not exist');
        }
        $config = parse_ini_file($config_path,true);
        self::$config = $config[$env];
        $this->controller_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/app/controllers';
    }

    public function run(){
        $uri = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        $uri = substr($uri,1);
        if($uri == ''){
            $uri = 'index/index';
        }
        $routes = explode('/', $uri);
        $_controller_name = $routes[0]?$routes[0]:'index';
        $_action_name = (isset($routes[1])&&$routes[1])?$routes[1]:'index';

        $controller_file = $this->controller_path . '/' . $_controller_name . '.php';
        if (file_exists($controller_file)) {
            require $controller_file;
            $controllerClass = $_controller_name . 'Controller';
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
        throw new ErrorException($name . ' is not defined');
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
        $this->views_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/app/views';
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
            $model = $model_name . 'Model';
            self::$_model_instances[$model_name] = new $model($model_name);
        }
        return self::$_model_instances[$model_name];
    }

    public static function getDBInstance($db_name)
    {
        if (!isset(self::$_db_instances[$db_name])) {
            $db = $db_name . 'DB';
            self::$_db_instances[$db_name] = new $db();
        }
        return self::$_db_instances[$db_name];
    }
}


Class Model
{

    private static $model_name = '';
    private static $model_path = '';

    public static function getInstance($model_name){
        self::$model_name = $model_name;
        self::$model_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/app/models';
        $model_file = self::$model_path.'/' . $model_name . '.php';
        if (file_exists($model_file)) {
            include_once $model_file;
            return Singleton::getModelInstance($model_name);
        } else {
            throw new \ErrorException('model file' . $model_file . ' is not exist');
        }
    }

    public function db()
    {
        $db_file = self::$model_path . '/db/' . self::$model_name . '.php';
        if (file_exists($db_file)){
            require $db_file;
            return Singleton::getDBInstance(self::$model_name);
        }else{
            throw new \ErrorException('db file '. $db_file . ' is not exist');
        }
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

    public function dbReader($db_key = 'db')
    {
        $config = App::$config;
        $key = md5($config[$db_key.'.host']);
        if (!isset(self::$instances[$key])) {
            try {
                $db = new \PDO($config[$db_key.'.host'], $config[$db_key.'.user'], $config[$db_key.'.password']);
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

    public function fetch($sql,$params){
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function fetchAll($sql,$params){
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}