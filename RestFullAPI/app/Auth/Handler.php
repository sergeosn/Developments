<?php

namespace app\Auth;

class Handler implements AuthInterface {
	/**
	 * Check authorized credentials
	 *
	 * @param object $obj - an instance of the controller.
	 *
	 * @return bool
	 */
	public function isAuth($obj) {
		if (method_exists($obj, 'authorize')) {
			return $obj->authorize();
		}

		return false;
	}

	/**
	 * Is not authorized.
	 * @throws \Exception
	 */
	public function unauthorized() {
		header("WWW-Authenticate: Basic realm=RestFull Api");
		throw new \Exception("Unauthorized", 401);
	}
}
