# Seablast for PHP
A minimalist MVC framework added by composer.
The goal is to be able to create a complex web application only by configuration.
(The future is in an easy to maintain technology.)

## Configuration
- the default environment parameters are set in the [conf/default.conf.php](conf/default.conf.php)
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`

## Stack
- PHP7.2+
- Latte
- Tracy

## Notes
- the constant `APP_DIR` = the directory of the current application (or the library if built directly)

## Directory description
| Directory | Description |
|-----|------|
| .github/ | automations |
| cache/ | Latte cache - this is just for development as production-wise, there will be cache/ directory in the root of the app |
| log/ | logs - this is just for development as production-wise, there will be logs/ directory in the root of the app |
| src/ | Seablast classes |
| templates/ | Latte templates to be inherited |
