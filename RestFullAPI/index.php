<?php
/**
 * Main index file
 *
 * HOW TO TEST:
 * -----------------------------------
 * At this moment exist three routes:
 *  1) POST http://hostname.com/auth/
 *  For get auth token need pass 'username' and 'password' in the header, then you will get token key for authorization
 *  Test data:
 *   username: admin
 *   password: 123456789
 *
 *  2)GET http://hostname.com/user/
 *  Endpoint for get list of users. Also need pass the get parameter 'token' for authorization.
 *
 *  3) GET http://hostname.com/user/<id>
 *  Endpoint for get user by his PersonalId. Also need pass the get parameter 'token' for authorization.
 *
 * -----------------------------------
 * Examples
 * 1) POST http://hostname.com/auth/
 * Response: {"token":"3yDTp8pYA/lyJpMmrQIDAQABAivkofN2YBB6mQDMBBnb"}
 *
 * 2) GET http://hostname.com/user?token=3yDTp8pYA/lyJpMmrQIDAQABAivkofN2YBB6mQDMBBnb
 * Response: [{"PersonalId":1258,"FirstName":"Sergey","SecondName":"Ivanov","Gender":"male","Age":30},{"PersonalId":2654,"FirstName":"Ivan","SecondName":"Petrov","Gender":"male","Age":25},{"PersonalId":3874,"FirstName":"Inna","SecondName":"Dmitrievna","Gender":"female","Age":19}]
 *
 * 3) GET http://hostname.com/user/3874?token=3yDTp8pYA/lyJpMmrQIDAQABAivkofN2YBB6mQDMBBnb
 * Response: {"PersonalId":3874,"FirstName":"Inna","SecondName":"Dmitrievna","Gender":"female","Age":19}
 *
 *
 * -----------------------------------
 * HOW TO USE
 * -----------------------------------
 * Add new route:
 *  Router::resourse(<method>, <url>, '<controller>@<action>');
 *  example: Router::resourse('GET', '/user', 'UserController@lists');
 *
 * Create new Controller in the controller namespase with implementation actions which you typed in the routes
 * Implement method 'public function authorize()' for check authorization
 * If in the controller`s action need send error need just throw \Exception with code (example  \Exception('User list is empty', 404);)
 *
 */

//region configs
require_once(__DIR__ . '/app/Server.php');
require_once(__DIR__ . '/app/Auth/AuthInterface.php');
require_once(__DIR__ . '/app/Auth/Handler.php');
require_once(__DIR__ . '/app/Controllers/AuthController.php');
require_once(__DIR__ . '/app/Controllers/UserController.php');
require_once(__DIR__ . '/app/Models/AuthModel.php');
require_once(__DIR__ . '/app/Models/UserModel.php');
require_once(__DIR__ . '/app/Router.php');
require_once(__DIR__ . '/app/Routing.php');
//endregion

app\Server::build(new app\Auth\Handler())->run();