<?php

/**
 * @author Matt Labrum <matt@labrum.me>
 * @license Beerware
 * @link url
 */
namespace FormValidator;

// Load the validation class
require_once(__DIR__ . "/Validation.php");

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
	public $listData = Array();
	
	/**
	 * Stores the form name
	 */
	public $className = "";
	
	/**
	 * Stores the data source to check against
	 */
	public $dataSource = null;
	
	/**
	 * If true, the form is tested to be unique submission
	 * @var Boolean
	 */
	public $prevent_duplicates = false;
		
	public function __construct($className=false, $namespace=false, $validation=Array()){
		
		if($className)
			$this->className = $className;
		
		if($namespace)
			$this->namespace = $namespace;
		
		if(!empty($validation))
			$this->validation = $validation;
	}
	
	/**
	 * Sets the data source to check against, this defaults to $_POST
	 */
	public function setDataSource($post){
		// Support passing in a symfony request object directly
		if(!is_array($post) && get_class($post) == 'Symfony\Component\HttpFoundation\Request'){
			$post = $post->request->all();
		}
	
		$this->dataSource = $post;
		
		return $this;
	}
	
	/**
	* Loads the specified form file and initializes it
	* @param String $name
	* @return Form
	*/	
	static public function loadForm($name, $arguments=Array(), $base_path=false){
		$name	= ucfirst($name);		
		$file	= (!empty($base_path) ? $base_path : self::$formDirectory) . $name . ".form.php";

		if(file_exists($file)){
			require_once($file);
			$class = __NAMESPACE__ . '\\' . basename($name) . "Form";
			
			// If passed arguments, create the class through reflection
			if(!empty($arguments)){
				$reflect = new \ReflectionClass($class);
				$classI = $reflect->newInstanceArgs($arguments);
				$classI->className = $name;
				return $classI;
			}else{
				// Otherwise load normally
				$classI = new $class;
				$classI->className = $name;
				return $classI;
			}
		}else{
			throw new FileDoesntExistException("File $file doesn't exist");
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
	* Validates the csrf field, useful if you're using the csrf checking without a full form
	*/
	public function validateCsrf(){
		return (property_exists($this, "namespace") ? $this->dataSource[$this->namespace]['csrf'] : $this->dataSource['csrf']) == self::$csrf_key;
	}

	/**
	* validates the form against the current validation rules
	* @param String $name
	* @param Int $errorCode
	* @return Boolean
	*/	
	public function validate(){
		
		// Set the default data source if it didn't exist
		if(!is_array($this->dataSource)){
			$this->setDataSource($_POST);
		}
	
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
		
		// Test for duplicate form submission
		$can_save_session_id = false;
		if($this->prevent_duplicates){
			
			// Must have an active session
			if(session_id() !== ""){
				// Check if there are form_submitted_ids
				if(!empty($_SESSION['form_submitted_ids'])){
					// Add a custom validation rule
					$this->validation['unique_uuid'] = Array(
						VALID_NOT_EMPTY,
						Array(VALID_CUSTOM, "errorCode" => "used", "callback" => function($value, $params){
							return !in_array($value, $_SESSION['form_submitted_ids']);
						})
					);
				}else{
					$this->validation['unique_uuid'] = Array(VALID_NOT_EMPTY);
					$_SESSION['form_submitted_ids'] = Array();
				}

				$can_save_session_id = true;				
			}
		}
		
		
		// Loop over each validation rule and check it
		foreach($this->validation as $name => $rules){
			if(property_exists($this, "namespace")){
				if(isset($this->dataSource[$this->namespace][$name])){
					$value 			= $this->dataSource[$this->namespace][$name];
				}
			}else{
				if(isset($this->dataSource[$name])){
					$value 			= $this->dataSource[$name];
				}
			}
			
			// Invalidate it if the posted elements dont exist
			if(!isset($value)){
				// Check if VALID_EMPTY is set
				if(!($rules == VALID_EMPTY || (is_array($rules) && in_array(VALID_EMPTY, $rules)))){
					$this->invalidateElement($name, VALID_ERROR_ELEMENT_DOESNT_EXIST);
				}
				continue;
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
		
		// Add the submitted id to the session store
		if(!empty($this->data['unique_uuid']) && $can_save_session_id){
			$_SESSION['form_submitted_ids'][] = $this->data['unique_uuid'];
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
		return $_SERVER['REQUEST_METHOD'] == "POST" || !empty($this->dataSource);
	}
	
	/**
	* Returns true if the current form has posted, this will only work if you use the $form->submitButtom() function to generate your submit button 
	* @param String $name
	* @param Int $errorCode
	* @return Boolean
	*/
	public function isMe(){
		if($this->hasPosted()){
			if($this->namespace) {
				if(isset($this->dataSource[$this->namespace][$this->className])){
					return true;
				}
			}elseif(isset($this->dataSource[$this->className])){
				return true;
			}
		}
		return false;
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
		
		// Allow an array input
		if(is_array($name)){
			$elementAttributes	= $name;
			$name				= $name['name'];
			unset($elementAttributes['name']); 
		}
			
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
		
		// Add the CSRF prevention key
		if(self::$csrf_key){
			$this->input("csrf", Array("type" => "hidden", "value" => self::$csrf_key));
		}
		
		// Output a unique ID for the form, note: this can be anything, the value is only tested by "have we seen this before?"
		if($this->prevent_duplicates){
			$this->input("unique_uuid", Array("type" => "hidden", "value" => sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),

				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,

				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,

				// 48 bits for "node"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			)));
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
		
		if(isset($attributes['placeholder'])){
			echo "<option disabled" . (!$selected ? " selected": ""). ">". htmlentities($attributes['placeholder']). "</option>";
			
			unset($attributes['placeholder']);
		}
		
		// Echo out the values included within the select element
		foreach($values as $value => $name){
			$html = "<option ";
			
			if($useKeys){
				$html .= sprintf("value='%s'", htmlentities($value, ENT_QUOTES));
				if($selected === (string)$value || $selected === $name){
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
	
	public function dumpIntoHiddenFields(){
		foreach($this->data as $key => $value){
			if($key == "csrf")continue;
			$this->input($key, Array("type" => "hidden", "value" => $value));
		}
	}

}

class FileDoesntExistException extends \Exception{}

?>