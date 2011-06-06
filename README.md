## FormValidator
FormValidator allows you to create and validate forms using a simple rule based approch.

------

### Basics

#### Setting up your first form

A form file is just a class that extends the FormValidator\Form class
In this example, the form validator checks if name isn't empty

		test.form.php

		namespace FormValidator
		class TestForm extends Form{
			public $validation = Array( // Contains a hash array of form elements
				"name" => VALID_NOT_EMPTY // name field must contain something
			); 
		}
		
		

Then to use this form, in our controller we initialize our form, then check if it submitted, and if it's valid
		
		index.php

		$form = new FormValidator\TestForm();
		
		/* Checks if the form has submitted then the form is checked for validation against the rules contained 
		within the $validation array of TestForm returning the post data if its successful
		*/
		
		if($form->hasPosted() && ($data = $form->validate())){
			// Form passes validation, use the POST data contained within $data
		
		}else{
			// Form hasn't posted or hasn't passed validation, so we load our html file
			require_once("form.html.php");
		}


Now in our view we just call $form->error passing in the name of the form field, with the error message to display, and if theres been an error on that form field, it will show the message
		form.html.php
		
		
		<form name="input" method="POST">
		<?php $form->error("name", "There was an error"); ?>

		 Please Enter your name: <?php $form->input("name"); ?><br/>
		<?php $form->submitButton("Submit"));?>
		</form> 


Now having all your form validation in a seperate class, neatens up the controller code, theres also alot of inbuilt options for the $validation array which we'll cover in the next section

Note: if the form fails validation, by using the $form->input method, we preserve whatever value was in that field (except for password fields)

-----

### The Validation Array

The Validation array contains all the form fields and rules that need to pass, for the form to be valid
In the example above, It showed a single rule applying to one form element, but you can apply multiple rules to an element by using an array

For example, if we wanted to add a age field, and that age field must be a number and not empty

We could do the following

		namespace FormValidator
		class TestForm extends Form{
			public $validation = Array( // Contains a hash array of form elements
				"name" => VALID_NOT_EMPTY, // name field must contain something
				"age"   => Array(
					VALID_NOT_EMPTY,
					VALID_NUMBER
				);
			); 
		}

and in our html file, if we wanted to show different errors for the different age validations we could do the following

		form.html.php
		
		<form name="input" method="POST">
		<?php $form->error("name", "There was an error"); ?>

		 Please Enter your name: <?php $form->input("name"); ?><br/>
		
		<?php $form->error("age", Array(
			VALID_ERROR_EMPTY            => "Sorry, age can't be left empty",
			VALID_ERROR_NOT_NUMBER   => "Sorry, age has to be a number"
		)); ?>
		
		Please Enter your age: <?php $form->input("name"); ?><br/>
		<?php $form->submitButton("Submit"));?>
		</form> 

Note: all the error codes can be found within the Validation class


#### Validation array params
The validation array also supports passing in parameters into the validation constants, this is done by using an array with the constant being the first value and the parameters being the rest of the hash

For example to ensure the age value is only between 0-100 we could do the following

		class TestForm extends Form{
			public $validation = Array( // Contains a hash array of form elements
				"name" => VALID_NOT_EMPTY, // name field must contain something
				"age"   => Array(
					VALID_NOT_EMPTY,
					VALID_NUMBER,
					Array(
						VALID_LENGTH,
						"min"  => 0,
						"max" => 100
					)
				);
			); 
		}
		

#### List of validation array constants


		VALID_DO_NOTHING:		The field is always valid
		VALID_EMPTY:			The field is allowed to be empty
		VALID_NOT_EMPTY:		The field must not be empty
		VALID_NUMBER:			The field must be all numbers
		VALID_EMAIL:			The field must be a valid email
		VALID_TIMEZONE:			The field must be a valid timezone
		VALID_URL:				The field must be a url
		
		Constants with paramters
		
		VALID_IN_DATA_LIST:		The field must be either in the Forms data list, or have a parameter "list" containing an array (see Using lists)
		VALID_CUSTOM:			The field value is checked against the provided callback, this takes the two parameters, The first being "callback" which will contain a valid php callback, 
							and the second "errorCode" the errorCode to raise if the callback returns false
		VALID_LENGTH:			The field must be between the provided values, this takes two optional parameters, "min" and "max"
		VALID_MUSTMATCHFIELD:	The field must be the same value as the field name contained within the parameter "field"
		VALID_MUSTMATCHREGEX:	The field must match the regex provided in the parameter "regex"
		
--------------
		
### Using Lists
FormValidator can also validate html lists, and generate those. There are two methods to do this

The first, is by using VALID\_IN\_DATA\_LIST with the parameter "list", but that doesn't allow for dynamic lists, for example a list of usernames
So we can use the method Form::addListData("FormField", Array());
The array can either be a hash of key/values or just values, if you pass in a hash then the key will be returned in the POST if it's selected in the list

For Example lets say we wanted to show and validate a list of usernames

		test.form.php

		namespace FormValidator
		class TestForm extends Form{
			public $validation = Array( // Contains a hash array of form elements
				"usernames" => VALID_IN_DATA_LIST
			); 
			
			public function __construct(){
				$usernames = Array(500 => "Matt", 300 => "Thor", 1 => "Asa", 5 => "Martina", 9 => "John", 12 => "Kate"); // Fetch our usernames from the database, with the keys being their userID
				$this->addListData("usernames", $usernames); // Add the list data
			}
		}


Now whenever this form is used, it will have a list of usernames within it, to show this within a html page you can use Form::Select($fieldname [, $elementAttributes [, $values [, $useKeys])

	<?php
		$form->select("usernames"); // loads and displays the data from the stored data
	?>
	
	
-----------

### Multiple Form Fields On One Page

If you want to use multiple form fields on one page, then all you have to do is to ensure that when you create the the html for your form, is that you use

		Form::submitButton()

To build your submit button in your form, this allows FormValidator to track which form triggered the POST

In your controller you can use the Form::isMe() method to check if the form is the one which triggered the POST