<?

namespace Gearbox\ActionPatk;

class Route{
    var $controller = null;
    var $action = null;
    var $url = null;
    var $url_check = null;
    var $url_params = null;
    var $params = [];
    var $values_params = [];
    var $method = null;
    var $full_route = false;
    var $format = 'html';
    var $query = [];

    static $routers = [];
    static $methods = [];

    static $last_resource = null;
    static $global_namespaces = [];
    static $global_params = [];
    static $global_params_match = [];
    static $global_path = null;
    static $global_path_match = null;
    static $root_path = null;
    static $root_path_regex = null;
    static $request_uri = null;
    static $current_format = 'html';
    static $current_route = null;

    const member = 1;
    const collection = 2;

    const actions_resources = [
        "index" => self::collection,
        "new" => self::collection,
        "create" => self::collection,
        "show" => self::member,
        "edit" => self::member,
        "update" => self::member,
        "destroy" => self::member
    ];

    const actions_resource = [
        "new" => self::collection,
        "create" => self::collection,
        "show" => self::collection,
        "edit" => self::collection,
        "update" => self::collection,
        "destroy" => self::collection
    ];

    function __construct($controller_action, $url, $params){
        $array_controller_action = explode("#", $controller_action);
        $this->action = "_{$array_controller_action[1]}";

        $namespaces = implode('\\', array_map('camelize', self::$global_namespaces));
        if(empty($namespaces)) $this->controller = camelize($array_controller_action[0])."Controller";
        else $this->controller = $namespaces.'\\'.camelize($array_controller_action[0])."Controller";

        $this->url = $url;
        $this->url_check = preg_replace("/(\/)/", "\/", preg_replace("/(:[a-z\_]*)/", "\d*", $url));
        $this->url_params = preg_replace("/(\/)/", "\/", preg_replace("/(:[a-z\_]*)/", "(\d*)", $url));
        $this->params = $params;
        $this->method =  preg_replace('/(\/:[a-z\_]*\/)|(\/)/', '_',
          preg_replace('/(\/:[a-z\_]*\/$)|(\/:[a-z\_]*$)/','/show',
            preg_replace('/('.self::$root_path_regex.')\/|('.self::$root_path_regex.')/', '', $url)
          )
        );
    }

    function __toString(){
      $url = $this->full_route ? 'http://'.$_SERVER['HTTP_HOST'].$this->url : $this->url;
      if(!empty($this->values_params)){
        foreach ($this->values_params as $key => $value) {
          $id = is_object($value) ? $value->id : $value;
          $url = preg_replace("/$key/", $id, $url);
        }
      }
      if($this->format != 'html'){
        $url .= '.'.$this->format;
      }
      if(!empty($this->query)){
        $url .= '?'. http_build_query($this->query);
      }
      return $url;
    }

    function query($params){
      $this->query = $this->query + $params;
      return $this;
    }

    function js(){
      $this->format = 'js';
      return $this;
    }

    function json(){
      $this->format = 'json';
      return $this;
    }

    function xml(){
      $this->format = 'xml';
      return $this;
    }

    function readValuesParams(){
      $values_params = [];
      preg_match('/\A'.$this->url_params.'$/', Route::$request_uri, $params);
      array_shift($params);

      foreach($this->params as $key => $key_params){
        $values_params[$key_params] = $params[$key];
      }
      $this->values_params = $_GET + $_POST + $values_params;
      return $this;
    }

    function setFormat($format){
      $this->format = $format;
      return $this;
    }

    static private function draw($draw){
        $draw();
    }

    static public function readRoute(){
        self::$request_uri = preg_replace("/\?{$_SERVER['QUERY_STRING']}/", "", $_SERVER['REQUEST_URI']);
        self::$request_uri = preg_replace("/\/$/", "", self::$request_uri);
        self::getFormat();
        if(self::$request_uri == self::$root_path){
          self::$current_route = clone self::$routers['root'];
          return self::$current_route->readValuesParams()->setFormat(self::$current_format);
        } else {
          foreach(self::$routers as $url_check => $route)
            if(preg_match('/\A'.$url_check.'$/', self::$request_uri)){
              self::$current_route = clone $route;
              return self::$current_route->readValuesParams()->setFormat(self::$current_format);
            }
        }
        return null;
    }

    static private function getFormat(){
      $accept = $_SERVER['HTTP_ACCEPT'];
      if(preg_match("/\.([a-z0-9]+$)/", self::$request_uri, $groups)){
        self::$current_format = $groups[1];
        self::$request_uri = preg_replace("/\\$groups[0]/", '', self::$request_uri);
      } elseif(preg_match('/text\/html/', $accept)){
        self::$current_format = 'html';
      } elseif(preg_match('/application\/javascript/', $accept)){
        self::$current_format = 'js';
      } elseif(preg_match('/application\/json/', $accept)){
        self::$current_format = 'json';
      } elseif(preg_match('/application\/xml/', $accept)){
        self::$current_format = 'xml';
      }
    }

