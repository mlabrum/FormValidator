<?php
/**
 * @author Matt Labrum <matt@labrum.me>
 * @license Beerware
 * @link url
 */
namespace FormValidator;

/* does nothing. useful when you want a input included in the checking array, but not checked */
define("VALID_DO_NOTHING", -1);

/* input is still valid if empty */
define("VALID_EMPTY", -2);

/* valid if input is not empty */
define("VALID_NOT_EMPTY", 0);

/* valid if input is a number */
define("VALID_NUMBER", 2);

/* valid if input is a string */
define("VALID_STRING", 3);

/* valid if input is an email */
define("VALID_EMAIL", 7);

/* valid if input is a valid timezone */
define("VALID_TIMEZONE", 8);

/* valid if input is a url */
define("VALID_URL", 10);

/* 
	if not specified in a param then Form::addListData is used to find the list, if you havent use that function your yourForm::getListData is called 
	Params:
	Array list
*/
define("VALID_IN_DATA_LIST", 9);

/*
	allows you to define a custom checking function, see above examples for an example of this
	Params:
	Callback callback
	mixed errorCode
	
	your callback will be called with the following params
	
	$value //the value to check
	$params //an array of params that you have defined
	
	if you return false then an error will be raised with the errorCode you defined
	
*/
define("VALID_CUSTOM", 1);

/*
	Input must be a certain length
	
	Params: (both optional)
	int Min 
	int Max
*/
define("VALID_LENGTH", 4);



define("VALID_MUSTMATCHFIELD", 5);
define("VALID_MUSTMATCHREGEX", 6);

define("VALID_UPLOAD", 7);


/*
	Standard error codes use these when calling the Form::error function
*/

define("VALID_ERROR_EMAIL", "notemail");
define("VALID_ERROR_EMPTY", "empty");
define("VALID_ERROR_CUSTOM", "custom");
define("VALID_ERROR_NOT_NUMBER", "number");
define("VALID_ERROR_NOT_STRING", "string");


define("VALID_ERROR_TOOSHORT", "stringshort");
define("VALID_ERROR_TOOLONG", "stringlong");

define("VALID_ERROR_NOT_MATCH_FIELD", "nomatchfield");
define("VALID_ERROR_NOT_MATCH_REGEX", "nomatchregexfield");

define("VALID_ERROR_TIMEZONE", "timezone");
define("VALID_ERROR_NOTINLIST", "notinlist");

define("VALID_ERROR_NOT_URL", "noturl");


class Validator{


	/**
	* validates the element against the passed validation rules
	* @param Mixed $rule
	* @param Mixed $value
	* @param String $name
	* @param Form $name
	* @return Mixed errorCode
	*/	
	static public function isElementValid($rule, $value, $name, $form){
		// Due to the format of the rules, some rules can have parameters, so we strip that into a seperate variable
		if(is_array($rule)){
			$params = $rule;
			$rule = array_shift($rule);
		}else{
			$params = Array();
		}
	
	
		
	
		switch($rule){
			case VALID_DO_NOTHING: 		return true;
			case VALID_EMPTY:			return empty($value) ? VALID_EMPTY : true;
			
			case VALID_NUMBER: 			return self::isNumber($value) ? true : VALID_ERROR_NOT_NUMBER;
			case VALID_STRING: 			return self::isString($value) ? true : VALID_ERROR_NOT_STRING;
			case VALID_EMAIL: 			return self::isEmail($value) ? true : VALID_ERROR_EMAIL;
		
			case VALID_TIMEZONE: 		return self::isValidTimeZone($value) ? true : VALID_ERROR_TIMEZONE;

			case VALID_URL:				return FormValidator::isValidUrl($value) ? true : VALID_ERROR_NOT_URL;
			
			case VALID_LENGTH :			return FormValidator::isValidLength($value, $params);
			
			
			case VALID_MUSTMATCHFIELD: 	return ($value == $form->data[$params['field']]) ? true : VALID_ERROR_NOT_MATCH_FIELD;
			case VALID_MUSTMATCHREGEX:	return preg_match($params['regex'], $value) ? true : VALID_ERROR_NOT_MATCH_REGEX;
			
			case VALID_CUSTOM :
				if(isset($params['run_noerror']) && $params['run_noerror'] && $form->itemHasError($name)){
					return true;
				}else if(!call_user_func($params['callback'], $value, $params)){
					return $params['errorCode'];
				}else{
					return true;
				}
			break;
		
		
			case VALID_IN_DATA_LIST:
				if(($list = $form->getListData($name)) != false){
					if(in_array($value, $list)){
						return true;
					}
					
					if(isset($params["searchKeys"]) && $params["searchKeys"]){
						if(in_array($value, array_keys($list))){
							return true;
						}
					}
				}else if(isset($params['list'])){
					if(in_array($value,  $params['list'])){
						return true;
					}
					
					if(isset($params["searchKeys"]) && $params["searchKeys"]){
						if(in_array($value, array_keys($params['list']))){
							return true;
						}
					}
				}
				return VALID_ERROR_NOTINLIST;
			break;
		}
	}


	/**
	* Checks if the value is empty
	* @param Array $value
	* @return Boolean
	*/	
	static public function isEmpty($value){
		return empty($value);
	}

	/**
	* Checks if the value is a number
	* @param int $value
	* @return Boolean
	*/
	static public function isNumber($value){
		return (boolean) filter_var($value, FILTER_VALIDATE_INT);
	}
	
	/**
	* Checks if the value is a string
	* @param String $value
	* @return Boolean
	*/
	static public function isString($value){
		return true;
	}
	
	/**
	* Checks if the value is a valid email
	* @param String $value
	* @return Boolean
	*/
	static public function isEmail($value){
		return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}
	
	/**
	* Checks if the value is timezone
	* @param String $value
	* @return Boolean
	*/
	static public function isValidTimeZone($value){
		return in_array($value, timezone_identifiers_list());
	}
	
	/**
	* Checks if the value is a url
	* @param String $url
	* @return Boolean
	*/
	static public function isValidUrl($value){
		return filter_var($value, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
	}
	
	/**
	* Checks if the value is a specified length
	* @param String $value
	* @param Map $params
	* @return Boolean
	*/
	static public function isValidLength($value, $params){
		
		if(isset($params['min'])){
			if(strlen($value) < $params['min']){
				return VALID_ERROR_TOOSHORT;
			}
		}
	
		if(isset($params['max'])){
			if(strlen($value) > $params['max']){
				return VALID_ERROR_TOOLONG;
			}
		}
		return true;
	}

	
}


?>