# 📘 Guide de Déploiement Professionnel
## Application Gestion Budget sur Windows Server 2016 + IIS + PostgreSQL

---

## 📋 Table des matières

1. [Pourquoi IIS plutôt que XAMPP](#pourquoi-iis)
2. [Architecture de déploiement](#architecture)
3. [Prérequis système](#prérequis)
4. [Installation et configuration](#installation)
5. [Sécurisation](#sécurisation)
6. [Maintenance et monitoring](#maintenance)
7. [Haute disponibilité](#haute-disponibilité)

---

## 🎯 Pourquoi IIS plutôt que XAMPP

### ✅ Avantages IIS

| Critère | XAMPP | IIS + PHP |
|---------|-------|-----------|
| **Performance** | ⚠️ Moyenne | ✅ Excellente (FastCGI natif) |
| **Sécurité** | ⚠️ Basique | ✅ Avancée (Windows Auth, SSL natif) |
| **Gestion** | ⚠️ Manuelle | ✅ Interface Windows native |
| **Services Windows** | ❌ Non | ✅ Oui (démarrage auto) |
| **Monitoring** | ⚠️ Limité | ✅ Event Viewer, perfmon |
| **Permissions** | ⚠️ Complexe | ✅ NTFS natif |
| **Support entreprise** | ❌ Non | ✅ Oui (Microsoft) |
| **SSL/TLS** | ⚠️ Basique | ✅ Natif + Let's Encrypt |
| **Scalabilité** | ⚠️ Limitée | ✅ Excellente |
| **Production** | ❌ Non recommandé | ✅ Recommandé |

### 🏆 Notre Stack Professionnelle
```
┌─────────────────────────────────────────┐
│     Windows Server 2016/2019/2022       │
├─────────────────────────────────────────┤
│  IIS 10 (Internet Information Services)│
│  • Application Pool dédié               │
│  • FastCGI activé                       │
│  • URL Rewrite                          │
│  • SSL/TLS natif                        │
├─────────────────────────────────────────┤
│  PHP 8.2+ (Thread Safe)                 │
│  • PHP Manager pour IIS                 │
│  • OPcache activé                       │
│  • Extensions requises                  │
├─────────────────────────────────────────┤
│  PostgreSQL 14+                         │
│  • Service Windows                      │
│  • pg_hba.conf sécurisé                 │
│  • Backups automatiques                 │
├─────────────────────────────────────────┤
│  Composer 2.x                           │
│  • Installé globalement                 │
│  • Optimisé pour production             │
└─────────────────────────────────────────┘
```

---

## 🖥️ Architecture de déploiement

### Architecture Simple (PME)
```
Internet
    ↓
[Firewall/Router] - Port 80, 443
    ↓
[Windows Server 2016]
    ├── IIS (Frontend)
    │   └── Application Pool: GestionBudget
    │       └── Site Web: budget.entreprise.local
    │
    ├── PostgreSQL (Backend)
    │   └── Database: gestion_budget
    │
    └── Scheduler
        └── Tâches planifiées Laravel
```

### Architecture Avancée (Grande entreprise)
```
Internet
    ↓
[Load Balancer]
    ↓
    ├─→ [Web Server 1] (IIS + PHP) ←┐
    │                                 │
    └─→ [Web Server 2] (IIS + PHP) ←┼→ [DB Server] (PostgreSQL)
                                      │       ↓
                                      └→ [Backup Server]
```

---

## 📦 Prérequis système

### Configuration serveur minimale

| Composant | Minimum | Recommandé |
|-----------|---------|------------|
| **OS** | Windows Server 2016 | Windows Server 2019/2022 |
| **RAM** | 8 GB | 16 GB |
| **CPU** | 2 cores | 4 cores |
| **Disque** | 50 GB SSD | 100 GB SSD (RAID 1) |
| **Réseau** | 100 Mbps | 1 Gbps |

### Logiciels requis

- **Windows Server 2016/2019/2022** (avec GUI ou Core)
- **IIS 10** (inclus dans Windows Server)
- **PHP 8.2+** (Non-Thread-Safe pour FastCGI)
- **PostgreSQL 14+**
- **Composer 2.x**
- **URL Rewrite Module** pour IIS
- **PHP Manager** pour IIS (optionnel mais recommandé)

---

## 🚀 Installation et configuration

## PARTIE 1 : Installation d'IIS

### Étape 1.1 : Installer le rôle IIS

**Via PowerShell (Administrateur) :**
```powershell
# Installer IIS avec les fonctionnalités nécessaires
Install-WindowsFeature -Name Web-Server -IncludeManagementTools

# Installer les modules complémentaires
Install-WindowsFeature -Name Web-Mgmt-Console
Install-WindowsFeature -Name Web-CGI
Install-WindowsFeature -Name Web-Url-Auth
Install-WindowsFeature -Name Web-Windows-Auth
Install-WindowsFeature -Name Web-Filtering

# Vérifier l'installation
Get-WindowsFeature -Name Web-*
```

**Via Server Manager (Interface graphique) :**

1. Ouvrir **Server Manager**
2. **Manage** → **Add Roles and Features**
3. **Role-based installation**
4. Sélectionner votre serveur
5. Cocher **Web Server (IIS)**
6. Dans **Role Services**, cocher :
   - ✅ Common HTTP Features → Default Document, Static Content
   - ✅ Application Development → CGI
   - ✅ Health and Diagnostics → HTTP Logging
   - ✅ Security → Request Filtering
   - ✅ Management Tools → IIS Management Console
7. **Install**

### Étape 1.2 : Vérifier l'installation

Ouvrir un navigateur : `http://localhost`  
Vous devez voir la page par défaut IIS ✅

---

## PARTIE 2 : Installation de PHP

### Étape 2.1 : Télécharger PHP

**Site officiel :** https://windows.php.net/download/

**Choisir :**
- Version : **PHP 8.2+**
- Type : **Thread Safe** (TS) pour IIS FastCGI
- Architecture : **x64** (64 bits)

Exemple : `php-8.2.14-Win32-vs16-x64.zip`

### Étape 2.2 : Installer PHP
```powershell
# Créer le dossier d'installation
New-Item -Path "C:\PHP" -ItemType Directory

# Extraire le ZIP dans C:\PHP
# (Utiliser 7-Zip ou l'explorateur)

# Copier php.ini
Copy-Item "C:\PHP\php.ini-production" "C:\PHP\php.ini"
```

### Étape 2.3 : Configurer PHP

**Éditer** `C:\PHP\php.ini` :
```ini
; Activer les extensions requises
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=pdo_pgsql
extension=pgsql
extension=openssl
extension=zip

; Configuration de base
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M

; OPcache (Performance)
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1

; Configuration timezone
date.timezone = Africa/Douala

; Logs
error_log = C:\PHP\logs\php_errors.log
log_errors = On

; FastCGI
fastcgi.impersonate = 1
cgi.fix_pathinfo = 1
cgi.force_redirect = 0
```

### Étape 2.4 : Créer le dossier de logs
```powershell
New-Item -Path "C:\PHP\logs" -ItemType Directory
```

### Étape 2.5 : Ajouter PHP au PATH
```powershell
# Ajouter PHP au PATH système
[Environment]::SetEnvironmentVariable(
    "Path",
    $env:Path + ";C:\PHP",
    [EnvironmentVariableTarget]::Machine
)

# Redémarrer PowerShell et vérifier
php -v
```

### Étape 2.6 : Configurer FastCGI dans IIS

**Via PowerShell :**
```powershell
# Configurer FastCGI
& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/fastCgi /+"[fullPath='C:\PHP\php-cgi.exe']" /commit:apphost

# Configurer le handler
& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\PHP\php-cgi.exe',resourceType='Either']" /commit:apphost
```

**Via IIS Manager (Interface graphique) :**

1. Ouvrir **IIS Manager**
2. Sélectionner le serveur (racine)
3. Double-clic sur **Handler Mappings**
4. **Add Module Mapping** (menu de droite)
   - Request path : `*.php`
   - Module : `FastCgiModule`
   - Executable : `C:\PHP\php-cgi.exe`
   - Name : `PHP_via_FastCGI`
5. **OK**

### Étape 2.7 : Tester PHP

**Créer un fichier test :**
```powershell
# Créer phpinfo.php
Set-Content -Path "C:\inetpub\wwwroot\phpinfo.php" -Value "<?php phpinfo(); ?>"
```

**Tester :** `http://localhost/phpinfo.php`

Vous devez voir la page phpinfo ✅

**Supprimer le fichier test :**
```powershell
Remove-Item "C:\inetpub\wwwroot\phpinfo.php"
```

---

## PARTIE 3 : Installation de PostgreSQL

### Étape 3.1 : Télécharger PostgreSQL

**Site officiel :** https://www.enterprisedb.com/downloads/postgres-postgresql-downloads

**Choisir :**
- Version : **PostgreSQL 14.x ou 15.x**
- OS : **Windows x86-64**

### Étape 3.2 : Installer PostgreSQL

1. Lancer l'installateur `postgresql-14.x-windows-x64.exe`
2. **Installation Directory** : `C:\Program Files\PostgreSQL\14`
3. **Components** :
   - ✅ PostgreSQL Server
   - ✅ pgAdmin 4
   - ✅ Command Line Tools
   - ❌ Stack Builder (optionnel)
4. **Data Directory** : `C:\Program Files\PostgreSQL\14\data`
5. **Password** : Définir un mot de passe fort (ex: `Postgres@2026!`)
6. **Port** : `5432` (par défaut)
7. **Locale** : `French, Cameroon` ou `Default locale`
8. **Install**

### Étape 3.3 : Vérifier le service PostgreSQL
```powershell
# Vérifier le service
Get-Service -Name postgresql*

# Doit afficher : Running
```

### Étape 3.4 : Configurer l'authentification

**Éditer** `C:\Program Files\PostgreSQL\14\data\pg_hba.conf` :
```conf
# TYPE  DATABASE        USER            ADDRESS                 METHOD

# Local connections
host    all             all             127.0.0.1/32            scram-sha-256
host    all             all             ::1/128                 scram-sha-256

# Réseau local (adapter selon votre réseau)
host    all             all             192.168.1.0/24          scram-sha-256
```

**Redémarrer PostgreSQL :**
```powershell
Restart-Service postgresql-x64-14
```

### Étape 3.5 : Créer la base de données
```powershell
# Se connecter à PostgreSQL
& "C:\Program Files\PostgreSQL\14\bin\psql.exe" -U postgres

# Dans psql :
CREATE DATABASE gestion_budget
    WITH ENCODING = 'UTF8'
    LC_COLLATE = 'French_Cameroon.1252'
    LC_CTYPE = 'French_Cameroon.1252'
    TEMPLATE = template0;

# Créer un utilisateur dédié (optionnel mais recommandé)
CREATE USER budget_user WITH PASSWORD 'Budget@2026!';
GRANT ALL PRIVILEGES ON DATABASE gestion_budget TO budget_user;

# Quitter
\q
```

---

## PARTIE 4 : Installation de Composer

### Étape 4.1 : Télécharger Composer

**Site officiel :** https://getcomposer.org/download/

Télécharger : `Composer-Setup.exe`

### Étape 4.2 : Installer Composer

1. Lancer `Composer-Setup.exe`
2. **Developer mode** : Non
3. **PHP executable** : `C:\PHP\php.exe`
4. **Proxy** : Laisser vide (sauf si proxy d'entreprise)
5. **Install**

### Étape 4.3 : Vérifier Composer
```powershell
composer --version
# Doit afficher : Composer version 2.x
```

---

## PARTIE 5 : Installation de l'application Laravel

### Étape 5.1 : Créer la structure des dossiers
```powershell
# Créer le dossier de l'application
New-Item -Path "C:\inetpub\wwwroot\gestion-budget" -ItemType Directory

# Naviguer dans le dossier
cd C:\inetpub\wwwroot\gestion-budget
```

### Étape 5.2 : Copier les fichiers de l'application

**Option A : Depuis un ZIP**
```powershell
# Extraire le ZIP dans C:\inetpub\wwwroot\gestion-budget
# Utiliser l'explorateur ou :
Expand-Archive -Path "C:\Users\Admin\Desktop\gestion-budget.zip" -DestinationPath "C:\inetpub\wwwroot\gestion-budget"
```

**Option B : Depuis Git**
```powershell
# Installer Git pour Windows d'abord
# Puis cloner
cd C:\inetpub\wwwroot
git clone https://github.com/votre-repo/gestion-budget.git
```

### Étape 5.3 : Installer les dépendances
```powershell
cd C:\inetpub\wwwroot\gestion-budget

# Installer les dépendances PHP
composer install --optimize-autoloader --no-dev

# Copier le fichier .env
Copy-Item .env.example .env

# Générer la clé
php artisan key:generate
```

### Étape 5.4 : Configurer .env

**Éditer** `C:\inetpub\wwwroot\gestion-budget\.env` :
```ini
APP_NAME="Gestion Budget"
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_DEBUG=false
APP_URL=http://budget.entreprise.local

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gestion_budget
DB_USERNAME=budget_user
DB_PASSWORD=Budget@2026!

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@entreprise.cm
MAIL_PASSWORD=xxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@entreprise.cm"
MAIL_FROM_NAME="${APP_NAME}"
```

### Étape 5.5 : Exécuter les migrations
```powershell
php artisan migrate --force
php artisan db:seed --class=ExerciceSeeder
```

### Étape 5.6 : Optimiser pour la production
```powershell
# Mettre en cache tout
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Créer le lien symbolique
php artisan storage:link

# Optimiser Composer
composer dump-autoload --optimize --classmap-authoritative
```

---

## PARTIE 6 : Configuration du site IIS

### Étape 6.1 : Créer un Application Pool dédié

**Via PowerShell :**
```powershell
Import-Module WebAdministration

# Créer l'Application Pool
New-WebAppPool -Name "GestionBudgetPool"

# Configurer l'Application Pool
Set-ItemProperty IIS:\AppPools\GestionBudgetPool -Name "managedRuntimeVersion" -Value ""
Set-ItemProperty IIS:\AppPools\GestionBudgetPool -Name "startMode" -Value "AlwaysRunning"
Set-ItemProperty IIS:\AppPools\GestionBudgetPool -Name "processModel.idleTimeout" -Value "00:00:00"
```

**Via IIS Manager :**

1. Ouvrir **IIS Manager**
2. **Application Pools** → **Add Application Pool**
3. Nom : `GestionBudgetPool`
4. .NET CLR Version : **No Managed Code**
5. **OK**
6. Clic droit sur `GestionBudgetPool` → **Advanced Settings**
   - Start Mode : `AlwaysRunning`
   - Idle Time-out : `0`
7. **OK**

### Étape 6.2 : Créer le site web

**Via PowerShell :**
```powershell
# Créer le site
New-WebSite -Name "GestionBudget" `
    -Port 80 `
    -PhysicalPath "C:\inetpub\wwwroot\gestion-budget\public" `
    -ApplicationPool "GestionBudgetPool"

# Ajouter un binding (optionnel, pour domaine)
New-WebBinding -Name "GestionBudget" -Protocol "http" -Port 80 -HostHeader "budget.entreprise.local"
```

**Via IIS Manager :**

1. **Sites** → **Add Website**
2. **Site name** : `GestionBudget`
3. **Application pool** : `GestionBudgetPool`
4. **Physical path** : `C:\inetpub\wwwroot\gestion-budget\public`
5. **Binding** :
   - Type : `http`
   - Port : `80`
   - Host name : `budget.entreprise.local` (ou laisser vide)
6. **OK**

### Étape 6.3 : Installer URL Rewrite Module

**Télécharger :** https://www.iis.net/downloads/microsoft/url-rewrite

**Installer** `rewrite_amd64_en-US.msi`

### Étape 6.4 : Configurer URL Rewrite

**Le fichier `web.config` est déjà dans `public/` de Laravel.**

Si absent, créer `C:\inetpub\wwwroot\gestion-budget\public\web.config` :
```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Imported Rule 1" stopProcessing="true">
                    <match url="^(.*)/$" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Redirect" redirectType="Permanent" url="/{R:1}" />
                </rule>
                <rule name="Imported Rule 2" stopProcessing="true">
                    <match url="^" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
            </files>
        </defaultDocument>
    </system.webServer>
</configuration>
```

### Étape 6.5 : Configurer les permissions NTFS
```powershell
# Donner les permissions au compte IIS
$path = "C:\inetpub\wwwroot\gestion-budget"
$acl = Get-Acl $path

# Permissions pour IIS_IUSRS
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
    "IIS_IUSRS",
    "ReadAndExecute",
    "ContainerInherit,ObjectInherit",
    "None",
    "Allow"
)
$acl.SetAccessRule($rule)
Set-Acl $path $acl

# Permissions d'écriture pour storage et bootstrap/cache
$writePaths = @(
    "$path\storage",
    "$path\bootstrap\cache"
)

foreach ($wpath in $writePaths) {
    $acl = Get-Acl $wpath
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS",
        "Modify",
        "ContainerInherit,ObjectInherit",
        "None",
        "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl $wpath $acl
}
```

### Étape 6.6 : Redémarrer IIS
```powershell
iisreset
```

---

## PARTIE 7 : Configuration DNS et accès réseau

### Étape 7.1 : Configurer le fichier hosts (local)

**Sur le serveur :**

`C:\Windows\System32\drivers\etc\hosts`
```
127.0.0.1    budget.entreprise.local
```

### Étape 7.2 : Configurer le pare-feu Windows
```powershell
# Autoriser HTTP (port 80)
New-NetFirewallRule -DisplayName "IIS HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow

# Autoriser HTTPS (port 443) si SSL configuré
New-NetFirewallRule -DisplayName "IIS HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow
```

### Étape 7.3 : Configuration DNS Active Directory (optionnel)

**Si vous avez un serveur DNS/AD :**

1. Ouvrir **DNS Manager**
2. Zone de recherche directe de votre domaine
3. Nouveau **Host (A)** :
   - Name : `budget`
   - IP : IP du serveur (ex: 192.168.1.10)
4. **OK**

Maintenant accessible via : `http://budget.entreprise.local`

### Étape 7.4 : Tester l'accès

**Depuis le serveur :**
```
http://localhost
http://budget.entreprise.local
```

**Depuis un autre PC du réseau :**
```
http://192.168.1.10
http://budget.entreprise.local
```

---

## 🔒 PARTIE 8 : Sécurisation

### Étape 8.1 : Configurer SSL/TLS

**Option A : Certificat auto-signé (dev/test)**
```powershell
# Créer un certificat auto-signé
New-SelfSignedCertificate -DnsName "budget.entreprise.local" -CertStoreLocation "cert:\LocalMachine\My"

# Lier au site IIS
New-WebBinding -Name "GestionBudget" -Protocol "https" -Port 443 -HostHeader "budget.entreprise.local" -SslFlags 1
```

**Option B : Let's Encrypt (production - domaine public)**

Installer **win-acme** : https://www.win-acme.com/
```powershell
# Télécharger win-acme
# Exécuter
wacs.exe

# Suivre l'assistant pour :
# - Créer un certificat pour budget.votredomaine.com
# - Lier automatiquement à IIS
# - Renouvellement automatique via Scheduled Task
```

**Option C : Certificat d'entreprise (AD CS)**

Si vous avez une PKI interne, demander un certificat au CA de l'entreprise.

### Étape 8.2 : Forcer HTTPS

**Dans** `public/web.config`, ajouter :
```xml
<rule name="Force HTTPS" stopProcessing="true">
    <match url="(.*)" />
    <conditions>
        <add input="{HTTPS}" pattern="off" ignoreCase="true" />
    </conditions>
    <action type="Redirect" url="https://{HTTP_HOST}/{R:1}" redirectType="Permanent" />
</rule>
```

**Dans** `.env` :
```ini
APP_URL=https://budget.entreprise.local
SESSION_SECURE_COOKIE=true
```

### Étape 8.3 : Sécuriser PHP

**Dans** `C:\PHP\php.ini` :
```ini
; Désactiver les fonctions dangereuses
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Masquer la version PHP
expose_php = Off

; Limiter les uploads
upload_max_filesize = 20M
post_max_size = 20M
```

### Étape 8.4 : Limiter l'accès par IP (optionnel)

**Via IIS Manager :**

1. Sélectionner le site **GestionBudget**
2. **IP Address and Domain Restrictions**
3. **Add Allow Entry** pour chaque sous-réseau autorisé
   - Ex: 192.168.1.0/24
4. **Deny** pour tous les autres

### Étape 8.5 : Activer les logs détaillés

**Via IIS Manager :**

1. Site **GestionBudget** → **Logging**
2. Format : **W3C**
3. Directory : `C:\inetpub\logs\LogFiles`
4. **Select Fields** → Cocher :
   - Date, Time
   - Client IP
   - Method, URI Stem
   - HTTP Status
   - User Agent

---

## 🛠️ PARTIE 9 : Maintenance et Monitoring

### Étape 9.1 : Créer des tâches planifiées Laravel

**Scheduler Laravel** : Créer une tâche Windows pour exécuter le scheduler.
```powershell
# Créer un script batch
$scriptPath = "C:\inetpub\wwwroot\gestion-budget\scheduler.bat"
@"
@echo off
cd C:\inetpub\wwwroot\gestion-budget
C:\PHP\php.exe artisan schedule:run >> C:\inetpub\wwwroot\gestion-budget\storage\logs\scheduler.log 2>&1
"@ | Out-File -FilePath $scriptPath -Encoding ASCII

# Créer la tâche planifiée
$action = New-ScheduledTaskAction -Execute "C:\inetpub\wwwroot\gestion-budget\scheduler.bat"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName "Laravel Scheduler - Gestion Budget" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings
```

### Étape 9.2 : Surveillance des performances

**Monitorer avec Performance Monitor (perfmon) :**
```powershell
# Lancer perfmon
perfmon
```

**Compteurs à surveiller :**
- Processor → % Processor Time
- Memory → Available MBytes
- PhysicalDisk → % Disk Time
- Web Service → Current Connections
- PostgreSQL → Transactions/sec

### Étape 9.3 : Sauvegarde automatique

**Script de backup PostgreSQL :**

`C:\Scripts\backup-postgres.ps1` :
```powershell
# Configuration
$pgPath = "C:\Program Files\PostgreSQL\14\bin"
$backupDir = "D:\Backups\PostgreSQL"
$dbName = "gestion_budget"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = "$backupDir\$dbName`_$timestamp.backup"

# Créer le dossier si inexistant
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir
}

# Sauvegarder
$env:PGPASSWORD = "Budget@2026!"
& "$pgPath\pg_dump.exe" -U budget_user -F c -b -v -f $backupFile $dbName

# Supprimer les sauvegardes de plus de 30 jours
Get-ChildItem $backupDir -Filter "*.backup" | 
    Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-30)} |
    Remove-Item -Force

Write-Host "Sauvegarde terminée : $backupFile"
```

**Créer la tâche planifiée de backup :**
```powershell
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-File C:\Scripts\backup-postgres.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At "02:00AM"
$principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "Backup PostgreSQL - Gestion Budget" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal
```

### Étape 9.4 : Rotation des logs

**Script de rotation des logs Laravel :**

`C:\Scripts\rotate-logs.ps1` :
```powershell
$logDir = "C:\inetpub\wwwroot\gestion-budget\storage\logs"
$archiveDir = "D:\Archives\Logs"
$daysToKeep = 90

# Créer le dossier d'archives
if (-not (Test-Path $archiveDir)) {
    New-Item -ItemType Directory -Path $archiveDir
}

# Archiver les logs de plus de 7 jours
Get-ChildItem $logDir -Filter "*.log" | 
    Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-7)} |
    ForEach-Object {
        $archiveName = "$archiveDir\$($_.Name)_$(Get-Date -Format 'yyyyMMdd').zip"
        Compress-Archive -Path $_.FullName -DestinationPath $archiveName
        Remove-Item $_.FullName -Force
    }

# Supprimer les archives de plus de 90 jours
Get-ChildItem $archiveDir -Filter "*.zip" |
    Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-$daysToKeep)} |
    Remove-Item -Force