    static public function setRoutes(){
        self::$global_path = preg_replace("/\/app.php/", "",$_SERVER['SCRIPT_NAME']);
        self::$root_path = self::$global_path;
        self::$root_path_regex = preg_replace("/(\/)/", "\/", self::$root_path);
        include \GearBox\Engine::baseDir()."/config/routes.php";
    }

    static private function root($route){
        $route = new Route($route, self::$root_path, self::$global_params);
        self::$routers['root'] = $route;
        self::$methods['root'] = $route;
    }

    static private function resources($route, $options_or_call = null, $call = null){
        self::$last_resource = $route;

        $temp_path = self::$global_path;
        self::$global_path = self::$global_path.'/'.$route;

        if(is_a($options_or_call, "Closure")) $call = $options_or_call;

        if(is_array($options_or_call)) $options = $options_or_call;
        else $options = [];

        $params_key = ':id';

        if(isset($options["only"])) $actions = $options["only"];
        else $actions = array_keys(self::actions_resources);

        foreach($actions as $action){
            $params = self::$global_params;
            if (self::actions_resources[$action] == self::collection) {
                if ($action == 'index') $url = self::$global_path;
                else $url = self::$global_path.'/'.$action;
            } else {
                if ($action == 'show') $url = self::$global_path.'/'.$params_key;
                else $url = self::$global_path.'/'.$params_key.'/'.$action;
                $params[] = $params_key;
            }

            $objRoute = new Route($route.'#'.$action, $url, $params);
            self::$routers[$objRoute->url_check] = $objRoute;
            self::$methods[$objRoute->method] = $objRoute;
        }

        if(isset($options["params_key"])) $params_key = ':'.$options["params_key"];
        else $params_key = ':'.$route.'_id';

        $temp_params = self::$global_params;
        self::$global_params_match = self::$global_params;

        self::$global_params[] = $params_key;
        self::$global_path_match = self::$global_path;
        self::$global_path = self::$global_path.'/'.$params_key;

        if(!empty($call)) $call();

        self::$global_path = $temp_path;
        self::$global_path_match = self::$global_path;
        self::$global_params = $temp_params;
        self::$global_params_match = self::$global_params;
        self::$last_resource = null;
    }

    static private function resource($route, $options_or_call = null, $call = null){
        self::$last_resource = $route;

        $temp_path = self::$global_path;
        self::$global_path = self::$global_path.'/'.$route;

        if(is_a($options_or_call, "Closure")) $call = $options_or_call;

        if(is_array($options_or_call)) $options = $options_or_call;
        else $options = [];

        if(isset($options["only"])) $actions = $options["only"];
        else $actions = array_keys(self::actions_resource);

        foreach($actions as $action){
            $params = self::$global_params;
            if ($action == 'index') $url = self::$global_path;
            else $url = self::$global_path.'/'.$action;

            $objRoute = new Route($route.'#'.$action, $url, $params);
            self::$routers[$objRoute->url_check] = $objRoute;
            self::$methods[$objRoute->method] = $objRoute;
        }
        self::$global_params_match = self::$global_params;
        self::$global_path_match = self::$global_path;

        if(!empty($call)) $call();

        self::$global_path = $temp_path;
        self::$global_path_match = self::$global_path;
        self::$last_resource = null;
    }

    static private function namespaces($namespace, $call){
        $temp_path = self::$global_path;
        $temp_namespaces = self::$global_namespaces;

        self::$global_namespaces[] = camelize($namespace);
        self::$global_path = self::$global_path.'/'.$namespace;
        self::$global_path_match = self::$global_path;

        $call();

        self::$global_path = $temp_path;
        self::$global_path_match = self::$global_path;
        self::$global_namespaces = $temp_namespaces;
    }

    static private function match($alias, $controller_action_or_options = null, $options = []){
        if(is_array($controller_action_or_options)){
            $options = $controller_action_or_options;
            $controller_action = null;
        } else {
            $controller_action = $controller_action_or_options;
        }
        if(empty($controller_action)) $controller_action = self::$last_resource.'#'.$alias;

        $url = self::$global_path_match;
        $params = self::$global_params_match;

        if(isset($options['on']) && $options['on'] == self::member) {
            $params[] = ':id';
            $url = $url.'/:id/'.$alias;
        } else {
            $url = $url.'/'.$alias;
        }
        if(empty($controller_action)) $controller_action = self::$last_resource.'#'.$url;

        $objRoute = new Route($controller_action, $url, $params);
        self::$routers[$objRoute->url_check] = $objRoute;
        self::$methods[$objRoute->method] = $objRoute;
    }

    static function getRouteByMethod($method_name, $values_params){
      $method = preg_replace('/(_path)|(_url)/', '', $method_name);
      if(isset(self::$methods[$method])){
        $route = clone self::$methods[$method];
        $route->full_route = preg_match("/_url/", $method_name);
        if(!empty($route->params)){
          if(count($route->params) == count($values_params)){
            foreach($route->params as $key => $params_key){
              $route->values_params[$params_key] = $values_params[$key];
            }
          } else {
            throw new \Exception('Erro ao Gerar a Rota');
          }
        }
      } else {
        throw new \Exception('Erro ao Gerar a Rota');
      }
      return $route;
    }
}
