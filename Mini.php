<?php
/**
 * Created by PhpStorm.
 * User: Luca
 * Date: 16/8/6
 * Time: 16:39
 */
namespace Mini;

class Router{
    public function run(){
        $keys = array_keys($_GET);
        $uri = isset($keys[0])?$keys[0]:'index/index';
        $routes = explode('/', $uri);
        $_controller_name = $routes[0]?$routes[0]:'index';
        $_action_name = (isset($routes[1])&&$routes[1])?$routes[1]:'index';

        if (file_exists(__DIR__ . '/controllers/' . $_controller_name . '.php')) {
            require __DIR__ . '/controllers/' . $_controller_name . '.php';
            $controllerClass = $_controller_name . 'Controller';
            $controller = new $controllerClass(['controllerName' => $_controller_name, 'actionName' => $_action_name]);
            $controller->{$_action_name . 'Action'}();
        } else {
            throw new ErrorException('controller file ' . $_controller_name . ' is not exist');
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
        return Singleton::getClassInstance('View', $this->data);
    }

    public function display(...$_display_name)
    {
        return Singleton::getClassInstance('View', $this->data)->display(...$_display_name);
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

    public function __construct($params)
    {
        $this->controller_name = $params['controllerName'];
        $this->action_name = $params['actionName'];
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function display(...$_display_name)
    {
        $_view_name = $_display_name ? $_display_name[0] : $this->action_name;
        if (file_exists(__DIR__ . '/views/' . $this->controller_name . '/' . $_view_name . '.php')) {
            extract($this->data);
            include __DIR__ . '/views/' . $this->controller_name . '/' . $_view_name . '.php';
        } else {
            throw new ErrorException('view file ' . $_view_name . '.php is not exist');
        }
    }
}


class Singleton
{
    private static $_class_instances = array();
    private static $_model_instances = array();
    private static $_db_instances = array();

    public static function getClassInstance($class_name, $params = array())
    {
        if (!isset(self::$_class_instances[$class_name])) {
            self::$_class_instances[$class_name] = new $class_name($params);
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

    private $model_name = '';

    public function __construct($model_name)
    {
        $this->model_name = $model_name;
    }

    public function db()
    {
        if (file_exists(__DIR__ . '/models/db/' . $this->model_name . '.php')){
            require __DIR__ . '/models/db/' . $this->model_name . '.php';
            return Singleton::getDBInstance($this->model_name);
        }else{
            throw new ErrorException('db file '.$this->model_name.' is not exist');
        }
    }

    //重载__clone方法，不允许对象实例被克隆
    public function __clone()
    {
        throw new Exception("Singleton Class Can Not Be Cloned");
    }
}


class DB
{
    private static $instances = array();

    public function dbReader($db_key = 'default')
    {
        include __DIR__ . '/../config/config.php';
        $db_config = db_config();
        $config_db = $db_config[$db_key];
        $key = md5($config_db['host']);
        if (!isset(self::$instances[$key])) {
            try {
                $db = new PDO($config_db['host'], $config_db['user'], $config_db['password']);
                $db->exec("SET NAMES utf8mb4");
            } catch (PDOException $e) {
                echo "数据库迷路了^_^";
                exit;
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instances[$key] = $db;
        }
        return self::$instances[$key];
    }

    public function fetch($sql,$params){
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll($sql,$params){
        $stmt = $this->dbReader()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function M($name)
{
    if (file_exists(__DIR__ . '/models/' . $name . '.php')) {
        include_once __DIR__ . '/models/' . $name . '.php';
        return Singleton::getModelInstance($name);
    } else {
        throw new ErrorException('model file' . $name . ' is not exist');
    }
}

