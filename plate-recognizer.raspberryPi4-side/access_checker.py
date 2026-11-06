"""
Client d'accès PARKit (Raspberry Pi).

Remplace l'ancienne version qui interrogeait MySQL directement :
la Pi envoie maintenant la plaque au serveur web (api/access_checker.php),
qui décide et met à jour la réservation (confirmed -> checked_in).

Configuration (variables d'environnement ou fichier .env) :
    PARKIT_SERVER_IP    IP du serveur sur le réseau local, ex: 192.168.1.50
    PARKIT_SERVER_PORT  port HTTP du serveur web (défaut : 80)
    PARKIT_API_PATH     chemin de l'endpoint (défaut : /parknova-frontend/api/access_checker.php)
    PARKIT_API_SCHEME   http ou https (défaut : http)
    PARKIT_API_KEY      la même valeur que API_KEY dans config.php
    PARKIT_API_TIMEOUT  secondes (défaut : 3)

Dépendances : pip install requests python-dotenv
"""
import os

import requests

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:  # python-dotenv est optionnel
    pass

SERVER_IP = os.getenv("PARKIT_SERVER_IP", "").strip()
SERVER_PORT = os.getenv("PARKIT_SERVER_PORT", "80").strip()
API_PATH = os.getenv("PARKIT_API_PATH", "/parknova-frontend/api/access_checker.php").strip()
API_SCHEME = os.getenv("PARKIT_API_SCHEME", "http").strip()
API_URL = f"{API_SCHEME}://{SERVER_IP}:{SERVER_PORT}{API_PATH}" if SERVER_IP else ""
API_KEY = os.getenv("PARKIT_API_KEY", "")
TIMEOUT_S = float(os.getenv("PARKIT_API_TIMEOUT", "3"))
MAX_ATTEMPTS = 2  

# Session réutilisée : garde la connexion ouverte, donc moins de latence par requête
_session = requests.Session()
_session.headers.update({
    "X-API-KEY": API_KEY,
    "Content-Type": "application/json",
})


def check_access_decision(plate_left, plate_right, plate_format="STD"):
    """
    Demande au serveur si la plaque a une réservation valide en ce moment.

    Retourne (granted: bool, message: str).
    En cas d'erreur (réseau, clé invalide, réponse illisible),
    l'accès est REFUSÉ : la barrière ne s'ouvre jamais « par défaut ».
    """
    if not SERVER_IP:
        return False, "Erreur config : PARKIT_SERVER_IP manquante"
    if not API_KEY:
        return False, "Erreur config : PARKIT_API_KEY manquante"

    payload = {
        "plate_left": str(plate_left or ""),
        "plate_right": str(plate_right or ""),
        "plate_format": plate_format or "STD",
    }

    last_error = None
    for _ in range(MAX_ATTEMPTS):
        try:
            resp = _session.post(API_URL, json=payload, timeout=TIMEOUT_S)
        except (requests.ConnectionError, requests.Timeout) as e:
            last_error = f"Serveur injoignable ({type(e).__name__})"
            continue
        except requests.RequestException as e:
            return False, f"Erreur requête : {e}"

        if resp.status_code == 401:
            return False, "Erreur : clé API refusée par le serveur"

        try:
            data = resp.json()
        except ValueError:
            return False, f"Réponse invalide du serveur (HTTP {resp.status_code})"

        granted = data.get("access") is True  # strictement True, rien d'autre
        message = data.get("message", "Pas de message")
        return granted, message

    return False, f"ACCÈS REFUSÉ | {last_error}"


if __name__ == "__main__":
    # Test rapide : python access_checker.py 247 7965 STD
    import sys

    left = sys.argv[1] if len(sys.argv) > 1 else "247"
    right = sys.argv[2] if len(sys.argv) > 2 else "7965"
    fmt = sys.argv[3] if len(sys.argv) > 3 else "STD"
    print(f"Serveur : {API_URL or '(non configuré)'}")
    print(check_access_decision(left, right, fmt))