<?

namespace Gearbox;

use Gearbox\ActionView\Render as render;
use Gearbox\ActiveSupport as AcS;
use Gearbox\ActionPatk\Route;
use Gearbox\ActionPatk\Params;
use Gearbox\Engine;


class ActionPatk{

  var $class_controller;
  var $controller_name;
  var $object_controller;
  var $helper_name;
  var $object_helper;
  var $action_name;
  var $attributes;
  var $params;
  var $format;
  var $layout_default;
  var $before_action = [];
  var $after_action = [];
  var $helper_method = [];
  var $views;
  var $partials;

  static $global_params;
  static $global_format;
  static $global_root;
  static $current_action = null;
  static $other_actions = [];


  static function runPrimaryAction(){
    Route::setRoutes();
		$route = Route::readRoute();

		if(empty($route)) return false;

    self::$global_params = $route->values_params;
    self::$global_format = $route->format;
    self::$global_root = $route;

    echo self::newAction($route->getActionPatk());

		return true;
  }

  static function newAction($actionPatk, $options= []){
    if(!empty(self::$current_action)){
      self::$other_actions[] = self::$current_action;
    }
    self::$current_action =  new ActionPatk($actionPatk, $options);

    $response = self::$current_action->build();

    if(!empty(self::$other_actions))
      self::$current_action = array_pop(self::$other_actions);
    else
      self::$current_action = null;

    return $response;
  }

  static function get($field = null){
    if(!empty($field)){
      return self::$current_action->$field;
    } else {
      return self::$current_action;
    }
  }

  static function set($field, $value){
    self::$current_action->$field = $value;
  }

  static function getView($view){
    $format = self::get('format');
    if(isset(self::get('views')[$format][$view]))
      return self::get('views')[$format][$view];
    else
      throw new \Exception("View não encontrada: {$view}.{$format}.php");
  }

  static function getPartial($partial){
    $format = self::get('format');
    if(isset(self::get('partials')[$format][$partial]))
      return self::get('partials')[$format][$partial];
    else
      throw new \Exception("Partial não encontrada: {$partial}.{$format}.php");
  }

  static function getAttributes($field){
    return isset(self::get('attributes')[$field]) ? self::get('attributes')[$field] : null;
  }

  static function setAttributes($field, $value){
    $attributes = self::get('attributes');
    $attributes[$field] = $value;
    self::set('attributes', $attributes);
  }

  static function getParams($field = null, $filter = null){
    $params = self::get('params');
    if(!empty($field)){
      $keys = array_keys($params);
      $field = in_array($field, $keys) ? $field : ":$field";
      if(in_array($field, $keys))
        $params = $params[$field];
    }
    if(!empty($filter)){
      $reflectionMethod = new \ReflectionMethod(self::get('controller_name'), $filter);
      return $reflectionMethod->invoke(self::get('object_controller'), $params);
    } else {
      return $params;
    }
  }

  static function callHelperMethod($method, $args = []){
    $isset = in_array($method, self::get('helper_method'));
    if($isset){
      $reflectionMethod = new \ReflectionMethod(self::get('controller_name'), $method);
      return $reflectionMethod->invokeArgs(self::get('object_controller'), $args);
    } else {
      $reflectionMethod = new \ReflectionMethod(self::get('helper_name'), $method);
      return $reflectionMethod->invokeArgs(self::get('object_helper'), $args);
    }
  }

  static function contollerPath(){
    return Engine::baseDir().'/app/controllers/'.implode('/',array_map(['Gearbox\ActiveSupport','underscore'],preg_split("/\\\\/",self::get("controller_name")))).'.php';
  }

