from django.db import models
from django.utils import timezone

class ShiftClosure(models.Model):
    """
    Model: ShiftClosure
    หน้าที่: บันทึกประวัติการปิด-เปิดกะ (Shift) ของเจ้าหน้าที่ IT
    ถูกเรียกใช้เมื่อ: Admin กดปุ่ม "ปิดกะ" หรือ "เปิดกะ" บนหน้า Dashboard
    """
    # วันเวลาที่กดปิดกะ
    closed_at = models.DateTimeField(null=True, blank=True)
    # ชื่อเครื่อง (Hostname/IP) ของคนที่กดปิดกะ
    closed_by = models.CharField(max_length=100, verbose_name="Closed By (Machine/IP)")
    
    # วันเวลาที่กดเปิดกะ (ถ้ายังเป็น Null แสดงว่ากะยังปิดอยู่)
    opened_at = models.DateTimeField(null=True, blank=True)
    # ชื่อเครื่อง (Hostname/IP) ของคนที่กดเปิดกะ
    opened_by = models.CharField(max_length=100, null=True, blank=True, verbose_name="Opened By (Machine/IP)")
    
    def save(self, *args, **kwargs):
        # Strip microseconds from closed_at
        if not self.closed_at:
            self.closed_at = timezone.now().replace(microsecond=0)
        else:
            self.closed_at = self.closed_at.replace(microsecond=0)
            
        # Strip microseconds from opened_at if set
        if self.opened_at:
            self.opened_at = self.opened_at.replace(microsecond=0)
            
        super().save(*args, **kwargs)

    def __str__(self):
        return f"Closed: {self.closed_at} - Opened: {self.opened_at}"

    class Meta:
        ordering = ['-closed_at']


class QueueStatus(models.Model):
    """
    Model: QueueStatus
    หน้าที่: เก็บสถานะต่างๆ ของคิวงาน เช่น Waiting, Active, Done
    """
    name = models.CharField(max_length=50) # ชื่อสถานะ (ภาษาไทย/อังกฤษ)
    code = models.CharField(max_length=20, unique=True) # รหัสสถานะ (ภาษาอังกฤษตัวใหญ่ เช่น WAITING)
    color = models.CharField(max_length=20, default='secondary') # สีของปุ่ม/Badge ในหน้าเว็บ (ใช้ Class ของ Bootstrap เช่น primary, success, warning)

    def __str__(self):
        return self.name

class QueueItem(models.Model):
    """
    Model: QueueItem
    หน้าที่: เก็บข้อมูลคิวรับบริการแต่ละรายการ (ตารางหลักของระบบคิว)
    """
    queue_number = models.CharField(max_length=20, unique=True) # เลขคิว เช่น IT-0001
    user_name = models.CharField(max_length=100) # ชื่อผู้มาติดต่อ
    user_department = models.CharField(max_length=100) # แผนกผู้มาติดต่อ
    issue_description = models.TextField() # รายละเอียดงาน/ปัญหา
    comment = models.TextField(null=True, blank=True) # หมายเหตุเพิ่มเติม
    is_urgent = models.IntegerField(default=0) # สถานะเร่งด่วน: 0=ปกติ, 1=เร่งด่วน (แสดงแถบสีแดง)
    created_at = models.DateTimeField(default=timezone.now) # วันเวลาที่สร้างคิว
    
    # เชื่อมโยงกับ JobsBms (ระบบงานซ่อมเก่า) - ใช้เก็บ jobno จาก MSSQL
    linked_job_no = models.IntegerField(null=True, blank=True, unique=True, db_index=True)
    
    # สถานะปัจจุบันของคิว (Relation ไปหา QueueStatus)
    status = models.ForeignKey(
        QueueStatus, 
        on_delete=models.SET_NULL, 
        null=True, 
        blank=True,
        related_name='items'
    )
    
    # วันเวลาที่กดเรียกคิว (เปลี่ยนสถานะเป็น ACTIVE)
    call_queue_date = models.DateTimeField(null=True, blank=True)
    
    # เป็นคิวที่ถูกแทรก (Ad-hoc) หรือไม่: 0=คิวปกติ (Normal), 1=คิวแทรก (Adhoc)
    is_adhoc = models.IntegerField(default=0)

    def save(self, *args, **kwargs):
        if self.created_at:
            self.created_at = self.created_at.replace(microsecond=0)
        if self.call_queue_date:
            self.call_queue_date = self.call_queue_date.replace(microsecond=0)
        super().save(*args, **kwargs)

    @property
    def bms_note(self):
        """
        Retrieves the 'note' from the linked JobsBms record.
        """
        if self.linked_job_no:
            try:
                # Import inside to avoid circular dependency if any (though models.py is same file usually safe but JobsBms is below)
                # Since JobsBms is defined later in the file, we might need to use explicit string reference or move it?
                # Actually, in Python class scope, if defined in same module it's fine as long as we use it at runtime.
                # But JobsBms is defined BELOW QueueItem. 
                # So we must use: from .models import JobsBms (but we are inside models.py)
                # Or just JobsBms.objects.get() works if JobsBms is defined at module level 
                # BUT since it is defined BELOW, it might not be available at define time but IS available at runtime.
                # To be safe, we can move JobsBms above or just assume runtime resolution works.
                # Standard Python: names defined in module are available to methods at runtime.
                
                return JobsBms.objects.get(jobno=self.linked_job_no).note or ''
            except JobsBms.DoesNotExist:
                return ''
        return ''

    def __str__(self):
        return f"{self.queue_number} - {self.user_name}"

    class Meta:
        ordering = ['created_at']

