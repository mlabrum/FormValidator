<?php

/**
 * @author Matt Labrum <matt@labrum.me>
 * @license Beerware
 * @link url
 */
namespace FormValidator;


class Form{
	
	/**
	* Stores the path of the Forms directory
	* @var String
	*/
	static public $formDirectory = "./Forms/";
	
	/**
	* Stores the name of the css error to be included on elements that don't pass validation
	* @var String
	*/
	static public $cssErrorClass = "Error";
	
	/**
	* Stores the csrf key
	* @var String
	*/
	public static $csrf_key = false;
	
	/**
	* Stores any errors the form has after validation
	* @var Map
	*/
	public $errors = Array();
	
	/**
	* Stores the validation array, this is overridden by the child class
	* @var Map
	*/
	public $validation = Array();
	
	/**
	* Stores the Form data
	* @var Map
	*/
	public $data = Array();
	
	/**
	 * Stores the list data for any elements that use lists, eg Select 
	 * @var Map
	 */
	private $listData = Array();
	
	/**
	* Loads the specified form file and initializes it
	* @param String $name
	* @return Form
	*/	
	static public function loadForm($name){
		$name	= ucfirst($name);
		$file		= self::$formDirectory . $name . ".form.php";
		
		if(file_exists($file)){
			require_once($file);
			$class = __NAMESPACE__ . '\\' . basename($name) . "Form";
			return new $class;
		}else{
			throw new FileDoesntExistException();
		}
	}
	
	/**
	* Adds list data to the form for validation
	* @param String $name
	* @param Array $data
	*/	
	public function addListData($name, $data){
		$this->listData[$name] = $data;
	}
	
	/**
	* retrieves stored list data
	* @param String $name
	* @return Array
	*/	
	public function getListData($name){
		return isset($this->listData[$name]) ? $this->listData[$name] : false;
	}


	/**
	* validates the form against the current validation rules
	* @param String $name
	* @param Int $errorCode
	* @return Boolean
	*/	
	public function validate(){
		// Add csrf validation
		if(self::$csrf_key && !property_exists($this, "no_csrf")){
			$this->validation['csrf'] = Array(
				VALID_NOT_EMPTY,
				Array(
					VALID_IN_DATA_LIST,
					"list" => Array(self::$csrf_key)
				)
			);
		}
		
		// Loop over each validation rule and check it
		foreach($this->validation as $name => $rules){
			if(property_exists($this, "namespace")){
				if(isset($_POST[$this->namespace][$name])){
					$value 			= $_POST[$this->namespace][$name];
				}
			}else{
				if(isset($_POST[$name])){
					$value 			= $_POST[$name];
				}
			}
			
			// Invalidate it if the posted elements dont exist
			if(!isset($value)){
				$this->invalidateElement($name, VALID_ERROR_ELEMENT_DOESNT_EXIST);
			}
			
			if(!isset($_FILES[$name])){	
				$this->data[$name] 	= $value;

				if(is_int($rules)){
					$ret = Validator::isElementValid($rules, $value, $name, $this);

					//when empty we can skip the rest of the validation rules
					if($ret == VALID_EMPTY){
						continue;
					}elseif($ret !== true){
						$this->invalidateElement($name, $ret);
					}
				}else if(is_array($rules)){
					//loop over $this->isValid	
					foreach($rules as $rule){
						$ret = Validator::isElementValid($rule, $value, $name, $this);
						
						//when empty we can skip the rest of the validation rules
						if($ret === VALID_EMPTY){
							break;
						}elseif($ret !== true){
							$this->invalidateElement($name, $ret);
						}
					}
				}
			}
		}
		
		// Call the subclass when the verify is finished, so they don't have to override the validation method
		if(method_exists($this, "verify")){
			$this->verify($this->data);
		}
		
		// Return the data if there isn't any errors
		return !$this->hasErrors() ? $this->data : false;
	}
	
	
	/**
	* Returns true if a form has posted
	* @return Boolean
	*/	
	public function hasPosted(){
		return $_SERVER['REQUEST_METHOD'] == "POST";
	}

	/**
	* Returns true if the current form has posted, this will only work if you use the $form->submitButtom() function to generate your submit button 
	* @param String $name
	* @param Int $errorCode
	* @return Boolean
	*/
	public function isMe(){
		return $this->hasPosted() && isset($_POST[$this->className]);
	}
	
	/**
	* Returns true if the form has validation errors
	* @return Boolean
	*/	
	public function hasErrors(){
		return count($this->errors) > 0;
	}
	
	/**
	* Returns true if the specified form element has an error, also by specifying an error code you can check if the element has a specific error
	* @param String $name
	* @param Int $errorCode
	* @return Boolean
	*/	
	public function elementHasError($name, $errorCode=false){
		if(isset($this->errors[$name])){
			if(!$errorCode){
				return true;
			}else if(is_array($this->errors[$name])){
				return in_array($errorCode, $this->errors[$name]);
			}else if(is_string($this->errors[$name])){
				return $this->errors[$name] == $errorCode;
			}
		}
		return false;
	}
	
