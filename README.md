# Seablast for PHP
A minimalist MVC framework added by composer.
The target is to be able to create a complex web application only by configuration.
(The future is in an easy to maintain technology.)

## Configuration
- the default environment parameters are set in the [conf/default.conf.php](conf/default.conf.php)
- everything can be overriden in the web app's `conf/app.conf.php` or even in its local deployment `conf/app.conf.local.php`

## Notes
- the constant `APP_DIR` = the directory of the current application (or the library if built directly)