class JobsBms(models.Model):
    """
    Model: JobsBms
    หน้าที่: เก็บข้อมูลงานซ่อมที่ Sync มาจากฐานข้อมูล MSSQL (ระบบเก่า)
    ข้อมูลในตารางนี้จะถูกอ่านมาสร้างเป็น QueueItem
    """
    jobno = models.IntegerField(unique=True) # เลขที่ใบงาน (PK จากระบบเก่า)
    catagory = models.CharField(max_length=50, null=True, blank=True) # หมวดหมู่งาน
    description = models.TextField(null=True, blank=True) # รายละเอียดอาการเสีย
    dept_tech = models.CharField(max_length=50, null=True, blank=True) # แผนกช่าง (Tech Department)
    name = models.CharField(max_length=100, null=True, blank=True, verbose_name="Employee Name")
    jobdate = models.DateTimeField(null=True, blank=True)
    assign_date = models.DateTimeField(null=True, blank=True) # วันที่มอบหมายงาน
    arrive_date = models.DateTimeField(null=True, blank=True) # วันที่ไปถึงหน้างาน
    req_date = models.DateTimeField(null=True, blank=True) # วันที่แจ้งซ่อม (Request Date)
    caller = models.CharField(max_length=100, null=True, blank=True) # ผู้แจ้งปัญหา
    sap_code = models.CharField(max_length=50, null=True, blank=True)
    aname = models.CharField(max_length=255, null=True, blank=True)
    note = models.TextField(null=True, blank=True) # หมายเหตุช่าง
    act_dstart = models.DateTimeField(null=True, blank=True) # วันที่เริ่มซ่อมจริง
    act_dfin = models.DateTimeField(null=True, blank=True) # วันที่ซ่อมเสร็จจริง
    job_status = models.CharField(max_length=10, null=True, blank=True) # รหัสสถานะงาน (1, 2, 11, etc.)
    return_date = models.DateTimeField(null=True, blank=True)
    enterdate = models.DateTimeField(null=True, blank=True)
    enterby = models.CharField(max_length=100, null=True, blank=True)
    outsource_date = models.DateTimeField(null=True, blank=True) # วันที่ส่งซ่อมภายนอก
    
    # ฟิลด์ใหม่จากการ join ตารางแผนก (เพื่อแสดงชื่อเต็มของแผนก)
    abb_desc = models.CharField(max_length=100, null=True, blank=True)
    descriptions = models.TextField(null=True, blank=True)
    
    def get_job_status_display(self):
        status_map = {
            '0': 'รอรับซ่อม',
            '1': 'กำลังดำเนินการ',
            '11': 'รอจ่ายงาน',
            '12': 'ตรวจรับงานแล้ว',
            '13': 'รอใบเสนอราคา',
            '2': 'ซ่อมเสร็จ',
            '3': 'ยกเลิก',
            '5': 'รออนุมัติ',
            '6': 'รออะไหล่',
            '7': 'ส่งซ่อมภายนอก'
        }
        return status_map.get(str(self.job_status), f"Unknown ({self.job_status})")
    
    # ฟิลด์ที่คำนวณขึ้นมาเอง
    difficulty = models.IntegerField(null=True, blank=True)
    job_category_type = models.CharField(max_length=50, null=True, blank=True)

    class Meta:
        db_table = 'jobs_bms'
        ordering = ['-req_date']

    def save(self, *args, **kwargs):
        # รายการฟิลด์วันที่ที่ต้องการลบเศษวินาที (microsecond)
        dt_fields = ['jobdate', 'assign_date', 'arrive_date', 'req_date', 
                     'act_dstart', 'act_dfin', 'return_date', 'enterdate', 'outsource_date']
        for field in dt_fields:
            val = getattr(self, field)
            if val:
                # ลบ microsecond ก่อนเสมอ
                val = val.replace(microsecond=0)
                
                # สำหรับ outsource_date ต้องการให้ format เหมือน enterdate (YYYY-MM-DD HH:MM)
                # คือตัดวินาทีและ Timezone ออก
                if field == 'outsource_date':
                    val = val.replace(second=0, tzinfo=None)
                
                setattr(self, field, val)
        super().save(*args, **kwargs)

    def __str__(self):
        return f"{self.jobno} - {self.description[:30]}"
