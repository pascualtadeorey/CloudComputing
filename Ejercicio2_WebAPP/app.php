<?php

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT value FROM my_table LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $message = sprintf(
            "<h1 style='color:%s;'>%s</h1> <p style='color:%s; font-size: %spx;'>%s</p>",
            getenv('MESSAGE_COLOR') ?: 'green',
            getenv('MESSAGE_TITLE') ?: 'Value from database:',
            getenv('VALUE_COLOR') ?: 'green',
            getenv('VALUE_SIZE') ?: '24',
            htmlspecialchars($result['value'])
        );
        echo $message;

    } else {
        echo "<h1 style='color:red;'>No value found in the database.</h1>";
    }
} catch (PDOException $e) {
    echo "<h1 style='color:red;'>Database error:</h1> <p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

