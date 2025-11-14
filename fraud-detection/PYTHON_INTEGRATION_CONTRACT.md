# 🤝 Python ↔ PHP Integration Contract

**Version:** 1.0
**Date:** November 14, 2025
**Status:** READY FOR IMPLEMENTATION

---

## 📋 WHAT YOUR PYTHON CV PIPELINE MUST DO

### Minimum Viable Integration (MVP):

#### 1. Load Camera Registry from Database ✅
```python
import mysql.connector

def load_cameras():
    """Load camera list from PHP system database"""
    db = mysql.connector.connect(
        host="your_host",
        user="your_user",
        password="your_pass",
        database="your_db"
    )

    cursor = db.cursor(dictionary=True)
    cursor.execute("""
        SELECT camera_id, camera_name, location, outlet_id,
               stream_url_encrypted, priority
        FROM camera_network
        WHERE online = 1
        ORDER BY priority DESC
    """)

    cameras = cursor.fetchall()
    cursor.close()
    db.close()

    return cameras
```

**Note:** Stream URLs are encrypted. You have two options:
1. Decrypt in Python (implement EncryptionService in Python)
2. Store decrypted URLs in memory only (never persist)

---

#### 2. POST Results to Callback API ✅

**Endpoint:** `POST /modules/fraud-detection/api/cv-callback.php`

**Required Headers:**
```python
headers = {
    "Content-Type": "application/json",
    "X-CV-Auth-Token": "your_token_from_env"
}
```

**Three Result Types You Must Support:**

##### A. Frame Analysis (Continuous Stream)
Send every 1-5 seconds per camera:

```python
{
    "result_type": "frame_analysis",
    "session_id": "unique_session_id",
    "camera_id": 5,
    "staff_id": 42,  # Optional, if detected
    "frame_timestamp": "2025-11-14 14:30:00",
    "analysis_results": {
        "indicators": {
            "stress": 0.65,      # 0.0 - 1.0
            "anxiety": 0.42,
            "deception": 0.31,
            "nervousness": 0.28
        },
        "anomalies": []  # Or list of detected anomalies
    }
}
```

##### B. Behavioral Detection (On Event)
Send when confidence > 0.8:

```python
{
    "result_type": "behavioral_detection",
    "staff_id": 42,
    "camera_id": 5,
    "detection_type": "stress_indicators",  # or "suspicious_behavior", etc.
    "category": "emotional",
    "confidence": 0.87,
    "deviation": 0.65,  # Deviation from baseline (0.0 - 1.0)
    "timestamp": "2025-11-14 14:30:00",
    "context": {
        "emotion": "anxious",
        "posture": "tense",
        "gaze": "avoiding",
        "movement": "fidgeting"
    },
    "frame_path": "/path/to/frame.jpg"  # Optional
}
```

##### C. Anomaly Alert (Critical Events)
Send immediately for critical anomalies:

```python
{
    "result_type": "anomaly_alert",
    "staff_id": 42,
    "camera_id": 5,
    "anomaly_type": "suspicious_behavior",
    "severity": "HIGH",  # CRITICAL, HIGH, MEDIUM, LOW
    "risk_score": 0.89,
    "indicators": ["deception_signals", "stress_markers", "unusual_movement"],
    "evidence": {
        "frame_path": "/path/to/evidence.jpg",
        "timestamp": "2025-11-14 14:30:00",
        "duration_seconds": 45
    }
}
```

---

#### 3. Handle PHP Responses ✅

**Success Response:**
```json
{
    "success": true,
    "message": "Frame analysis recorded",
    "risk_score": 0.45,
    "risk_level": "MEDIUM"
}
```

**Error Response:**
```json
{
    "success": false,
    "error": "Missing required field: camera_id"
}
```

