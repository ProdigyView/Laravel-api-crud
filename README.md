# Reflexions API CRUD Controller
This controller is designed to allow rapid protopying of the applications by providing a robust, standardized way that allows javascript applications and mobile applications to easily communicate with a database. The methods mentioned in this repo are designed for Laravel's MVC.
## Thinking Behind Implementation
### The Problem: Unneccessary Requirements For Basic Operations
In Laravel, writing api routes requires a lot of coordination between the mobile team/javascript team and api team, even for simple operations. For example, if a mobile developer wanted to search for users that recently registered but are NOT active and to order it by registration date, we would have to:
#### Define New Api Route
```
<?php
Route::get('/users/getlatest', 'UsersController::getLatestRegistrations')
```
#### Then Write out a controller function
```
<?php
class UsersController extends Controller {
    public function getLatestRegistrations() {
        $results = Users::where('is_active',0)->orderBy('registration_date') -> get();
        return response() ->json($results):
    }
}
```
This method often has the following extended development requirements:
* Code changes must be QAed and pushed to production on both api team and mobile/javascript team
* Creating more code to maintain in controllers, somtimes unneccesary code bloat
* Easy to get lazy and start putting functions that SHOULD be in models, in the controller
* Often does not scale well as development requirements change with new or different functionality

Instead an approach like a universal crud controllers allows use to quickly and flexibility write code that easily changed with requirements and is easier to push to production.

### Solution: Implement A Solution That Requires Less Work/People/Processes
With a controller like the one below we are going to:
* Cut out a portion of the QA processes needed to validate good code
* Not write extra routes and controller functions
* Require no interaction with the API team for implemention
* Set standard access for access also data

The mobile/javascript team will simply just have to define a route and pass the right parameters to get the data they want. This is done by the following url:

/crud/users?conditions=[{active: 0}]&order_by=registration_date&limit=5

Thats all the javascript/mobile team has to call to get the same results with the above. They are free to change the parameters as needed on their end without and the only QA that is required is in their section. They can use the same methods for calling ANY model and even calling methods in models.

## How To Install
This controller should added as vendor within a project. Please clone this down to your project with the following:...coming sonng

## How It Works
### Setuping up the routes
First you will need to setup your routes to correctly access the funtionality. Add the following to your router file in Laravel and change the paths as needed to fit your environment.
```
<?php
Route::get('/crud/{model}', 
'Reflexions\Laravel\Controllers\CrudController@query');
Route::get('/crud/{model}/{id}', 
'Reflexions\Laravel\Controllers\CrudController@query')->where('id', '[0-9]+');;
Route::post('/crud/{model}', 
'Reflexions\Laravel\Controllers\CrudController@create');
Route::post('/crud/{model}/{function}', 
'Reflexions\Laravel\Controllers\CrudController@execute');
Route::post('/crud/{model}/{function}/{id}', '
Reflexions\Laravel\Controllers\CrudController@execute')->where('id', '[0-9]+');;
Route::put('/crud/{model}/{id}', 
'Reflexions\Laravel\Controllers\CrudController@update')->where('id', '[0-9]+');;
Route::delete('/crud/{model}', 
'Reflexions\Laravel\Controllers\CrudController@delete');
```
You can change the paths, but the variables {model}, {function} and {id} MUST remain the same.

### Understanding The Model
You will notice that every route has a {model} tag. This model tag will directly relate to a model in our project.
For examples:
* /crud/users Should relate to the users model
* /crud/posts Should relate to the posts model

Just by switching the model name, we can change the model being accessed. When you are writing your mobile or web app, this will make calling different models very easy without having to define extra routes in Laravel.

### Model Mapping
In some instances, you may want to map your models because:
* Security to not expose the actual name of your models
* You have models in funky places that do not follow a set structure

Using a service provider, you can pass in paramters to and set the model map. For example:
```
<?php
$map = array(
    'accounts' = "App\Models\Users",
    'entries'=> "Vendors\Tumblr\Posts"
);
$controller = new CrudController('App\', $map);
```
When this code is entered, the following will happen:
* The route '/crud/accounts' will map to the App\Models\Users model
* The route '/crud/entries' will map to the Vendors\Tumblr\Posts
* 
### Creating An Item
With an understanding of how the models work, we can move to basic crud operations. The creation of object is normally done by a post. If our model requires an 'email', 'name' to create a user, we can set request like this:
```
$.ajax({
  type: "POST",
  url: '/crud/users',
  data: {data : {'email': 'jondoe@example.com', 'name' : 'John Doe'}},
  success: function(response) {},
});
```
### Searching For An Item
In the CRUD api, we can easily search on any model. In our next examples, we might want to search for posts in the system. We need to first understand the parameters we can use.
##### Conditions
Conditions relates to the WHERE clause in your sql. If want to search where posts are published AND on the home page, we can post conditions like this:
```
$.ajax({
  type: "GET",
  url: '/crud/posts',
  data: {conditions : [{'is_published' : 1}, {'on_homepage' : 'Yes'}]},
  success: function(response) {},
});
```
Lets see we want on published, on homepage and the number of likes is greater than 10
```
$.ajax({
  type: "GET",
  url: '/crud/posts',
  data: {conditions : [{'is_published' : 1}, {'on_homepage' : 'Yes'}, {'likes': ['>', 10]}]},
  success: function(response) {},
});
```
##### With
In Laraval, one way of doing joins is with the "with" options. Remember the with operationsin Laravel should be defined IN THE MODEL. For example, this is with the author:
```
<?php
class Posts extends Model {
    pubic function author() {
         return $this->belongsTo(User::class, 'user_id');
    }
}
```
Our ajax call will look like this:
```
$.ajax({
  type: "GET",
  url: '/crud/posts',
  data: {
    conditions : [{'is_published' : 1}, {'on_homepage' : 'Yes'}],
    with: ['author']
  },
  success: function(response) {},
});
```

#### Joins
We can also explicity define joins on our tables directly. On your post table, lets join the users table and an image table.

```
$.ajax({
  type: "GET",
  url: '/crud/posts',
  data: {
    conditions : [{'is_published' : 1}, {'on_homepage' : 'Yes'}],
    'joins': [
        {'users' : [posts.user_id, '=', 'users.id']}, 
        {'images' : [posts.images_id, '=', 'image.id']}
    ]
  },
  success: function(response) {},
});
```

### Calling A Models Functions
Sometimes you will have to run very complex operations that a query cannot easily solve. Complex operations should be defined in the model, not controllers and by following that thinking, we can access those functions via the api. Let's see I want to send a push notification to a user.

```
<?php
class Users extends Model {
    public function logout() {
        //End the Users Session
    }
    pubic function sendMessage($text) {
         //Will Send Massage
    }
}
```
To call the logout function, we can do:
```
$.ajax({
  type: "POST",
  url: '/users/logout/3',
  data: {},
  success: function(response) {},
});
```

To send a message to a user:
```
$.ajax({
  type: "POST",
  url: '/users/sendMessage/3',
  data: {
    'params' : ['You Rock Dude!']
  },
  success: function(response) {},
});
```
Again, we can dynamically access our api without haivng to do update.

# MORE TO COME SOOON
