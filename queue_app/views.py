from django.shortcuts import render, redirect
from django.utils import timezone
from django.core.paginator import Paginator, EmptyPage, PageNotAnInteger
from django.db.models import Q
from .models import QueueItem, QueueStatus, JobsBms
from .utils import sync_jobs_from_mssql 
# การ Sync ข้อมูลถูกจัดการโดย management command แล้ว: python manage.py import_job_analysis
from django.views.decorators.csrf import csrf_exempt
from django.http import JsonResponse
import json
import random

def dashboard(request):
    # เราใช้ management command ในการ sync ข้อมูลแล้ว
    # sync_jobs_from_mssql() ถูกนำออกจากตรงนี้เพื่อเพิ่มประสิทธิภาพการทำงาน
    
    # query ข้อมูลจาก QueueItem แทน JobsBms
    items = QueueItem.objects.all()
    
    total_today = items.count()
    
    # นับจำนวนตามสถานะ
    waiting_count = items.filter(status__code='WAITING').count()
    active_count = items.filter(status__code='ACTIVE').count()
    done_count = items.filter(status__code='DONE').count()
    
    # คิวที่กำลังเรียกอยู่ปัจจุบัน
    current_queue = items.filter(status__code='ACTIVE').order_by('created_at').first()
    
    # Logic การกรองข้อมูล
    status_filter = request.GET.get('status', 'waiting') # เก็บ URL param เป็นตัวเล็กเพื่อความสวยงาม แต่จะ map เป็นตัวใหญ่ในการ query
    search_query = request.GET.get('q', '')
    
    if status_filter == 'active':
        queue_list = items.filter(status__code='ACTIVE').order_by('created_at')
        list_title = "รายการที่กำลังดำเนินการ (Active)"
    elif status_filter == 'done':
        queue_list = items.filter(status__code='DONE').order_by('created_at')
        list_title = "รายการที่เสร็จสิ้น (Done)"
    elif status_filter == 'waiting':
        queue_list = items.filter(status__code='WAITING').order_by('created_at')
        list_title = "รายการที่รอคิว (Waiting)"
    elif status_filter == 'total':
        queue_list = items.order_by('created_at')
        list_title = "รายการคิวทั้งหมด"
    else:
        queue_list = items.filter(status__code='WAITING').order_by('created_at')
        list_title = "รายการที่รอคิว (Waiting)"

    # Logic การค้นหา
    if search_query:
        queue_list = queue_list.filter(
            Q(issue_description__icontains=search_query) | 
            Q(user_name__icontains=search_query) |
            Q(queue_number__icontains=search_query)
        )

    # Logic การแบ่งหน้า (Pagination)
    paginator = Paginator(queue_list, 5) # แสดง 5 รายการต่อหน้า
    page = request.GET.get('page')
    try:
        queue_list = paginator.page(page)
    except PageNotAnInteger:
        queue_list = paginator.page(1)
    except EmptyPage:
        queue_list = paginator.page(paginator.num_pages)

    context = {
        'total_today': total_today,
        'waiting_count': waiting_count,
        'active_count': active_count,
        'done_count': done_count,
        'current_queue': current_queue,
        'queue_list': queue_list,
        'list_title': list_title,
        'active_filter': status_filter,
        'search_query': search_query,
    }
    
    return render(request, 'queue_app/dashboard.html', context)

@csrf_exempt
def update_job_description(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            comment = data.get('comment') # เปลี่ยนจาก description เป็น comment
            
            queue_item = QueueItem.objects.get(id=item_id)
            queue_item.comment = comment
            queue_item.save()
            
            return JsonResponse({'success': True})
        except QueueItem.DoesNotExist:
            return JsonResponse({'success': False, 'error': 'Queue Item not found'})
        except Exception as e:
            return JsonResponse({'success': False, 'error': str(e)})
            
    return JsonResponse({'success': False, 'error': 'Invalid request method'})

def add_queue_item(request):
    return redirect('dashboard')

def call_next_queue(request):
    # ตรวจสอบว่ามีคิวที่กำลัง Active อยู่หรือไม่
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        waiting_status = QueueStatus.objects.get(code='WAITING')
    except QueueStatus.DoesNotExist:
        # ป้องกันการ error หากไม่พบสถานะ อาจจะ redirect หรือ log เก็บไว้
        return redirect('dashboard')
    
    current_active = QueueItem.objects.filter(status=active_status).exists()
    
    if current_active:
        # ไม่สามารถเรียกคิวถัดไปได้หากมีคิว Active อยู่ (UI อาจจะจัดการแล้วแต่เช็คเพื่อความชัวร์)
        pass 
    else:
        # ดึงรายคิวถัดไปที่รออยู่ (Waiting)
        next_item = QueueItem.objects.filter(status=waiting_status).order_by('created_at').first()
        if next_item:
            next_item.status = active_status
            next_item.save()
            
    return redirect('dashboard')

def finish_current_queue(request):
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        done_status = QueueStatus.objects.get(code='DONE')
    except QueueStatus.DoesNotExist:
        return redirect('dashboard')
    
    # ดึงรายการที่กำลัง Active อยู่ตอนนี้
    current_items = QueueItem.objects.filter(status=active_status)
    
    for item in current_items:
        item.status = done_status
        item.save()
        
    return redirect('dashboard')
