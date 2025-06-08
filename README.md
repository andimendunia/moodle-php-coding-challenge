# User Upload Script

A PHP command-line script for processing CSV files containing user data and inserting records into a PostgreSQL database.

## Assumptions

- Database name was not explicitly specified, so "postgres" database is used
- Schema name was not explicitly specified, so public schema is used (PostgreSQL default)
- The --create_table and --file arguments cannot be used together to enforce explicit workflow steps
- The -h argument defaults to localhost if not provided
- Dry run mode still requires -u and -p flags to validate database connection
- Email validation follows RFC standards (characters like ! and ' are technically allowed)
- Name capitalization applies to compound names (O'hare → O'Hare, mary-jane → Mary-Jane)

## Requirements

### System Requirements
- **Ubuntu**
- **PHP 8.3**
- **PostgreSQL 13+**

### PHP Extensions Required
- `pdo`
- `pdo_pgsql`
- `mbstring`

Install required extensions on Ubuntu:
```bash
sudo apt-get update
sudo apt-get install php8.3-cli php8.3-pdo php8.3-pgsql
```

## Installation

1. Clone this repository:
```bash
git clone <repository-url>
cd <repository-directory>
```

2. Ensure PostgreSQL is running and you have database credentials


## Usage

### Basic Syntax
```bash
php user_upload.php [options]
```

### Command Line Options

| Option | Description | Required |
|--------|-------------|----------|
| `--file [filename]` | CSV file to process | For file processing |
| `--create_table` | Create/rebuild users table | No |
| `--dry_run` | Process file without database insertion | No |
| `-u [username]` | PostgreSQL username | Yes* |
| `-p [password]` | PostgreSQL password | Yes* |
| `-h [host]` | PostgreSQL host (default: localhost) | No |
| `--help` | Show help message | No |

*Required for database operations

### Examples

#### 1. Show Help
```bash
php user_upload.php --help
```

#### 2. Create Users Table
```bash
php user_upload.php --create_table -u admin -p secret -h localhost
```

#### 3. Process CSV File
```bash
php user_upload.php --file users.csv -u admin -p secret
```

#### 4. Dry Run (Test Without Database Changes)
```bash
php user_upload.php --file users.csv --dry_run -u admin -p secret
```

#### 5. Process Remote Database
```bash
php user_upload.php --file users.csv -u dbuser -p mypassword -h 192.168.1.100
```

## CSV File Format

### Required Format
```csv
name,surname,email
John,Smith,john@example.com
Jane,Doe,jane@example.com
```

### Requirements
- **Headers**: Must be exactly `name,surname,email` (case-insensitive)
- **Columns**: All three columns are required for each row
- **Encoding**: UTF-8 recommended

### Data Processing Rules

1. **Names**: Capitalized (including after apostrophes/hyphens)
   - `john` → `John`
   - `o'connor` → `O'Connor`
   - `mary-jane` → `Mary-Jane`

2. **Emails**: Converted to lowercase and validated
   - Must be valid email format
   - Duplicate emails are rejected

3. **Special Characters**: Removed from names (except apostrophes and hyphens)

## Database Schema

The script creates a `users` table with the following structure:

```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
);
```

### Important Notes
- The `email` field has a **UNIQUE constraint**
- The `--create_table` option will **DROP** the existing table if it exists
- All fields are required (NOT NULL)

## Error Handling

The script gracefully handles various error conditions:

### File Errors
- File not found
- File not readable
- Invalid CSV format
- Missing required columns

### Data Errors
- Invalid email formats
- Empty required fields
- Duplicate email addresses
- Malformed CSV rows

### Database Errors
- Connection failures
- Authentication errors
- Constraint violations

## Output Examples

### Successful Processing
```
Processing CSV file: users.csv

Processing CSV file...
--------------------------------------------------
Inserted: John Smith (john@example.com)
Inserted: Jane Doe (jane@example.com)
Error on line 4: Invalid email format 'invalid@@email'. Skipping.
--------------------------------------------------
Processing complete!
Lines processed: 3
Successfully processed: 2
Errors: 1
Skipped: 0
```

### Dry Run Mode
```
Processing CSV file: users.csv
DRY RUN MODE: No data will be inserted into database

Processing CSV file...
--------------------------------------------------
Would insert: John Smith (john@example.com)
Would insert: Jane Doe (jane@example.com)
--------------------------------------------------
Processing complete!
Lines processed: 2
Successfully would process: 2
Errors: 0
Skipped: 0
```

## Troubleshooting

### Database Connection Issues

**Problem**: `Database connection failed: SQLSTATE[08006]`
**Solution**: 
- Verify PostgreSQL is running: `sudo systemctl status postgresql`
- Check connection parameters (host, username, password)
- Ensure user has database access permissions

**Problem**: `FATAL: database "postgres" does not exist`
**Solution**: 
- Create the postgres database or specify an existing database
- Connect as a user with database creation privileges

### Permission Issues

**Problem**: `Error: CSV file 'users.csv' is not readable`
**Solution**:
```bash
chmod +r users.csv
```

### PHP Extension Issues

**Problem**: `could not find driver`
**Solution**:
```bash
sudo apt-get install php8.3-pdo php8.3-pgsql
```

### Email Validation

**Problem**: Emails with special characters being rejected
**Note**: The script follows RFC 5322 standards. Characters like `!` and `'` in email local parts are technically valid but may cause issues with some email providers.

## Testing

### Test with Provided Data
The repository includes `users.csv` with test data including:
- Valid records
- Invalid email formats
- Duplicate emails
- Names with special characters
- Extra whitespace

### Manual Testing Steps
1. Create table: `php user_upload.php --create_table -u admin -p secret`
2. Dry run: `php user_upload.php --file users.csv --dry_run -u admin -p secret`
3. Process file: `php user_upload.php --file users.csv -u admin -p secret`
4. Verify results in database

## License

This project is part of a coding challenge and is for evaluation purposes.