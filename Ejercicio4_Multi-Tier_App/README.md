### Ejercicio 4: Implementación de una aplicación de varios niveles con HA y simulación de topología
## Objetivo: 
Implemente una aplicación web de tres niveles (HTML/JS Frontend, Node.js Backend API, PostgreSQL Database) en Minikube. Este ejercicio integra conceptos como implementaciones, StatefulSets, servicios, ConfigMaps, secretos, volúmenes persistentes, sondas, gestión de recursos y simula alta disponibilidad en diferentes "zonas" utilizando restricciones de distribución de topología.
## Dificultad: 
Avanzado
## Requisitos previos:
- Finalización o comprensión de los conceptos de los tres ejercicios anteriores.
- kubectl configurado para su clúster Minikube.
- Docker instalado localmente.
- Minikube en ejecución (preferiblemente con múltiples nodos si desea que la distribución de la topología funcione de manera efectiva: minikube start --nodes 3 -p multinode-cluster). Si se utiliza un solo nodo, las restricciones de topología no impedirán la programación, pero no distribuirán activamente los pods.
- Comprensión básica de Node.js/npm.
## Mejores prácticas enfatizadas:
- Tiered architecture: Separación de obligaciones (frontend, backend, base de datos).
- Gestión de configuración: Usando ConfigMaps y Secrets.
- Alta disponibilidad: Múltiples réplicas para niveles sin estado (frontend, backend).
- Cargas de trabajo con estado: Usando StatefulSets y PersistentVolumeClaims para la base de datos.
- Controles de salud: Probes para verificar el estado y preparación para todos los componentes.
- Gestión de recursos: Configuración de solicitudes y límites de CPU/memoria.
- Seguridad: Usar secrets para credenciales, considerando contextos de seguridad (aunque está simplificado).
- Topology Awareness: Uso de topologySpreadConstraints para simular la distribución de pods en dominios de falla (nodos que actúan como zonas).
## Estructura de la aplicación:
1.	Interfaz (HTML/PHP): Una página HTML con PHP estática simple para recuperar datos de la API Backend. Servido por Nginx en una implementación con múltiples réplicas.
2.	Server (Node.js/Express): Una API simple que se conecta a la base de datos PostgreSQL para recuperar/almacenar datos. Se ejecuta en una implementación con múltiples réplicas.
3.	Base de datos (PostgreSQL): Almacena datos de la aplicación. Se ejecuta como StatefulSet con PersistentVolumeClaim.

## Paso 1. Configurar el espacio de nombres y la configuración básica:
- Crear espacio de nombres:
```bash
kubectl create namespace multi-tier-app
```

### - Secret de la base de datos: Crear postgres-secret.yaml. Reemplazar los comodines con valores codificados en Base64 `(echo -n 'password' | base64)`.

Crear postgres-secret.yaml
```yaml
apiVersion: v1
kind: Secret
metadata:
  name: postgres-credentials
  namespace: multi-tier-app
type: Opaque
data:
  POSTGRES_USER: dXNlcg== # Base64 for 'user'
  POSTGRES_PASSWORD: <your_base64_password> # e.g., cGFzc3dvcmQ= for 'password'
```

Aplicar:
```bash
kubectl apply -f postgres-secret.yaml -n multi-tier-app
```
### Mapa de configuración de la base de datos: Cree postgres-configmap.yaml.

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: postgres-config
  namespace: multi-tier-app
data:
  POSTGRES_DB: myappdb
```

Aplicar:
```bash
kubectl apply -f postgres-configmap.yaml -n multi-tier-app
```
### ConfigMap API: Cree api-configmap.yaml. Esto le indica a la API de backend cómo llegar a la base de datos.

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: api-config
  namespace: multi-tier-app
data:
  DB_HOST: postgres-db-service # Service name for the database
  DB_PORT: "5432"
```

Aplicar:
```bash
kubectl apply -f api-configmap.yaml -n multi-tier-app
```
## Paso 2. Preparar el código de la aplicación y las imágenes de Docker:
- Crear dos directorios: aplicación backend y aplicación frontend.
- Aplicación backend (directorio de aplicaciones backend):
Crear package.json:
```json
{
  "name": "backend-api",
  "version": "1.0.0",
  "description": "Simple API for K8s exercise",
  "main": "server.js",
  "scripts": {
    "start": "node server.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "pg": "^8.11.3"
  }
}
```