**Your Python Must:**
- Check `success` field
- Log errors
- Retry on network failures (max 3 attempts)
- Continue processing on errors (don't crash)

---

## 🎯 EXPECTED BEHAVIOR

### Startup Sequence:
1. Load cameras from database
2. Initialize ML models (6 models)
3. Decrypt stream URLs (or load from secure config)
4. Start capture threads (1 per camera, max 120)
5. Begin frame analysis
6. POST results to callback API

### Runtime Loop (per camera):
```
LOOP:
  1. Capture frame from RTSP stream (5 FPS)
  2. Batch frames (16 frames per batch for GPU)
  3. Run through ML models:
     - Emotion recognition
     - Pose estimation
     - Object detection
     - Gaze tracking
     - Action recognition
     - Anomaly detection
  4. Calculate indicators & risk scores
  5. POST frame_analysis to callback
  6. IF confidence > 0.8:
     POST behavioral_detection
  7. IF critical anomaly:
     POST anomaly_alert
  8. Sleep until next frame
```

---

## 🔧 CONFIGURATION YOUR PYTHON NEEDS

### Environment Variables:
```bash
# PHP System Integration
PHP_CALLBACK_URL="https://yourdomain.com/modules/fraud-detection/api/cv-callback.php"
CV_PIPELINE_TOKEN="your_secure_token"

# Database
DB_HOST="localhost"
DB_USER="fraud_user"
DB_PASS="secure_password"
DB_NAME="fraud_detection"

# Processing
MAX_CAMERAS=120
ANALYSIS_FPS=5
BATCH_SIZE=16
GPU_MEMORY_FRACTION=0.8

# ML Models
MODEL_DIR="/path/to/models"
EMOTION_MODEL="emotion_recognition_v2.h5"
POSE_MODEL="pose_estimation_v2.h5"
OBJECT_MODEL="yolov5_retail.pt"
GAZE_MODEL="gaze_estimation_v2.h5"
ACTION_MODEL="action_lstm_v2.h5"
ANOMALY_MODEL="anomaly_autoencoder_v2.h5"
```

### Config File (config.json):
```json
{
    "php_system": {
        "callback_url": "https://yourdomain.com/api/cv-callback.php",
        "auth_token": "your_token",
        "timeout_seconds": 5,
        "retry_attempts": 3
    },
    "cameras": {
        "load_from_database": true,
        "max_concurrent": 120,
        "priority_threshold": 5
    },
    "processing": {
        "fps": 5,
        "batch_size": 16,
        "use_gpu": true,
        "gpu_device": 0,
        "gpu_memory_fraction": 0.8
    },
    "detection": {
        "confidence_threshold": 0.8,
        "alert_threshold": 0.9,
        "baseline_enabled": true,
        "baseline_days": 30
    }
}
```

---

## 📊 PERFORMANCE TARGETS

### Your Python Must Achieve:
- **Latency:** < 300ms per frame
- **Throughput:** 600 frames/sec total (100 cameras @ 6 FPS)
- **GPU Memory:** < 20GB for 100 cameras
- **Accuracy:** > 85% confidence for alerts
- **Uptime:** > 99% (auto-restart on crash)

### Optimization Tips:
- Use GPU batching (16 frames at once)
- Async HTTP requests for callbacks
- Thread pool for camera capture
- Redis/memory cache for baselines
- Skip frames if processing lags behind

---

## 🔒 SECURITY REQUIREMENTS

### Your Python Must:
1. **Never log stream credentials** (passwords in URLs)
2. **Use HTTPS** for callback API (production)
3. **Validate SSL certificates** (no self-signed in prod)
4. **Secure token storage** (environment variable, not hardcoded)
5. **Encrypt baselines** if stored locally
6. **Rate limit callbacks** (max 10/sec per camera)
7. **Sanitize file paths** before saving frames

---

## 🧪 TESTING CHECKLIST

### Before Integration:
- [ ] Can load cameras from database
- [ ] Can make HTTPS POST requests
- [ ] Token authentication works
- [ ] All 6 ML models load successfully
- [ ] GPU detection working
- [ ] RTSP stream capture working

### During Integration:
- [ ] Single camera test (1 camera, verify data in DB)
- [ ] Multi-camera test (5 cameras, check no conflicts)
- [ ] Load test (50+ cameras, measure latency)
- [ ] Failure test (network down, database down, PHP error)
- [ ] Long-running test (24 hours, check memory leaks)

### Verification Queries:
```sql
-- Check frame analyses are being stored
SELECT COUNT(*) FROM cv_analysis_results
WHERE created_at > NOW() - INTERVAL 1 HOUR;

-- Check behavioral detections
SELECT * FROM cv_behavioral_detections
ORDER BY detection_timestamp DESC LIMIT 10;

-- Check alerts generated
SELECT * FROM cv_behavioral_alerts
WHERE alert_timestamp > NOW() - INTERVAL 1 HOUR;

-- Check camera health
SELECT camera_id, camera_name, online, last_seen
FROM camera_network
ORDER BY last_seen DESC;
```

---

## 🎯 SUCCESS CRITERIA

### Phase 1: MVP (Single Camera)
- [ ] Load 1 camera from database
- [ ] Capture frames at 5 FPS
- [ ] Run through ML models
- [ ] POST frame_analysis every 5 seconds
- [ ] Verify data in database
- [ ] Risk score calculated correctly

### Phase 2: Multi-Camera (10 Cameras)
- [ ] Load 10 cameras
- [ ] Process all simultaneously
- [ ] No frame drops
- [ ] Latency < 300ms average
- [ ] Callbacks succeed 99%+

### Phase 3: Production (100+ Cameras)
- [ ] 100+ cameras processing
- [ ] 600+ frames/sec throughput
- [ ] Latency < 270ms average
- [ ] GPU memory < 20GB
- [ ] System stable 24+ hours
- [ ] Auto-recovery from errors

---

## 🚨 ERROR HANDLING

### Your Python Must Handle:

#### Network Errors:
```python
try:
    response = requests.post(callback_url, json=payload, timeout=5)
    response.raise_for_status()
except requests.exceptions.Timeout:
    log.warning(f"Callback timeout for camera {camera_id}")
    # Retry up to 3 times
except requests.exceptions.ConnectionError:
    log.error(f"Cannot reach PHP system")
    # Queue for retry, continue processing
```

#### Camera Stream Errors:
```python
if not frame_captured:
    log.warning(f"Camera {camera_id} frame drop")
    # Update camera status in database
    # Continue with other cameras
    # Retry connection after 30 seconds
```

#### ML Model Errors:
```python
try:
    predictions = model.predict(frames)
except Exception as e:
    log.error(f"Model prediction failed: {e}")
    # Fall back to CPU or simpler model
    # Or skip this batch and continue
```

---

## 📞 COMMUNICATION PROTOCOL

### Callback Frequency:
- **Frame Analysis:** Every 1-5 seconds per camera
- **Behavioral Detection:** When confidence > 0.8
- **Anomaly Alert:** Immediately on detection
- **Baseline Update:** Once per day per staff

### Callback Timing:
```
Camera 1: POST at 14:30:00.000
Camera 2: POST at 14:30:00.200  (stagger by 200ms)
Camera 3: POST at 14:30:00.400
...
```

Stagger requests to avoid overwhelming PHP system.

### Batch Updates (Optional):
If you process many frames quickly, you can batch:
```python
{
    "result_type": "batch_complete",
    "camera_id": 5,
    "frames_processed": 300,
    "analysis_summary": {
        "avg_stress": 0.45,
        "high_confidence_detections": 5,
        "alerts_generated": 1
    }
}
```

---

## ✅ INTEGRATION COMPLETE WHEN:

1. ✅ Python loads cameras from database
2. ✅ Captures frames from all cameras
3. ✅ Runs ML models on frames
4. ✅ POSTs results to callback API
5. ✅ PHP stores data in database
6. ✅ Risk scores calculated
7. ✅ Alerts generated for high-confidence detections
8. ✅ System runs stable for 24+ hours
9. ✅ Performance targets met
10. ✅ Security requirements satisfied

---

## 🎉 YOU'RE READY WHEN YOU CAN:

```bash
# Start your Python pipeline
python cv_pipeline.py --config=config.json

# See data flowing into database
mysql -e "SELECT COUNT(*) FROM cv_analysis_results WHERE created_at > NOW() - INTERVAL 1 MINUTE;"
# Returns: 300+ (100 cameras * 5 FPS * 60 sec / 100)

# See behavioral detections
mysql -e "SELECT * FROM cv_behavioral_detections ORDER BY detection_timestamp DESC LIMIT 5;"
# Returns: Recent detections with high confidence

# Check system health
curl "http://yourdomain.com/api/camera-management.php?action=health_check"
# Returns: {"success":true,"health":{"total_cameras":100,"online":100,...}}
```

---

**THIS IS YOUR CONTRACT WITH THE PHP SYSTEM.**
**DELIVER THESE 3 RESULT TYPES AND YOU'RE GOLDEN!** 🚀

Ready? Let's integrate! 💪
