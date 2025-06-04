<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Tier App (PHP)</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        #data-container { margin-top: 20px; border: 1px solid #ccc; padding: 15px; min-height: 50px; background-color: #f9f9f9;}
        #error-container { color: red; margin-top: 10px; font-weight: bold; }
        ul { list-style-type: none; padding: 0; }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <h1>Welcome to the Multi-Tier App! (PHP Frontend)</h1>

    <h2>Data from Database via API:</h2>
    <div id="data-container">
        <?php
        // URL for the backend API service within the Kubernetes cluster
        // The service name 'backend-api-service' is resolvable via Kube DNS
        // Port 80 is the service port defined in backend-api-deployment.yaml
        $apiUrl = 'http://backend-api-service.multi-tier-app.svc.cluster.local:80/api/data';
        $errorMsg = '';
        $data = null;

        // Use file_get_contents with error handling context
        // Enable allow_url_fopen in php.ini (usually enabled by default in php:apache images)
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]); // Timeout in seconds
        $responseJson = @file_get_contents($apiUrl, false, $context); // Suppress warnings on failure

        if ($responseJson === false) {
            $error = error_get_last();
            $errorMsg = "Failed to connect to API: " . ($error['message'] ?? 'Unknown error');
        } else {
            // Check HTTP status code from headers ($http_response_header is auto-populated)
            if (isset($http_response_header[0]) && strpos($http_response_header[0], '200 OK') === false) {
                 $errorMsg = "API Error: Received status " . htmlspecialchars($http_response_header[0]);
            } else {
                $data = json_decode($responseJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errorMsg = "Failed to decode JSON response from API: " . json_last_error_msg();
                    $data = null; // Ensure data is null on decode error
                }
            }
        }

        // Display data or error message
        if (!empty($errorMsg)) {
            echo '<div id="error-container">' . htmlspecialchars($errorMsg) . '</div>';
        } elseif ($data !== null && count($data) > 0) {
            echo '<ul>';
            foreach ($data as $item) {
                echo '<li>ID: ' . htmlspecialchars($item['id'] ?? '?') . ', Name: ' . htmlspecialchars($item['name'] ?? '?') . '</li>';
            }
            echo '</ul>';
        } elseif ($data !== null) {
            echo 'No data found in the database.';
        } else {
             // Should have been caught by errorMsg, but as a fallback
             echo '<div id="error-container">An unexpected issue occurred while fetching data.</div>';
        }
        ?>
    </div>
     <p style="font-size: 0.8em; color: #666; margin-top: 15px;">Served by Pod: <?php echo htmlspecialchars(gethostname()); ?></p>
</body>
</html>
