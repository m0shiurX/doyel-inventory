<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function doyel_windows_config_stub(): string
{
    return <<<'PHP'
<?php
$base = getenv('ProgramData');
if (!$base || !preg_match('~^[A-Za-z]:[\\\\/]~', $base)) throw new RuntimeException('ProgramData unavailable');
return require $base . '/Doyel/config/runtime.php';
PHP
    . "\n";
}
function doyel_windows_apache_conf(string $app, string $state): string {
    return <<<CONF
ServerRoot "$app/apache"
Listen 127.0.0.1:18080
ServerName 127.0.0.1:18080
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule mime_module modules/mod_mime.so
LoadModule dir_module modules/mod_dir.so
LoadFile "$app/php/php8ts.dll"
LoadModule php_module "$app/php/php8apache2_4.dll"
PHPIniDir "$state/config"
TypesConfig conf/mime.types
DocumentRoot "$app/www"
DirectoryIndex index.php
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$app/www">
    Options -Indexes
    AllowOverride None
    Require local
</Directory>
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<FilesMatch "(?i)^(\\.|config\\.(local|sample)\\.php$)|\\.(sql|log|ini|cnf|bak)$">
    Require all denied
</FilesMatch>
ErrorLog "$state/logs/apache-error.log"
CustomLog "$state/logs/apache-access.log" common
PidFile "$state/tmp/httpd.pid"

CONF;
}
