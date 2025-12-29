from django.db import models
from django.utils import timezone

class QueueStatus(models.Model):
    name = models.CharField(max_length=50)
    code = models.CharField(max_length=20, unique=True)
    color = models.CharField(max_length=20, default='secondary') # primary, success, warning, etc.

    def __str__(self):
        return self.name

class QueueItem(models.Model):
    queue_number = models.CharField(max_length=20, unique=True)
    user_name = models.CharField(max_length=100)
    user_department = models.CharField(max_length=100)
    issue_description = models.TextField()
    comment = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)
    
    # Link to JobsBms
    linked_job_no = models.IntegerField(null=True, blank=True, unique=True, db_index=True)
    
    status = models.ForeignKey(
        QueueStatus, 
        on_delete=models.SET_NULL, 
        null=True, 
        blank=True,
        related_name='items'
    )

    def save(self, *args, **kwargs):
        if self.created_at:
            self.created_at = self.created_at.replace(microsecond=0)
        super().save(*args, **kwargs)

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
    
    # New fields from join
    abb_desc = models.CharField(max_length=100, null=True, blank=True)
    descriptions = models.TextField(null=True, blank=True)
    
    # Calculated fields
    difficulty = models.IntegerField(null=True, blank=True)
    job_category_type = models.CharField(max_length=50, null=True, blank=True)

    class Meta:
        db_table = 'jobs_bms'
        ordering = ['-req_date']

    def save(self, *args, **kwargs):
        # List of datetime fields to clean
        dt_fields = ['jobdate', 'assign_date', 'arrive_date', 'req_date', 
                     'act_dstart', 'act_dfin', 'return_date', 'enterdate']
        for field in dt_fields:
            val = getattr(self, field)
            if val:
                setattr(self, field, val.replace(microsecond=0))
        super().save(*args, **kwargs)

    def __str__(self):
        return f"{self.jobno} - {self.description[:30]}"
