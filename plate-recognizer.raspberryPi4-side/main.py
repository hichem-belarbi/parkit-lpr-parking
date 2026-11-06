
import os
import re
import signal
import time

import cv2

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:  # python-dotenv est optionnel
    pass

from gpiozero import AngularServo, DigitalInputDevice

from recognizer_v6_ncnn_fastocr import TunisianLPR
from access_checker import check_access_decision


# ----------------------------------------------------------------------
# Configuration
# ----------------------------------------------------------------------
def _env_int(name, default):
    return int(os.getenv(name, default))


def _env_float(name, default):
    return float(os.getenv(name, default))


def _env_bool(name, default):
    value = os.getenv(name)
    if value is None:
        return default
    return value.strip().lower() in ("1", "true", "yes", "on", "oui")


# Broches GPIO (numérotation BCM) : à adapter à ton câblage
IR1_PIN = _env_int("PARKIT_IR1_PIN", 17)       # déclencheur de capture
IR2_PIN = _env_int("PARKIT_IR2_PIN", 27)       # capteur de présence (barrière)
SERVO_PIN = _env_int("PARKIT_SERVO_PIN", 18)   # servomoteur de la barrière

# Capteurs IR : la plupart des modules « obstacle » passent à LOW quand ils détectent
IR_ACTIVE_LOW = _env_bool("PARKIT_IR_ACTIVE_LOW", True)
DEBOUNCE_S = _env_float("PARKIT_DEBOUNCE_S", 0.05)
POLL_INTERVAL_S = 0.02

# Servomoteur
CLOSED_ANGLE = _env_float("PARKIT_SERVO_CLOSED_ANGLE", 0)
OPEN_ANGLE = _env_float("PARKIT_SERVO_OPEN_ANGLE", 90)
SERVO_MIN_PULSE = _env_float("PARKIT_SERVO_MIN_PULSE", 0.0005)   # secondes
SERVO_MAX_PULSE = _env_float("PARKIT_SERVO_MAX_PULSE", 0.0025)   # secondes
SERVO_MOVE_TIME_S = _env_float("PARKIT_SERVO_MOVE_TIME", 0.6)
SERVO_DETACH = _env_bool("PARKIT_SERVO_DETACH", True)  # coupe le signal après le mouvement (évite les vibrations)

# Barrière
WAIT_IR2_TIMEOUT_S = _env_float("PARKIT_WAIT_IR2_TIMEOUT", 30)   # attente max du véhicule à la barrière
CLOSE_DELAY_S = _env_float("PARKIT_CLOSE_DELAY", 2)              # IR2 libre depuis X s -> on referme

# Caméra / capture
BURST_FRAMES = _env_int("PARKIT_BURST_FRAMES", 1)
BURST_DURATION_S = 0.002
FLUSH_FRAMES = _env_int("PARKIT_FLUSH_FRAMES", 2)  # vide le tampon pour ne pas lire une image périmée
RESULT_PATH = "result.jpg"

_running = True


def _stop(signum, frame):
    global _running
    _running = False


# ----------------------------------------------------------------------
# Capture
# ----------------------------------------------------------------------
def sharpness_score(frame):
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    return cv2.Laplacian(gray, cv2.CV_64F).var()


def capture_burst(cap, n=BURST_FRAMES, duration=BURST_DURATION_S):
    # Jette les images en attente dans le tampon : on veut l'image d'ARRIVÉE du véhicule
    for _ in range(FLUSH_FRAMES):
        cap.grab()

    interval = duration / n
    frames = []
    for _ in range(n):
        ret, frame = cap.read()
        if ret:
            frames.append(frame)
        if n > 1:  # Évite le sleep si n=1 pour une capture instantanée
            time.sleep(interval)
    return frames


def pick_best_frame(frames):
    return max(frames, key=sharpness_score)


def open_camera():
    # Sur Raspberry Pi (Linux) : V4L2, puis backend automatique
    for index in [0, 1, 2]:
        for backend in [cv2.CAP_V4L2, cv2.CAP_ANY]:
            try:
                cap = cv2.VideoCapture(index, backend)
                if cap.isOpened():
                    ret, frame = cap.read()
                    if ret and frame is not None:
                        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                        print(f"Caméra ouverte sur l'index {index} avec backend {backend}")
                        return cap
                cap.release()
            except Exception:
                continue
    return None


