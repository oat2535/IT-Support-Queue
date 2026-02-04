# เลือกใช้ Python 3.12 (แบบ Slim) เป็น Image หลัก เพื่อลดขนาดไฟล์
FROM python:3.12-slim

# ตั้งค่าตัวแปรสิ่งแวดล้อม (Environment Variables)
# ป้องกันไม่ให้ Python เขียนไฟล์ .pyc (ไม่จำเป็นใน Docker)
ENV PYTHONDONTWRITEBYTECODE=1
# ให้ Python ส่ง Log ออกมาทันที ไม่ต้องรอ Buffer (ช่วยให้เห็น Log Real-time)
ENV PYTHONUNBUFFERED=1

# ติดตั้งโปรแกรมพื้นฐานที่จำเป็นสำหรับระบบ (curl, gnupg, certificates)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    gnupg \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# ดาวน์โหลด Key และเพิ่ม Repository ของ Microsoft สำหรับติดตั้ง Driver SQL Server ของ Debian 12
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64,arm64,armhf signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list

# ติดตั้ง ODBC Driver 18 สำหรับ SQL Server และ Library สำหรับการพัฒนา (unixodbc-dev)
# ACCEPT_EULA=Y คือการยอมรับเงื่อนไขการใช้งานของ Microsoft โดยอัตโนมัติ
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
    unixodbc-dev \
    msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# กำหนดโฟลเดอร์ทำงานภายใน Container เป็น /app
WORKDIR /app

# คัดลอกไฟล์ requirements.txt เข้าไปก่อน เพื่อติดตั้ง Library
# (ทำแยกเพื่อใช้ประโยชน์จาก Docker Cache Layer หากไม่มีการแก้ไฟล์นี้)
COPY requirements.txt /app/
RUN pip install --upgrade pip && pip install -r requirements.txt

# คัดลอกไฟล์โปรเจคทั้งหมดใน Folder ปัจจุบัน เข้าสู่ Container
COPY . /app/

# เปิด Port 8000 สำหรับการเชื่อมต่อ
EXPOSE 8000

# คำสั่งเริ่มต้นเมื่อ Container ทำงาน (รัน Server Django)
CMD ["python", "manage.py", "runserver", "0.0.0.0:8000"]
