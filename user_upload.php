<?php

// Parse argumetns
$options = parseCommandLineArgs($argc, $argv);

// Validate mutually exclusive options
if (isset($options['create_table']) && isset($options['file'])) {
   echo "Error: --create_table and --file options cannot be used together.\n";
   echo "Use --create_table first, then run again with --file.\n";
   showHelp();
   exit(1);
}

// Main functions
if (isset($options['help'])) {
   showHelp();
   exit(0);
}

if (isset($options['create_table'])) {
   echo "Creating users table...\n";
   $pdo = connectToDatabase($options);
   if ($pdo) {
       echo "Database connection successful.\n";
       createUsersTable($pdo);
   }
   exit(0);
}

if (isset($options['file'])) {
    echo "CSV processing function called\n";
    echo "File: " . $options['file'] . "\n";
    echo "Dry run: " . (isset($options['dry_run']) ? 'yes' : 'no') . "\n";
    echo "Host: " . ($options['h'] ?? 'localhost') . "\n";
    echo "User: " . ($options['u'] ?? 'not provided') . "\n";
    exit(0);
}

// Default: show help if no valid options are provided
showHelp();
exit(1);

function parseCommandLineArgs($argc, $argv) {
    $shortOpts = "u:p:h:";  // all require values
    $longOpts = [
        "file:",        // requires value
        "create_table", // flag
        "dry_run",      // flag  
        "help"          // flag
    ];
    
    return getopt($shortOpts, $longOpts);
}

function createUsersTable($pdo) {
    try {
        // Drop table if it exists (rebuild as per requirements)
        $dropSql = "DROP TABLE IF EXISTS users";
        $pdo->exec($dropSql);
        echo "Existing users table dropped (if it existed).\n";
        
        // Create users table with required fields
        $createSql = "
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                surname VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE
            )
        ";
        
        $pdo->exec($createSql);
        echo "Users table created successfully!\n";
        echo "Table structure:\n";
        echo "  - id (SERIAL PRIMARY KEY)\n";
        echo "  - name (VARCHAR, NOT NULL)\n";
        echo "  - surname (VARCHAR, NOT NULL)\n";
        echo "  - email (VARCHAR, NOT NULL, UNIQUE)\n";
        
    } catch (PDOException $e) {
        echo "Error creating users table: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function connectToDatabase($options) {
    $host = $options['h'] ?? 'localhost';
    $username = $options['u'] ?? null;
    $password = $options['p'] ?? null;
    $database = 'postgres'; // Default database for PostgreSQL
    
    // Validate required credentials
    if (!$username) {
        echo "Error: PostgreSQL username is required (-u flag)\n";
        return null;
    }
    
    if (!isset($options['p'])) {
        echo "Error: PostgreSQL password is required (-p flag)\n";
        return null;
    }
    
    try {
        $dsn = "pgsql:host=$host;dbname=$database";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        echo "Connected to PostgreSQL at $host as $username\n";
        return $pdo;
        
    } catch (PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        echo "Please check your connection parameters:\n";
        echo "  Host: $host\n";
        echo "  Username: $username\n";
        echo "  Database: $database\n";
        return null;
    }
}

function showHelp() {
    echo "Usage: php user_upload.php [options]\n\n";
    echo "Options:\n";
    echo "  --file [csv file name]    Process CSV file\n";
    echo "  --create_table           Create users table (no other action taken)\n";
    echo "  --dry_run                Process file without database changes\n";
    echo "  -u [username]            PostgreSQL username\n";
    echo "  -p [password]            PostgreSQL password\n";
    echo "  -h [host]                PostgreSQL host\n";
    echo "  --help                   Show this help message\n\n";
    echo "Examples:\n";
    echo "  php user_upload.php --help\n";
    echo "  php user_upload.php --create_table -u admin -p secret -h localhost\n";
    echo "  php user_upload.php --file users.csv -u admin -p secret\n";
    echo "  php user_upload.php --file users.csv --dry_run -u admin -p secret\n";
}


?>