<?

namespace Gearbox\ActionPatk;
use Gearbox\ActionPatk as AcP;

class ActionHelper{

  function __set($field, $value){
		AcP::setAttributes($field, $value);
	}

	function __get($field){
			return AcP::getAttributes($field);
	}

}
