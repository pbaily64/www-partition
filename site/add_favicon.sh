#!/bin/bash

# Script pour ajouter la favicon à tous les fichiers PHP
# ========================================================

echo "╔══════════════════════════════════════════════════════╗"
echo "║     Ajout de la favicon à tous les fichiers PHP     ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# Code HTML de la favicon à insérer
read -r -d '' FAVICON_CODE << 'EOF'
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="favicon-256x256.png">
    <link rel="manifest" href="site.webmanifest">
    <meta name="theme-color" content="#667eea">
EOF

# Liste des fichiers PHP à modifier
FILES=(
    "login.php"
    "liste.php"
    "ajouter_chant.php"
    "ajouter_partitions_masse.php"
    "admin_chants.php"
    "admin_utilisateurs.php"
    "profil.php"
    "voir_partition.php"
)

# Fonction pour ajouter la favicon à un fichier
add_favicon_to_file() {
    local file=$1
    local source_dir="/mnt/project"
    local output_dir="/home/claude"
    
    if [ ! -f "${source_dir}/${file}" ]; then
        echo "✗ ${file} non trouvé"
        return 1
    fi
    
    # Vérifier si la favicon n'est pas déjà présente
    if grep -q "favicon.ico" "${source_dir}/${file}"; then
        echo "⊘ ${file} - favicon déjà présente"
        return 0
    fi
    
    # Créer une copie de travail
    cp "${source_dir}/${file}" "${output_dir}/${file}.tmp"
    
    # Insérer le code favicon après la balise <title>
    python3 << PYEOF
import re

with open('${output_dir}/${file}.tmp', 'r', encoding='utf-8') as f:
    content = f.read()

# Chercher la ligne après </title>
favicon_code = '''${FAVICON_CODE}'''

# Pattern pour trouver </title> et insérer après
pattern = r'(</title>\s*\n)'
replacement = r'\1' + favicon_code + '\n'

new_content = re.sub(pattern, replacement, content, count=1)

with open('${output_dir}/${file}', 'w', encoding='utf-8') as f:
    f.write(new_content)

print(f"✓ {file}")
PYEOF
    
    # Nettoyer le fichier temporaire
    rm -f "${output_dir}/${file}.tmp"
}

# Traiter chaque fichier
echo "Traitement des fichiers..."
echo ""

for file in "${FILES[@]}"; do
    add_favicon_to_file "$file"
done

echo ""
echo "═══════════════════════════════════════════════════════"
echo "Traitement terminé !"
echo ""
echo "Les fichiers mis à jour sont dans /home/claude/"
echo "Pensez à copier aussi les fichiers favicon :"
echo "  - favicon.ico"
echo "  - favicon.svg"
echo "  - favicon-*.png"
echo "  - site.webmanifest"
echo "═══════════════════════════════════════════════════════"
