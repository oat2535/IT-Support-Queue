from django.db import models
from django.utils import timezone

class ShiftClosure(models.Model):
    """
    บันทึกการปิดกะ (ช่วงเวลาที่หยุดให้บริการ)
    - สร้าง Record ใหม่เมื่อกด 'ปิดกะ' (closed_at)
    - อัปเดต Record เดิมเมื่อกด 'เปิดกะ' (opened_at)
    """
    closed_at = models.DateTimeField(null=True, blank=True)
    closed_by = models.CharField(max_length=100, verbose_name="Closed By (Machine/IP)")
    
    opened_at = models.DateTimeField(null=True, blank=True)
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
    name = models.CharField(max_length=50)
    code = models.CharField(max_length=20, unique=True)
    color = models.CharField(max_length=20, default='secondary') # primary, success, warning, etc. (สีปุ่มที่ใช้ในหน้าเว็บ)

    def __str__(self):
        return self.name

class QueueItem(models.Model):
    queue_number = models.CharField(max_length=20, unique=True)
    user_name = models.CharField(max_length=100)
    user_department = models.CharField(max_length=100)
    issue_description = models.TextField()
    comment = models.TextField(null=True, blank=True)
    is_urgent = models.IntegerField(default=0) # สถานะเร่งด่วน (0=ปกติ, 1=เร่งด่วน)
    created_at = models.DateTimeField(default=timezone.now)
    
    # เชื่อมโยงกับ JobsBms (ระบบงานซ่อมเก่า)
    linked_job_no = models.IntegerField(null=True, blank=True, unique=True, db_index=True)
    
    status = models.ForeignKey(
        QueueStatus, 
        on_delete=models.SET_NULL, 
        null=True, 
        blank=True,
        related_name='items'
    )
    
    # วันที่กดเรียกคิว (status -> 2 / ACTIVE)
    call_queue_date = models.DateTimeField(null=True, blank=True)
    
    # เป็นคิวที่ถูกแทรก (Ad-hoc) 0=Normal, 1=Adhoc
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
    jobno = models.IntegerField(unique=True)
    catagory = models.CharField(max_length=50, null=True, blank=True)
    description = models.TextField(null=True, blank=True)
    dept_tech = models.CharField(max_length=50, null=True, blank=True)
    name = models.CharField(max_length=100, null=True, blank=True, verbose_name="Employee Name")
    jobdate = models.DateTimeField(null=True, blank=True)
    assign_date = models.DateTimeField(null=True, blank=True)
    arrive_date = models.DateTimeField(null=True, blank=True)
    req_date = models.DateTimeField(null=True, blank=True)
    caller = models.CharField(max_length=100, null=True, blank=True)
    sap_code = models.CharField(max_length=50, null=True, blank=True)
    aname = models.CharField(max_length=255, null=True, blank=True)
    note = models.TextField(null=True, blank=True)
    act_dstart = models.DateTimeField(null=True, blank=True)
    act_dfin = models.DateTimeField(null=True, blank=True)
    job_status = models.CharField(max_length=10, null=True, blank=True)
    return_date = models.DateTimeField(null=True, blank=True)
    enterdate = models.DateTimeField(null=True, blank=True)
    enterby = models.CharField(max_length=100, null=True, blank=True)
    outsource_date = models.DateTimeField(null=True, blank=True)
    
    # ฟิลด์ใหม่จากการ join ตารางแผนก
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
