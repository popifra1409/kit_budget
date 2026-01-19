
# Installation de LARAGON
1- Exécutez l'installateur laragon.exe
2- Choisissez le chemin d'installation : C:\laragon (évitez C:\xampp)
3- Décochez "Start Laragon after installation" (nous allons d'abord configurer)

# Arrêter les services XAMPP temporairement
Via le Panneau de Contrôle XAMPP, arrêtez :
- Apache
- MySQL

(Nous les redémarrerons après configuration)

# Configuration de laragon
Service	XAMPP	Laragon	Statut
HTTP	Port 80	    Port 8082	✅ Pas de conflit
HTTPS	Port 443	Port 8443	✅ Pas de conflit
MySQL	Port 3306	Port 3307	✅ Pas de conflit
phpMyAdmin	Port 80	Port 8082	✅ Différents chemins

## Configuration des Ports de Laragon
Ouvrez C:\laragon\etc\nginx\nginx.conf et changez :
    # Changer la ligne :
    listen       80;

    # En :
    listen       8082;  # Port alternatif non utilisé

## Configurer MySQL sur un port différent
Ouvrez C:\laragon\bin\mysql\mysql-8.0.xx\my.ini et changez :
[client]
port=3307  # Au lieu de 3306 (utilisé par XAMPP)

[mysqld]
port=3307  # Au lieu de 3306

# Modifier la configuration Laragon
Ouvrez C:\laragon\laragon.ini et changez :
[nginx]
port=8082  # Port HTTP
ssl_port=8443  # Port HTTPS

[mysql]
port=3307

# Configurer les ports dans le .env de Laravel
Dans votre fichier C:\laragon\www\kit_budget\.env :

APP_NAME="Kit Budget"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://kit_budget.test:8082

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432 
DB_DATABASE=kit_budget
DB_USERNAME=postgres
DB_PASSWORD=kisinit2025

# Autres configurations...
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Déploiement de l'Application
## Copier l'application
Copiez votre projet Laravel dans :
C:\laragon\www\kit_budget

OU via PowerShell (administrateur) :
Copy-Item "C:\xampp\htdocs\kit_budget" "C:\laragon\www\kit_budget" -Recurse

## Configurer le nom de domaine local
Ouvrez le fichier hosts Windows en tant qu'administrateur :

Chemin : C:\Windows\System32\drivers\etc\hosts
