# ⚡ Démarrage Rapide - IIS Windows Server

## Pour les administrateurs expérimentés 🚀

### 1. Installation rapide (PowerShell Admin)
```powershell
# IIS
Install-WindowsFeature Web-Server,Web-CGI,Web-Mgmt-Console -IncludeManagementTools

# PHP
# Télécharger PHP 8.2 TS x64, extraire dans C:\PHP
# Copier php.ini-production → php.ini
# Activer extensions : pdo_pgsql, pgsql, mbstring, curl, gd, zip
[Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\PHP", [EnvironmentVariableTarget]::Machine)

# FastCGI
& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/fastCgi /+"[fullPath='C:\PHP\php-cgi.exe']" /commit:apphost
& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\PHP\php-cgi.exe',resourceType='Either']" /commit:apphost

# PostgreSQL
# Installer PostgreSQL 14, créer DB "gestion_budget"

# Composer
# Installer Composer-Setup.exe

# Application
cd C:\inetpub\wwwroot
# Copier/Extraire l'application
cd gestion-budget
composer install --no-dev --optimize-autoloader
copy .env.example .env
php artisan key:generate
# Configurer .env (DB_CONNECTION=pgsql, etc.)
php artisan migrate
php artisan db:seed --class=ExerciceSeeder
php artisan optimize

# Site IIS
New-WebAppPool -Name "GestionBudgetPool"
Set-ItemProperty IIS:\AppPools\GestionBudgetPool -Name "managedRuntimeVersion" -Value ""
New-WebSite -Name "GestionBudget" -Port 80 -PhysicalPath "C:\inetpub\wwwroot\gestion-budget\public" -ApplicationPool "GestionBudgetPool"

# Permissions
icacls C:\inetpub\wwwroot\gestion-budget\storage /grant IIS_IUSRS:M /T
icacls C:\inetpub\wwwroot\gestion-budget\bootstrap\cache /grant IIS_IUSRS:M /T

# Pare-feu
New-NetFirewallRule -DisplayName "IIS HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow

# Redémarrer
iisreset

# Accès : http://localhost ou http://IP-SERVEUR
```

---

**Détails complets** → `README_DEPLOYMENT_IIS_WINDOWS_SERVER.md`