Crear server.js:
```javascript
const express = require('express');
const { Pool } = require('pg');
const app = express();
const port = 3001; // Port the backend listens on

// PostgreSQL connection pool
const pool = new Pool({
  user: process.env.DB_USER,
  host: process.env.DB_HOST,
  database: process.env.DB_NAME,
  password: process.env.DB_PASSWORD,
  port: process.env.DB_PORT,
});

// Simple health check endpoint for probes
app.get('/healthz', (req, res) => {
  // Optional: Add a quick DB check here if needed
  res.status(200).send('OK');
});

// API endpoint to get data from DB
app.get('/api/data', async (req, res) => {
  try {
    const result = await pool.query('SELECT id, name FROM items ORDER BY id');
    res.json(result.rows);
  } catch (err) {
    console.error('Error fetching data from DB:', err);
    res.status(500).send('Error fetching data');
  }
});

// Basic root response
app.get('/', (req, res) => {
    res.send('Backend API is running!');
});


app.listen(port, () => {
  console.log(`Backend API listening on port ${port}`);
});
```

Crear Dockerfile:
```dockerfile
# Dockerfile for backend-api
FROM node:18-alpine

WORKDIR /app

# Copy package files and install dependencies
COPY package*.json ./
RUN npm install --only=production

# Copy application code
COPY server.js .

# Expose the port the app runs on
EXPOSE 3001

# Command to run the application
CMD [ "node", "server.js" ]
```

> [!IMPORTANT] 
> IMPORTANTE: Correr npm install en el directorio de la aplicación backend. Si no está instalado npm, correr sudo apt install npm.

### Aplicación frontend (directorio de aplicaciones frontend):
Crear index.php:
```php
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
```

Crear Dockerfile:
```dockerfile
# Dockerfile for frontend-web (PHP)
FROM php:8.1-apache

# Apache document root is /var/www/html

# Copy the PHP file into the web root
COPY index.php /var/www/html/index.php

RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-
install pdo pdo_pgsql
# Optional: Install extensions if needed (e.g., curl, pgsql for direct DB access - not needed here)
# RUN docker-php-ext-install curl pdo pdo_pgsql

# Ensure Apache permissions are correct (usually handled by base image)
# RUN chown -R www-data:www-data /var/www/html

# Port 80 is exposed by the base image
```

### Construir y cargar imágenes de Docker:
En el directorio de la aplicación backend
```bash
docker build -t backend-api:v1 .
```
En el directorio de aplicaciones frontend
```bash
docker build -t frontend-web:v1 .
```

### Cargar en Minikube (use el perfil correcto si es necesario: -p multinode-cluster)
```bash
minikube image load backend-api:v1
minikube image load frontend-web:v1
```

## Paso 3. Implementar la base de datos (StatefulSet):
Crear postgres-statefulset.yaml. Tener en cuenta el enlace serviceName al Headless Service y volumeClaimTemplates.

```yaml
apiVersion: v1
kind: Service # Headless Service for StatefulSet DNS
metadata:
  name: postgres-db-headless
  namespace: multi-tier-app
spec:
  clusterIP: None
  selector:
    app: postgres-db
  ports:
    - port: 5432
      targetPort: 5432
---
apiVersion: v1
kind: Service # ClusterIP Service for Backend API access
metadata:
  name: postgres-db-service
  namespace: multi-tier-app
spec:
  selector:
    app: postgres-db
  ports:
    - protocol: TCP
      port: 5432
      targetPort: 5432
---
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: postgres-db
  namespace: multi-tier-app
spec:
  serviceName: postgres-db-headless
  replicas: 1 # Single replica for simplicity in Minikube HA
  selector:
    matchLabels:
      app: postgres-db
  template:
    metadata:
      labels:
        app: postgres-db
    spec:
      terminationGracePeriodSeconds: 10
      containers:
        - name: postgres
          image: postgres:15
          ports:
            - containerPort: 5432
              name: postgres
          env:
            - name: POSTGRES_DB
              valueFrom:
                configMapKeyRef:
                  name: postgres-config
                  key: POSTGRES_DB
            - name: POSTGRES_USER
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: POSTGRES_USER
            - name: POSTGRES_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: POSTGRES_PASSWORD
          volumeMounts:
            - name: postgres-data
              mountPath: /var/lib/postgresql/data
          resources:
            requests:
              cpu: "100m"
              memory: "256Mi"
            limits:
              cpu: "500m"
              memory: "512Mi"
          # Add Liveness/Readiness Probes (e.g., using pg_isready)
          readinessProbe:
            exec:
              command: ["pg_isready", "-U", "$(POSTGRES_USER)", "-d", "$(POSTGRES_DB)", "-h", "127.0.0.1", "-p", "5432"]
            initialDelaySeconds: 15
            periodSeconds: 10
            timeoutSeconds: 5
          livenessProbe:
            exec:
              command: ["pg_isready", "-U", "$(POSTGRES_USER)", "-d", "$(POSTGRES_DB)", "-h", "127.0.0.1", "-p", "5432"]
            initialDelaySeconds: 30
            periodSeconds: 15
            timeoutSeconds: 5
  volumeClaimTemplates:
    - metadata:
        name: postgres-data
      spec:
        accessModes: [ "ReadWriteOnce" ]
        resources:
          requests:
            storage: 1Gi # Request 1 GB persistent storage
        # storageClassName: standard # Specify if needed for your Minikube storage provisioner
```

