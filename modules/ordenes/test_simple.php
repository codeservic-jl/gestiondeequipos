<?php
// Configuración básica
@ini_set('upload_max_filesize', '50M');
@ini_set('post_max_size', '60M');

echo "<h1>Test Simple</h1>";
echo "<p>Upload max filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post max size: " . ini_get('post_max_size') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
?> 