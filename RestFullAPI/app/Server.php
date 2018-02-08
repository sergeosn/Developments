<?php
namespace app;
use app\Auth\AuthInterface;

/**
 * RESTFull API
 *
 */
class Server {
	/**
	 * List of HTTP methods supported by this server
	 *
	 * @type array
	 */
	private static $supported_methods = [
		self::HTTP_GET,
		self::HTTP_POST,
		self::HTTP_DELETE,
		self::HTTP_PUT,
		self::HTTP_PATCH,
	];

	/**
	 * POST parameters
	 * @var array
	 */
	protected static $data_post;

	/**
	 * GET parameters
	 * @var array
	 */
	protected static $data_get;

	const HTTP_GET = 'GET';
	const HTTP_POST = 'POST';
	const HTTP_PUT = 'PUT';
	const HTTP_PATCH = 'PATCH';
	const HTTP_DELETE = 'DELETE';

	const JSON = 'application/json';
	const XML = 'application/xml';

	/**
	 * Instance of AuthInterface
	 * @var AuthInterface
	 */
	protected $authHandler = null;

	/**
	 * The url which will be call
	 * @var string
	 */
	protected $uri;

	/**
	 * The method that is called
	 * @var string
	 */
	protected $method;

	/**
	 * Content type of the caller request
	 * @var string
	 */
	protected $content_type;


	/**
	 * Builder of the class
	 *
	 * @param \app\Auth\AuthInterface $handler
	 *
	 * @return \app\Server
	 */
	public static function build(AuthInterface $handler) {
		return new self($handler);
	}

	/**
	 * Get list of HTTP Methods supported by the server
	 *
	 * @return array
	 */
	public static function get_supported_methods() {
		return self::$supported_methods;
	}

	/**
	 * Get one or all $_GET parameters
	 *
	 * @param string $name - if null return full $_GET array
	 * @param mixed $default_value
	 *
	 * @return mixed
	 */
	public static function data_get($name = null, $default_value = null) {
		if (empty($name)) {
			return self::$data_get;
		}

		if (isset(self::$data_get[$name])) {
			return self::$data_get[$name];
		}

		return $default_value;
	}

	/**
	 * Get one or all $_POST parameters
	 *
	 * @param string $name - if null return full $_POST array
	 * @param mixed $default_value
	 *
	 * @return mixed
	 */
	public static function data_post($name = null, $default_value = null) {
		if (empty($name)) {
			return self::$data_post;
		}

		if (isset(self::$data_post[$name])) {
			return self::$data_post[$name];
		}

		return $default_value;
	}

	/**
	 * Run actions
	 */
	public function run() {
		try {
			$route = Router::find_route($this->method, $this->uri);

			if ($route) {
				$controller = new $route['controller'];

				if (!$this->authHandler->isAuth($controller)) {
					$this->authHandler->unauthorized();
				}

				$result = call_user_func_array([$controller, $route['action']], $route['parameters']);

				if ($result !== null) {
					$this->send($result);
				} else {
					$this->error(404, 'Not Found');
				}
			} else {
				$this->error(404, 'Not Found');
			}
		} catch (\Exception $e) {
			$this->error($e->getCode(), $e->getMessage());
		}
	}

	/**
	 * Close connection with error code.
	 *
	 * @param int $code
	 * @param string $message
	 */
	protected static function error($code, $message) {
		header('Content-Type: text/html', true, $code);
		header('Content-Length: ' . strlen($message));

		echo $message;
	}

	/**
     * Constructor of class
     * @param AuthInterface $handler
     */
    protected function  __construct(AuthInterface $handler) {
        $this->authHandler = $handler;
		$this->uri = $this->getPath();
		$this->method = $this->getMethod();
		$this->content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : (isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : self::JSON);

		self::$data_post = $this->getPostData();
		self::$data_get = empty($_GET) ? [] : $_GET;
    }

    /**
	 * Get path from ulr
	 *
	 * @return string
	 * @throws \Exception
	 */
    protected function getPath() {
		if (!empty($_SERVER['PATH_INFO'])) {
			$uri = $_SERVER['PATH_INFO'];
		} else if (!empty($_SERVER['REQUEST_URI'])) {
			$uri = $_SERVER['REQUEST_URI'];
		} else if (!empty($_SERVER['PHP_SELF'])) {
			$uri = $_SERVER['PHP_SELF'];

			if (strpos($uri, '/index.php') !== false) {
				$uri = substr_replace($uri, '', strpos($uri, '/index.php'), strlen('/index.php'));
			}
		} else {
			throw new \Exception('Cannot find URI from: PATH_INFO, REQUEST_URI, PHP_SELF');
		}

		// Clear url
		if (strpos($uri, '?') !== false) {
			$uri = substr($uri, 0, strpos($uri, '?'));
		}

		//Get base path
		$rootUri = '';

		if (!empty($_SERVER['SCRIPT_NAME'])) {
			$index_position = strpos($_SERVER['SCRIPT_NAME'], 'index.php');

			if ($index_position !== false) {
				$rootUri = substr($_SERVER['SCRIPT_NAME'], 0, $index_position);
			}
		}

		if (!empty($rootUri) && (strpos($uri, $rootUri) === 0)) {
			$uri = substr($uri, strlen($rootUri));
		}

		return trim($uri, '/');
	}

	/**
	 * Get call method
	 *
	 * @return string
	 */
	protected function getMethod() {
		return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : self::HTTP_GET;
	}

	/**
	 * Get post data
	 *
	 * @return array
	 */
	protected function getPostData() {
		switch ($this->content_type) {
			case self::JSON:
				$data_post = json_decode(file_get_contents("php://input"), true);

				break;
			case self::XML:
				$xml = simplexml_load_string(file_get_contents("php://input"));
				$json = json_encode($xml);
				$data_post = json_decode($json, true);

				break;
			default:
				if (isset($_SERVER['REQUEST_METHOD']) && (in_array($_SERVER['REQUEST_METHOD'], [self::HTTP_PUT,  self::HTTP_PATCH, self::HTTP_DELETE]))) {
					parse_str(file_get_contents("php://input"), $data_post);
				} else {
					$data_post = empty($_POST) ? [] : $_POST;
				}
		}

		return $data_post;
	}

	/**
	 * Render response as string, send headers and response to output
	 *
	 * @param array $data
	 */
	protected function send($data) {
		switch ($this->content_type) {
			case self::JSON:
				$response = json_encode($data, JSON_UNESCAPED_SLASHES);
				break;
			case self::XML:
				$response = self::xml_encode('response', $data);
				break;
			default:
				$response = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES) : (string) $data;
				break;
		}

		header('Content-Type: ' . $this->content_type . '; charset=utf-8');
		header('Content-Length: ' . strlen($response));

		echo $response;
	}

	/**
	 * Format data for response in XML type
	 */
	protected function xml_encode($root, $values) {
		return $this->xml_add_children(new \SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?><$root/>"), $values)->asXML();
	}

	protected function xml_add_children($root, $values) {
		if (!is_array($values)) {
			$root->addChild('string', htmlspecialchars($values));
		} else {
			foreach ($values as $key => $value) {
				if ($key[0] == '@') {
					$root->addAttribute(substr($key, 1), $value);
				} else if (!is_array($value) && !is_object($value)) {
					$root->addChild($key, htmlspecialchars($value));
				} else {
					$this->xml_add_children($root->addChild($key), $value);
				}
			}
		}

		return $root;
	}
}
