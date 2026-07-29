# GUIDE D'INSTALLATION COMPLET
# Système de Gestion des Partitions Catholiques

## ═══════════════════════════════════════════════════════════
## ÉTAPE 1 : PRÉPARATION DE LA BASE DE DONNÉES
## ═══════════════════════════════════════════════════════════

# Option A : Importer depuis l'hôte
docker exec -i mariadb mysql -u root -p < database.sql

# Option B : Depuis le conteneur MySQL
docker exec -it mariadb mysql -u root -p
# Puis dans MySQL :
CREATE DATABASE IF NOT EXISTS partitions_catholiques CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE partitions_catholiques;
source /chemin/vers/database.sql;
exit;

## ═══════════════════════════════════════════════════════════
## ÉTAPE 2 : INSTALLATION DES FICHIERS
## ═══════════════════════════════════════════════════════════

# Méthode automatique (recommandée)
sudo chmod +x install.sh
sudo ./install.sh

# OU Méthode manuelle :

# 1. Créer la structure de répertoires
sudo mkdir -p /data/html/www3
sudo mkdir -p /data/html/www3/uploads/partitions

# 2. Copier tous les fichiers PHP
sudo cp *.php /data/html/www3/
sudo cp .htaccess /data/html/www3/

# 3. Définir les permissions
sudo chown -R www-data:www-data /data/html/www3
sudo chmod -R 755 /data/html/www3
sudo chmod -R 775 /data/html/www3/uploads

# 4. Vérifier les permissions d'écriture
sudo -u www-data touch /data/html/www3/uploads/partitions/test.txt
sudo rm /data/html/www3/uploads/partitions/test.txt

## ═══════════════════════════════════════════════════════════
## ÉTAPE 3 : CONFIGURATION DU FICHIER config.php
## ═══════════════════════════════════════════════════════════

# Éditer le fichier de configuration
sudo nano /data/html/www3/config.php

# Modifier ces lignes :
define('DB_HOST', 'mariadb');           # Nom de votre conteneur MariaDB
define('DB_NAME', 'partitions_catholiques');
define('DB_USER', 'root');              # Votre utilisateur MySQL
define('DB_PASS', 'VOTRE_MOT_DE_PASSE'); # Votre mot de passe MySQL
define('SITE_URL', 'https://www.famillebaily.be');

## ═══════════════════════════════════════════════════════════
## ÉTAPE 4 : CONFIGURATION DU SERVEUR WEB
## ═══════════════════════════════════════════════════════════

### Pour NGINX + PHP-FPM (via Docker) :

# 1. Créer le fichier de configuration Nginx
sudo nano /chemin/vers/nginx.conf
# (Copier le contenu du fichier nginx.conf fourni)

# 2. Vérifier la configuration Docker Compose
# S'assurer que les volumes sont montés :
#   volumes:
#     - /data/html/www3:/var/www/html/www3

# 3. Redémarrer les services
docker-compose restart nginx
docker-compose restart php

### Pour Apache (si utilisé à la place de Nginx) :

# 1. Activer les modules nécessaires
sudo a2enmod rewrite
sudo a2enmod headers

# 2. Créer un VirtualHost
sudo nano /etc/apache2/sites-available/partitions.conf

# Contenu du VirtualHost :
<VirtualHost *:80>
    ServerName partitions.famillebaily.be
    DocumentRoot /data/html/www3
    
    <Directory /data/html/www3>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/partitions_error.log
    CustomLog ${APACHE_LOG_DIR}/partitions_access.log combined
</VirtualHost>

# 3. Activer le site et redémarrer Apache
sudo a2ensite partitions.conf
sudo systemctl restart apache2

## ═══════════════════════════════════════════════════════════
## ÉTAPE 5 : CONFIGURATION TRAEFIK (si utilisé)
## ═══════════════════════════════════════════════════════════

# Ajouter ces labels au service nginx/apache dans docker-compose.yml :

labels:
  - "traefik.enable=true"
  - "traefik.http.routers.partitions.rule=Host(`partitions.famillebaily.be`)"
  - "traefik.http.routers.partitions.entrypoints=websecure"
  - "traefik.http.routers.partitions.tls.certresolver=myresolver"
  - "traefik.http.services.partitions.loadbalancer.server.port=80"

# Redémarrer le service
docker-compose up -d --force-recreate nginx

## ═══════════════════════════════════════════════════════════
## ÉTAPE 6 : VÉRIFICATIONS FINALES
## ═══════════════════════════════════════════════════════════

# 1. Vérifier la structure des fichiers
ls -la /data/html/www3/
ls -la /data/html/www3/uploads/partitions/

