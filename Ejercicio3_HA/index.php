<!DOCTYPE html>
<html>
<head>
   <title>HA Guestbook</title>
   <style>
       body { font-family: sans-serif; }
       .container { margin: 20px; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
       .pod-id { font-size: 0.8em; color: #666; margin-top: 15px; }
   </style>
</head>
<body>
   <div class="container">
       <h1>
           <?php
           // 1. Leer variables de entorno con los datos para conectar a MySQL
           $db_host = getenv('DB_HOST') ?: 'mysql-service';
           $db_user = getenv('DB_USER');
           $db_pass = getenv('DB_PASSWORD');
           $db_name = getenv('DB_NAME') ?: 'guestbook';

           // 2. Crear conexión
           $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

           $message_from_db = "Error connecting to DB.";
           if ($conn->connect_error) {
               $message_from_db = "Database Connection Failed: " . htmlspecialchars($conn->connect_error);
           } else {
               $sql = "SELECT message FROM welcome_message WHERE id = 1";
               $result = $conn->query($sql);

               if ($result && $result->num_rows > 0) {
                   $row = $result->fetch_assoc();
                   $message_from_db = htmlspecialchars($row['message']);
               } elseif ($result) {
                   $message_from_db = "(No message found with ID 1)";
               } else {
                   $message_from_db = "Query Error: " . htmlspecialchars($conn->error);
               }

               $conn->close();
           }

           echo "Message: " . $message_from_db;
           ?>
       </h1>
       <div class="pod-id">
           Served by Pod: <?php echo htmlspecialchars(gethostname()); ?>
       </div>
   </div>
</body>
</html>
