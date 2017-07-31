<?php

/**
 * CrudController
 * @author Devin Dixon
 * @author Devin Dixon <devin@reflexions.co>
 * 
 * The Crud Controller is an abstract controller designed to interface with the models
 * of the system via api. The controller is created to accept dynamic variables and process
 * them in the system.
 * 
 * For example, if we want to get all the users in a system.
 * 
 * /crud/user
 * 
 * This will return all the users in the system. Now let's say we want get all users that are active
 * 
 * /crud/user?conditions={0:{is_active:1}}
 * 
 * Taking is a step further, lets get all users who email is like 'John' and then join them with badges
 * 
 * /crud/user?conditions={0:{'email' :['Like', 'John']}}&joins={0:{'badges' : ['Badges', 'user.id','badges.user_id']}}
 * 
 * We can also call the routes for creating, updating, and getting a single record.
 * 
 * 
 */

namespace Reflexions\Laravel\Controllers\;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Validation\ValidatesRequests;


class CrudController extends Controller {
	
	protected $_modelPath = '';
	
	/**
	 * Model mapping is a way of defining a models name by a key value assocation.
	 * 
	 * Example:
	 * 
	 * $_modelMapping = array(
	 * 		'users' => 'App\Model\Accounts',
	 * 		'message' => 'App\Model\MessageBus',
	 * );
	 * 
	 * In a route if the /crud/users is passed in as the model, it will used the namespace model
	 * App\Model\Accounts
	 */
	protected $_modelMapping = '';
	/**
	 * The function is designed to be able to query results frm the database
	 */
	
	/**
	 * The constructor is used to define the how the class should relate to models.
	 * The variables should be passed via ServiceProdiver.
	 * 
	 * @param string $model_path
	 * @param array $model_mapping
	 */
	public function construct($model_path = '', $model_mapping = array()) {
		$this -> _modelPath = $model_path;
		
		$this -> _modelMapping = $model_mapping;	
	}
	
	public function query($model) {
		
		$object = $this -> _getModel($model);
		
		if(!$object) {
			return $this -> _error(449, 'Model does not exist');
		}
		
		//Get all the query string
		$query = Input::all();
		
		//Get Variables
		$conditions = (isset($query['conditions']) && $query['conditions']) ? $query['conditions'] : array();
		$joins = (isset($query['joins']) && $query['joins']) ? $query['joins'] : array();
		$fields = (isset($query['fields']) && $query['fields']) ? $query['fields'] : '*';
		$with = (isset($query['with']) && $query['with']) ? $query['with'] : array();
		
		$instance = new $object;
		
		$builder = $instance->newQuery();
		
		$builder = $this -> _processWhere($builder, $conditions);
		$builder = $this -> _processJoins($builder, $joins);
		$builder = $this -> _processSelect($builder, $fields); 
		$builder = $this -> _processWith($builder, $with); 
		
		$results = $builder -> get();
		
		return response()->json($results);

		
	}
	
	/**
	 * Finds a record in the database with the passed model.
	 */
	public function first($model, $id) {
		
		$object = $this -> _getModel($model);
		
		if(!$object) {
			return $this -> _error(449, 'Model does not exist');
		}
		
		$instance = $object::find($id);
		
		
		if($instance) {
			return response()->json($instance);
		}
		
		return $this -> _error(452, 'Not data found for instance');
		
		
	}
	
	/**
	 * Creates a new object based on the model being passed
	 * 
	 * @param string $model name
	 * 
	 * @return $mixed Returns either the data for the new model or an error message
	 */
	public function create($model) {
		
		$object = $this -> _getModel($model);
		
		if(!$object) {
			return $this -> _error(449, 'Model does not exist');
		}
		
		$query = Input::all();
		
		//Get the data from query string
		$data = (isset($query['data']) && $query['data']) ? $query['data'] : array();
		
		if($data && is_string($data) && $this ->_isJson($data)) {
			$data = json_decode($data, true);
		}
		
		$validator = \Validator::make($data, $object::getValidationRules('create'));
		
		if ($validator->fails()) {
			
				$errors = $validator->errors();
				$errors =  json_decode($errors); 
				
				return $this -> _error(450, $errors);
		}
		
		
		$instance = new $object($data);
		
		$result = $instance->save();
		
		if($result) {
			return response()->json($instance);
		}
		
		
	}
	
	/**
	 * Updates a given model in the database using its id.
	 * 
	 * An example of updating a user with the id of 5 email address is:
	 * 
	 * PUT operation
	 * 
	 * Example
	 * /crud/5?data={'email':'johndoe@aol.com'}
	 */
	public function update($model, $id) {
		
		$object = $this -> _getModel($model);
		
		if(!$object) {
			return $this -> _error(449, 'Model does not exist');
		}
		
		$query = Input::all();
		
		//Get the data to update from query string or input variable
		$data = (isset($query['data']) && $query['data']) ? $query['data'] : array();
		
		if($data && is_string($data) && $this ->_isJson($data)) {
			$data = json_decode($data, true);
		}
		
		$validator = \Validator::make($data, $object::getValidationRules('update'));
		
		if ($validator->fails()) {
			
				$errors = $validator->errors();
				$errors =  json_decode($errors); 
				
				return $this -> _error(450, $errors);
		}
		
		$instance = $object::find($id);
		
		if(!$instance) {
			return $this -> _error(452, 'Not data found for instance');
		}
		
		$instance->fill($data);
		
		$result = $instance->save();
		
		if($result) {
			return response()->json($instance);
		}
		
	}
	
