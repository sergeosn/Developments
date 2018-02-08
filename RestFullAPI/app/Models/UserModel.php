<?php

namespace app\Models;

/**
 * Class Stub UserModel
 * @package app\Models
 */
class UserModel {
    protected static $users = [
        [
            'PersonalId' => 1258,
            'FirstName' => 'Sergey',
            'SecondName' => 'Ivanov',
            'Gender' => 'male',
            'Age' => 30,
        ],
        [
            'PersonalId' => 2654,
            'FirstName' => 'Ivan',
            'SecondName' => 'Petrov',
            'Gender' => 'male',
            'Age' => 25,
        ],
        [
            'PersonalId' => 3874,
            'FirstName' => 'Inna',
            'SecondName' => 'Dmitrievna',
            'Gender' => 'female',
            'Age' => 19,
        ]
    ];

    public static function getUsers() {
        return self::$users;
    }

    public static function getUserById($id) {
        foreach (self::$users as $user) {
            if ($user['PersonalId'] == $id) {
                return $user;
            }
        }

        return [];
    }
}