Aplicar:
```bash
kubectl apply -f postgres-statefulset.yaml -n multi-tier-app
```
### Inicializar base de datos (manual): Esperar a que se ejecute el pod postgres-db-0. Ejecutar en el pod y crear las tablas necesarias para la API de backend.
```bash
kubectl exec -it postgres-db-0 -n multi-tier-app -- psql -U user -d myappdb
```

Inside psql:
```sql
# CREATE TABLE IF NOT EXISTS items ( id SERIAL PRIMARY KEY, name VARCHAR(100) );
# INSERT INTO items (name) VALUES ('Sample Item 1'), ('Sample Item 2');
# \q
```

> [!NOTE]
> (Nota de mejores prácticas: utilizar un Init Container o un job para la inicialización automática del esquema).
## Paso 4. Implementar la API de backend (implementación):
Crear backend-api-deployment.yaml. Réplicas: 2, límites de recursos, probes, uso de configuración/secreto y topologySpreadConstraints.

```yaml
apiVersion: v1
kind: Service
metadata:
  name: backend-api-service
  namespace: multi-tier-app
spec:
  selector:
    app: backend-api
  ports:
    - protocol: TCP
      port: 80 # Service port (frontend will call this)
      targetPort: 3001 # Container port the Node.js app listens on
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: backend-api
  namespace: multi-tier-app
spec:
  replicas: 2 # HA for the stateless API
  selector:
    matchLabels:
      app: backend-api
  template:
    metadata:
      labels:
        app: backend-api
    spec:
      containers:
        - name: backend-api
          image: backend-api:v1 # Your built image
          imagePullPolicy: Never # If using local image loaded into Minikube
          ports:
            - containerPort: 3001 # Port defined in server.js
          env:
            # DB Config from ConfigMap
            - name: DB_HOST
              valueFrom:
                configMapKeyRef:
                  name: api-config
                  key: DB_HOST
            - name: DB_PORT
              valueFrom:
                configMapKeyRef:
                  name: api-config
                  key: DB_PORT
            # DB Credentials from Secret
            - name: DB_USER
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: POSTGRES_USER
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: postgres-credentials
                  key: POSTGRES_PASSWORD
            # DB Name from other ConfigMap
            - name: DB_NAME
              valueFrom:
                configMapKeyRef:
                  name: postgres-config
                  key: POSTGRES_DB
          resources:
            requests:
              cpu: "50m"
              memory: "128Mi"
            limits:
              cpu: "200m"
              memory: "256Mi"
          readinessProbe:
            httpGet:
              path: /healthz # Health check endpoint in server.js
              port: 3001 # Port defined in server.js
            initialDelaySeconds: 5
            periodSeconds: 5
          livenessProbe:
            httpGet:
              path: /healthz
              port: 3001 # Port defined in server.js
            initialDelaySeconds: 15
            periodSeconds: 10
      # --- Simulate Zone Spreading ---
      topologySpreadConstraints:
      - maxSkew: 1
        topologyKey: kubernetes.io/hostname # Spread across nodes
        whenUnsatisfiable: ScheduleAnyway # Or DoNotSchedule
        labelSelector:
          matchLabels:
            app: backend-api
```

Aplicar:
```bash
kubectl apply -f backend-api-deployment.yaml -n multi-tier-app
```

## Paso 5. Implementar el Frontend (Implementación):
Crear frontend-deployment.yaml. Esto implementa el contenedor Nginx que sirve HTML/JS estático.

