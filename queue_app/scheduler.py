from apscheduler.schedulers.background import BackgroundScheduler
from queue_app.utils import sync_jobs_from_mssql
from queue_app.models import ShiftClosure
from django.utils import timezone
import datetime
import logging

logger = logging.getLogger(__name__)

def auto_close_shift_logic():
    """
    ตรวจสอบและปิดกะอัตโนมัติ (Auto-Close Shift)
    ทำงาน: ทุกๆ 5 นาที ในช่วงเวลา 21:00 - 06:00
    """
    now = timezone.now()
    
    # ตรวจสอบช่วงเวลา (21:00 - 23:59 หรือ 00:00 - 06:00)
    if now.hour >= 21 or now.hour < 6:
        # เช็คว่าปิดอยู่แล้วหรือยัง
        is_currently_closed = ShiftClosure.objects.filter(opened_at__isnull=True).exists()
        
        if not is_currently_closed:
             # คำนวณเวลา 21:00 ของ "กะนี้"
             # ถ้าตอนนี้เป็นเช้า (00:00 - 06:00) ต้องเช็คย้อนไปถึง 21:00 ของเมื่อวาน
             if now.hour < 6:
                 reference_date = now.date() - timezone.timedelta(days=1)
             else:
                 reference_date = now.date()
            
             shift_start_check = timezone.make_aware(datetime.datetime.combine(reference_date, datetime.time(21, 0)))

             # เช็คว่ามีการเปิด OT (เปิดหลัง 21:00 ของกะนี้) หรือไม่
             has_overtime_open = ShiftClosure.objects.filter(opened_at__gte=shift_start_check).exists()
             
             if not has_overtime_open:
                 # สร้าง Record ปิดกะโดย System
                 ShiftClosure.objects.create(
                     closed_by='System (Auto)'
                 )
                 logger.info(f"System Auto-Closed Shift at {now} (Reference Check: {shift_start_check})")
                 print(f"DEBUG: System Auto-Closed Shift at {now}")
             else:
                 logger.info(f"Auto-close skipped: OT detected (Open since > {shift_start_check})")


def start():
    scheduler = BackgroundScheduler()
    # ตั้งให้รัน Sync ทุกๆ 1 นาที (ปรับเปลี่ยนได้ตามความเหมาะสม)
    scheduler.add_job(sync_jobs_from_mssql, 'interval', minutes=1, id='sync_mssql_job', replace_existing=True)
    
    # เพิ่ม Job สำหรับ Auto Close Shift
    # รันทุก 5 นาที ในช่วงเวลา 21:00 - 06:00
    # ใช้ cron expression: hour='21-23,0-6'
    scheduler.add_job(auto_close_shift_logic, 'cron', hour='21-23,0-6', minute='*/5', id='auto_close_shift_job', replace_existing=True)
    
    scheduler.start()
    logger.info("APScheduler started: Syncing MSSQL jobs every 1 minute.")
    logger.info("APScheduler started: Auto-close shift check enabled (21:00 - 06:00, every 5 mins).")
