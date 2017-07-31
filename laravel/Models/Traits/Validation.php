<?php
/**
* Validation
* Validations rules is the process of deciding if an input is valid before data
* is saved to a database. Because this functionality should be consistant no matter
* how the data is saved, validation should occur in the model and NOT in the controller.
*
* This trait is designed to be added to model classes as a base for validation and then expanded
* on as needed. This trait works WITH the Crud API implementation.
*
* Example Usage:
*
* class Users extends HModel {
*   //Define the trait just incase is never used but still accessible
*   use Reflexions\Laravel\Models\Traits\Validation;
*
*   //Ovveride the trait function with your own functionacity
*     public static function getValidationRules($method = '') {
*
*        //Ensure the method is always lowercase
*        $method = strtolower($method);(*
*
*        $rules = array();
*
*        if ($method == 'create' || $method == 'post') {
*
*            $rules['email'] = 'required';
*            $rules['name'] = 'required';
*            $rules['is_active'] = 'required|numeric';
*        } else if ($method == 'update' || $method == 'put') {
*        
*            $rules['name'] = 'required';
*        }
*
*
*        return $rules;
*    }
*   
* }
*
* Whether in the controller, through api or any other point, the same validation rules can be re-used.
* $valid = User::getValidationRules('create', $request -> input());
*/

namespace Reflexions\Laravel\Models\Traits;

trait Validation
{
    /**
     * Defines the validation rules for the model. This method
     * should be ovverried by the child class
     *
     * @param string $method
     *
     * @return array $rules
     */
    public static function getValidationRules($method = '')
    {

        return array();

    }
}
