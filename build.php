<?

Gearbox\Engine::addGear([
	"name" => "Gearbox::ActionPatk",
	"loader" => function($class_name){
		if(Gearbox\Engine\Loader::classicGearLoader($class_name, 'ActionPatk', 'actionpatk/lib/action_patk/')){
    	return true;
		} elseif (preg_match("/[a-zA-Z]Controller/", $class_name)) {
			$dir = \Gearbox\Engine::baseDir()."/app/controllers";
			if(preg_match('/\\\\/', $class_name)){
				$namespaces = explode('\\', $class_name);
				$class_name = array_pop($namespaces);
				$dir = $dir.'/'.implode('/', array_map(['Gearbox\ActiveSupport','underscore'], $namespaces));
			}
			if(file_exists($dir.'/'.\Gearbox\ActiveSupport::underscore($class_name).'.php')){
				require_once $dir.'/'.\Gearbox\ActiveSupport::underscore($class_name).'.php';
				return true;
			}
		} elseif (preg_match("/[a-zA-Z]Helper/", $class_name)) {
			$dir = \Gearbox\Engine::baseDir()."/app/helpers";
			if(preg_match('/\\\\/', $class_name)){
				$namespaces = explode('\\', $class_name);
				$class_name = array_pop($namespaces);
				$dir = $dir.'/'.implode('/', array_map(['Gearbox\ActiveSupport','underscore'], $namespaces));
			}
			if(file_exists($dir.'/'.\Gearbox\ActiveSupport::underscore($class_name).'.php')){
				require_once $dir.'/'.\Gearbox\ActiveSupport::underscore($class_name).'.php';
				return true;
			}
		}
		return false;
	},
	"run" => function(){
		Gearbox\ActionPatk::runPrimaryAction();
	}
]);
