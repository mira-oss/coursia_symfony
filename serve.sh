#!/bin/bash

# Démarre le serveur Symfony accessible sur le réseau local (0.0.0.0)
# Utilisation : ./serve.sh

PORT=8000
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=================================="
echo "  Coursia Backend - Démarrage"
echo "=================================="

# Charger les variables d'environnement
if [ -f "$PROJECT_DIR/.env.local" ]; then
    export $(grep -v '^#' "$PROJECT_DIR/.env.local" | xargs) 2>/dev/null
fi

# Arrêter tout serveur existant sur le port
echo "⏹  Arrêt du serveur existant..."
symfony server:stop 2>/dev/null || true
fuser -k ${PORT}/tcp 2>/dev/null || true

sleep 1

# Afficher l'IP locale
LOCAL_IP=$(hostname -I | awk '{print $1}')
echo ""
echo "✅ Serveur démarré sur :"
echo "   - Local    : http://127.0.0.1:${PORT}"
echo "   - Mobile   : http://${LOCAL_IP}:${PORT}"
echo ""
echo "📱 Dans Flutter (api_config.dart) :"
echo "   static const String baseUrl = 'http://${LOCAL_IP}:${PORT}/api';"
echo ""
echo "Appuie sur Ctrl+C pour arrêter"
echo "=================================="

# Démarrer Symfony sur toutes les interfaces
cd "$PROJECT_DIR"
symfony server:start --port=${PORT} --no-tls --allow-all-ip
