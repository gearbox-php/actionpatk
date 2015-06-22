<?

namespace Gearbox\ActionPatk;
use Gearbox\ActionView\Render as render;
use  Gearbox\ActionPatk as AcP;
use Gearbox\ActiveSupport as AcS;

class ActionController{

  static $layout_default = false;

  function __set($field, $value){
		AcP::setAttributes($field, $value);
	}

	function __get($field){
			return AcP::getAttributes($field);
	}


}
