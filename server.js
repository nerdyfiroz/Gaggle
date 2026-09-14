const express = require('express');
const path = require('path');
const apiHandler = require('./api/index');

const app = express();
const PORT = 3000;
const HOST = '0.0.0.0';

const publicDir = path.join(__dirname, 'public');

// Parse JSON and form bodies
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Handle all /api calls
app.use('/api', (req, res) => {
  apiHandler(req, res);
});

// Admin panel route
app.get('/admin', (req, res) => {
  res.sendFile(path.join(publicDir, 'admin', 'index.html'));
});

// Static assets
app.use(express.static(publicDir));

// Fallback to landing page
app.use((req, res) => {
  res.sendFile(path.join(publicDir, 'index.html'));
});

app.listen(PORT, HOST, () => {
  console.log(`Quackery application running on http://${HOST}:${PORT}`);
});
