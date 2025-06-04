0.  Instalar php (optional)
```bash
sudo apt install php libapache2-mod-php
```

1. Estructura del proyecto

```bash
php-postgres-minikube/
├── app.php
├── Dockerfile
├── postgres-deployment.yaml
├── php-app-deployment.yaml
├── postgres-secret.yaml
├── postgres-configmap.yaml
└── php-app-configmap.yaml
```

2. PHP Application (app.php)
```javascript
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
```

  Explicación:
- Se conecta a una base de datos PostgreSQL mediante variables de entorno.
- Recupera el primer valor de la tabla my_table.
- Muestra el valor o un mensaje de error.
- Utiliza htmlspecialchars() para evitar vulnerabilidades XSS.
- El formato y el estilo del mensaje ahora se pueden configurar mediante variables de entorno (que pueden obtenerse de un ConfigMap).

3. Dockerfile de la aplicación PHP

```bash
FROM php:8.1-apache

COPY app.php /var/www/html/index.php
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql
EXPOSE 80
```

Explicación:
- Utiliza PHP 8.1 con Apache.
- Copia app.php a la raíz de documentos de Apache.
- Instala la extensión pdo_pgsql para compatibilidad con PostgreSQL.
- Expone el puerto 80 para el servidor web.

```bash
4. PostgreSQL Secret (postgres-secret.yaml)
apiVersion: v1
kind: Secret
metadata:
  name: postgres-credentials
type: Opaque
data:
  username: [...REDACTED...]  # Base64 encoded username
  password: [...REDACTED...]  # Base64 encoded password
```

 Explicación:
- Este archivo YAML define un secreto de Kubernetes llamado postgres-credentials.
- Es de tipo opaco, que se utiliza para datos de clave-valor arbitrarios.
- La sección de datos contiene el nombre de usuario y la contraseña.
- Importante: Los valores de nombre de usuario y contraseña deben estar codificados en base64. No guarde contraseñas en texto plano en sus archivos YAML.
- En Linux:

```bash
echo -n "myuser" | base64
echo -n "mypassword" | base64
```

- Reemplazar [...] los valores deseados en base64-encoded.
```bash
5. PostgreSQL ConfigMap (postgres-configmap.yaml)
apiVersion: v1
kind: ConfigMap
metadata:
  name: postgres-config
data:
  POSTGRES_DB: mydb
```

Explicación:
- Este ConfigMap almacena datos de configuración no confidenciales para PostgreSQL. En este caso, define el nombre de la base de datos (POSTGRES_DB). Puede agregar aquí otra configuración de PostgreSQL si es necesario.

6. PostgreSQL Deployment y Service (postgres-deployment.yaml)
```bash
apiVersion: apps/v1
kind: Deployment
metadata:
  name: postgres-deployment
  labels:
    app: postgres
spec:
  replicas: 1
  selector:
    matchLabels:
      app: postgres
  template:
    metadata:
      labels:
        app: postgres
    spec:
      containers:
        - name: postgres
          image: postgres:15
          env:
            - name: POSTGRES_USER
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: username
            - name: POSTGRES_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: password
            - name: POSTGRES_DB
              valueFrom:
                configMapKeyRef:
                  name: postgres-config
                  key: POSTGRES_DB
          ports:
            - containerPort: 5432
          volumeMounts:
            - name: postgres-data
              mountPath: /var/lib/postgresql/data
          resources: # Add resource requests and limits
            requests:
              cpu: 100m
              memory: 256Mi
            limits:
              cpu: 500m
              memory: 1Gi
      volumes:
        - name: postgres-data
          emptyDir: {}
---
apiVersion: v1
kind: Service
metadata:
  name: postgres-service
spec:
  selector:
    app: postgres
  ports:
    - protocol: TCP
      port: 5432
      targetPort: 5432
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: postgres-pv-claim
  labels:
	app: postgres
spec:
  accessModes:
	- ReadWriteOnce
  resources:
	requests:
  	storage: 5Gi
```

Explicación:
Deployment:
- Implementa una instancia de PostgreSQL 15.
- Obtiene POSTGRES_USER y POSTGRES_PASSWORD del secreto postgres-credentials.
- Obtiene POSTGRES_DB del ConfigMap de postgres-config.
- Gestión de recursos: Incluye recursos para especificar solicitudes y límites de CPU y memoria. Esto es crucial para la estabilidad y para evitar la contención de recursos.
- Monta un volumen emptyDir para los datos (no persistente tras reinicios del pod). Para producción, utilice PersistentVolumeClaim.
Service:
- Crea un servicio para exponer PostgreSQL dentro del clúster.

7. PHP Application ConfigMap (php-app-configmap.yaml)

```bash
apiVersion: v1
kind: ConfigMap
metadata:
  name: php-app-config
data:
  MESSAGE_COLOR: "green"
  MESSAGE_TITLE: "Value from database:"
  VALUE_COLOR: "blue"
  VALUE_SIZE: "20"
```

