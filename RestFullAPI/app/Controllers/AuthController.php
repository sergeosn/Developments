<?php

namespace app\Controllers;

use app\Models\AuthModel;
use app\Server;

/**
 * Class AuthController
 */
class AuthController {
    /**
     * All users could be verified
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Check credentials
     *
     * @return array
     * @throws \Exception
     */
    public function getAuthToken() {
        $username = Server::data_post('username');

        if (empty($username)) {
            throw new \Exception('Username parameter is invalid', 400);
        }

        $password = Server::data_post('password');

        if (empty($password)) {
            throw new \Exception('Password parameter is invalid', 400);
        }

        if ($username != AuthModel::getUsername() || $password != AuthModel::getPassword()) {
            throw new \Exception('Credentials is invalid', 401);
        }

        return ['token' => AuthModel::getAccessToken()];
    }
}