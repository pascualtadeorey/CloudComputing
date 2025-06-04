# Ejercicio 1

1.	Instalar node y npm
- https://nodejs.org/en/download

2.	Crear un archivo index.js

```javascript
const express = require('express');
const app = express();
const port = 3000;

app.get('/', (req, res) => {
  res.send(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Hello, World!</title>
      </head>
      <body>
        <h1>Hello, World!</h1>
        <p>Welcome to this simple web application!</p>
      </body>
    </html>
  `);
});

app.listen(port, () => {
  console.log(`Server running on port ${port}`);
});
```

3.	npm init dentro de la carpeta del proyecto.
4.	npm install express
5.	Crear un Dockerfile

```dockerfile
FROM node:18

WORKDIR /app

COPY package*.json ./
COPY index.js ./

RUN npm install

EXPOSE 3000

CMD ["node", "index.js"]
```


6.	Crear un archivo de deployment
```bash
$ kubectl create deployment myapp --image=myapp:v1 --dry-run=client -o yaml > myapp-deployment.yaml
```


7.	Abrir el deployment.yaml
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
 creationTimestamp: null
 labels:
   app: myapp:v1
 name: myapp:v1
spec:
 replicas: 1
 selector:
   matchLabels:
     app: myapp:v1
 strategy: {}
 template:
   metadata:
     creationTimestamp: null
     labels:
       app: hello-world-php
   spec:
     containers:
     - image: myapp:v1
       name: myapp
       resources: {}
       imagePullPolicy: Never  # Agregar esta línea
       ports: # Agregar esta linea
          - containerPort: 3000  # Agregar esta linea
status: {}
```

Nota: si se está corriendo en una vm, ejecutar el siguiente comando; 
```bash
$ eval $(minikube docker-env)
```
Verificar que funcione ejecutando

```bash
$ kubectl get pods -o wide
```
En caso de verificar que el status del pod es ErrImageNeverPull, ejecutar nuevamente el comando “eval…” y realizar un rebuild de docker.

	
8.	Construir la imagen de Docker

```bash
$ docker build -t myapp:v1 .
```

9.	Aplicar el deployment

```bash
$ kubectl apply -f myapp-deployment.yaml
```

10.	Exponer la aplicación a través de un servicio

```bash
$ kubectl expose deployment myapp --type=NodePort --port=3000
```

11.	Testear el deployment

```bash
$ minikube service myapp --url
```