```

---

## 🚨 PARTIE 10 : Dépannage

### Problème : Erreur 500

**Solution :**
```powershell
# 1. Activer le mode debug temporairement
# Dans .env : APP_DEBUG=true

# 2. Vérifier les logs
Get-Content C:\inetpub\wwwroot\gestion-budget\storage\logs\laravel.log -Tail 50

# 3. Vérifier les logs IIS
Get-Content C:\inetpub\logs\LogFiles\W3SVC1\*.log -Tail 50

# 4. Vérifier les permissions
icacls C:\inetpub\wwwroot\gestion-budget\storage
icacls C:\inetpub\wwwroot\gestion-budget\bootstrap\cache

# 5. Nettoyer le cache
cd C:\inetpub\wwwroot\gestion-budget
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Problème : FastCGI ne répond pas

**Solution :**
```powershell
# Augmenter le timeout FastCGI
& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/fastCgi /[fullPath='C:\PHP\php-cgi.exe'].activityTimeout:"600" /commit:apphost

& "$env:windir\system32\inetsrv\appcmd.exe" set config -section:system.webServer/fastCgi /[fullPath='C:\PHP\php-cgi.exe'].requestTimeout:"600" /commit:apphost

# Redémarrer IIS
iisreset
```

### Problème : Connexion PostgreSQL refusée

