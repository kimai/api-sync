# Sync your Kimai data via API to a local database

- Requires PHP >= 8.1.3
- MySQL 8
- GIT
- Composer (siehe https://getcomposer.org/download/)

## Installation

- clone the repo: `git clone `
- create the database and then the necessary tables, structure can be found in `database.sql`  
- execute `php composer.phar install --optimize-autoloader -n`
- edit `configuration.php` and adjust settings to your needs

Everything setup? Great! Now you can sync your data

## Usage

The sync script can be run with `php sync.php` and it has two optional parameters:

- `--modified="2024-01-01 12:00:00"` - only sync timesheets, which were changed after a certain date-time, format: `YYYY-MM-DD HH:mm:SS`
- `--timesheets` - only sync timesheets

If `--modified` is skipped, only the latest 24 hours will be synced

## Initial sync

For the initial sync you should choose a date far in the past, so all non-synced timesheets will be fetched:

```
php sync.php --modified="2020-12-31 00:00:00"
```
