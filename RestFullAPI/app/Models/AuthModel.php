<?php

namespace app\Models;

/**
 * Class Stub AuthModel
 * @package app\Models
 */
class AuthModel {
    protected static $username = 'admin';
    protected static $password = '123456789';
    protected static $access_token = '3yDTp8pYA/lyJpMmrQIDAQABAivkofN2YBB6mQDMBBnb';

    public static function getUsername() {
        return self::$username;
    }

    public static function getPassword() {
        return self::$password;
    }

    public static function getAccessToken() {
        return self::$access_token;
    }
}
