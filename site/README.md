# Système de Gestion des Partitions Catholiques

## Description
Application web de gestion de partitions catholiques avec authentification utilisateur, gestion de références alphanumériques et upload de fichiers PDF.

## Fonctionnalités
- Authentification sécurisée des utilisateurs
- Gestion des chants avec références alphanumériques
- Upload et visualisation de partitions PDF
- Recherche et tri des chants
- Interface d'administration complète
- Gestion des utilisateurs (admin)
- Gestion des chants (admin)

## Structure des fichiers
```
/data/html/www3/
├── config.php                 # Configuration et connexion BDD
├── index.php                  # Page d'accueil (redirection)
├── login.php                  # Page de connexion
├── logout.php                 # Déconnexion
├── liste.php                  # Liste des chants avec recherche/tri
├── ajouter_chant.php          # Ajout d'un chant
├── voir_partition.php         # Visualisation PDF
├── telecharger.php            # Téléchargement PDF
├── admin_utilisateurs.php     # Gestion des utilisateurs (admin)
├── admin_chants.php           # Gestion des chants (admin)
├── .htaccess                  # Configuration Apache
├── uploads/
│   └── partitions/            # Stockage des PDF
└── database.sql               # Script de création de la BDD
```

## Installation

### 1. Préparation de la base de données

Connectez-vous à votre conteneur MariaDB :
```bash
docker exec -it mariadb mysql -u root -p
```

Importez le fichier SQL :
```bash
docker exec -i mariadb mysql -u root -p < database.sql
```

Ou depuis le shell MySQL :
```sql
source /chemin/vers/database.sql
```

### 2. Configuration du site

Modifiez le fichier `config.php` avec vos paramètres :
```php
define('DB_HOST', 'mariadb');  // Nom de votre conteneur MariaDB
define('DB_NAME', 'partitions_catholiques');
define('DB_USER', 'root');     // Votre utilisateur MySQL
define('DB_PASS', 'votre_mot_de_passe');
define('SITE_URL', 'https://www.famillebaily.be');  // Votre URL
```

### 3. Création des répertoires et permissions

```bash
# Créer le répertoire du site
sudo mkdir -p /data/html/www3

# Copier tous les fichiers PHP dans /data/html/www3
sudo cp *.php /data/html/www3/
sudo cp .htaccess /data/html/www3/

# Créer le répertoire d'upload
sudo mkdir -p /data/html/www3/uploads/partitions

# Définir les permissions appropriées
sudo chown -R www-data:www-data /data/html/www3
sudo chmod -R 755 /data/html/www3
sudo chmod -R 775 /data/html/www3/uploads
```

### 4. Configuration Docker (si nécessaire)

Si vous utilisez docker-compose, assurez-vous que votre configuration monte le volume :

```yaml
services:
  nginx:
    volumes:
      - /data/html/www3:/var/www/html/www3
```

Pour PHP-FPM, vérifiez que le volume est également monté :
```yaml
  php:
    volumes:
      - /data/html/www3:/var/www/html/www3
```

### 5. Configuration Nginx/Apache

#### Pour Nginx :
```nginx
server {
    listen 80;
    server_name partitions.famillebaily.be;
    root /var/www/html/www3;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Augmenter la taille max d'upload pour les PDFs
    client_max_body_size 10M;
}
```

#### Pour Apache (via .htaccess déjà fourni) :
Assurez-vous que mod_rewrite est activé :
```bash
a2enmod rewrite
a2enmod headers
systemctl restart apache2
```

### 6. Configuration Traefik (si utilisé)

Ajoutez les labels à votre service Nginx/Apache :
```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.partitions.rule=Host(`partitions.famillebaily.be`)"
  - "traefik.http.routers.partitions.entrypoints=websecure"
  - "traefik.http.routers.partitions.tls.certresolver=myresolver"
```

### 7. Vérification des permissions finales

```bash
# Vérifier que www-data peut écrire dans uploads
sudo -u www-data touch /data/html/www3/uploads/partitions/test.txt
sudo rm /data/html/www3/uploads/partitions/test.txt

# Si erreur, corriger les permissions :
sudo chown -R www-data:www-data /data/html/www3/uploads
sudo chmod -R 775 /data/html/www3/uploads
```

## Première connexion

**Identifiants par défaut :**
- Utilisateur : `admin`
- Mot de passe : `Admin123!`

⚠️ **IMPORTANT** : Changez immédiatement ce mot de passe après la première connexion !

## Utilisation

### Pour les utilisateurs
1. Se connecter avec ses identifiants
2. Consulter la liste des chants
3. Rechercher et filtrer les chants
4. Ajouter de nouveaux chants
5. Visualiser/télécharger les partitions PDF

### Pour les administrateurs
En plus des fonctions utilisateur :
1. Créer/désactiver/supprimer des utilisateurs
2. Modifier tous les chants existants
3. Supprimer des chants
4. Gérer les partitions

## Sécurité

- Tous les mots de passe sont hashés avec password_hash()
- Protection CSRF via sessions
- Validation des types de fichiers uploadés
- Limitation de taille des uploads (10 MB)
- Headers de sécurité configurés
- Échappement HTML automatique

## Dépannage

### Erreur de connexion à la base de données
Vérifiez que :
- Le conteneur MariaDB est démarré
- Les identifiants dans config.php sont corrects
- Le nom du service Docker est correct

### Impossible d'uploader des fichiers
```bash
# Vérifier les permissions
ls -la /data/html/www3/uploads/partitions

# Corriger si nécessaire
sudo chown -R www-data:www-data /data/html/www3/uploads
sudo chmod -R 775 /data/html/www3/uploads
```

### Erreur 500
```bash
# Activer les erreurs temporairement dans config.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

# Consulter les logs
tail -f /var/log/nginx/error.log
# ou
tail -f /var/log/apache2/error.log
```

## Maintenance

### Sauvegarde de la base de données
```bash
docker exec mariadb mysqldump -u root -p partitions_catholiques > backup_$(date +%Y%m%d).sql
```

### Sauvegarde des fichiers PDF
```bash
sudo tar -czf partitions_backup_$(date +%Y%m%d).tar.gz /data/html/www3/uploads/partitions/
```

## Support
Pour toute question ou problème, contactez l'administrateur système.