Explicación:
- Este ConfigMap contiene la configuración de visualización de la aplicación PHP.
- MESSAGE_COLOR, MESSAGE_TITLE, VALUE_COLOR y VALUE_SIZE controlan el estilo de la salida. Esto permite cambiar la apariencia de la aplicación sin tener que reconstruir la imagen de Docker.

8. PHP Application Deployment y Service (php-app-deployment.yaml)

```bash
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app-deployment
  labels:
    app: php-app
spec:
  replicas: 1
  selector:
    matchLabels:
      app: php-app
  template:
    metadata:
      labels:
        app: php-app
    spec:
      containers:
        - name: php-app
          image: php-app:latest
          imagePullPolicy: Never
          ports:
            - containerPort: 80
          env:
            - name: DB_HOST
              value: postgres-service
            - name: DB_NAME
              valueFrom:
                configMapKeyRef:
                  name: postgres-config
                  key: POSTGRES_DB
            - name: DB_USER
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: username
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: password
            - name: MESSAGE_COLOR
              valueFrom:
                configMapKeyRef:
                  name: php-app-config
                  key: MESSAGE_COLOR
            - name: MESSAGE_TITLE
              valueFrom:
                configMapKeyRef:
                  name: php-app-config
                  key: MESSAGE_TITLE
            - name: VALUE_COLOR
              valueFrom:
                configMapKeyRef:
                  name: php-app-config
                  key: VALUE_COLOR
            - name: VALUE_SIZE
              valueFrom:
                configMapKeyRef:
                  name: php-app-config
                  key: VALUE_SIZE
          readinessProbe:
            httpGet:
              path: /index.php
              port: 80
            initialDelaySeconds: 5
            periodSeconds: 5
          livenessProbe:
            httpGet:
              path: /index.php
              port: 80
            initialDelaySeconds: 15
            periodSeconds: 10
          resources: # Add resource requests and limits
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              cpu: 300m
              memory: 256Mi
      securityContext: #add security context
        runAsUser: 1000
        runAsGroup: 1000
        fsGroup: 1000
---
apiVersion: v1
kind: Service
metadata:
  name: php-app-service
spec:
  type: NodePort # Use NodePort for Minikube
  selector:
    app: php-app
  ports:
    - protocol: TCP
      port: 80
      targetPort: 80
```

Explicación:
Deployment:
- Implementa la aplicación PHP.
- Utiliza la imagen de Docker php-app:latest.
- Obtiene DB_USER y DB_PASSWORD del secreto postgres-credentials.
- Obtiene DB_NAME del ConfigMap de postgres-config.
- Obtiene las variables de estilo de los mensajes del ConfigMap de php-app-config.
- Gestión de recursos: Incluye recursos para especificar las solicitudes y los límites de CPU y memoria.
- Contexto de seguridad: Aplica un contexto de seguridad al contenedor, especificando los ID de usuario y grupo para ejecutar el proceso. Esto mejora la seguridad al ejecutar el contenedor con un usuario no root.
Service:
- Expone la aplicación PHP mediante un servicio NodePort, lo que la hace accesible desde fuera del clúster Minikube.

8. Pasos para el Deployment 
1.	Start Minikube:
```bash
minikube start
```

2.	Build the Docker Image:
```bash
docker build -t php-app:latest .
```
Asegúrate de estar en el directorio que contiene Dockerfile y app.php.

3.	Load the Docker Image into Minikube:
```bash
minikube image load php-app:latest
```
4.	Deployar el Secret:
```bash
kubectl apply -f postgres-secret.yaml
```

5.	Deployar el PostgreSQL ConfigMap:
```bash
kubectl apply -f postgres-configmap.yaml
```
6.	Deployar PostgreSQL:
```bash
kubectl apply -f postgres-deployment.yaml
```

7.	Deployar el PHP Application ConfigMap:
```bash
kubectl apply -f php-app-configmap.yaml
```

8.	Deployar el PHP Application:
```bash
kubectl apply -f php-app-deployment.yaml
```
9.	Agregar datos a la Database:
- Obtener el nombre del pod de PostgreSQL:

```bash
kubectl get pods -l app=postgres
```

- Acceder al pod de PostgreSQL:
```bash
kubectl exec -it <postgres-pod-name> -- psql -U myuser -d mydb
```
Reemplazar <postgres-pod-name> con el nombre del pod.
- Introduce la contraseña: mypassword (o la que usaste en el secreto).
- Crea la tabla e inserta los datos:
```bash
CREATE TABLE my_table (value VARCHAR(255));
INSERT INTO my_table (value) VALUES ('Hello from PostgreSQL!');
\q
```

10.	Acceder a la aplicación:
Obtener la URL del servicio de la aplicación PHP:
```bash
minikube service php-app-service --url
```
- Abra la URL en su navegador. Debería ver el mensaje de la base de datos, con el estilo correspondiente a los valores del archivo ConfigMap de php-app-configmap.yaml..

