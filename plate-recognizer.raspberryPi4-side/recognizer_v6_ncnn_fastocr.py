"""
Tunisian License Plate Recognition v5 (Optimisé NCNN + FastPlateOCR)
Format unique supporté : Série Normale (STD) - e.g. "152-8269" -> "152 تونس 8269"
"""
import re
import argparse
import cv2
import numpy as np
import time
from ultralytics import YOLO
from fast_plate_ocr import LicensePlateRecognizer


class TunisianLPR:
    def __init__(self, 
                 yolo_model_path='licence-plate-detection_ncnn_model', 
                 onnx_model_path='best.onnx',
                 plate_config_path='plate_config.yaml',
                 imgsz=320):
        
        self.detector = YOLO(yolo_model_path, task='detect')
        self.imgsz = imgsz
        self.ocr_engine = LicensePlateRecognizer(
            onnx_model_path=onnx_model_path,
            plate_config_path=plate_config_path
        )
        self._warmup()

    def _warmup(self):
        dummy = np.zeros((320, 320, 3), dtype=np.uint8)
        try:
            t0 = time.time()
            self.detector(dummy, verbose=False, imgsz=self.imgsz)
            print(f"[Warmup] Terminé en {time.time() - t0:.2f}s")
        except Exception as e:
            print(f"[Warmup] ÉCHEC : {e}")

    def detect_plates(self, frame, conf_thresh=0.4):
        begin_time = time.time()
        results = self.detector(frame, verbose=False, imgsz=self.imgsz)[0]
        boxes = []
        for box in results.boxes:
            conf = float(box.conf[0])
            if conf > conf_thresh:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                boxes.append((x1, y1, x2, y2, conf))
        print(f"[Detection NCNN] Temps d'inférence YOLO: {time.time() - begin_time:.4f}s")
        return boxes

    def deskew(self, gray_or_thresh):
        _, mask = cv2.threshold(gray_or_thresh, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        coords = cv2.findNonZero(mask)
        if coords is None or len(coords) < 10:
            return gray_or_thresh

        angle = cv2.minAreaRect(coords)[-1]
        if angle < -45:
            angle = 90 + angle
        if abs(angle) < 1.0:
            return gray_or_thresh

        h, w = gray_or_thresh.shape
        center = (w // 2, h // 2)
        M = cv2.getRotationMatrix2D(center, angle, 1.0)
        return cv2.warpAffine(gray_or_thresh, M, (w, h),
                              flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE)

    def preprocess_plate(self, crop, save_path='proc_crop.jpg'):
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        gray = cv2.bilateralFilter(gray, 11, 17, 17)

        h, w = gray.shape
        if w < 300:
            scale = 300 / w
            gray = cv2.resize(gray, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

        gray = self.deskew(gray)
        _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        
        if save_path:
            cv2.imwrite(save_path, thresh)

        return save_path

    def ocr_plate(self, crop_path='proc_crop.jpg'):
        begin_time = time.time()
        prediction = self.ocr_engine.run(crop_path)
        print(f"[OCR FastPlate] Temps d'inférence OCR: {time.time() - begin_time:.4f}s")
        
        # Extraction du texte depuis la liste de PlatePrediction
        if isinstance(prediction, list) and len(prediction) > 0:
            raw_text = getattr(prediction[0], 'plate', str(prediction[0]))
        else:
            raw_text = getattr(prediction, 'plate', str(prediction))
            
        return raw_text.strip()

    def process_frame(self, frame):
        out = []
        boxes = self.detect_plates(frame)
        
        for i, (x1, y1, x2, y2, conf) in enumerate(boxes):
            crop = frame[y1:y2, x1:x2]
            crop_path = f"proc_crop_{i}.jpg" if len(boxes) > 1 else "proc_crop.jpg"
            
            self.preprocess_plate(crop, save_path=crop_path)
            text = self.ocr_plate(crop_path=crop_path)
            
            out.append({
                'bbox': (x1, y1, x2, y2),
                'text': text,
                'det_conf': conf,
            })
        return out