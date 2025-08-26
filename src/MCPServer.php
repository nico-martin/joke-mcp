<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MCPServer
{
	private $sessionsFile = 'data/mcp_sessions.json';

	public function __construct()
	{
		// Ensure data directory exists
		if (!is_dir('data')) {
			mkdir('data', 0755, true);
		}
	}

	private function loadSessions()
	{
		if (file_exists($this->sessionsFile)) {
			$data = file_get_contents($this->sessionsFile);
			return json_decode($data, true) ?: [];
		}
		return [];
	}

	private function saveSessions($sessions)
	{
		file_put_contents($this->sessionsFile, json_encode($sessions));
	}

	private function validateOrigin($request)
	{
		// Security: Validate Origin header to prevent DNS rebinding attacks
		$origin = $request->headers['Origin'] ?? '';
		$allowedOrigins = [
			'http://localhost',
			'https://localhost',
			'http://127.0.0.1',
			'https://127.0.0.1'
		];

		// Allow requests without Origin (direct API calls)
		if (empty($origin)) {
			return true;
		}

		foreach ($allowedOrigins as $allowed) {
			if (strpos($origin, $allowed) === 0) {
				return true;
			}
		}

		return false;
	}

	private function getSessionId($request)
	{
		// Check multiple possible header formats
		return $request->headers['Mcp-Session-Id'] ??
			$request->headers['mcp-session-id'] ??
			$request->headers['MCP-Session-Id'] ??
			null;
	}

	private function generateSessionId()
	{
		return bin2hex(random_bytes(32)); // Cryptographically secure session ID
	}

	private function validateSession($sessionId)
	{
		if (!$sessionId) {
			return false;
		}

		$sessions = $this->loadSessions();
		return isset($sessions[$sessionId]);
	}

	public function getHandler($request)
	{
		// Validate Origin for security
		if (!$this->validateOrigin($request)) {
			http_response_code(403);
			$error = ['error' => 'Forbidden: Invalid origin'];
			return $error;
		}

		// Check Accept header for SSE support
		$accept = $request->headers['Accept'] ?? '';
		if (strpos($accept, 'text/event-stream') === false) {
			http_response_code(406);
			$error = ['error' => 'Not Acceptable: text/event-stream required'];
			return $error;
		}

		// For GET requests, we can start an SSE stream for server-to-client notifications
		// For now, we'll return 405 as we don't implement server-initiated notifications
		http_response_code(405);
		$error = ['error' => 'Method Not Allowed: GET SSE stream not implemented'];
		return $error;
	}

	public function postHandler($request)
	{
		if (!$this->validateOrigin($request)) {
			http_response_code(403);
			$error = ['error' => 'Forbidden: Invalid origin'];
			return $error;
		}

		// Check Accept header
		$accept = $request->headers['Accept'] ?? '';
		if (strpos($accept, 'application/json') === false && strpos($accept, 'text/event-stream') === false) {
			http_response_code(406);
			$error = ['error' => 'Not Acceptable: application/json or text/event-stream required'];
			return $error;
		}

		$input = $request->post;

		// Handle empty input or notifications/responses only
		if (!$input) {
			http_response_code(400);
			$error = ['error' => 'Bad Request: Empty body'];
			return $error;
		}

		// Support both single messages and batched arrays
		$messages = is_array($input) && isset($input[0]) ? $input : [$input];
		$hasRequests = false;

		foreach ($messages as $message) {
			if (isset($message['method']) && isset($message['id'])) {
				$hasRequests = true;
				break;
			}
		}

		// If no requests (only responses/notifications), return 202
		if (!$hasRequests) {
			http_response_code(202);
			return null; // No body
		}

		// Process requests
		$responses = [];
		foreach ($messages as $message) {
			if (isset($message['method'])) {
				$method = $message['method'];
				$params = $message['params'] ?? [];
				$id = $message['id'] ?? null;

				// Session validation for non-initialize requests (skip notifications)
				if ($method !== 'initialize' && $id !== null) {
					$sessionId = $this->getSessionId($request);

					if (!$sessionId) {
						// Create a new session if none provided
						$sessionId = $this->generateSessionId();
						$sessions = $this->loadSessions();
						$sessions[$sessionId] = [
							'created' => time(),
							'lastActivity' => time()
						];
						$this->saveSessions($sessions);
						// Set session header for response
						header("Mcp-Session-Id: $sessionId");
					} else if (!$this->validateSession($sessionId)) {
						http_response_code(400);
						$error = $this->error('Bad Request: Invalid session ID', -32603, $id);
						return $error;
					}
				}

				try {
					$response = $this->processMethod($method, $params, $id, $request);
					if ($response) {
						$responses[] = $response;
					}
				} catch (Exception $e) {
					$responses[] = $this->error($e->getMessage(), -32603, $id);
				}
			}
		}

		// Return JSON response (not implementing SSE for simplicity)
		$result = count($responses) === 1 ? $responses[0] : $responses;
		return $result;
	}

	public function deleteHandler($request)
	{
		// Handle session termination
		$sessionId = $this->getSessionId($request);
		$sessions = $this->loadSessions();

		if ($sessionId && isset($sessions[$sessionId])) {
			unset($sessions[$sessionId]);
			$this->saveSessions($sessions);
			http_response_code(200);
			$response = ['message' => 'Session terminated'];
			return $response;
		}

		http_response_code(404);
		$error = ['error' => 'Session not found'];
		return $error;
	}

	private function processMethod($method, $params, $id, $request)
	{
		switch ($method) {
			case 'initialize':
				return $this->initialize($params, $id, $request);
			case 'tools/list':
				return $this->listTools($params, $id);
			case 'tools/call':
				return $this->callTool($params, $id, $request);
			case 'notifications/initialized':
				// This is a notification, no response needed
				return null;
			default:
				return $this->error('Method not found', -32601, $id);
		}
	}

	private function initialize($params, $id, $request)
	{
		// Generate new session ID
		$sessionId = $this->generateSessionId();

		// Load existing sessions and add new one
		$sessions = $this->loadSessions();
		$sessions[$sessionId] = [
			'created' => time(),
			'lastActivity' => time()
		];
		$this->saveSessions($sessions);

		// Set session header for response
		header("Mcp-Session-Id: $sessionId");

		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => [
				'protocolVersion' => '2024-11-05',
				'capabilities' => [
					'tools' => [
						'listChanged' => false
					]
				],
				'serverInfo' => [
					'name' => 'Google Calendar MCP Server',
					'version' => '1.0.0'
				]
			]
		];
	}

	private function listTools($params, $id)
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => [
				'tools' => [
					[
						'name' => 'get_joke',
						'description' => 'Fetches and returns a joke from JokeAPI that you can share with the user. The joke will be returned as text content ready to be displayed.',
						'inputSchema' => [
							'type' => 'object',
							'properties' => [
								'category' => [
									'type' => 'string',
									'enum' => ['Any', 'Programming', 'Misc', 'Pun', 'Spooky', 'Christmas'],
									'description' => 'Category of joke to retrieve'
								],
								'type' => [
									'type' => 'string',
									'enum' => ['single', 'twopart'],
									'description' => 'Type of joke (single line or setup/delivery)'
								],
								'contains' => [
									'type' => 'string',
									'description' => 'Search for jokes containing this string'
								],
								'amount' => [
									'type' => 'integer',
									'minimum' => 1,
									'maximum' => 10,
									'description' => 'Number of jokes to retrieve (1-10)'
								]
							],
							'required' => []
						]
					],
				]
			]
		];
	}


	private function callTool($params, $id, $request)
	{
		$name = $params['name'] ?? null;
		$arguments = $params['arguments'] ?? [];

		if (!$name) {
			return $this->error('Tool name is required', -32602, $id);
		}

		switch ($name) {
			case 'get_joke':
				return $this->getJoke($arguments, $id);
			default:
				return $this->error('Unknown tool: ' . $name, -32602, $id);
		}
	}

	private function getJoke($arguments, $id)
	{
		$category = $arguments['category'] ?? 'Any';
		$type = $arguments['type'] ?? null;
		$contains = $arguments['contains'] ?? null;
		$amount = $arguments['amount'] ?? 1;

		// Build API URL
		$url = 'https://v2.jokeapi.dev/joke/' . urlencode($category);
		$params = [];

		// Add query parameters
		if ($type) {
			$params['type'] = $type;
		}
		if ($contains) {
			$params['contains'] = $contains;
		}
		if ($amount > 1) {
			$params['amount'] = $amount;
		}

		// Always enable safe-mode
		$params['safe-mode'] = 'true';

		// Add format parameter
		$params['format'] = 'json';

		// Build query string
		if (!empty($params)) {
			$url .= '?' . http_build_query($params);
		}

		try {
			// Make HTTP request using Guzzle
			$client = new Client([
				'timeout' => 10,
				'headers' => [
					'User-Agent' => 'JokeMCP/1.0'
				]
			]);

			$response = $client->get($url);
			$body = $response->getBody()->getContents();
			
			$data = json_decode($body, true);
			if (!$data) {
				return [
					'jsonrpc' => '2.0',
					'id' => $id,
					'result' => [
						'content' => [[
							'type' => 'text',
							'text' => 'Failed to parse API response'
						]],
						'isError' => true
					]
				];
			}

			// Handle API errors
			if (isset($data['error']) && $data['error']) {
				return [
					'jsonrpc' => '2.0',
					'id' => $id,
					'result' => [
						'content' => [[
							'type' => 'text',
							'text' => 'API Error: ' . ($data['message'] ?? 'Unknown error')
						]],
						'isError' => true
					]
				];
			}

			// Format the response
			if (isset($data['jokes'])) {
				// Multiple jokes
				$jokes = [];
				foreach ($data['jokes'] as $joke) {
					$jokes[] = $this->formatJoke($joke);
				}
				$text = implode("\n\n", $jokes);
			} else {
				// Single joke
				$text = $this->formatJoke($data);
			}

			return [
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => [
					'content' => [[
						'type' => 'text',
						'text' => $text
					]],
					'isError' => false
				]
			];

		} catch (RequestException $e) {
			return [
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => [
					'content' => [[
						'type' => 'text',
						'text' => 'HTTP Error: ' . $e->getMessage()
					]],
					'isError' => true
				]
			];
		} catch (Exception $e) {
			return [
				'jsonrpc' => '2.0',
				'id' => $id,
				'result' => [
					'content' => [[
						'type' => 'text',
						'text' => 'Error: ' . $e->getMessage()
					]],
					'isError' => true
				]
			];
		}
	}

	private function formatJoke($joke)
	{
		if ($joke['type'] === 'single') {
			return $joke['joke'];
		} else {
			return $joke['setup'] . "\n" . $joke['delivery'];
		}
	}

	private function error($message, $code, $id = null)
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => [
				'code' => $code,
				'message' => $message
			]
		];
	}
}