	/**
	 * Execute is design to execute a function on an instance. Not all functionality
	 * can be called by a query. This method makes possible to call specialied methods.
	 * 
	 * For example, the user model has the method quit_methods(). To call that on user 3,
	 * you went send a POST as:
	 * 
	 * /crud/user/quit_method/3
	 * 
	 * We are also able to pass in params as well to functions.
	 * 
	 * /crud/coachnote
	 * 
	 * @param string $model
	 * @param string The name of the method the is part of the model
	 * @param int $id An optional id that can be used if an database instance is required
	 * 
	 * 
	 * 
	 */
	public function execute($model, $function, $id = null) {
		$object = $this -> _getModel($model);
		
		if(!$object) {
			return $this -> _error(449, 'Model does not exist');
		}
		
		$query = Input::all();
		
		//Get Variables
		$params = (isset($query['params']) && $query['params']) ? $query['params'] : array();
		
		if(method_exists($object, $function)) {
			if($id) {
				$instance = $object::find($id);
				
				if(!instance) {
					return $this -> _error(452, 'Not data found for instance');
				}
			} else {
				$instance = new $object();
			}
			
			if($params) {
				$result = call_user_func_array(array($instance, $function), $params);
			} else {
				$result = $instance::$function();
			}
			
			return response()->json(array('result' =>  $result));
		}
		
		return $this -> _error(453, 'Method does not exist');
		
	}
	
	public function delete() {
		
		
		
	}
	
	/**
	 * This function is to process the conditions passed in to create a where clause
	 * when query models.
	 * 
	 * The conditions are designed to take json/array values and conver to larvel system. For example
	 * 
	 * /users?conditions={0 : {email:abc123}, 1 : {is_active : 1}}
	 * 
	 * This were create the condition in laravel $builder -> where('email', 'abc123') -> where('is_active', 1)
	 * 
	 * /users?conditions={0 : {email:[ '=','abc123']}
	 * 
	 * This wil create the builder $builder -> where('email', '=', 'abc123') 
	 * 
	 * @param $builder The eloquent query builder
	 * @param array $joins
	 * 
	 * @return $builder
	 */
	protected function _processWhere($builder, $conditions) {
		
		if($conditions && is_string($conditions) && $this ->_isJson($conditions)) {
			$conditions = json_decode($conditions, true);
		}
		
		
		foreach($conditions as $key => $value) {
			
			if(is_string($value)) {
				$builder -> where($key, $value);
			} else if(is_array($value)) {
				$builder -> where($key, $value[0], $value[1]);
			}
			
		}//endforeach
		
		return $builder;
	}
	
	/**
	 * This function is used for creating joins in the database when models are
	 * called. Many times we will have to join data from different models to accomplish
	 * results.
	 * 
	 * For examples, joining user and bades
	 * /crud/user?joins={0:{'badges' : ['Badges', 'user.id','badges.user_id']}}
	 * 
	 * @param $builder The eloquent query builder
	 * @param array $joins
	 * 
	 * @return $builder
	 */
	protected function _processJoins($builder, $joins) {
		if($joins && is_string($joins) && $this ->_isJson($joins)) {
			$joins = json_decode($joins, true);
		}
		
		foreach($joins as $key => $value) {
			if(is_array($value)) {
				$builder -> join($key, $value[0], $value[1], $value[2]);
			}
		}
		
		return $builder;
		
	}
	
	/**
	 * Process the with will allow the builder to use
	 * join arguements that are defined in the model.
	 * 
	 * Example
	 * 
	 * The route /user?with=['badge','coach']
	 * 
	 * Will be the same as
	 * 
	 * User::with('badge') -> with('coach') -> get()
	 * 
	 * @param $builder
	 */
	protected function _processWith($builder, $withs) {
		
		foreach($withs as $with) {
			$builder -> with($with);
		}
		return $builder;
	} 
	
	/**
	 * Sets up the collect system for query to execute
	 */
	protected function _processSelect($builder, $select) {
		$builder -> select($select);
		
		return $builder;
	}
	
	/**
	 * Check if the model actually exist.
	 * 
	 * @param string $model The model name is string form
	 * 
	 * @return mixed Either returns the models full path or a false
	 */
	protected function _getModel($model) {
		
		$model_name = 'Sidekick\\' .$model;
		
		if($this -> _modelMapping && isset($this -> _modelMapping[$model])) {
			return $this -> _modelMapping[$model];
		} else if(class_exists($model_name)) {
			return $model_name;
		}
		
		return false;
	}
	
	/**
	 * Checks if a string is json or not
	 * 
	 * @param $string
	 */
	protected function _isJson($string) {
 		@json_decode($string);
 		return (json_last_error() == JSON_ERROR_NONE);
	}
	
	/**
	 * Returns an error response to be given to the requesting party
	 * 
	 * @param int $code The error code
	 * @param string $message The error message
	 * 
	 * @return Response $response 
	 */
	protected function _error($code, $message) {
		
		return response()->json([
			'success' => false,
			'message' => $message
		], $code);
		
	}
	
}
