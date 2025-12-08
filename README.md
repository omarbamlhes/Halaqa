# Full Stack Drupal Developer – Evaluation Task

This is a Drupal 10 project built with Lando for local development.

## Repository

- **GitHub**: https://github.com/adnankhan033/fullstack-drupal-task.git

## Prerequisites

- [Lando](https://docs.lando.dev/getting-started/installation.html) installed
- Docker Desktop (or Docker Engine) running

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/adnankhan033/fullstack-drupal-task.git
cd fullstack-drupal-task
```

### 2. Start Lando

```bash
lando start
```

### 3. Install the Site

Run the automated installation script:

```bash
lando install-site
```

This command will:
- Install all Composer dependencies
- Install Drupal with the site name: **"Full Stack Drupal Developer – Evaluation Task"**
- Configure database settings
- Set up configuration sync directory
- Create default shortcut set
- Import configuration files (if available)

**Installation Credentials:**
- **Username**: `admin`
- **Password**: `admin`

### 4. Get Login URL

After installation, get a one-time login URL:

```bash
lando drush uli
```

## Site Information

- **Site Name**: Full Stack Drupal Developer – Evaluation Task
- **Drupal Version**: 10.x
- **PHP Version**: 8.2
- **Database**: MySQL 5.7.27
- **Web Server**: Apache (via Lando)

## Project Structure

```
fullstack-drupal-task/
├── web/                          # Drupal web root
│   ├── core/                     # Drupal core
│   ├── modules/
│   │   ├── contrib/              # Contributed modules
│   │   └── custom/               # Custom modules (create here)
│   ├── themes/
│   │   ├── contrib/              # Contributed themes
│   │   └── custom/               # Custom themes (create here)
│   └── sites/
│       └── default/
│           ├── settings.php      # Main settings file
│           └── settings.local.php # Local development settings
├── config/
│   └── sync/                     # Configuration sync directory
├── .lando.yml                    # Lando configuration
└── composer.json                 # Composer dependencies
```

## Custom Modules and Themes

### Creating a Custom Module

1. Create your module directory:
```bash
mkdir -p web/modules/custom/my_module
```

2. Create `my_module.info.yml`:
```yaml
name: My Module
type: module
description: 'A custom module description'
core_version_requirement: ^10 || ^11
package: Custom
```

3. Create `my_module.module`:
```php
<?php

/**
 * @file
 * My custom module.
 */
```

### Creating a Custom Theme (Claro-based)

1. Create your theme directory:
```bash
mkdir -p web/themes/custom/my_theme
```

2. Create `my_theme.info.yml`:
```yaml
name: My Theme
type: theme
description: 'A custom theme based on Claro'
core_version_requirement: ^10 || ^11
base theme: claro
libraries:
  - my_theme/global-styling
```

3. Create `my_theme.libraries.yml`:
```yaml
global-styling:
  css:
    theme:
      css/style.css: {}
  js:
    js/script.js: {}
```

4. Create basic CSS file:
```bash
mkdir -p web/themes/custom/my_theme/css
touch web/themes/custom/my_theme/css/style.css
```

5. Create basic JS file:
```bash
mkdir -p web/themes/custom/my_theme/js
touch web/themes/custom/my_theme/js/script.js
```

## Lando Commands

### Common Commands

```bash
# Start Lando
lando start

# Stop Lando
lando stop

# Restart Lando
lando restart

# View site information
lando info

# Access database
lando mysql

# Run Drush commands
lando drush status
lando drush cr          # Clear cache
lando drush cex         # Export configuration
lando drush cim         # Import configuration

# Access site via browser
# URL: http://local.fullstack-drupal-task.dev
```

### Custom Lando Commands

```bash
# Run the installation script
lando install-site

# Apply configuration changes
lando dejavu

# Revert composer changes
lando dejavu-hard

# Enable Xdebug
lando xdebug-on

# Run PHPCS
lando phpcs

# Run PHPCBF
lando phpcbf
```

## Configuration Management

Configuration files are stored in `config/sync/` directory. To export configuration:

```bash
lando drush config:export
```

To import configuration:

```bash
lando drush config:import
```

## Database

- **Host**: `database`
- **Database**: `drupal10`
- **Username**: `drupal10`
- **Password**: `drupal10`
- **Port**: `3306`

Access via:
```bash
lando mysql
```

Or use phpMyAdmin:
```bash
lando pma
```

## Development Settings

The project includes development-specific settings in `web/sites/default/settings.local.php`:

- CSS/JS preprocessing disabled
- Development modules excluded from config export
- Configuration sync directory set to `../config/sync`

## Troubleshooting

### Permission Issues

If you encounter permission errors:

```bash
lando ssh -c "sudo chown -R www-data:www-data /app/web/sites/default && chmod -R 0777 /app/web/sites/default"
```

### Rebuild Lando

If you need to rebuild the environment:

```bash
lando rebuild -y
```

### Clear Drupal Cache

```bash
lando drush cr
```

## Additional Resources

- [Lando Documentation](https://docs.lando.dev/)
- [Drupal 10 Documentation](https://www.drupal.org/docs/10)
- [Drush Documentation](https://www.drush.org/)

## License

[Add your license information here]
