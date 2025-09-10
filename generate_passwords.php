<?php
// Tämä skripti generoi salasanahashit testikäyttäjille
// This script generates password hashes for test users

$password = 'password'; // Kaikille testikäyttäjille sama salasana
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Salasana: " . $password . "\n";
echo "Hash: " . $hash . "\n\n";

echo "Voit käyttää tätä hashia sample_data.sql tiedostossa.\n";
echo "You can use this hash in the sample_data.sql file.\n";
?>