# ----------------------------------------------------------------------
# Extraction de la plaque
# ----------------------------------------------------------------------
def extract_tunisian_plate(ocr_text):
    """
    Extrait la plaque selon les différents formats tunisiens :
    - RS (Régime Suspensif) : "RS 123456"
    - Série Normale (STD)   : "247 تونس 7965"
    - Gouvernement (GOV)    : "12 - 3456"
    """
    text_upper = ocr_text.upper().strip()

    # 1. Format RS (Régime Suspensif)
    rs_match = re.search(r'\bRS\s*(\d{1,6})\b', text_upper) or re.search(r'\bن.ت(\d{1,6})\b', text_upper)

    if rs_match:
        return 'RS', 'RS', rs_match.group(1)

    # Nettoyage des symboles
    cleaned = re.sub(r'[^\w\s\u0600-\u06FF]', '', ocr_text)
    numbers = re.findall(r'\d+', cleaned)

    if len(numbers) >= 2:
        num1, num2 = numbers[0], numbers[1]

        # 2. Format Gouvernement (ex: 2 chiffres / 4 chiffres sans "تونس")
        if "تونس" not in cleaned and len(num1) <= 2 and len(num2) >= 3:
            return 'GOV', num1, num2

        # 3. Format Série Normale (STD)
        if len(num1) <= 3 and len(num2) > 3:
            plate_left, plate_right = num1, num2
        elif len(num2) <= 3 and len(num1) > 3:
            plate_left, plate_right = num2, num1
        else:
            plate_left, plate_right = num1, num2

        return 'STD', plate_left, plate_right

    return None, None, None


def run_and_report(frame, lpr):
    """Détecte, lit et vérifie la plaque. Retourne (résultats, accès_accordé)."""
    access_granted_any = False
    results = lpr.process_frame(frame)

    if not results:
        print("[LPR] Aucune plaque détectée.")
        return results, False

    for r in results:
        raw_text = r['text']
        print(f"\n[OCR Brut] text='{raw_text}' (Confiance Détection: {r['det_conf']:.2f})")

        # Extraction multi-format
        plate_format, plate_left, plate_right = extract_tunisian_plate(raw_text)

        if plate_right:
            if plate_format == 'RS':
                formatted_plate = f"RS {plate_right}"
            elif plate_format == 'GOV':
                formatted_plate = f"{plate_left} - {plate_right}"
            else:
                formatted_plate = f"{plate_left} تونس {plate_right}"

            print(f"[Plaque Extraite] {formatted_plate} (Format: {plate_format})")

            # Vérification d'accès auprès du serveur
            access_granted, decision_msg = check_access_decision(plate_left, plate_right, plate_format)

            if access_granted:
                print(f"🟢 {decision_msg}")
                access_granted_any = True
            else:
                print(f"🔴 {decision_msg}")

            status_text = f"{formatted_plate} - {'OK' if access_granted else 'DENIED'}"
            color = (0, 255, 0) if access_granted else (0, 0, 255)
        else:
            print("⚠️ Impossible d'isoler le format de plaque Tunisienne.")
            status_text = "UNVERIFIED PLATE"
            color = (0, 165, 255)

        # Annotation visuelle
        x1, y1, x2, y2 = r['bbox']
        cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
        cv2.putText(frame, status_text, (x1, max(0, y1 - 10)),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)

    cv2.imwrite(RESULT_PATH, frame)
    print(f"Capture annotée sauvegardée dans '{RESULT_PATH}'")
    return results, access_granted_any


