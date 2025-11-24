<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Mcp-Session-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header_remove();
	$requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
	$allowedHeaders = 'Content-Type, Authorization, X-Requested-With, Mcp-Session-Id';
	if (!empty($requestedHeaders)) {
		$allowedHeaders = $requestedHeaders . ', ' . $allowedHeaders;
	}
	header('Access-Control-Allow-Headers: ' . $allowedHeaders);
	header('Access-Control-Max-Age: 3600');
	http_response_code(200);
	exit();
}

require_once 'vendor/autoload.php';
require_once 'src/Helpers.php';
require_once 'src/Rest.php';
require_once 'src/RestError.php';
require_once 'src/MCPServer.php';

use NicoMartin\Rest;


try {
	$mcpServer = new MCPServer();
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Service initialization failed', 'message' => $e]);
	exit();
}

$restClient = new Rest\Rest();

/**
 * MCP
 */

$restClient->get('/mcp', function ($request) use ($mcpServer) {
	return $mcpServer->getHandler($request);
});

$restClient->post('/mcp', function ($request) use ($mcpServer) {
	return $mcpServer->postHandler($request);
});

$restClient->delete('/mcp', function ($request) use ($mcpServer) {
	return $mcpServer->deleteHandler($request);
});

/**
 * Joke endpoint
 */

$restClient->get('/joke', function ($request) use ($mcpServer) {
	// Get query parameters (category, type, contains, amount)
	$params = [];
	if (isset($request->get['category'])) {
		$params['category'] = $request->get['category'];
	}
	if (isset($request->get['type'])) {
		$params['type'] = $request->get['type'];
	}
	if (isset($request->get['contains'])) {
		$params['contains'] = $request->get['contains'];
	}
	if (isset($request->get['amount'])) {
		$params['amount'] = $request->get['amount'];
	}

	return $mcpServer->getJokeRest($params);
});

$restClient->start();
