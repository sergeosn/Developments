<?php

namespace app\Controllers;

use app\Models\AuthModel;
use app\Models\UserModel;
use app\Server;

class UserController {
    /**
     * Granted only for authorized users
     * @return bool
     */
    public function authorize() {
        $token = Server::data_get('token');

        if (empty($token) || $token != AuthModel::getAccessToken()) {
            return false;
        }

        return true;
    }

    /**
     * Get list of users
     * @return array
     * @throws \Exception
     */
    public function lists() {
        $list = UserModel::getUsers();

        if (empty($list)) {
            throw new \Exception('User list is empty', 404);
        }

        return $list;
    }

    /**
     * Get user by id
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function getUserById($id) {
        $user = UserModel::getUserById($id);;

        if (empty($user)) {
            throw new \Exception('User is not found', 404);
        }

        return $user;
    }
}
