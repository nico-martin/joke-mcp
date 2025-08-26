# Joke MCP Server

A Model Context Protocol (MCP) server that provides access to the [JokeAPI](https://sv443.net/jokeapi/v2/) for retrieving jokes with various filtering options.

## Quick Start

Add this MCP server to your client using the endpoint:
```
https://jokes.nico.dev/mcp
```

Your MCP client will automatically discover the `get_joke` tool and you can start fetching jokes immediately.

## Features

- **Single tool**: `get_joke` - Fetch safe jokes from the JokeAPI
- **Safe by default**: Always uses safe-mode to filter out offensive content
- **Multiple filtering options**: Category, joke type, search terms
- **Multiple jokes**: Retrieve up to 10 jokes at once
- **MCP compliant**: Full JSON-RPC 2.0 implementation with session management

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd joke-mcp
```

2. Install dependencies:
```bash
composer install
```

## Usage

The server exposes one tool: `get_joke`

### Parameters

All parameters are optional:

- `category` (string): Joke category - `Any`, `Programming`, `Misc`, `Pun`, `Spooky`, `Christmas`
- `type` (string): Joke format - `single` (one-liner) or `twopart` (setup/delivery)
- `contains` (string): Search for jokes containing this text
- `amount` (integer): Number of jokes to retrieve (1-10)

**Note**: This server automatically uses safe-mode to ensure all jokes are appropriate and filter out offensive content.

### Example Requests

Get a programming joke:
```json
{
  "jsonrpc": "2.0",
  "id": "1",
  "method": "tools/call",
  "params": {
    "name": "get_joke",
    "arguments": {
      "category": "Programming"
    }
  }
}
```

Get multiple pun jokes:
```json
{
  "jsonrpc": "2.0",
  "id": "2",
  "method": "tools/call",
  "params": {
    "name": "get_joke",
    "arguments": {
      "category": "Pun",
      "amount": 3
    }
  }
}
```

Search for jokes about cats:
```json
{
  "jsonrpc": "2.0",
  "id": "3",
  "method": "tools/call",
  "params": {
    "name": "get_joke",
    "arguments": {
      "contains": "cat"
    }
  }
}
```

## API Integration

This server integrates with the [JokeAPI v2](https://sv443.net/jokeapi/v2/) which provides:
- 120 requests per minute rate limit
- No authentication required
- Multiple response formats (JSON default)
- Safe-mode enabled by default to filter out offensive content

## Technical Details

- **PHP**: Requires PHP 8.0+
- **HTTP Client**: Uses Guzzle HTTP for reliable API requests
- **Session Management**: Implements MCP session handling with file-based storage
- **Security**: Origin validation, proper error handling, and safe-mode enforcement
- **MCP Protocol**: Full compliance with MCP specification

## File Structure

```
src/
├── MCPServer.php          # Main MCP server implementation
data/
├── mcp_sessions.json      # Session storage (created automatically)
composer.json              # Dependencies and autoloading
```

## Development

The server follows MCP protocol standards and can be integrated with any MCP-compatible client. It handles:

- Tool discovery via `tools/list`
- Tool execution via `tools/call` 
- Session management with secure session IDs
- Proper JSON-RPC 2.0 response formatting
- Error handling for network and API issues
