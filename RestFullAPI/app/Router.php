<?php
namespace app;

/**
 * Route class for add and build routes
 */
class Router {
	/**
	 * Registered routing table
	 * @var array
	 */
	private static $map = [];

	public static function resourse($method, $uri, $controller) {
		if (!in_array($method, server::get_supported_methods())) {
			throw new \InvalidArgumentException("Invalid HTTP method: '{$method}''!");
		}

		if (empty($uri) || !is_string($uri)) {
			throw new \InvalidArgumentException("Uri parameter is invalid");
		}

		if (empty($controller) || !is_string($controller) || substr_count($controller, '@') != 1) {
			throw new \InvalidArgumentException("Controller parameter is invalid");
		}

		$controller = explode('@', $controller);
		$controller_name = '\\app\\Controllers\\' . $controller[0];
		$action = $controller[1];

		if (!class_exists($controller_name)) {
			throw new \InvalidArgumentException("Controller is invalid");
		}

		if (!method_exists($controller_name, $action)) {
			throw new \InvalidArgumentException("Action is invalid");
		}

		self::$map[$controller_name][] = [
			'action' => $action,
			'method' => $method,
			'uri' => trim($uri, '/'),
		];
	}

	/**
	 * Find the action by url and method and return array with data:
	 * [
	 * 		'controller' => string, // controller_name
	 *      'action' => string, // action_name
	 *      'parameters' => array, // values of the request parameters
	 * ]
	 *
	 * @param string $http_method
	 * @param string $uri
	 *
	 * @return array|false
	 * @throws \InvalidArgumentException
	 */
	public static function find_route($http_method, $uri) {
		$http_method = strtoupper($http_method);

		foreach (self::$map as $controller_name => $actions) {
			foreach ($actions as $action) {
				if ($http_method != $action['method']) {
					continue;
				}

				$parameters = []; //parameters from URI

				if (strpos($action['uri'], '<') === false) {
					if ($action['uri'] != $uri) {
						continue;
					}
				} else {
					// action with parameters
					$action_parts = explode('/', $action['uri']);
					$uri_parts = explode('/', $uri);
					$action_parts_count = count($action_parts);

					if ($action_parts_count != count($uri_parts)) {
						continue; // uri and action have different amount of parts
					}

					// check uri parts with action parts one by one
					for ($i = 0; $i < $action_parts_count; $i++) {
						$action_part = $action_parts[$i];
						$uri_part = $uri_parts[$i];

						if ($action_part[0] != '<') {
							// constant part
							if ($action_part != $uri_part) {
								// next action
								continue 2;
							}
						} else {
							if (!is_scalar($uri_part)) {
								throw new \InvalidArgumentException('Parameter is not scalar');
							}

							$parameters[] = $uri_part; // collect parameters from URI
						}
					}
				}

				return [
					'controller' => $controller_name,
					'action' => $action['action'],
					'parameters' => $parameters,
				];
			}
		}

		return false;
	}
}
