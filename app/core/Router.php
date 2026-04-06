<?php
/**
 * SHIFFIN - Simple API Router
 */
class Router
{
    public static function handle(): void
    {
        $uri = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];

        // Remove base path and query string
        $basePath = '/shifin/api';
        $uri = parse_url($uri, PHP_URL_PATH);

        if (strpos($uri, $basePath) !== 0) {
            Response::error('Not found', 404);
        }

        $path = substr($uri, strlen($basePath));
        $path = trim($path, '/');
        $segments = $path ? explode('/', $path) : [];

        if (empty($segments)) {
            Response::success(['app' => 'SHIFFIN API', 'version' => '1.0.0']);
        }

        // Build file path from segments
        $apiBase = __DIR__ . '/../../api';
        $file = null;
        $params = [];

        // Try to match: /group/action or /group/id/action
        if (count($segments) >= 2) {
            $group = $segments[0];

            // Check for /group/action pattern (e.g., /auth/login)
            $testFile = $apiBase . '/' . $group . '/' . $segments[1] . '.php';
            if (file_exists($testFile)) {
                $file = $testFile;
                $params = array_slice($segments, 2);
            }

            // Check for /group/resource pattern with remaining as params
            if (!$file) {
                $testFile = $apiBase . '/' . $group . '.php';
                if (file_exists($testFile)) {
                    $file = $testFile;
                    $params = array_slice($segments, 1);
                }
            }

            // Check for /group/id pattern => use group handler with id
            if (!$file && count($segments) >= 2) {
                $testFile = $apiBase . '/' . $group . '/index.php';
                if (file_exists($testFile)) {
                    $file = $testFile;
                    $params = array_slice($segments, 1);
                }
            }
        }

        // Single segment: /group
        if (!$file && count($segments) === 1) {
            $testFile = $apiBase . '/' . $segments[0] . '.php';
            if (file_exists($testFile)) {
                $file = $testFile;
            }
            $testFile = $apiBase . '/' . $segments[0] . '/index.php';
            if (!$file && file_exists($testFile)) {
                $file = $testFile;
            }
        }

        if (!$file) {
            Response::error('Endpoint not found: ' . $path, 404);
        }

        // Make params available
        $_REQUEST['_params'] = $params;
        $_REQUEST['_method'] = $method;
        $_REQUEST['_segments'] = $segments;

        require $file;
    }

    public static function getParam(int $index = 0): ?string
    {
        return $_REQUEST['_params'][$index] ?? null;
    }

    public static function getMethod(): string
    {
        return $_REQUEST['_method'] ?? $_SERVER['REQUEST_METHOD'];
    }

    public static function getSegments(): array
    {
        return $_REQUEST['_segments'] ?? [];
    }

    public static function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            return $input ?: [];
        }
        return $_POST;
    }

    public static function getQuery(): array
    {
        return $_GET;
    }
}
