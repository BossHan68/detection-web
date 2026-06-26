import cv2
import mediapipe as mp

mp_pose = mp.solutions.pose
pose = mp_pose.Pose()

cap = cv2.VideoCapture(0)

def is_fall(lm, h):
    nose_y = lm[mp_pose.PoseLandmark.NOSE].y * h
    hip_y  = lm[mp_pose.PoseLandmark.LEFT_HIP].y * h
    return abs(nose_y - hip_y) < 60  # ปรับได้

while True:
    ok, frame = cap.read()
    if not ok:
        break

    h, w, _ = frame.shape
    rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    res = pose.process(rgb)

    if res.pose_landmarks:
        lm = res.pose_landmarks.landmark
        if is_fall(lm, h):
            cv2.putText(frame, "FALL DETECTED", (30, 60),
                        cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 3)

    cv2.imshow("Fall Logic", frame)
    if cv2.waitKey(1) & 0xFF == 27:
        break

cap.release()
cv2.destroyAllWindows()
