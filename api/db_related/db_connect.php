<?php
// Supabase PostgreSQL database configuration
$host = 'aws-1-ap-southeast-1.pooler.supabase.com'; // Use the Session connection pooler host from your dashboard
$port = '5432'; // Default Supabase port
$dbname = 'postgres'; // Default Supabase database name
$user = 'postgres.jvqeqliakfulibnszgdj'; // User must include project ref for pooler connections
$password = 'no sana no life'; // Your database password

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}