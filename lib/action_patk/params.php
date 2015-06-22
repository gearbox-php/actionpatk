<?


namespace Gearbox\ActionPatk;
use Gearbox\ActionPatk;

class Params{

  static function get($filter = null){
    return ActionPatk::getParams(null, $filter);
  }

  static function __callStatic($field, $args = []){
    return ActionPatk::getParams($field, isset($args[0]) ? $args[0] : null);
  }

}
