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
    echo "Processing CSV file: " . $options['file'] . "\n";
    
    $isDryRun = isset($options['dry_run']);
    if ($isDryRun) {
        echo "DRY RUN MODE: No data will be inserted into database\n";
    }
    
    // Connect to database (even for dry run to validate connection)
    $pdo = null;
    if (!$isDryRun) {
        $pdo = connectToDatabase($options);
        if (!$pdo) {
            exit(1);
        }
    }
    
    // Process the CSV file
    processCsvFile($options['file'], $pdo, $isDryRun);
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

function processCsvFile($filename, $pdo, $isDryRun) {
    // Check if file exists
    if (!file_exists($filename)) {
        echo "Error: CSV file '$filename' not found.\n";
        return;
    }
    
    // Check if file is readable
    if (!is_readable($filename)) {
        echo "Error: CSV file '$filename' is not readable.\n";
        return;
    }
    
    $handle = fopen($filename, 'r');
    if (!$handle) {
        echo "Error: Could not open CSV file '$filename'.\n";
        return;
    }
    
    $lineNumber = 0;
    $processedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    echo "\nProcessing CSV file...\n";
    echo str_repeat("-", 50) . "\n";
    
    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        // Skip header row
        if ($lineNumber === 1) {
            // Validate header format
            if (count($row) < 3 || 
                strtolower(trim($row[0])) !== 'name' || 
                strtolower(trim($row[1])) !== 'surname' || 
                strtolower(trim($row[2])) !== 'email') {
                echo "Warning: CSV header format may be incorrect.\n";
                echo "Expected: name,surname,email\n";
                echo "Found: " . implode(',', array_map('trim', $row)) . "\n";
            }
            continue;
        }
        
        // Check if row has enough columns
        if (count($row) < 3) {
            echo "Error on line $lineNumber: Row has insufficient columns. Skipping.\n";
            $skippedCount++;
            continue;
        }
        
        // Extract and clean data
        $name = trim($row[0]);
        $surname = trim($row[1]);
        $email = trim($row[2]);
        
        // Skip empty rows
        if (empty($name) && empty($surname) && empty($email)) {
            $skippedCount++;
            continue;
        }
        
        // Validate required fields
        if (empty($name) || empty($surname) || empty($email)) {
            echo "Error on line $lineNumber: Missing required field(s). ";
            echo "Name: '$name', Surname: '$surname', Email: '$email'. Skipping.\n";
            $errorCount++;
            continue;
        }
        
        // Clean and validate data
        $cleanedData = cleanUserData($name, $surname, $email);
        if (!$cleanedData) {
            echo "Error on line $lineNumber: Invalid email format '$email'. Skipping.\n";
            $errorCount++;
            continue;
        }
        
        // Insert into database or show what would be inserted
        if ($isDryRun) {
            echo "Would insert: {$cleanedData['name']} {$cleanedData['surname']} ({$cleanedData['email']})\n";
        } else {
            if (insertUser($pdo, $cleanedData)) {
                echo "Inserted: {$cleanedData['name']} {$cleanedData['surname']} ({$cleanedData['email']})\n";
            } else {
                echo "Error on line $lineNumber: Failed to insert user. Possible duplicate email.\n";
                $errorCount++;
                continue;
            }
        }
        
        $processedCount++;
    }
    
    fclose($handle);
    
    // Summary
    echo str_repeat("-", 50) . "\n";
    echo "Processing complete!\n";
    echo "Lines processed: " . ($lineNumber - 1) . "\n";
    echo "Successfully " . ($isDryRun ? "would process" : "processed") . ": $processedCount\n";
    echo "Errors: $errorCount\n";
    echo "Skipped: $skippedCount\n";
}

function cleanUserData($name, $surname, $email) {
    // Remove special characters from name and surname (keeping apostrophes for O'Connor, etc.)
    $name = preg_replace('/[^a-zA-Z\'\s-]/', '', $name);
    $surname = preg_replace('/[^a-zA-Z\'\s-]/', '', $surname);
    
    // Capitalize names properly
    $name = ucwords(strtolower(trim($name)));
    $surname = ucwords(strtolower(trim($surname)));
    
    // Clean and validate email
    $email = strtolower(trim($email));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    return [
        'name' => $name,
        'surname' => $surname,
        'email' => $email
    ];
}

function insertUser($pdo, $userData) {
    try {
        $sql = "INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            ':name' => $userData['name'],
            ':surname' => $userData['surname'],
            ':email' => $userData['email']
        ]);
        
    } catch (PDOException $e) {
        // Check if it's a unique constraint violation
        if (strpos($e->getMessage(), 'duplicate key') !== false || 
            strpos($e->getMessage(), 'unique constraint') !== false) {
            return false; // Duplicate email
        }
        
        echo "Database error: " . $e->getMessage() . "\n";
        return false;
    }
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