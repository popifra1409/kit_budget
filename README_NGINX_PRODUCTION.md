# 🚀 Déploiement NGINX sur Windows Server 2016
## Simple, Performant, Production-Ready

---

## 🎯 Pourquoi NGINX sur Windows ?

| Avantage | NGINX | IIS | XAMPP |
|----------|-------|-----|-------|
| **Simplicité** | ✅✅✅ | ⚠️ Complexe | ✅✅ |
| **Performance** | ✅✅✅ | ✅✅ | ⚠️ |
| **Configuration** | ✅ Fichier texte | ⚠️ GUI/XML | ⚠️ Limité |
| **Production** | ✅✅✅ | ✅✅✅ | ❌ |
| **Portabilité** | ✅ Linux/Windows | ❌ Windows only | ⚠️ |
| **Ressources** | ✅ Léger | ⚠️ Moyen | ✅ |

---

## 📦 Stack complète
```
Windows Server 2016/2019/2022
    ↓
NGINX 1.24+ (Serveur web)
    ↓
PHP 8.2/8.3 NTS (Non-Thread-Safe)
    ↓
PostgreSQL 14+ (Base de données)
    ↓
NSSM (Service Manager pour Laravel)
```

---

## ⏱️ Temps estimé : 40 minutes

---

## 🔧 PARTIE 1 : Installation NGINX (10 min)

### Étape 1.1 : Télécharger NGINX

**Site officiel :** http://nginx.org/en/download.html

**Choisir :** `nginx/Windows-x.xx.x` (Stable version)

Exemple : `nginx-1.24.0.zip`

### Étape 1.2 : Installer NGINX
```powershell
# Créer le dossier
New-Item -Path "C:\nginx" -ItemType Directory

# Extraire le ZIP dans C:\nginx
# (Utiliser l'explorateur ou 7-Zip)
```

Votre structure devrait ressembler à :
```
C:\nginx\
    ├── conf\
    ├── docs\
    ├── html\
    ├── logs\
    └── nginx.exe
```

### Étape 1.3 : Tester NGINX
```powershell
# Aller dans le dossier NGINX
cd C:\nginx

# Démarrer NGINX
.\nginx.exe

# Vérifier
Start-Process "http://localhost"
```

Vous devez voir la page "**Welcome to nginx!**" ✅

### Étape 1.4 : Installer NGINX comme Service Windows

**Télécharger NSSM** (Non-Sucking Service Manager) :
- Site : https://nssm.cc/download
- Télécharger : `nssm-2.24.zip`
```powershell
# Extraire NSSM
Expand-Archive -Path "nssm-2.24.zip" -DestinationPath "C:\nssm"

# Installer NGINX comme service
C:\nssm\win64\nssm.exe install nginx "C:\nginx\nginx.exe"

# Configurer le service
C:\nssm\win64\nssm.exe set nginx AppDirectory C:\nginx
C:\nssm\win64\nssm.exe set nginx DisplayName "NGINX Web Server"
C:\nssm\win64\nssm.exe set nginx Description "High Performance Web Server"
C:\nssm\win64\nssm.exe set nginx Start SERVICE_AUTO_START

# Démarrer le service
Start-Service nginx

# Vérifier
Get-Service nginx
```

---

## 🐘 PARTIE 2 : Installation PHP (10 min)

### Étape 2.1 : Télécharger PHP

**Site officiel :** https://windows.php.net/download/

**IMPORTANT : Choisir NTS (Non-Thread-Safe)** pour NGINX !

Exemple : `php-8.3.1-nts-Win32-vs16-x64.zip`

### Étape 2.2 : Installer PHP
```powershell
# Créer le dossier
New-Item -Path "C:\php" -ItemType Directory

# Extraire le ZIP dans C:\php

# Copier php.ini
Copy-Item "C:\php\php.ini-production" "C:\php\php.ini"
```

### Étape 2.3 : Configurer PHP

**Éditer** `C:\php\php.ini` :
```ini
; Extensions PostgreSQL (OBLIGATOIRE)
extension=pdo_pgsql
extension=pgsql

; Extensions Laravel (OBLIGATOIRE)
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=zip

; Extensions optionnelles (recommandées)
extension=intl
extension=bcmath
extension=redis

; Configuration de base
max_execution_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M

; Timezone
date.timezone = Africa/Douala

; Logs
error_log = C:\php\logs\php_errors.log
log_errors = On

; Configuration FastCGI
cgi.fix_pathinfo = 0
```

### Étape 2.4 : Créer le dossier de logs
```powershell
New-Item -Path "C:\php\logs" -ItemType Directory
```

### Étape 2.5 : Ajouter PHP au PATH
```powershell
# Ajouter PHP au PATH système
[Environment]::SetEnvironmentVariable(
    "Path",
    $env:Path + ";C:\php",
    [EnvironmentVariableTarget]::Machine
)

# Redémarrer PowerShell et vérifier
php -v
```

