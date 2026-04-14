from fastapi import FastAPI, UploadFile, File, Form, HTTPException
import numpy as np
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

_face_app = None


def _get_face_app():
    global _face_app
    if _face_app is None:
        from insightface.app import FaceAnalysis
        logger.info("Loading InsightFace model (this may take a minute on first run)...")
        # Use buffalo_l for better accuracy, with larger detection size
        fa = FaceAnalysis(name="buffalo_l", providers=['CPUExecutionProvider'])
        # det_thresh=0.4 makes it more lenient (default is usually 0.6)
        fa.prepare(ctx_id=0, det_size=(640, 640))
        # Manually override threshold if needed
        for model in fa.models.values():
            if hasattr(model, 'det_thresh'):
                model.det_thresh = 0.3
                logger.info(f"Set det_thresh to {model.det_thresh} for model")
        _face_app = fa
        logger.info("InsightFace model loaded successfully")
    return _face_app


# Preload model at startup
@app.on_event("startup")
async def startup_event():
    logger.info("Preloading face model...")
    _get_face_app()
    logger.info("Face model ready")


def _read_image_bytes_to_bgr(image_bytes: bytes):
    import cv2

    arr = np.frombuffer(image_bytes, dtype=np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Invalid image")
    return img


def _extract_embedding(image_bytes: bytes) -> np.ndarray:
    import cv2
    import os
    
    bgr = _read_image_bytes_to_bgr(image_bytes)
    fa = _get_face_app()
    
    # Pre-process for better detection if needed (Contrast Enhancement)
    lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)
    l, a, b = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8,8))
    cl = clahe.apply(l)
    limg = cv2.merge((cl,a,b))
    enhanced = cv2.cvtColor(limg, cv2.COLOR_LAB2BGR)

    # List of images to try detection on
    attempts = [
        ("Original", bgr),
        ("Enhanced", enhanced),
    ]
    
    # Add scaled versions
    h, w = bgr.shape[:2]
    if max(h, w) > 1000:
        attempts.append(("Downscaled", cv2.resize(bgr, (0,0), fx=0.5, fy=0.5)))
    
    faces = []
    for label, img in attempts:
        logger.info(f"Attempting detection on {label} image...")
        faces = fa.get(img)
        if faces:
            logger.info(f"Face found in {label} attempt")
            break

    # Final attempt with padding if still nothing
    if not faces:
        logger.info("No face in standard passes, trying with square padding...")
        size = max(h, w)
        padded = np.zeros((size, size, 3), dtype=np.uint8)
        padded[:h, :w, :] = bgr
        faces = fa.get(padded)

    logger.info(f"Detection found {len(faces)} faces")
    
    if not faces:
        debug_path = "debug_last_failed_capture.jpg"
        cv2.imwrite(debug_path, bgr)
        logger.info(f"No face detected. Saved to {os.path.abspath(debug_path)}")
        raise HTTPException(status_code=422, detail="No face detected. Please ensure good lighting and face the camera directly.")

    # Sort by detection score
    faces_sorted = sorted(faces, key=lambda f: float(getattr(f, "det_score", 0.0)), reverse=True)
    emb = faces_sorted[0].embedding
    
    if emb is None:
        raise HTTPException(status_code=422, detail="Embedding extraction failed")

    emb = np.asarray(emb, dtype=np.float32)
    norm = np.linalg.norm(emb)
    if norm == 0:
        raise HTTPException(status_code=422, detail="Invalid embedding")
    
    return emb / norm


def _cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/enroll")
async def enroll(image: UploadFile = File(...), user_key: str = Form("")):
    logger.info(f"Received enroll request for user_key: {user_key}")
    logger.info(f"Image filename: {image.filename}, content_type: {image.content_type}")
    content = await image.read()
    if not content:
        logger.error("Empty image content received")
        raise HTTPException(status_code=422, detail="Empty image")
    
    logger.info(f"Received image size: {len(content)} bytes")

    try:
        with open("debug_last_upload.jpg", "wb") as f:
            f.write(content)
        logger.info("Saved raw upload to debug_last_upload.jpg")
    except Exception as e:
        logger.warning(f"Failed to save debug_last_upload.jpg: {str(e)}")
    
    try:
        emb = _extract_embedding(content)
        return {
            "user_key": user_key,
            "embedding": emb.tolist(),
            "embedding_dim": int(emb.shape[0]),
        }
    except HTTPException as he:
        raise he
    except Exception as e:
        logger.error(f"Unexpected error in enroll: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/verify")
async def verify(image: UploadFile = File(...), embedding_json: str = Form(...), threshold: float = Form(0.35)):
    content = await image.read()
    if not content:
        raise HTTPException(status_code=422, detail="Empty image")

    try:
        import json

        stored = np.asarray(json.loads(embedding_json), dtype=np.float32)
    except Exception:
        raise HTTPException(status_code=400, detail="Invalid embedding_json")

    if stored.ndim != 1:
        raise HTTPException(status_code=400, detail="Invalid embedding shape")

    stored_norm = np.linalg.norm(stored)
    if stored_norm == 0:
        raise HTTPException(status_code=400, detail="Invalid embedding")
    stored = stored / stored_norm

    live = _extract_embedding(content)

    cos = _cosine_similarity(stored, live)
    distance = float(1.0 - cos)
    match = distance <= float(threshold)

    return {
        "match": bool(match),
        "distance": float(distance),
        "cosine": float(cos),
        "threshold": float(threshold),
    }