# ----------------------------------------------------------------------
# Matériel : capteurs IR et servomoteur
# ----------------------------------------------------------------------
def setup_hardware():
    active_state = not IR_ACTIVE_LOW  # active_state=False -> « actif » quand la broche est à LOW
    ir1 = DigitalInputDevice(IR1_PIN, pull_up=None, active_state=active_state)
    ir2 = DigitalInputDevice(IR2_PIN, pull_up=None, active_state=active_state)
    servo = AngularServo(
        SERVO_PIN,
        initial_angle=CLOSED_ANGLE,
        min_angle=0,
        max_angle=180,
        min_pulse_width=SERVO_MIN_PULSE,
        max_pulse_width=SERVO_MAX_PULSE,
    )
    time.sleep(SERVO_MOVE_TIME_S)
    if SERVO_DETACH:
        servo.detach()
    return ir1, ir2, servo


def stable_active(sensor):
    """Vrai si le capteur est actif ET le reste pendant DEBOUNCE_S (anti-parasites)."""
    if not sensor.is_active:
        return False
    time.sleep(DEBOUNCE_S)
    return sensor.is_active


def wait_until_active(sensor, timeout):
    deadline = time.time() + timeout
    while _running and time.time() < deadline:
        if stable_active(sensor):
            return True
        time.sleep(POLL_INTERVAL_S)
    return False


def wait_until_clear(sensor, clear_for):
    """Attend que le capteur reste libre pendant `clear_for` secondes. Retourne False si arrêt demandé."""
    clear_since = None
    while _running:
        if sensor.is_active:
            clear_since = None
        elif clear_since is None:
            clear_since = time.time()
        elif time.time() - clear_since >= clear_for:
            return True
        time.sleep(POLL_INTERVAL_S)
    return False


def move_servo(servo, angle):
    servo.angle = angle
    time.sleep(SERVO_MOVE_TIME_S)
    if SERVO_DETACH:
        servo.detach()


def open_barrier(servo):
    move_servo(servo, OPEN_ANGLE)
    print("[BARRIÈRE] Ouverte")


def close_barrier(servo):
    move_servo(servo, CLOSED_ANGLE)
    print("[BARRIÈRE] Fermée")


# ----------------------------------------------------------------------
# Scénario complet pour un véhicule
# ----------------------------------------------------------------------
def handle_vehicle(cap, lpr, ir2, servo):
    begin_time = time.time()
    print("\n[IR1] Véhicule détecté - capture...")

    frames = capture_burst(cap)
    if not frames:
        print("⚠️ Capture impossible : aucune image reçue de la caméra.")
        return

    best = pick_best_frame(frames)
    _, access_granted = run_and_report(best, lpr)
    print(f"\nTemps de traitement: {time.time() - begin_time:.2f} secondes")

    if not access_granted:
        return

    print("[BDD] Accès autorisé - attente IR2...")
    if not wait_until_active(ir2, WAIT_IR2_TIMEOUT_S):
        print("[IR2] Aucun véhicule à la barrière : autorisation annulée.")
        return

    print("[IR2] Véhicule détecté - ouverture barrière...")
    open_barrier(servo)

    # On ne referme jamais tant que le véhicule est sur IR2 (sécurité)
    if wait_until_clear(ir2, CLOSE_DELAY_S):
        close_barrier(servo)


def main():
    signal.signal(signal.SIGINT, _stop)
    signal.signal(signal.SIGTERM, _stop)

    print("Chargement du modèle de détection...")
    lpr = TunisianLPR()

    cap = open_camera()
    if cap is None or not cap.isOpened():
        raise RuntimeError("Impossible d'ouvrir la caméra - vérifiez la connexion.")

    ir1, ir2, servo = setup_hardware()

    try:
        print(f"Système prêt (IR1=GPIO{IR1_PIN}, IR2=GPIO{IR2_PIN}, servo=GPIO{SERVO_PIN}). Ctrl+C pour quitter.")

        ir1_was_active = ir1.is_active
        while _running:
            # Déclenchement sur front montant : une seule capture par véhicule
            if not ir1_was_active and stable_active(ir1):
                handle_vehicle(cap, lpr, ir2, servo)
                print("\nSystème prêt.\n")
                ir1_was_active = ir1.is_active
            else:
                ir1_was_active = ir1.is_active
                time.sleep(POLL_INTERVAL_S)
    finally:
        print("Arrêt du système...")
        cap.release()
        ir1.close()
        ir2.close()
        servo.close()


if __name__ == '__main__':
    main()