# 2. Vérifier les permissions
stat -c '%A %U:%G' /data/html/www3/
stat -c '%A %U:%G' /data/html/www3/uploads/partitions/

# 3. Tester la connexion PHP à la base de données
docker exec -it php php -r "new PDO('mysql:host=mariadb;dbname=partitions_catholiques', 'root', 'VOTRE_MOT_DE_PASSE');"

# 4. Vérifier les logs en cas de problème
docker logs nginx
docker logs php
tail -f /var/log/nginx/partitions_error.log

## ═══════════════════════════════════════════════════════════
## ÉTAPE 7 : PREMIÈRE CONNEXION
## ═══════════════════════════════════════════════════════════

# Accéder au site via votre navigateur :
# https://partitions.famillebaily.be

# Identifiants par défaut :
# Utilisateur : admin
# Mot de passe : Admin123!

# ⚠️ IMPORTANT : Changez immédiatement ce mot de passe !

## ═══════════════════════════════════════════════════════════
## COMMANDES DE MAINTENANCE
## ═══════════════════════════════════════════════════════════

### Sauvegarde de la base de données
docker exec mariadb mysqldump -u root -p partitions_catholiques > backup_$(date +%Y%m%d).sql

### Sauvegarde des fichiers PDF
sudo tar -czf partitions_backup_$(date +%Y%m%d).tar.gz /data/html/www3/uploads/partitions/

### Restauration de la base de données
docker exec -i mariadb mysql -u root -p partitions_catholiques < backup_YYYYMMDD.sql

### Vérifier l'espace disque utilisé
du -sh /data/html/www3/uploads/partitions/

### Nettoyer les anciennes partitions (avec précaution !)
# Liste les fichiers PDF non référencés dans la base
cd /data/html/www3/uploads/partitions/
ls -la

## ═══════════════════════════════════════════════════════════
## DÉPANNAGE
## ═══════════════════════════════════════════════════════════

### Problème : Impossible d'uploader des fichiers

# Vérifier les permissions
ls -la /data/html/www3/uploads/partitions/

# Corriger les permissions
sudo chown -R www-data:www-data /data/html/www3/uploads
sudo chmod -R 775 /data/html/www3/uploads

# Vérifier les limites PHP
docker exec php php -i | grep upload_max_filesize
docker exec php php -i | grep post_max_size

### Problème : Erreur 500

# Activer temporairement l'affichage des erreurs
sudo nano /data/html/www3/config.php
# Ajouter après <?php :
ini_set('display_errors', 1);
error_reporting(E_ALL);

# Consulter les logs
docker logs php
tail -f /var/log/nginx/partitions_error.log

### Problème : Connexion base de données refusée

# Vérifier que MariaDB est démarré
docker ps | grep mariadb

# Tester la connexion
docker exec -it mariadb mysql -u root -p -e "SHOW DATABASES;"

# Vérifier les identifiants dans config.php
cat /data/html/www3/config.php | grep DB_

### Problème : Page blanche

# Vérifier les logs PHP
docker logs php --tail 50

# Vérifier la syntaxe PHP
docker exec php php -l /var/www/html/www3/index.php

### Problème : PDF ne s'affichent pas

# Vérifier que le fichier existe
ls -la /data/html/www3/uploads/partitions/

# Vérifier les permissions de lecture
sudo chmod 644 /data/html/www3/uploads/partitions/*.pdf

# Vérifier la configuration Nginx pour les fichiers statiques
# (voir nginx.conf)

## ═══════════════════════════════════════════════════════════
## SÉCURITÉ - LISTE DE VÉRIFICATION
## ═══════════════════════════════════════════════════════════

☐ Mot de passe admin changé
☐ Mot de passe MySQL fort et unique
☐ HTTPS activé (via Traefik ou Let's Encrypt)
☐ Permissions fichiers correctes (755/644)
☐ Permissions uploads correctes (775)
☐ Sauvegardes automatiques configurées
☐ Logs surveillés régulièrement
☐ Utilisateurs test supprimés
☐ Firewall configuré (si applicable)
☐ Headers de sécurité activés

## ═══════════════════════════════════════════════════════════
## SUPPORT
## ═══════════════════════════════════════════════════════════

Pour toute question ou problème :
- Vérifier d'abord les logs : docker logs [container] --tail 100
- Consulter le fichier README.md
- Vérifier les permissions et la configuration

Fichiers importants :
- Configuration : /data/html/www3/config.php
- Logs Nginx : /var/log/nginx/partitions_error.log
- Logs PHP : docker logs php
- Uploads : /data/html/www3/uploads/partitions/