### Étape 2.6 : Démarrer PHP-CGI comme Service

**Créer un script de lancement** `C:\php\start-php-cgi.bat` :
```batch
@echo off
cd C:\php
php-cgi.exe -b 127.0.0.1:9000
```

**Installer comme service avec NSSM :**
```powershell
# Installer PHP-CGI comme service
C:\nssm\win64\nssm.exe install php-cgi "C:\php\start-php-cgi.bat"

# Configurer
C:\nssm\win64\nssm.exe set php-cgi AppDirectory C:\php
C:\nssm\win64\nssm.exe set php-cgi DisplayName "PHP FastCGI"
C:\nssm\win64\nssm.exe set php-cgi Start SERVICE_AUTO_START

# Démarrer
Start-Service php-cgi

# Vérifier
Get-Service php-cgi
```

---

## 🗄️ PARTIE 3 : Installation PostgreSQL (5 min)

### Étape 3.1 : Télécharger et installer

**Site :** https://www.postgresql.org/download/windows/

1. Télécharger l'installateur Windows
2. Installer avec :
   - Port : `5432`
   - Mot de passe : `Postgres@2026!` (à retenir !)
   - Locale : Default

### Étape 3.2 : Créer la base de données
```powershell
# Se connecter
& "C:\Program Files\PostgreSQL\14\bin\psql.exe" -U postgres

# Dans psql :
CREATE DATABASE gestion_budget
    WITH ENCODING = 'UTF8'
    LC_COLLATE = 'French_Cameroon.1252'
    LC_CTYPE = 'French_Cameroon.1252';

CREATE USER budget_user WITH PASSWORD 'Budget@2026!';
GRANT ALL PRIVILEGES ON DATABASE gestion_budget TO budget_user;

\q
```

---

## 🎯 PARTIE 4 : Installer Composer (3 min)

**Télécharger :** https://getcomposer.org/Composer-Setup.exe

1. Lancer l'installateur
2. Sélectionner PHP : `C:\php\php.exe`
3. Installer

**Vérifier :**
```powershell
composer --version
```

---

## 📁 PARTIE 5 : Déployer l'application (10 min)

### Étape 5.1 : Copier les fichiers
```powershell
# Créer le dossier
New-Item -Path "C:\webapps\gestion-budget" -ItemType Directory

# Copier votre application dans ce dossier
# (via SFTP, RDP, ou extraire un ZIP)
```

### Étape 5.2 : Installer les dépendances
```powershell
cd C:\webapps\gestion-budget

# Installer SANS scripts
composer install --no-scripts --no-dev --optimize-autoloader

# Générer l'autoload
composer dump-autoload --optimize
```

### Étape 5.3 : Configuration
```powershell
# Copier .env
Copy-Item .env.example .env

# Générer la clé
php artisan key:generate
```

**Éditer** `C:\webapps\gestion-budget\.env` :
```ini
APP_NAME="Gestion Budget"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.100

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gestion_budget
DB_USERNAME=budget_user
DB_PASSWORD=Budget@2026!

SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=database
```

### Étape 5.4 : Exécuter les scripts Laravel
```powershell
# Découvrir les packages
php artisan package:discover --ansi

# Lien symbolique
php artisan storage:link

# Migrer
php artisan migrate --force

# Seeder
php artisan db:seed --class=ExerciceSeeder

# Optimiser
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Étape 5.5 : Permissions
```powershell
# Donner les permissions complètes aux dossiers storage et cache
icacls "C:\webapps\gestion-budget\storage" /grant Everyone:F /T
icacls "C:\webapps\gestion-budget\bootstrap\cache" /grant Everyone:F /T
```

---

## ⚙️ PARTIE 6 : Configurer NGINX (10 min)

### Étape 6.1 : Configuration principale

**Éditer** `C:\nginx\conf\nginx.conf` :
```nginx
worker_processes  auto;