	/**
	* Invalidates the element $name with the errorcode 
	* @param String $name
	* @param Int $errorCode
	*/	
	public function invalidateElement($name, $errorCode){
		if(isset($this->errors[$name])){
			if(!is_array($this->errors[$name])){
				$this->errors[$name] = Array($this->errors[$name]);
			}
			$this->errors[$name][] = $errorCode;
		}else{
			$this->errors[$name] = $errorCode;
		}
	}

	/**
	* Echos out any errors the form has 
	* @param String $name
	* @param mixed $message
	*/	
	public function error($name, $message){
		if(isset($this->errors[$name])){
			if(is_array($message)){
				$er = Array();
				foreach($message as $errorCode => $m){
					if($this->ElementHasError($name, $errorCode)){
						if($errorCode == VALID_ERROR_TOOLONG){
							$er[] = "<div>" . sprintf($m, strlen($this->data[$name])) . "</div>";
						}else{
							$er[] = "<div>$m</div>";
						}
					}
				}
				echo implode("\n", $er);
			}else if(is_string($message)){
				if($this->ElementHasError($name)){
					echo "<div>$message</div>";
				}
			}
		}
	}

	/**
	* Creates an input element with the attributes provided
	* @param String $name
	* @param Array $elementAttributes
	*/	
	public function input($name, $elementAttributes=Array()){	
		if(property_exists($this, "namespace")){
			$full_name = $this->namespace . "[" . $name . "]";
		}else{
			$full_name = $name;
		}
		
		$defaultAttributes = Array(
			"name"	=> $full_name,
			"type"	=> "text",
			"value"	=> ""
		);
		$attributes 	= array_merge($defaultAttributes, $elementAttributes);
		
		// Add the error class if the element has an error
		if($this->elementHasError($name)){
			if(isset($attributes['class'])){
				$attributes['class'] .= " " . self::$cssErrorClass;
			}else{
				$attributes['class'] = self::$cssErrorClass;
			}
		}
		
		// Preserve the saved values if the form fails validation
		if(isset($this->data[$name]) && $attributes['type'] != "password" && empty($attributes['value'])){
			$attributes['value'] = $this->data[$name];
		}
		
		// Convert the name/value key pairs into strings
		$a = Array();
		foreach($attributes as $name => $value){
			if(is_array($value)){continue;}
			$a[] = sprintf("%s='%s'", $name, htmlentities($value, ENT_QUOTES));
		}
		
		// Handle textarea needing a different value format
		if($attributes['type'] == "textarea"){
			echo "<textarea " . implode(" ", $a) . ">" . $attributes['value'] . "</textarea>";
		}else{
			echo "<input " . implode(" ", $a) . " />";
		}
	}

	/**
	 * Creates the csrf hidden key
	 */
	public function csrf(){
		if(self::$csrf_key){
			$this->input("csrf", Array("type" => "hidden", "value" => self::$csrf_key));
		}
	}
	
	/**
	* Creates an submit button that this class can identify
	* @param Mixed $elementAttributes
	*/	
	public function submitButton($elementAttributes){
		if(is_string($elementAttributes)){
			$elementAttributes = Array(
				"value" => $elementAttributes
			);
		}
		
		$elementAttributes['type'] = "submit";
		$this->input($this->className, $elementAttributes);
	}
	
	/**
	* Creates a select element
	* @param Array $elementAttributes
	* @param Array $values
	* @param Boolean $useKeys
	*/	
	public function select($name, $elementAttributes=Array(), $values=Array(), $useKeys=false){
		if(property_exists($this, "namespace")){
			$full_name = $this->namespace . "[" . $name . "]";
		}else{
			$full_name = $name;
		}
		
		$defaultAttributes = Array(
			"name" => $full_name,
			"type" => "normal",
		);
		
		
		$attributes = array_merge($defaultAttributes, $elementAttributes);
		$selected 	= false;
		if(isset($this->data[$name])){
			$selected = $this->data[$name];
		}
		
		// If the passed values are empty, try to get it from the list data the class holds
		if(empty($values)){
			if($list = $this->getListData($name)){
				$values = $list;
			}
		}
		
		// Handle custom select types
		switch($attributes['type']){
			case "timezone" : $values = timezone_identifiers_list(); break;
		}
		unset($attributes['type']);
		
		// Convert the name/value key pairs into strings
		$a = Array();
		foreach($attributes as $name => $value){
			$a[] = sprintf("%s='%s'", $name, $value);
		}
		
		// Echo out the first part of the select element
		echo "<select " . implode(" ", $a) . " >\n";
		
		// Echo out the values included within the select element
		foreach($values as $value => $name){
			$html = "<option ";
			
			if($useKeys){
				$html .= sprintf("value='%s'", htmlentities($value, ENT_QUOTES));
				if($selected == $value || $selected == $name){
					$html .= " selected = 'selected' ";
				}
			}else{
				if($selected == $name){
					$html .= " selected = 'selected' ";
				}
			}
			echo $html . ">$name</option>\n";
		}
		echo "</select>\n";
	}

}

class FileDoesntExistException extends \Exception{}

?>