from django.urls import path
from . import views

urlpatterns = [
    # หน้า Dashboard หลัก
    path('', views.dashboard, name='dashboard'),
    
    # API: จัดการคิว (เพิ่ม/เรียก/จบ)
    path('add-queue/', views.add_queue_item, name='add_queue'),
    path('call-next/', views.call_next_queue, name='call_next'), # เรียกคิวถัดไป
    path('finish-queue/', views.finish_current_queue, name='finish_queue'), # จบงานคิวปัจจุบัน
    
    # API: แก้ไขข้อมูลคิว (Note/Urgent)
    path('update-job-desc/', views.update_job_description, name='update_job_desc'),
    path('toggle-urgent/', views.toggle_urgent_status, name='toggle_urgent'),
    
    # API: ระบบคิวแทรก (Ad-hoc)
    path('insert-queue/', views.insert_queue_adhoc, name='insert_queue'),
    path('finish-adhoc/', views.finish_adhoc_queue, name='finish_adhoc'),
    
    # API: ระบบปิดกะ (Shift Control)
    path('toggle-shift-status/', views.toggle_shift_status, name='toggle_shift_status'),
]
