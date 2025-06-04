const express = require('express');
const { Pool } = require('pg');
const app = express();
const port = 3001;

const pool = new Pool({
  user: process.env.DB_USER,
  host: process.env.DB_HOST,
  database: process.env.DB_NAME,
  password: process.env.DB_PASSWORD,
  port: process.env.DB_PORT,
});

app.get('/healthz', (req, res) => {
  res.status(200).send('OK');
});

app.get('/api/data', async (req, res) => {
  try {
    const result = await pool.query('SELECT id, name FROM items ORDER BY id');
    res.json(result.rows);
  } catch (err) {
    console.error('Error fetching data from DB:', err);
    res.status(500).send('Error fetching data');
  }
});

app.get('/', (req, res) => {
  res.send('Backend API is running!');
});

app.listen(port, () => {
  console.log(`Backend API listening on port ${port}`);
});
