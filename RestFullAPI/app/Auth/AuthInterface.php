<?php

namespace app\Auth;

interface AuthInterface {
    /**
     * Check authorized credentials
     *
     * @param object $obj - an instance of the controller.
     *
     * @return bool
     */
    public function isAuth($obj);

    /**
     * Is not authorized.
     */
    public function unauthorized();
}
