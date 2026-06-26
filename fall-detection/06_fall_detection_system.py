import cv2
import mediapipe as mp
import requests
import time
import os
import tempfile
from collections import deque
import subprocess
import json
import sys
import numpy as np

# =========================
# CONFIG
# =========================
API_URL = "http://localhost/web/api/add_event.php"

CONTROL_FILE = "D:/detection-web/web/camera_control.txt"
STATUS_FILE = "D:/detection-web/web/camera_status.txt"
PID_FILE = "D:/detection-web/web/camera_pid.txt"
CAPTURE_FLAG_FILE = "D:/detection-web/web/capture_flag.txt"
BASE_UPLOAD_DIR = "D:/xampp/htdocs/web/uploads"

DEVICE_ID = "cam01"
COOLDOWN_SEC = 10
FPS = 20
PRE_SEC = 5 # ก่อนล้ม
POST_SEC = 6 # หลังล้ม

# ✅ Multi-person detection config
MAX_PEOPLE = 10  # ตรวจจับได้สูงสุด 10 คน
MIN_DETECTION_CONFIDENCE = 0.5
MIN_TRACKING_CONFIDENCE = 0.5

# =========================
# PERSON TRACKER CLASS
# =========================
class PersonTracker:
    """ติดตามแต่ละคนที่ตรวจพบ"""
    def __init__(self, person_id, landmarks, frame_shape):
        self.id = person_id
        self.landmarks = landmarks
        self.last_seen = time.time()
        self.is_falling = False
        self.fall_start_time = None
        self.bbox = self._calculate_bbox(landmarks, frame_shape)
        self.center = self._calculate_center()
        
    def _calculate_bbox(self, landmarks, frame_shape):
        """คำนวณ Bounding Box"""
        h, w = frame_shape[:2]
        xs = [lm.x * w for lm in landmarks]
        ys = [lm.y * h for lm in landmarks]
        x1 = int(max(0, min(xs)))
        y1 = int(max(0, min(ys)))
        x2 = int(min(w - 1, max(xs)))
        y2 = int(min(h - 1, max(ys)))
        return (x1, y1, x2, y2)
    
    def _calculate_center(self):
        """คำนวณจุดกึ่งกลาง"""
        x1, y1, x2, y2 = self.bbox
        return ((x1 + x2) // 2, (y1 + y2) // 2)
    
    def update(self, landmarks, frame_shape):
        """อัพเดทตำแหน่ง"""
        self.landmarks = landmarks
        self.last_seen = time.time()
        self.bbox = self._calculate_bbox(landmarks, frame_shape)
        self.center = self._calculate_center()


# =========================
# MULTI-PERSON MANAGER
# =========================
class MultiPersonManager:
    """จัดการหลายคนพร้อมกัน"""
    def __init__(self, max_people=10):
        self.max_people = max_people
        self.people = {}  # {person_id: PersonTracker}
        self.next_id = 1
        self.distance_threshold = 100  # pixels
        
    def update(self, all_landmarks, frame_shape):
        """อัพเดทคนทั้งหมดที่ตรวจพบ"""
        current_time = time.time()
        
        # ลบคนที่หายไปนานเกิน 2 วินาที
        to_remove = []
        for pid, person in self.people.items():
            if current_time - person.last_seen > 2.0:
                to_remove.append(pid)
        for pid in to_remove:
            del self.people[pid]
        
        # Match landmarks กับคนที่มีอยู่แล้ว
        matched = set()
        for landmarks in all_landmarks:
            temp_person = PersonTracker(0, landmarks, frame_shape)
            new_center = temp_person.center
            
            # หาคนที่ใกล้ที่สุด
            best_match = None
            best_distance = float('inf')
            
            for pid, person in self.people.items():
                if pid in matched:
                    continue
                    
                distance = np.sqrt(
                    (new_center[0] - person.center[0])**2 + 
                    (new_center[1] - person.center[1])**2
                )
                
                if distance < best_distance and distance < self.distance_threshold:
                    best_distance = distance
                    best_match = pid
            
            # อัพเดทหรือสร้างใหม่
            if best_match is not None:
                self.people[best_match].update(landmarks, frame_shape)
                matched.add(best_match)
            elif len(self.people) < self.max_people:
                new_person = PersonTracker(self.next_id, landmarks, frame_shape)
                self.people[self.next_id] = new_person
                self.next_id += 1
        
        return list(self.people.values())
    
    def get_all_people(self):
        """ดึงคนทั้งหมด"""
        return list(self.people.values())


# =========================
# SAVE IMAGE TO uploads/images/{normal|fall}
# =========================
def save_frame_to_uploads(frame_bgr, status: str, device_id: str, person_id: int = None, timestamp: int = None):
    if frame_bgr is None:
        print("❌ save_frame_to_uploads: frame is None")
        return None

    if timestamp is None:
        timestamp = int(time.time())

    subfolder = "fall" if status == "fall" else "normal"
    image_dir = os.path.join(BASE_UPLOAD_DIR, "images", subfolder)
    os.makedirs(image_dir, exist_ok=True)

    # เพิ่ม person_id ในชื่อไฟล์
    person_suffix = f"_person{person_id}" if person_id else ""
    filename = f"{timestamp}_{status}{person_suffix}.jpg"
    filepath = os.path.join(image_dir, filename)

    ok = cv2.imwrite(filepath, frame_bgr)
    if not ok:
        print("❌ บันทึกภาพไม่สำเร็จ:", filepath)
        return None

    print(f"✅ บันทึกภาพสำเร็จ: {filepath}")
    return {
        "success": True,
        "filename": filename,
        "filepath": filepath,
        "status": status,
        "device_id": device_id,
        "person_id": person_id,
        "timestamp": timestamp,
        "subfolder": subfolder,
    }


# =========================
# VIDEO ENCODING
# =========================
def save_video_ffmpeg(frames_bgr, fps=20, person_id=None):
    """สร้างวิดีโอ H.264 ที่เบราว์เซอร์เล่นได้"""
    if not frames_bgr:
        print("❌ ไม่มีเฟรมให้บันทึก")
        return None

    h, w = frames_bgr[0].shape[:2]
    person_suffix = f"_person{person_id}" if person_id else ""
    out_path = os.path.join(tempfile.gettempdir(), f"fall_{int(time.time())}{person_suffix}.mp4")
    temp_raw = os.path.join(tempfile.gettempdir(), f"temp_{int(time.time())}.avi")

    try:
        fourcc = cv2.VideoWriter_fourcc(*"XVID")
        writer = cv2.VideoWriter(temp_raw, fourcc, fps, (w, h))
        if not writer.isOpened():
            print("❌ เปิด VideoWriter ไม่ได้")
            return None

        for frame in frames_bgr:
            writer.write(frame)
        writer.release()

        if not os.path.exists(temp_raw) or os.path.getsize(temp_raw) == 0:
            print("❌ temp avi ว่างเปล่า")
            return None

        ffmpeg_cmd = [
            "ffmpeg", "-y", "-i", temp_raw,
            "-c:v", "libx264", "-pix_fmt", "yuv420p",
            "-preset", "fast", "-profile:v", "baseline",
            "-level", "3.0", "-movflags", "+faststart",
            out_path,
        ]

        result = subprocess.run(
            ffmpeg_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30
        )

        if result.returncode != 0:
            print("❌ FFmpeg error:", result.stderr.decode("utf-8", errors="ignore"))
            return None

        if os.path.exists(out_path) and os.path.getsize(out_path) > 0:
            try:
                os.remove(temp_raw)
            except:
                pass
            return out_path
        return None

    except subprocess.TimeoutExpired:
        print("❌ FFmpeg timeout")
        return None
    except FileNotFoundError:
        print("❌ ไม่พบ FFmpeg!")
        return None
    except Exception as e:
        print(f"❌ save_video_ffmpeg error: {e}")
        return None


def save_video_opencv_fallback(frames_bgr, fps=20, person_id=None):
    """Fallback: ใช้ OpenCV ถ้าไม่มี FFmpeg"""
    if not frames_bgr:
        return None

    h, w = frames_bgr[0].shape[:2]
    person_suffix = f"_person{person_id}" if person_id else ""
    out_path = os.path.join(tempfile.gettempdir(), f"fall_{int(time.time())}{person_suffix}.mp4")

    try:
        fourcc = cv2.VideoWriter_fourcc(*"mp4v")
        writer = cv2.VideoWriter(out_path, fourcc, fps, (w, h))
        if not writer.isOpened():
            return None

        for frame in frames_bgr:
            writer.write(frame)
        writer.release()

        if os.path.exists(out_path) and os.path.getsize(out_path) > 0:
            return out_path
        return None
    except:
        return None


# =========================
# SEND EVENT TO WEB
# =========================
def send_event_with_image_and_video(status: str, device_id: str, image_frame_bgr,
                                     video_path: str = None, person_id: int = None,
                                     saved_image_info: dict = None):
    """
    ส่ง event เข้าเว็บ — Python บันทึกไฟล์ลงโฟลเดอร์ก่อนแล้ว
    จึงส่งแค่ path (text) ให้ PHP บันทึกลง DB เท่านั้น ไม่ส่งไฟล์ซ้ำ
    """
    db_image_path = None
    db_video_path = None

    # ---------- image_path ----------
    if saved_image_info and saved_image_info.get("success"):
        subfolder     = saved_image_info["subfolder"]
        filename      = saved_image_info["filename"]
        db_image_path = f"{subfolder}/{filename}"
        print(f"📌 image_path สำหรับ DB: {db_image_path}")
    elif image_frame_bgr is not None:
        ts    = int(time.time())
        saved = save_frame_to_uploads(image_frame_bgr, status=status,
                                      device_id=device_id,
                                      person_id=person_id, timestamp=ts)
        if saved and saved.get("success"):
            db_image_path = f"{saved['subfolder']}/{saved['filename']}"
            print(f"📌 image_path (manual) สำหรับ DB: {db_image_path}")
        else:
            print("❌ ไม่สามารถบันทึกรูปได้")

    # ---------- video_path ----------
    if video_path and os.path.exists(video_path) and os.path.getsize(video_path) > 0:
        vid_subfolder = "fall" if status == "fall" else "normal"
        vid_dir       = os.path.join(BASE_UPLOAD_DIR, "videos", vid_subfolder)
        os.makedirs(vid_dir, exist_ok=True)
        vid_filename  = os.path.basename(video_path)
        vid_dest      = os.path.join(vid_dir, vid_filename)
        try:
            import shutil
            shutil.move(video_path, vid_dest)
            db_video_path = f"{vid_subfolder}/{vid_filename}"
            print(f"📌 video_path สำหรับ DB: {db_video_path}")
        except Exception as e:
            print(f"⚠️ ย้ายวิดีโอไม่ได้: {e}")

    # ---------- POST ไปยัง API (text เท่านั้น ไม่มีไฟล์) ----------
    try:
        data = {
            "status":     status,
            "device_id":  device_id,
            "image_path": db_image_path or "",
            "video_path": db_video_path or "",
        }
        r = requests.post(API_URL, data=data, timeout=30)
        print(f"✅ API Response: {r.status_code}")
        print(f"📄 Body: {r.text}")
    except Exception as e:
        print(f"❌ Send error: {e}")


# =========================
# CONTROL FILE CHECKER
# =========================
def check_control_command():
    """ตรวจสอบคำสั่งจากเว็บ (start/stop)"""
    if not os.path.exists(CONTROL_FILE):
        return None
    
    try:
        with open(CONTROL_FILE, 'r', encoding='utf-8') as f:
            raw = f.read().strip()
        
        if not raw:
            return None
        
        cmd = json.loads(raw)
        
        try:
            os.remove(CONTROL_FILE)
        except:
            pass
        
        return cmd
    
    except Exception as e:
        print(f"⚠️ Error reading control file: {e}")
        return None


def check_capture_flag():
    """ตรวจสอบคำสั่ง capture จากเว็บ"""
    if not os.path.exists(CAPTURE_FLAG_FILE):
        return None

    try:
        with open(CAPTURE_FLAG_FILE, "r", encoding="utf-8") as f:
            raw = f.read().strip()

        if not raw:
            return None

        try:
            cmd = json.loads(raw)
        except json.JSONDecodeError:
            print("⚠️ capture_flag ยังเขียนไม่เสร็จ รออ่านใหม่...")
            return None

        try:
            os.remove(CAPTURE_FLAG_FILE)
        except:
            pass

        if "action" not in cmd:
            cmd["action"] = "capture"

        return cmd

    except Exception as e:
        print(f"⚠️ Error reading capture flag: {e}")
        return None


def write_status(status):
    """เขียนสถานะกล้อง"""
    try:
        with open(STATUS_FILE, 'w', encoding='utf-8') as f:
            f.write(status)
    except Exception as e:
        print(f"⚠️ Error writing status: {e}")


def write_pid():
    """เขียน PID"""
    try:
        with open(PID_FILE, 'w', encoding='utf-8') as f:
            f.write(str(os.getpid()))
    except Exception as e:
        print(f"⚠️ Error writing PID: {e}")


# =========================
# FALL DETECTION HELPERS
# =========================
mp_pose = mp.solutions.pose
# ✅ เปลี่ยนเป็น model_complexity=1 เพื่อรองรับหลายคน
pose = mp_pose.Pose(
    static_image_mode=False,
    model_complexity=1,
    min_detection_confidence=MIN_DETECTION_CONFIDENCE,
    min_tracking_confidence=MIN_TRACKING_CONFIDENCE
)
mp_draw = mp.solutions.drawing_utils


def is_fall(lm, h):
    """ตรวจสอบว่าคนคนนี้ล้มหรือไม่"""
    try:
        nose_y = lm[mp_pose.PoseLandmark.NOSE].y * h
        hip_y = lm[mp_pose.PoseLandmark.LEFT_HIP].y * h
        shoulder_y = lm[mp_pose.PoseLandmark.LEFT_SHOULDER].y * h

        body_vertical_distance = abs(nose_y - hip_y)
        body_threshold = 60  #จมูกกับสะโพกอยู่ใกล์กับแนวตั้ง
        shoulder_hip_distance = abs(shoulder_y - hip_y) 

        return body_vertical_distance < body_threshold and shoulder_hip_distance < 100 #ไหล่กับสะโพกอยู่ใกล้กับแนวตั้ง
    except:
        return False


def person_bbox(lm, w, h):
    """คำนวณ Bounding Box ของคน"""
    try:
        xs = [p.x * w for p in lm]
        ys = [p.y * h for p in lm]
        x1 = int(max(0, min(xs)))
        y1 = int(max(0, min(ys)))
        x2 = int(min(w - 1, max(xs)))
        y2 = int(min(h - 1, max(ys)))
        return x1, y1, x2, y2
    except:
        return 0, 0, 0, 0


def draw_person_info(frame, person, person_num, is_falling):
    """วาดข้อมูลของแต่ละคน"""
    x1, y1, x2, y2 = person.bbox
    
    # สีตาม status
    color = (0, 0, 255) if is_falling else (0, 255, 0)
    
    # วาดกรอบ
    cv2.rectangle(frame, (x1, y1), (x2, y2), color, 3)
    
    # วาดป้าย
    label = f"PERSON #{person_num}"
    status_text = "FALLING!" if is_falling else "SAFE"
    
    # Background สำหรับข้อความ
    cv2.rectangle(frame, (x1, y1 - 60), (x1 + 200, y1), color, -1)
    
    # ข้อความ
    cv2.putText(frame, label, (x1 + 5, y1 - 35),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
    cv2.putText(frame, status_text, (x1 + 5, y1 - 10),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)


# =========================
# FALL RECORDER FOR EACH PERSON
# =========================
class FallRecorder:
    """บันทึกวิดีโอเมื่อมีคนล้ม"""
    def __init__(self, person_id, fps=20, pre_sec=2, post_sec=3):
        self.person_id = person_id
        self.fps = fps
        self.pre_sec = pre_sec
        self.post_sec = post_sec
        
        self.pre_buffer = deque(maxlen=fps * pre_sec)
        self.recording = False
        self.record_start_time = 0.0
        self.frames_to_save = []
        self.fall_snapshot = None
        
    def add_frame(self, frame):
        """เพิ่มเฟรมเข้า pre-buffer"""
        if not self.recording:
            self.pre_buffer.append(frame.copy())
    
    def start_recording(self, fall_frame):
        """เริ่มบันทึกเมื่อตรวจพบการล้ม"""
        if self.recording:
            return
            
        print(f"\n🚨 FALL DETECTED - Person #{self.person_id}!")
        self.fall_snapshot = fall_frame.copy()
        self.frames_to_save = list(self.pre_buffer)
        self.recording = True
        self.record_start_time = time.time()
    
    def add_recording_frame(self, frame):
        """เพิ่มเฟรมระหว่างบันทึก"""
        if self.recording:
            self.frames_to_save.append(frame.copy())
    
    def should_stop_recording(self):
        """ตรวจสอบว่าควรหยุดบันทึกหรือยัง"""
        if not self.recording:
            return False
        return time.time() - self.record_start_time >= self.post_sec
    
    def finalize_recording(self):
        """จบการบันทึกและส่งไปเว็บ"""
        if not self.recording:
            return
            
        ts = int(time.time())
        
        # บันทึกรูป
        saved = save_frame_to_uploads(
            self.fall_snapshot,
            status="fall",
            device_id=DEVICE_ID,
            person_id=self.person_id,
            timestamp=ts
        )
        if saved:
            print(f"📌 Saved fall image: {saved['filepath']}")
        
        # สร้างวิดีโอ
        video_path = save_video_ffmpeg(self.frames_to_save, fps=self.fps, person_id=self.person_id)
        if not video_path:
            video_path = save_video_opencv_fallback(self.frames_to_save, fps=self.fps, person_id=self.person_id)
        
        # ส่งเข้าเว็บ — ส่ง saved info ด้วยเพื่อให้ใช้ชื่อไฟล์ที่บันทึกไปแล้ว
        send_event_with_image_and_video("fall", DEVICE_ID, self.fall_snapshot, video_path, self.person_id, saved_image_info=saved)
        
        # Reset
        self.recording = False
        self.frames_to_save = []
        self.fall_snapshot = None


# =========================
# MAIN CAMERA LOOP
# =========================
def run_camera():
    """ฟังก์ชันหลักที่รันกล้อง - รองรับหลายคน"""
    
    # เปิดกล้อง
    cap = cv2.VideoCapture(0)
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

    if not cap.isOpened():
        print("❌ ไม่สามารถเปิดกล้องได้")
        write_status("error")
        return

    # เขียนสถานะ
    write_status("running")
    write_pid()
    
    # ✅ Multi-person manager
    person_manager = MultiPersonManager(max_people=MAX_PEOPLE)
    fall_recorders = {}  # {person_id: FallRecorder}
    last_fall_time = {}  # {person_id: timestamp} - cooldown

    frame_count = 0
    start_time = time.time()
    fps_actual = 0.0
    latest_frame = None

    print("=" * 60)
    print("🎥 Fall Detection System (Multi-Person Support)")
    print("=" * 60)
    print("🌐 API:", API_URL)
    print("👥 Max People:", MAX_PEOPLE)
    print("📁 Uploads:", BASE_UPLOAD_DIR)
    print("💡 Press ESC to stop")
    print("=" * 60)

    while True:
        # ตรวจสอบคำสั่ง STOP
        cmd = check_control_command()
        if cmd and cmd.get('action') == 'stop':
            print("\n🛑 Received STOP command from web")
            break

        # อ่านเฟรม
        ok, frame = cap.read()
        if not ok:
            print("❌ ไม่สามารถอ่านเฟรมจากกล้อง")
            break
        
        # ✅ Flip horizontal
        frame = cv2.flip(frame, 1)
        latest_frame = frame.copy()
        
        frame_count += 1
        h, w = frame.shape[:2]
        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        
        # ✅ ตรวจจับ pose (MediaPipe รองรับ 1 คนต่อครั้ง แต่เราทำ tracking)
        res = pose.process(rgb)
        
        # เก็บ landmarks ทั้งหมดที่ตรวจพบ
        all_landmarks = []
        if res.pose_landmarks:
            all_landmarks.append(res.pose_landmarks.landmark)
        
        # อัพเดท person manager
        people = person_manager.update(all_landmarks, frame.shape)
        
        current_time = time.time()
        
        # ประมวลผลแต่ละคน
        for idx, person in enumerate(people, 1):
            person_id = person.id
            lm = person.landmarks
            
            # วาด landmarks
            mp_draw.draw_landmarks(
                frame, 
                type('obj', (object,), {'landmark': lm})(),
                mp_pose.POSE_CONNECTIONS
            )
            
            # ตรวจสอบการล้ม
            is_falling = is_fall(lm, h)
            
            # วาดข้อมูลคน
            draw_person_info(frame, person, idx, is_falling)
            
            # จัดการ fall recorder
            if person_id not in fall_recorders:
                fall_recorders[person_id] = FallRecorder(person_id, FPS, PRE_SEC, POST_SEC)
            
            recorder = fall_recorders[person_id]
            
            # เพิ่มเฟรมเข้า buffer
            recorder.add_frame(latest_frame)
            
            # ตรวจพบการล้ม
            if is_falling:
                # เช็ค cooldown
                last_fall = last_fall_time.get(person_id, 0)
                if current_time - last_fall > COOLDOWN_SEC:
                    if not recorder.recording:
                        recorder.start_recording(latest_frame)
                        last_fall_time[person_id] = current_time
            
            # บันทึกเฟรมถ้ากำลัง recording
            if recorder.recording:
                recorder.add_recording_frame(latest_frame)
                
                # เช็คว่าควรหยุดหรือยัง
                if recorder.should_stop_recording():
                    recorder.finalize_recording()

        # แสดงจำนวนคนที่ตรวจพบ
        cv2.putText(frame, f"People: {len(people)}/{MAX_PEOPLE}", (10, 30),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # ===== CHECK CAPTURE FLAG (manual capture) =====
        capture_cmd = check_capture_flag()
        if capture_cmd and capture_cmd.get("action") == "capture":
            cmd_status = capture_cmd.get("status", "normal")
            cmd_device = capture_cmd.get("device_id", DEVICE_ID)
            cmd_ts = capture_cmd.get("timestamp", int(time.time()))

            print("\n📸 MANUAL CAPTURE from WEB")
            print("   cmd =", capture_cmd)

            if latest_frame is None:
                print("❌ No latest_frame available")
            else:
                saved = save_frame_to_uploads(latest_frame, status=cmd_status, device_id=cmd_device, timestamp=cmd_ts)
                if not saved:
                    print("❌ Save image failed")
                else:
                    print(f"✅ Saved: {saved['filepath']}")
                    send_event_with_image_and_video(cmd_status, cmd_device, None, None, saved_image_info=saved)
        
        # FPS
        if frame_count % 30 == 0:
            elapsed_time = time.time() - start_time
            fps_actual = frame_count / elapsed_time if elapsed_time > 0 else 0.0

        cv2.putText(frame, f"FPS: {fps_actual:.1f}", (w - 150, 30),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)

        cv2.imshow("Fall Detection (Multi-Person) - ESC to exit", frame)

        key = cv2.waitKey(1) & 0xFF
        if key == 27:  # ESC
            print("🛑 ESC pressed - System shutting down...")
            break

    cap.release()
    cv2.destroyAllWindows()
    write_status("stopped")
    print("✅ Camera stopped")


# =========================
# MAIN ENTRY POINT
# =========================
def main():
    """Main function - รอคำสั่ง START จากเว็บ"""
    
    print("=" * 60)
    print("🎯 Fall Detection System (Multi-Person Support)")
    print("=" * 60)
    print("📄 Control File:", CONTROL_FILE)
    print("📊 Status File:", STATUS_FILE)
    print("👥 Max People:", MAX_PEOPLE)
    print("=" * 60)
    
    write_status("stopped")
    
    print("💤 Waiting for START command from web...")
    print("🌐 Go to dashboard and click 'เปิดระบบตรวจจับ'")
    print("=" * 60)
    
    try:
        while True:
            cmd = check_control_command()
            
            if cmd and cmd.get('action') == 'start':
                print("\n📥 Received START command!")
                print("🎥 Starting camera (Multi-Person Mode)...\n")
                
                run_camera()
                
                print("\n💤 Waiting for next START command...")
            
            time.sleep(1)
    
    except KeyboardInterrupt:
        print("\n\n🛑 System interrupted by user")
        write_status("stopped")
    
    except Exception as e:
        print(f"\n❌ System error: {e}")
        write_status("error")
    
    finally:
        print("✅ System exited")


if __name__ == "__main__":
    main()