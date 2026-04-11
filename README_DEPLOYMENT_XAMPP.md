# 📘 Guide de Déploiement - Application Gestion Budget
## Déploiement sur XAMPP (Réseau Local)

---

## 📋 Table des matières

1. [Prérequis](#prérequis)
2. [Installation de l'environnement](#installation-de-lenvironnement)
3. [Installation de l'application](#installation-de-lapplication)
4. [Configuration de la base de données](#configuration-de-la-base-de-données)
5. [Démarrage de l'application](#démarrage-de-lapplication)
6. [Accès depuis le réseau local](#accès-depuis-le-réseau-local)
7. [Configuration des utilisateurs](#configuration-des-utilisateurs)
8. [Dépannage](#dépannage)
9. [Maintenance](#maintenance)

---

## 🔧 Prérequis

### Logiciels requis

- **XAMPP** (version 8.2 ou supérieure)
  - Télécharger : https://www.apachefriends.org/fr/download.html
  - Inclut : Apache, PHP 8.2+, MySQL/MariaDB

- **PostgreSQL** (version 14 ou supérieure)
  - Télécharger : https://www.postgresql.org/download/
  - Port par défaut : 5432

- **Composer** (gestionnaire de dépendances PHP)
  - Télécharger : https://getcomposer.org/download/
  - Version 2.x recommandée

- **Node.js et NPM** (optionnel, pour assets frontend)
  - Télécharger : https://nodejs.org/
  - Version LTS recommandée

### Configuration système minimale

- **RAM** : 4 GB minimum (8 GB recommandé)
- **Disque** : 2 GB d'espace libre
- **OS** : Windows 10/11, Linux, macOS

---

## 📦 Installation de l'environnement

### 1. Installer XAMPP

1. Télécharger XAMPP depuis le site officiel
2. Lancer l'installateur
3. Sélectionner les composants :
   - ✅ Apache
   - ✅ PHP
   - ✅ MySQL (même si on utilisera PostgreSQL)
   - ✅ phpMyAdmin (optionnel)

4. Installer dans : `C:\xampp` (Windows) ou `/opt/lampp` (Linux)

5. Démarrer le **XAMPP Control Panel**

### 2. Installer PostgreSQL

1. Télécharger l'installateur PostgreSQL
2. Installer avec les paramètres :
   - Port : **5432**
   - Mot de passe superuser : **postgres** (à changer en production)
   - Locale : **French, Cameroon** ou **Default locale**

3. Installer **pgAdmin 4** (inclus dans l'installateur)

### 3. Vérifier PHP

Ouvrir un terminal/invite de commandes :
```bash
# Vérifier la version PHP
php -v
# Doit afficher : PHP 8.2.x ou supérieur

# Vérifier les extensions PHP
php -m | grep pdo_pgsql
php -m | grep pgsql
```

**Si les extensions PostgreSQL sont manquantes :**

**Windows :**
1. Ouvrir `C:\xampp\php\php.ini`
2. Décommenter (supprimer le `;`) :
```ini
   extension=pdo_pgsql
   extension=pgsql
```
3. Redémarrer Apache dans XAMPP

**Linux :**
```bash
sudo apt-get install php8.2-pgsql
sudo systemctl restart apache2
```

### 4. Installer Composer

**Windows :**
- Télécharger `Composer-Setup.exe`
- Exécuter l'installateur
- Sélectionner le PHP de XAMPP : `C:\xampp\php\php.exe`

**Linux/macOS :**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Vérifier :
```bash
composer --version
```

---

## 🚀 Installation de l'application

### 1. Copier les fichiers de l'application

**Option A : Depuis un dépôt Git**
```bash
cd C:\xampp\htdocs
git clone <URL_DU_DEPOT> gestion-budget
cd gestion-budget
```

**Option B : Depuis une archive ZIP**
1. Extraire le ZIP dans `C:\xampp\htdocs\gestion-budget`
2. Ouvrir un terminal dans ce dossier

### 2. Installer les dépendances
```bash
# Dépendances PHP
composer install --optimize-autoloader --no-dev

# Dépendances Node.js (optionnel)
npm install
npm run build
```

### 3. Configuration de l'environnement
```bash
# Copier le fichier d'exemple
copy .env.example .env

# Générer la clé d'application
php artisan key:generate
```

---

## 🗄️ Configuration de la base de données

### 1. Créer la base de données PostgreSQL

**Via pgAdmin :**
1. Ouvrir **pgAdmin 4**
2. Se connecter au serveur PostgreSQL
3. Clic droit sur **Databases** → **Create** → **Database**
4. Nom : `gestion_budget`
5. Owner : `postgres`
6. Encoding : `UTF8`
7. Cliquer **Save**

**Via terminal :**
```bash
# Se connecter à PostgreSQL
psql -U postgres

# Créer la base de données
CREATE DATABASE gestion_budget;

# Quitter
\q
```

### 2. Configurer le fichier .env

Ouvrir `C:\xampp\htdocs\gestion-budget\.env` et modifier :
```ini
APP_NAME="Gestion Budget"
APP_ENV=local
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_DEBUG=true
APP_URL=http://192.168.1.100  # IP de votre serveur

LOG_CHANNEL=stack
LOG_LEVEL=debug

# Base de données PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gestion_budget
DB_USERNAME=postgres
DB_PASSWORD=postgres

# Mail (optionnel)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@budget.local"
MAIL_FROM_NAME="${APP_NAME}"

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

### 3. Configurer Apache (XAMPP)

**Éditer le fichier httpd.conf :**

**Windows :**
```bash
notepad C:\xampp\apache\conf\httpd.conf
```

**Linux :**
```bash
sudo nano /opt/lampp/etc/httpd.conf
```

**Trouver la ligne `Listen 80` et ajouter après :**
```apache
# Autoriser l'accès depuis le réseau local
<Directory "C:/xampp/htdocs/gestion-budget/public">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**Créer un Virtual Host (optionnel mais recommandé) :**

Éditer : `C:\xampp\apache\conf\extra\httpd-vhost.conf`

Ajouter à la fin :
```apache
<VirtualHost *:80>
    ServerName budget.local
    ServerAlias 192.168.1.100  # Votre IP locale
    DocumentRoot "C:/xampp/htdocs/gestion-budget/public"
    
    <Directory "C:/xampp/htdocs/gestion-budget/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "logs/budget-error.log"
    CustomLog "logs/budget-access.log" common
</VirtualHost>
```

**Redémarrer Apache dans XAMPP Control Panel**

---

## 🎯 Démarrage de l'application

### 1. Exécuter les migrations
```bash
cd C:\xampp\htdocs\gestion-budget

# Migrer la base de données
php artisan migrate

# Confirmer avec "yes" si demandé
```

### 2. Créer les exercices budgétaires
```bash
php artisan db:seed --class=ExerciceSeeder
```

### 3. Créer un utilisateur administrateur
```bash
php artisan tinker
```

Dans Tinker :
```php
$user = new App\Models\User();
$user->name = 'Administrateur';
$user->email = 'admin@budget.local';
$user->password = bcrypt('Admin@2026');
$user->save();

// Assigner le rôle super_admin (si Spatie Roles installé)
$user->assignRole('super_admin');

echo "✅ Utilisateur créé : admin@budget.local / Admin@2026\n";
exit
```

### 4. Optimiser l'application
```bash
# Mettre en cache la configuration
php artisan config:cache

# Mettre en cache les routes
php artisan route:cache

# Mettre en cache les vues
php artisan view:cache

# Créer le lien symbolique pour le stockage
php artisan storage:link
```

---

## 🌐 Accès depuis le réseau local

### 1. Trouver l'adresse IP du serveur

**Windows :**
```bash
ipconfig
```
Chercher : `Adresse IPv4` → Exemple : `192.168.1.100`

**Linux :**
```bash
ip addr show
# ou
ifconfig
```

### 2. Configurer le pare-feu

**Windows :**
1. Ouvrir **Pare-feu Windows Defender**
2. **Règles de trafic entrant** → **Nouvelle règle**
3. Type : **Port**
4. Protocole : **TCP**
5. Port : **80** (et **443** si HTTPS)
6. Action : **Autoriser la connexion**
7. Profils : Cocher **Domaine**, **Privé**, **Public**
8. Nom : `XAMPP Apache`

**Linux :**
```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw reload
```

### 3. Tester l'accès

**Depuis le serveur :**
```
http://localhost/gestion-budget/public
# ou
http://budget.local
```

**Depuis un autre ordinateur du réseau :**
```
http://192.168.1.100/gestion-budget/public
# ou
http://192.168.1.100  (si Virtual Host configuré)
```

### 4. URL propre (sans /public)

**Créer un fichier .htaccess à la racine :**

`C:\xampp\htdocs\gestion-budget\.htaccess`
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Maintenant accessible via : `http://192.168.1.100`

---

## 👥 Configuration des utilisateurs

### Créer des utilisateurs avec rôles
```bash
php artisan tinker
```
```php
// Fonction helper pour créer un utilisateur
function createUser($name, $email, $password, $role) {
    $user = App\Models\User::create([
        'name' => $name,
        'email' => $email,
        'password' => bcrypt($password),
    ]);
    
    if (\Spatie\Permission\Models\Role::where('name', $role)->exists()) {
        $user->assignRole($role);
    }
    
    return $user;
}

// Super Admin
createUser('Admin Principal', 'admin@budget.local', 'Admin@2026', 'super_admin');

// Chef Service Budget
createUser('Chef Service', 'chef@budget.local', 'Chef@2026', 'chef_service_budget');

// Opérateur Budget
createUser('Opérateur', 'operateur@budget.local', 'Oper@2026', 'operateur_budget');

// Directeur Général
createUser('Directeur', 'directeur@budget.local', 'Dir@2026', 'directeur_general');

echo "✅ Utilisateurs créés avec succès\n";

exit
```

---

## 🔧 Dépannage

### Problème : Page blanche

**Solution :**
```bash
# Vérifier les logs
tail -f storage/logs/laravel.log

# Activer le mode debug
# Dans .env : APP_DEBUG=true

# Nettoyer le cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Problème : Erreur 500

**Vérifications :**
1. Permissions des dossiers :
```bash
# Windows (exécuter en tant qu'administrateur)
icacls storage /grant Users:F /T
icacls bootstrap\cache /grant Users:F /T

# Linux
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

2. Vérifier `.env` :
   - APP_KEY est bien généré
   - Identifiants DB corrects

### Problème : Connexion base de données refusée

**Solution :**
```bash
# Vérifier que PostgreSQL est démarré
# Windows : Services → PostgreSQL

# Tester la connexion
psql -U postgres -h localhost

# Vérifier pg_hba.conf
# Windows : C:\Program Files\PostgreSQL\14\data\pg_hba.conf
# Ligne : host all all 127.0.0.1/32 md5
```

### Problème : Extensions PHP manquantes
```bash
# Vérifier les extensions
php -m

# Extensions requises :
# - pdo_pgsql, pgsql
# - mbstring, xml, curl, zip
# - gd, fileinfo, tokenizer

# Éditer php.ini et décommenter les extensions
notepad C:\xampp\php\php.ini
```

### Problème : "Class not found"
```bash
# Régénérer l'autoload
composer dump-autoload

# Nettoyer
php artisan optimize:clear
```

---

## 🔄 Maintenance

### Sauvegarder la base de données
```bash
# Depuis le serveur
pg_dump -U postgres gestion_budget > backup_$(date +%Y%m%d).sql

# Restaurer
psql -U postgres gestion_budget < backup_20260106.sql
```

### Mettre à jour l'application
```bash
cd C:\xampp\htdocs\gestion-budget

# Sauvegarder d'abord !

# Mettre à jour le code
git pull origin main

# Mettre à jour les dépendances
composer install --optimize-autoloader --no-dev

# Exécuter les migrations
php artisan migrate --force

# Nettoyer et optimiser
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Surveiller les logs
```bash
# Logs Laravel
tail -f storage/logs/laravel.log

# Logs Apache
tail -f C:\xampp\apache\logs\error.log

# Logs PostgreSQL
# Windows : C:\Program Files\PostgreSQL\14\data\log\
```

### Optimiser les performances
```bash
# Activer le mode production
# Dans .env : APP_DEBUG=false

# Optimiser Composer
composer install --optimize-autoloader --no-dev --classmap-authoritative

# Mettre en cache tout
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimiser la base de données
php artisan db:vacuum  # Si la commande existe
```

---

## 📞 Support

### Contacts

- **Développeur** : [Votre nom]
- **Email** : [Votre email]
- **Documentation** : http://192.168.1.100/docs

### Ressources

- **Laravel** : https://laravel.com/docs
- **Filament** : https://filamentphp.com/docs
- **PostgreSQL** : https://www.postgresql.org/docs/

---

## 📝 Checklist de déploiement
```
☐ XAMPP installé et Apache démarré
☐ PostgreSQL installé et démarré
☐ Extensions PHP activées (pdo_pgsql, pgsql)
☐ Composer installé
☐ Code source copié dans htdocs
☐ composer install exécuté
☐ .env configuré avec bons identifiants
☐ Base de données créée
☐ php artisan migrate exécuté
☐ Exercices créés (seeder)
☐ Utilisateur admin créé
☐ Pare-feu configuré
☐ Accès réseau testé depuis un autre PC
☐ Logs vérifiés (aucune erreur)
☐ Sauvegarde configurée
```

---

## 🎉 Félicitations !

Votre application **Gestion Budget** est maintenant déployée et accessible sur votre réseau local !

**URL d'accès** : `http://192.168.1.100`

**Connexion admin** :
- Email : `admin@budget.local`
- Mot de passe : `Admin@2026`

---

**Version** : 1.0.0  
**Date** : Janvier 2026  
**Auteur** : [Votre nom]