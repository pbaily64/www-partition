#!/bin/bash
# install.sh - Script d'installation automatisé du système de gestion des partitions

echo "=========================================="
echo "Installation - Gestion des Partitions Catholiques"
echo "=========================================="
echo ""

# Variables de configuration
INSTALL_DIR="/data/html/www3"
WEB_USER="www-data"
WEB_GROUP="www-data"

# Vérifier que le script est exécuté en root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en tant que root (sudo)"
    exit 1
fi

echo "📁 Création de la structure de répertoires..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/uploads/partitions"

echo "📋 Copie des fichiers PHP..."
cp *.php "$INSTALL_DIR/"
cp .htaccess "$INSTALL_DIR/"

echo "🔐 Configuration des permissions..."
chown -R $WEB_USER:$WEB_GROUP "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/uploads"

echo "✅ Vérification des permissions d'écriture..."
if sudo -u $WEB_USER test -w "$INSTALL_DIR/uploads/partitions"; then
    echo "   ✓ Permissions d'écriture OK"
else
    echo "   ⚠ Attention : Problème de permissions détecté"
    echo "   Tentative de correction..."
    chmod -R 777 "$INSTALL_DIR/uploads"
fi

echo ""
echo "=========================================="
echo "📊 Installation de la base de données"
echo "=========================================="
echo ""
echo "Voulez-vous installer la base de données maintenant ? (o/n)"
read -r install_db

if [ "$install_db" = "o" ] || [ "$install_db" = "O" ]; then
    echo "Nom du conteneur MariaDB (par défaut: mariadb):"
    read -r db_container
    db_container=${db_container:-mariadb}
    
    echo "Importation du fichier database.sql..."
    docker exec -i "$db_container" mysql -u root -p < database.sql
    
    if [ $? -eq 0 ]; then
        echo "✅ Base de données installée avec succès"
    else
        echo "❌ Erreur lors de l'installation de la base de données"
    fi
fi

echo ""
echo "=========================================="
echo "⚙️ Configuration"
echo "=========================================="
echo ""
echo "⚠️  N'oubliez pas de modifier les paramètres dans $INSTALL_DIR/config.php :"
echo "   - DB_HOST (nom du conteneur MariaDB)"
echo "   - DB_USER (utilisateur MySQL)"
echo "   - DB_PASS (mot de passe MySQL)"
echo "   - SITE_URL (votre URL)"
echo ""

echo "=========================================="
echo "📝 Résumé de l'installation"
echo "=========================================="
echo "Répertoire d'installation : $INSTALL_DIR"
echo "Répertoire des uploads     : $INSTALL_DIR/uploads/partitions"
echo "Propriétaire              : $WEB_USER:$WEB_GROUP"
echo ""
echo "🔑 Identifiants par défaut :"
echo "   Utilisateur : admin"
echo "   Mot de passe : Admin123!"
echo ""
echo "⚠️  IMPORTANT : Changez le mot de passe admin dès la première connexion !"
echo ""

echo "✅ Installation terminée !"
echo ""
echo "Prochaines étapes :"
echo "1. Modifier $INSTALL_DIR/config.php avec vos paramètres"
echo "2. Configurer votre serveur web (Nginx/Apache)"
echo "3. Accéder à votre site et vous connecter"
echo ""