**Solution :**
```powershell
# 1. Vérifier que le service tourne
Get-Service postgresql*

# 2. Tester la connexion
& "C:\Program Files\PostgreSQL\14\bin\psql.exe" -U budget_user -d gestion_budget -h 127.0.0.1

# 3. Vérifier pg_hba.conf
notepad "C:\Program Files\PostgreSQL\14\data\pg_hba.conf"

# 4. Redémarrer PostgreSQL
Restart-Service postgresql-x64-14
```

### Problème : Performance lente

**Solutions :**
```powershell
# 1. Activer OPcache (déjà fait normalement)
# Vérifier dans php.ini

# 2. Optimiser Laravel
cd C:\inetpub\wwwroot\gestion-budget
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Indexer la base de données
# Se connecter à PostgreSQL et exécuter :
ANALYZE;
VACUUM ANALYZE;

# 4. Augmenter la mémoire PHP
# Dans php.ini : memory_limit = 1024M
```

---

## 📊 PARTIE 11 : Haute disponibilité (optionnel)

### Architecture Load Balancer
```
                    [Internet]
                        ↓
                 [Load Balancer]
                 (ARR ou HAProxy)
                        ↓
        ┌───────────────┴───────────────┐
        ↓                               ↓
  [Web Server 1]                  [Web Server 2]
  IIS + PHP                       IIS + PHP
  192.168.1.10                    192.168.1.11
        ↓                               ↓
        └───────────────┬───────────────┘
                        ↓
              [Database Server]
              PostgreSQL Master
              192.168.1.20
                        ↓
              [Replica Database]
              PostgreSQL Slave
              192.168.1.21
```

