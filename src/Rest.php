<?php

namespace NicoMartin\Rest;

require_once 'RestError.php';

/**
 * @property array<Route> $routes
 */
class Rest
{
	private array $routes = [];

	public function __construct()
	{
	}

	public function get(string $route, callable $callback)
	{
		$this->addRoute('GET', $route, $callback);
	}

	public function post(string $route, callable $callback)
	{
		$this->addRoute('POST', $route, $callback);
	}

	public function put(string $route, callable $callback)
	{
		$this->addRoute('PUT', $route, $callback);
	}

	public function delete(string $route, callable $callback)
	{
		$this->addRoute('DELETE', $route, $callback);
	}

	private function addRoute(string $method, string $route, callable $callback)
	{
		$this->routes[] = new Route($method, $route, $callback);
	}

	private function parsePath(string $pattern, string $path): ?array
	{
		$regex = preg_replace('/\{(\w+)\}/', '(?P<\1>[^/]+)', $pattern);
		if (preg_match("#^$regex$#", $path, $matches)) {
			return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
		}

		return null;
	}

	public function start()
	{
		// CORS headers and OPTIONS handling are done in index.php
		header('Content-Type: application/json');
		try {
			$method = $_SERVER['REQUEST_METHOD'];
			$uri = $_SERVER['REQUEST_URI'];
			$uri = explode('?', $uri)[0];
			$found = false;
			foreach ($this->routes as $route) {
				$urlParams = $this->parsePath($route->path, $uri);
				if ($route->method === $method && $urlParams !== null) {

					$request = new Request();
					$request->get = array_merge($urlParams, $_GET);
					$request->cookies = $_COOKIE;
					$request->headers = getallheaders();
					
					// Handle JSON request body
					$contentType = $request->headers['Content-Type'] ?? '';
					if (strpos($contentType, 'application/json') !== false) {
						$jsonInput = file_get_contents('php://input');
						$request->post = json_decode($jsonInput, true) ?? [];
					} else {
						$request->post = $_POST;
					}

					$return = ($route->callback)($request);

					// Ensure CORS headers are set before response
					header('Access-Control-Allow-Origin: *');
					header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
					header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Mcp-Session-Id');
					
					http_response_code(200);
					echo json_encode($return);
					$found = true;
					break;
				}
			}
			if (!$found) {
				throw new RestError('not_found', 'Route not found', 404);
			}
		} catch (RestError $e) {
			// Ensure CORS headers are set for error responses
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
			header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Mcp-Session-Id');
			
			http_response_code($e->statusCode);
			echo json_encode($e->errorObject());
		}
	}
}

class Route
{
	public string $method;
	public string $path;
	/**
	 * @var callable
	 */
	public mixed $callback;

	public function __construct(string $method, string $path, callable $callback)
	{
		if (!is_callable($callback)) {
			throw new \InvalidArgumentException("Handler must be callable.");
		}
		$this->method = $method;
		$this->path = $path;
		$this->callback = $callback;
	}
}

/**
 * @property array<string, string> $get
 * @property array<string, string> $post
 * @property array<string, string> $cookies
 * @property array<string, string> $headers
 */
class Request
{
	public array $get = [];
	public array $post = [];
	public array $cookies = [];
	public array $headers = [];
}