  function __construct($actionPatk, $options){
    $aActionPatk = explode('#', $actionPatk);
    $this->controller_name =    $aActionPatk[0];
    $this->action_name =  $aActionPatk[1];

    $this->class_controller = new \ReflectionClass($this->controller_name );
    $this->params = isset($options['params']) ? $options['params'] : self::$global_params;
    $this->format = isset($options['format']) ? $options['format'] : self::$global_format;
    $this->layout_default = $this->getAttributesController('layout_default');

    $methods = $this->getMethods();
    foreach($methods as $method){
      if(preg_match("/@beforeAction\((.*)\)/", $method->getDocComment(), $groups)){
        if($groups[1] == '*' || in_array($this->action_name, explode(',',$groups[1]))){
          $this->before_action[] = $method->name;
        }
      }
      if(preg_match("/@afterAction\((.*)\)/", $method->getDocComment(), $groups)){
        if($groups[1] == '*' || in_array($this->action_name, explode(',',$groups[1]))){
          $this->after_action[] = $method->name;
        }
      }
      if(preg_match("/@helperMethod\(\)/", $method->getDocComment())){
        $this->helper_method[] = $method->name;
      }
    }

    $method = $this->class_controller->getMethod("_{$this->action_name}");
    if(preg_match("/@skipBeforeAction\((.*)\)/", $method->getDocComment(), $groups)){
      $this->before_action = array_diff($this->before_action, explode(',',$groups[1]));
    }
    if(preg_match("/@skipAfterAction\((.*)\)/", $method->getDocComment(), $groups)){
      $this->after_action = array_diff($this->after_action, explode(',',$groups[1]));
    }
    if(preg_match("/@layout\((.*)\)/", $method->getDocComment(), $groups)){
      $this->layout_default = $groups[1];
    }

    $classes = self::getParentsClass($this->controller_name);
    $classes[] = $this->controller_name;

    foreach($classes as $class){
      if(!$this->layout_default){
        $temp_layout = self::getDefaultLayout($this->format, $class);
        if(!empty($temp_layout)){
          $this->layout_default = $temp_layout;
        }
      }

      $helper_name = preg_replace("/Controller/", 'Helper', $class);
      if(class_exists($helper_name)){
          $this->helper_name = $helper_name;
          $this->object_helper = new $this->helper_name();
      }

      $views_partials = self::getViewsAndPartial($class);
      foreach($views_partials['views'] as $format => $views){
        $this->views[$format] = isset($this->views[$format]) ? $this->views[$format] : [];
        foreach($views as $name => $view){
          $this->views[$format][$name] = $view;
        }
       }

      foreach($views_partials['partials'] as $format => $partials){
        $this->partials[$format] = isset($this->partials[$format]) ? $this->partials[$format] : [];
        foreach($partials as $name => $partial){
          $this->partials[$format][$name] = $partial;
        }
      }
    }
    $this->layout_default = isset($options['layout']) ? $options['layout'] : $this->layout_default;
  }

  function build(){
    ob_start();
    $this->object_controller = new $this->controller_name();
    if(!empty($this->before_action)){
      foreach ($this->before_action as $method) {
          $this->object_controller->{$method}();
      }
    }

    render::setReturn(true);
		$this->object_controller->{"_{$this->action_name}"}();
    render::setReturn(false);

    if(!empty($this->after_action)){
      foreach ($this->after_action as $method) {
          $this->object_controller->{$method}();
      }
    }
		$reponse_action = ob_get_contents();
		ob_end_clean();

		if(!empty($reponse_action)){
			$response = render::layout($reponse_action);
		} else {
			$response = render::view();
		}
    return $response;
  }

  function setAttributesController($array_or_attribute, $value = null){
    if(is_array($array_or_attribute)){
      foreach($array_or_attribute as $attribute => $value){
        $this->class_controller->setStaticPropertyValue($attribute, $value);
      }
    } else {
      $this->class_controller->setStaticPropertyValue($array_or_attribute, $value);
    }
  }

  function getAttributesController($attribute){
    return $this->class_controller->getStaticPropertyValue($attribute);
  }

  function getMethods(){
    return $this->class_controller->getMethods();
  }


  static function getViewsAndPartial($class){
		$array_path_views  =   explode('\\', $class);
		$array_path_views  =   array_map(['Gearbox\ActiveSupport','underscore'], $array_path_views);
		$path = Engine::baseDir().'/app/views/'.preg_replace("/_controller/", '', implode('/', $array_path_views));

		$views = [];
		$partials = [];
		$layout = null;

		if(file_exists($path)){
			$dir = dir($path);
			while ($view = $dir->read()) {
				if ($view != "." && $view != ".." && !is_dir($path.'/'.$view)) {
					$viewFullPath = $path . '/' . $view;

					if(!preg_match("/\/([a-z0-9_]*)\.([a-z]{2,5})\.php/", $viewFullPath, $group)){
						throw new Exception('Nome da View com Erro:'.$viewFullPath);
					}
					$name = $group[1];
					$format = $group[2];

					if(!preg_match("/\A_/", $name)){
						$views[$format] = isset($views[$format]) ? $views[$format] : [];
						$views[$format][$name] = $viewFullPath;
					} else {
						$partials[$format] = isset($partials[$format]) ? $partials[$format] : [];
						$partials[$format][$name] = $viewFullPath;
					}
				}
			}
		}

		return ['views' => $views, 'partials' => $partials, 'layout' => $layout];
	}

  static function getDefaultLayout($format, $class_name){
		$array_path_views  =   explode('\\', $class_name);
		$array_path_views  =   array_map(['Gearbox\ActiveSupport','underscore'], $array_path_views);

		$name_layout = preg_replace("/_controller/", '', implode('/', $array_path_views));
		$path_layout = Engine::baseDir().'/app/views/layouts/';

		if(file_exists("{$path_layout}{$name_layout}.{$format}.php")){
			return $name_layout;
		} else {
			return null;
		}
	}

  static function getParentsClass($class_name){
		$class = new \ReflectionClass($class_name);
		$parents = [];

		while ($parent = $class->getParentClass()) {
			$class_name = $parent->getName();
			if($class_name != 'Gearbox\ActionPatk\ActionController'){
				$parents[] = $class_name;
				$class = $parent;
			} else {
				break;
			}
		}

		return array_reverse($parents);
	}
}