```yaml
apiVersion: v1
kind: Service
metadata:
  name: frontend-service
  namespace: multi-tier-app
spec:
  type: NodePort # Expose externally via NodePort for Minikube
  selector:
    app: frontend-web
  ports:
    - protocol: TCP
      port: 80 # Port service listens on
      targetPort: 80 # Port the Nginx container listens on
      # nodePort: 30080 # Optional: Specify NodePort
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: frontend-web
  namespace: multi-tier-app
spec:
  replicas: 2 # HA for the stateless frontend
  selector:
    matchLabels:
      app: frontend-web
  template:
    metadata:
      labels:
        app: frontend-web
    spec:
      containers:
        - name: frontend-web
          image: frontend-web:v1 # Your built image
          imagePullPolicy: Never # If using local image loaded into Minikube
          ports:
            - containerPort: 80 # Nginx default port
          resources:
            requests:
              cpu: "50m"
              memory: "64Mi"
            limits:
              cpu: "100m"
              memory: "128Mi"
          readinessProbe:
            httpGet:
              path: /index.php # Check if the HTML file is served
              port: 80
            initialDelaySeconds: 5
            periodSeconds: 5
          livenessProbe:
            httpGet:
              path: /index.php
              port: 80
            initialDelaySeconds: 15
            periodSeconds: 10
      # --- Simulate Zone Spreading ---
      topologySpreadConstraints:
      - maxSkew: 1
        topologyKey: kubernetes.io/hostname
        whenUnsatisfiable: ScheduleAnyway
        labelSelector:
          matchLabels:
            app: frontend-web
```

Aplicar:
```bash
kubectl apply -f frontend-deployment.yaml -n multi-tier-app
```
## Paso 6. Verificación:
Check Pods, Servicios, PVC:
```bash
kubectl get pods,svc,pvc,statefulset,deployment -n multi-tier-app -o wide
```

Verificar que todos los pods estén en ejecución, que el PVC esté vinculado y que los servicios tengan IP/puertos. Si utiliza varios nodos Minikube, verifique si los pods para frontend-web y backend-api están programados en diferentes nodos debido a topologySpreadConstraints.
### Acceso a la interfaz:
```bash
minikube service frontend-service -n multi-tier-app --url
```

Abrir la URL en el navegador. Debería verse la página HTML. Hacer clic en el botón "Obtener datos". JavaScript debe llamar a la API de backend (a través del servicio de API de backend), recuperar datos de PostgreSQL y mostrarlos en la página. Nota: La ruta relativa /api/data en la recuperación de JavaScript puede requerir un controlador de Ingress para un enrutamiento adecuado en una configuración real. Con NodePort/LoadBalancer directamente en el servicio frontend, es posible que deba ajustar la URL de recuperación o configurar Nginx como proxy inverso dentro del contenedor frontend si la llamada al servicio directo no funciona.
### Verificar registros: 
Investigar problemas utilizando `kubectl logs <pod-name> -n multi-tier-app`. Por ejemplo, consulte los registros de backend: 
`kubectl logs -l app=backend-api -n multi-tier-app --tail=50`

## Paso 7 - Discusión: Simulación multirregional

### TopologySpreadConstraints

- TopologySpreadConstraints que utiliza kubernetes.io/hostname intenta distribuir pods entre diferentes nodos dentro del Clúster de minikubes. Esto simula la distribución de pods en zonas de disponibilidad (AZ) dentro de una única región para lograr una mayor disponibilidad frente a fallas de nodos.

### Verdadera multiregión

- Lograr una verdadera HA multirregional en Kubernetes normalmente implica:
  - Múltiples grupos: Implementar clústeres de Kubernetes separados en diferentes regiones geográficas (por ejemplo, clústeres de GKE en us-central1 y europe-west1).
  - Federation/Multi-Cluster Management: Usar herramientas como Kubefed (v2), Karmada o soluciones de proveedores de nube (Anthos, EKS Anywhere/Connector, Azure Arc) para gestionar implementaciones en clústeres.
  - Equilibrio de carga global: Emplear Global Load Balancers (GLB) del proveedor de la nube para dirigir el tráfico de usuarios al clúster regional más cercano o más saludable.
  - Replicación de datos: Implementar replicación entre regiones para la base de datos (por ejemplo, replicación de PostgreSQL, funciones de replicación de bases de datos administradas en la nube). Esto es complejo y, a menudo, específico de la aplicación.
  - Configuración: Garantizar que las aplicaciones en cada región se conecten a réplicas o puntos finales de bases de datos locales/regionales.

### Simulación en este ejercicio

Este ejercicio simula el aspecto de in-cluster HA que es un requisito previo para múltiples regiones, pero una verdadera implementación multirregional requiere infraestructura y herramientas más allá de una única instancia de Minikube.

## Paso 8 - Limpieza

```bash
kubectl delete namespace multi-tier-app
```
```bash
# Opcional: detener/eliminar el clúster Minikube si se creó específicamente para esto
# minikube stop -p multinode-cluster
# minikube delete -p multinode-cluster
```