### Configurer ARR (Application Request Routing)

**Sur le serveur Load Balancer :**

1. Installer **Application Request Routing** pour IIS
2. Installer **URL Rewrite Module**
3. Créer une **Server Farm** avec les serveurs web
4. Configurer le **Load Balance** (Round Robin ou Least Current Request)

---

## ✅ Checklist finale
```
☐ Windows Server 2016/2019 installé
☐ IIS installé et configuré
☐ PHP 8.2+ installé et extensions activées
☐ FastCGI configuré dans IIS
☐ PostgreSQL installé et service démarré
☐ Composer installé
☐ Base de données créée
☐ Application Laravel copiée dans C:\inetpub\wwwroot
☐ composer install exécuté
☐ .env configuré
☐ php artisan migrate exécuté
☐ Utilisateur admin créé
☐ Application Pool créé
☐ Site IIS créé et lié à l'Application Pool
☐ URL Rewrite configuré
☐ Permissions NTFS correctes
☐ Pare-feu configuré
☐ DNS/Hosts configuré
☐ SSL/TLS configuré (production)
☐ Tâches planifiées créées (scheduler, backup)
☐ Monitoring configuré
☐ Tests d'accès réussis
☐ Documentation remise à l'équipe IT
```

---

## 🎉 Félicitations !

Votre application **Gestion Budget** est maintenant déployée professionnellement sur Windows Server avec IIS !

**Avantages de cette configuration :**
- ✅ Performance optimale (FastCGI natif)
- ✅ Sécurité renforcée (SSL, permissions NTFS)
- ✅ Facilité de gestion (IIS Manager)
- ✅ Intégration Active Directory possible
- ✅ Monitoring natif Windows
- ✅ Haute disponibilité possible
- ✅ Support entreprise Microsoft

---

**Version** : 2.0.0  
**Date** : Janvier 2026  
**Auteur** : [Votre nom]  
**Support** : [Email/Téléphone]
