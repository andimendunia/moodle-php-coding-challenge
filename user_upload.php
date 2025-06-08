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
   echo "Create table function called\n";
   echo "Host: " . ($options['h'] ?? 'localhost') . "\n";
   echo "User: " . ($options['u'] ?? 'not provided') . "\n";
   echo "Password: " . (isset($options['p']) ? '[provided]' : '[not provided]') . "\n";
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