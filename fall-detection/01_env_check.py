import sys
import mediapipe as mp

print("Python:", sys.version)
print("Python exe:", sys.executable)
print("mediapipe version:", getattr(mp, "__version__", "N/A"))
print("mediapipe path:", getattr(mp, "__file__", "N/A"))
print("has solutions:", hasattr(mp, "solutions"))

# ถ้าเป็นตัวจริง should be True
if hasattr(mp, "solutions"):
    print("pose module:", mp.solutions.pose)
else:
    print("❌ mediapipe ตัวนี้ผิดปกติ (ไม่มี solutions)")
