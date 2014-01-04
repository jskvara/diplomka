Jak spustit aplikaci
====================

K rozběhnutí aplikace, je potřeba nainstalovat:
 - Apache (http://www.apache.org/)
 - PHP (http://www.php.net/)
 - MySQL (http://www.mysql.com/)
 - MongoDB (http://www.mongodb.org/)
 - Mozilla Firefox (http://www.mozilla.org/)
 - Java (http://www.java.com/)
 - Selenium WebDriver (http://docs.seleniumhq.org/download/)

Před spuštěním aplikace je potřeba:
 - v MySQL vytvořit databázi diplomka a tabulky (database/facebookClient.sql)
 - v MongoDB vytvořit databázi diplomka a připravit kolekce: profiles, friends, statuses, queue
 - nakopírovat zdrojové soubory aplikace do složky serveru Apache pro webové aplikace
 - v aplikaci je potřeba nastavit složky www/log a www/temp zapisovatelné
 - je potřeba nastavit připojení k databázi a k instagram API v souboru www/app/config/config.neon
 - spustit webový server Apache
 - spustit Selenium WebDriver (java -jar selenium-server-standalone-version.jar)
 - aplikace je defaultně na webové adrese http://localhost/www/www/ (zaleží na nastavení serveru Apache a složce, kde je apliace uložena)

Knihovny
=========
PHP Webdriver:
https://github.com/facebook/php-webdriver