events {
    worker_connections  1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    sendfile        on;
    keepalive_timeout  65;

    # Logs
    access_log  logs/access.log;
    error_log   logs/error.log;

    # Gzip compression
    gzip  on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;

    # Inclure les sites
    include sites-enabled/*.conf;
}
```

### Étape 6.2 : Créer la structure sites-enabled
```powershell
New-Item -Path "C:\nginx\conf\sites-enabled" -ItemType Directory
```

### Étape 6.3 : Configuration du site

**Créer** `C:\nginx\conf\sites-enabled\gestion-budget.conf` :
```nginx
server {
    listen       80;
    server_name  localhost 192.168.1.100 budget.entreprise.local;

    root C:/webapps/gestion-budget/public;
    index index.php index.html;

    # Logs spécifiques
    access_log  logs/gestion-budget-access.log;
    error_log   logs/gestion-budget-error.log;

    # Sécurité
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Charset
    charset utf-8;

    # Gestion des requêtes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # PHP via FastCGI
    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
        
        # Timeouts
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Cache des assets statiques
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Désactiver les logs pour favicon et robots.txt
    location = /favicon.ico { 
        access_log off; 
        log_not_found off; 
    }
    
    location = /robots.txt  { 
        access_log off; 
        log_not_found off; 
    }
}
```

### Étape 6.4 : Tester et redémarrer NGINX
```powershell
# Tester la configuration
cd C:\nginx
.\nginx.exe -t

# Si OK, redémarrer le service
Restart-Service nginx

# Vérifier
Get-Service nginx
```

---

## 🌐 PARTIE 7 : Accès réseau et sécurité (5 min)

### Étape 7.1 : Configurer le pare-feu Windows
```powershell
# Autoriser HTTP (port 80)
New-NetFirewallRule -DisplayName "NGINX HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow

# Autoriser HTTPS (port 443) si SSL configuré
New-NetFirewallRule -DisplayName "NGINX HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow
```

### Étape 7.2 : Trouver l'IP du serveur
```powershell
ipconfig
```

Chercher `Adresse IPv4` → Exemple : `192.168.1.100`

### Étape 7.3 : Configuration DNS/Hosts (optionnel)

**Sur le serveur et les clients :**

`C:\Windows\System32\drivers\etc\hosts`
```
192.168.1.100    budget.entreprise.local
```

---

## ✅ PARTIE 8 : Tester l'application

### Depuis le serveur :
```
http://localhost
http://192.168.1.100
```

### Depuis un autre PC du réseau :
```
http://192.168.1.100
http://budget.entreprise.local
```

**Vous devriez voir l'application Gestion Budget !** 🎉

### Créer un utilisateur admin
```powershell
cd C:\webapps\gestion-budget
php artisan tinker
```
```php
$user = new App\Models\User();
$user->name = 'Administrateur';
$user->email = 'admin@budget.local';
$user->password = bcrypt('Admin@2026');
$user->save();

if (class_exists('Spatie\Permission\Models\Role')) {
    $user->assignRole('super_admin');
}

echo "✅ Utilisateur créé\n";
exit
```

---

## 🔒 PARTIE 9 : SSL/HTTPS (Optionnel mais recommandé)

### Option 1 : Certificat auto-signé (dev/test)

**Créer le certificat :**
```powershell
# Installer OpenSSL (via Chocolatey)
choco install openssl -y

# Créer le dossier SSL
New-Item -Path "C:\nginx\ssl" -ItemType Directory

# Générer le certificat
cd C:\nginx\ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout nginx-selfsigned.key `
    -out nginx-selfsigned.crt `
    -subj "/C=CM/ST=Centre/L=Yaounde/O=Entreprise/CN=budget.entreprise.local"
```

**Modifier la configuration NGINX :**

`C:\nginx\conf\sites-enabled\gestion-budget.conf`

Ajouter un bloc server pour HTTPS :
```nginx
server {
    listen       443 ssl http2;
    server_name  192.168.1.100 budget.entreprise.local;

    # Certificats SSL
    ssl_certificate      C:/nginx/ssl/nginx-selfsigned.crt;
    ssl_certificate_key  C:/nginx/ssl/nginx-selfsigned.key;

    # Configuration SSL moderne
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    root C:/webapps/gestion-budget/public;
    index index.php index.html;

    # ... (reste identique au bloc HTTP)
}

# Redirection HTTP → HTTPS
server {
    listen       80;
    server_name  192.168.1.100 budget.entreprise.local;
    return 301 https://$server_name$request_uri;
}
```

**Redémarrer NGINX :**
```powershell
Restart-Service nginx
```

Accès : `https://192.168.1.100` (accepter le certificat auto-signé)

---

## 📊 PARTIE 10 : Maintenance et Monitoring

### Créer un utilisateur dédié (optionnel mais recommandé)
```powershell
# Créer un utilisateur de service
net user nginx_service "MotDePasse123!" /add
net localgroup "IIS_IUSRS" nginx_service /add

# Donner les permissions
icacls "C:\webapps\gestion-budget" /grant nginx_service:F /T
icacls "C:\nginx" /grant nginx_service:F /T
```

### Sauvegarder la base de données

**Créer** `C:\Scripts\backup-postgres.ps1` :
```powershell
$backupDir = "D:\Backups\PostgreSQL"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = "$backupDir\gestion_budget_$timestamp.backup"

if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir
}

$env:PGPASSWORD = "Budget@2026!"
& "C:\Program Files\PostgreSQL\14\bin\pg_dump.exe" `
    -U budget_user `
    -F c -b -v `
    -f $backupFile `
    gestion_budget

Get-ChildItem $backupDir -Filter "*.backup" | 
    Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-30)} |
    Remove-Item -Force

Write-Host "Sauvegarde terminée : $backupFile"
```

**Planifier avec le Planificateur de tâches :**
```powershell
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
    -Argument "-File C:\Scripts\backup-postgres.ps1"

$trigger = New-ScheduledTaskTrigger -Daily -At "02:00AM"

$principal = New-ScheduledTaskPrincipal `
    -UserId "NT AUTHORITY\SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

Register-ScheduledTask -TaskName "Backup PostgreSQL - Gestion Budget" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal
```

### Voir les logs
```powershell
# Logs NGINX
Get-Content C:\nginx\logs\gestion-budget-error.log -Tail 50 -Wait

# Logs Laravel
Get-Content C:\webapps\gestion-budget\storage\logs\laravel.log -Tail 50 -Wait

# Logs PHP
Get-Content C:\php\logs\php_errors.log -Tail 50 -Wait
```

### Redémarrer tous les services
```powershell
# Script de redémarrage complet
Restart-Service nginx
Restart-Service php-cgi
Restart-Service postgresql-x64-14

Write-Host "✅ Tous les services redémarrés"
```

---

## 🔧 Dépannage

### Erreur : "502 Bad Gateway"

**Cause :** PHP-CGI ne répond pas

**Solution :**
```powershell
# Vérifier que PHP-CGI tourne
Get-Service php-cgi

# Redémarrer
Restart-Service php-cgi

# Vérifier que le port 9000 est écouté
netstat -ano | findstr :9000
```

### Erreur : "403 Forbidden"

**Cause :** Problème de permissions

**Solution :**
```powershell
icacls "C:\webapps\gestion-budget" /grant Everyone:F /T
```

### Erreur : "Connection refused" PostgreSQL

**Cause :** PostgreSQL n'est pas démarré

**Solution :**
```powershell
# Vérifier le service
Get-Service postgresql*

# Démarrer
Start-Service postgresql-x64-14
```

### Erreur : Page blanche

**Cause :** Erreur PHP non affichée

**Solution :**
```powershell
# Activer le debug temporairement
# Dans .env : APP_DEBUG=true

# Voir les logs
Get-Content C:\webapps\gestion-budget\storage\logs\laravel.log -Tail 50
```

---

## 📈 Optimisation des performances

### Activer OPcache

**Dans** `C:\php\php.ini` :
```ini
[opcache]
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
```

**Redémarrer PHP-CGI :**
```powershell
Restart-Service php-cgi
```

### Augmenter les workers NGINX

**Dans** `C:\nginx\conf\nginx.conf` :
```nginx
worker_processes  4;  # Nombre de CPU
```

---

## ⚡ Checklist de déploiement
```
☐ Windows Server 2016/2019/2022
☐ NGINX téléchargé et extrait dans C:\nginx
☐ NGINX installé comme service Windows (NSSM)
☐ PHP 8.2/8.3 NTS téléchargé et extrait dans C:\php
☐ Extensions PHP activées (pdo_pgsql, pgsql, etc.)
☐ PHP-CGI installé comme service Windows
☐ PostgreSQL installé, base "gestion_budget" créée
☐ Composer installé
☐ Application copiée dans C:\webapps\gestion-budget
☐ composer install --no-scripts exécuté
☐ .env configuré
☐ php artisan migrate exécuté
☐ Permissions correctes (Everyone ou utilisateur dédié)
☐ Configuration NGINX créée (gestion-budget.conf)
☐ nginx -t OK
☐ Services NGINX et PHP-CGI démarrés
☐ Pare-feu configuré (port 80/443)
☐ SSL configuré (optionnel)
☐ Backup automatique configuré
☐ Accès testé depuis le serveur
☐ Accès testé depuis un autre PC
☐ Utilisateur admin créé
```

---

## 🎉 Félicitations !

Votre application **Gestion Budget** tourne maintenant sur **NGINX + Windows Server** !

### Avantages de cette configuration :

✅ **Simple** - Configuration en fichiers texte  
✅ **Performant** - NGINX est ultra-rapide  
✅ **Portable** - Config identique Linux/Windows  
✅ **Stable** - Services Windows avec auto-démarrage  
✅ **Professionnel** - Production-ready  
✅ **Évolutif** - Scalabilité facile  

---

## 📞 Support et ressources

- **Documentation NGINX** : http://nginx.org/en/docs/
- **Documentation PHP** : https://www.php.net/docs.php
- **Documentation Laravel** : https://laravel.com/docs

---

**Version** : 1.0 NGINX Windows Server  
**Date** : Janvier 2026  
**Auteur** : [Votre nom]  
**Email** : [Votre email]