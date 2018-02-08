<?php
use \app\Router;

/**
 * Get auth token
 *
 * URL: POST http://hostname.com/auth/
 * @see AuthController::getAuthToken()
 */
Router::resourse('POST', '/auth', 'AuthController@getAuthToken');

/**
 * Get user list
 *
 * URL: GET http://hostname.com/user/
 * @see UserController::list()
 */
Router::resourse('GET', '/user', 'UserController@lists');

/**
 * Get user by id
 *
 * URL: GET http://hostname.com/user/<id>
 * @see UserController::getUserById()
 */
Router::resourse('GET', '/user/<id>', 'UserController@getUserById');
