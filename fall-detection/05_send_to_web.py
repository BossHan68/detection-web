import requests

API_URL = "http://localhost/web/api/add_event.php"

data = {
    "status": "normal",   # เปลี่ยนเป็น "fall" ก็ได้
    "device_id": "cam01"
}

r = requests.post(API_URL, data=data, timeout=5)
print("status_code:", r.status_code)
print("response:", r.text)
