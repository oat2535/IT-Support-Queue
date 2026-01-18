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
import socket

def dashboard(request):
    # เราใช้ management command ในการ sync ข้อมูลแล้ว
    # sync_jobs_from_mssql() ถูกนำออกจากตรงนี้เพื่อเพิ่มประสิทธิภาพการทำงาน
    
    # query ข้อมูลจาก QueueItem แทน JobsBms
    items = QueueItem.objects.all()
    
    # นับจำนวนตามสถานะ
    waiting_count = items.filter(status__code='WAITING').count()
    active_count = items.filter(status__code='ACTIVE').count()
    done_count = items.filter(status__code='DONE').count()
    coordinating_count = items.filter(status__code='COORDINATING').count()
    waiting_parts_count = items.filter(status__code='WAITING_PARTS').count()
    
    # Fetch specific statuses for the dropdown (Waiting, Coordinating, Waiting Parts)
    target_codes = ['WAITING', 'COORDINATING', 'WAITING_PARTS']
    all_statuses = QueueStatus.objects.filter(code__in=target_codes).order_by('id')
    
    # คิวที่กำลังเรียกอยู่ปัจจุบัน (Normal)
    current_queue = items.filter(status__code='ACTIVE', is_adhoc=0).order_by('created_at').first()
    
    # คิว Ad-hoc ที่กำลัง Active
    current_adhoc = items.filter(status__code='ACTIVE', is_adhoc=1).order_by('created_at').first()
    
    # Logic การกรองข้อมูล
    status_filter = request.GET.get('status', 'waiting') # เก็บ URL param เป็นตัวเล็กเพื่อความสวยงาม แต่จะ map เป็นตัวใหญ่ในการ query
    search_query = request.GET.get('q', '')
    
    if status_filter == 'active':
        queue_list = items.filter(status__code='ACTIVE').order_by('created_at')
        list_title = "รายการที่กำลังดำเนินการ (Active)"
    elif status_filter == 'done':
        queue_list = items.filter(status__code='DONE').order_by('created_at')
        list_title = "รายการที่เสร็จสิ้น (Done)"
    elif status_filter == 'pending':
        queue_list = items.filter(status__code__in=['COORDINATING', 'WAITING_PARTS']).order_by('created_at')
        list_title = "รายการที่อยู่ระหว่างประสานงานและรออะไหล่"
    elif status_filter == 'waiting':
        queue_list = items.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number')
        list_title = "รายการที่รอคิว (Waiting)"
    else:
        queue_list = items.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number')
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

    # Inject Rank for Waiting List (Fix: Search resets rank issue)
    if status_filter == 'waiting':
        try:
             # Fetch all IDs in correct order to determine true rank
             # Must strictly match the order_by used before filtering for consistent ranking
             all_waiting_ids = list(QueueItem.objects.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number').values_list('id', flat=True))
             rank_map = {pk: i+1 for i, pk in enumerate(all_waiting_ids)}
             
             # Attach rank to the current page's objects
             for item in queue_list:
                 item.waiting_rank = rank_map.get(item.id, '-')
        except Exception as e:
            print(f"Error calculating ranks: {e}")

    # ตรวจสอบชื่อเครื่อง
    is_admin_computer = False
    client_ip = request.META.get('REMOTE_ADDR')
    try:
        # พยายาม resolve hostname
        hostname, alias, iplist = socket.gethostbyaddr(client_ip)
        print(f"DEBUG: Client IP={client_ip}, Hostname={hostname}") # Debugging
        
        # รายชื่อเครื่องที่อนุญาต (ควรทำเป็น List ไว้)
        admin_hosts = ['DESKTOP-TIC1FOD', 'B-IT-24']

        # ใช้ any() เพื่อเช็คว่ามีชื่อใดชื่อหนึ่งใน admin_hosts ปรากฏอยู่ใน hostname หรือไม่
        current_hostname = hostname.upper()
        if any(admin_host in current_hostname for admin_host in admin_hosts) or client_ip == '127.0.0.1':
            is_admin_computer = True
    except Exception as e:
        print(f"DEBUG: Could not resolve hostname for {client_ip}: {e}")
        # กรณี resolve ไม่ได้ จะไม่ให้สิทธิ์ admin
        pass

    context = {
        'waiting_count': waiting_count,
        'active_count': active_count,
        'done_count': done_count,
        'coordinating_count': coordinating_count,
        'waiting_parts_count': waiting_parts_count,
        'current_queue': current_queue,
        'queue_list': queue_list,
        'list_title': list_title,
        'active_filter': status_filter,
        'search_query': search_query,
        'is_admin_computer': is_admin_computer,
        'all_statuses': all_statuses,
        'all_statuses': all_statuses,
        'current_adhoc': current_adhoc,
    }
    
    return render(request, 'queue_app/dashboard.html', context)

@csrf_exempt
def update_job_description(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            comment = data.get('comment') # เปลี่ยนจาก description เป็น comment
            status_id = data.get('status_id')
            
            queue_item = QueueItem.objects.get(id=item_id)
            queue_item.comment = comment
            
            if status_id:
                try:
                    new_status = QueueStatus.objects.get(id=status_id)
                    queue_item.status = new_status
                except QueueStatus.DoesNotExist:
                    pass
            
            queue_item.save()
            
            return JsonResponse({'success': True})
        except QueueItem.DoesNotExist:
            return JsonResponse({'success': False, 'error': 'Queue Item not found'})
        except Exception as e:
            return JsonResponse({'success': False, 'error': str(e)})
            
    return JsonResponse({'success': False, 'error': 'Invalid request method'})

@csrf_exempt
def toggle_urgent_status(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            is_urgent = data.get('is_urgent') # รับค่ามาเป็น 1 หรือ 0 หรือ boolean
            
            # แปลงเป็น integer (0 หรือ 1)
            if is_urgent is True or is_urgent == 'true' or is_urgent == 1:
                urgent_val = 1
            else:
                urgent_val = 0
            
            queue_item = QueueItem.objects.get(id=item_id)
            queue_item.is_urgent = urgent_val
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
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        waiting_status = QueueStatus.objects.get(code='WAITING')
        done_status = QueueStatus.objects.get(code='DONE')
    except QueueStatus.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'System statuses not defined'})
    
    # 1. ตรวจสอบงานปัจจุบันก่อนปิด (Validation Logic) -> เฉพาะ Normal Queue
    current_active_items = QueueItem.objects.filter(status=active_status, is_adhoc=0)
    for item in current_active_items:
        # ถ้ามีการเชื่อมโยงกับ Job BMS
        if item.linked_job_no:
            try:
                # ดึงข้อมูล Job BMS ล่าสุด
                bms_job = JobsBms.objects.get(jobno=item.linked_job_no)
                
                # ตรวจสอบสถานะ (Allow only '2' or '12')
                # หมายเหตุ: job_status เป็น CharField
                if bms_job.job_status not in ['2', '12']:
                     return JsonResponse({
                         'success': False, 
                         'error': f'ไม่สามารถกดเรียกคิวถัดไปได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: {bms_job.get_job_status_display()})'
                     })
                     
            except JobsBms.DoesNotExist:
                # กรณีไม่เจอ Job ใน BMS (อาจจะ Sync ไม่ทัน หรือถูกลบ) 
                # ให้ข้ามการตรวจสอบไปก่อน หรือจะ Block ก็ได้ แต่ข้ามดีกว่าเพื่อไม่ให้ระบบค้าง
                pass

    # 2. ปิดงานเก่า (ถ้าผ่าน Validation)
    for item in current_active_items:
        item.status = done_status
        item.save()
    
    # 3. เรียกคิวถัดไป (Priority: Urgent > Normal, then Queue Number)
    next_item = QueueItem.objects.filter(status=waiting_status).order_by('-is_urgent', 'queue_number').first()
    if next_item:
        next_item.status = active_status
        next_item.call_queue_date = timezone.now() # บันทึกเวลาที่เรียกคิว
        next_item.save()
            
    return JsonResponse({'success': True})

def finish_current_queue(request):
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        done_status = QueueStatus.objects.get(code='DONE')
    except QueueStatus.DoesNotExist:
        return redirect('dashboard')
    
    # ดึงรายการที่กำลัง Active อยู่ตอนนี้ (เฉพาะ Normal)
    current_items = QueueItem.objects.filter(status=active_status, is_adhoc=0)
    
    for item in current_items:
        item.status = done_status
        item.save()
        
    return redirect('dashboard')

@csrf_exempt
def insert_queue_adhoc(request):
    """
    แทรกคิว (Ad-hoc)
    - เปลี่ยนสถานะคิวที่เลือกเป็น ACTIVE (2)
    - เงื่อนไข: ต้องไม่มีคิวที่กำลัง ACTIVE อยู่ในขณะนั้น
    """
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            
            # 1. ตรวจสอบว่ามีคิว *Ad-hoc* ที่กำลังดำเนินการอยู่หรือไม่ (Normal ไม่เกี่ยว)
            active_status = QueueStatus.objects.get(code='ACTIVE')
            if QueueItem.objects.filter(status=active_status, is_adhoc=1).exists():
                return JsonResponse({
                    'success': False, 
                    'error': 'มีรายการคิวแทรก (Ad-hoc) ที่กำลังดำเนินการอยู่ ไม่สามารถแทรกคิวซ้ำซ้อนได้ กรุณาจบงานคิวแทรกปัจจุบันก่อน'
                })
            
            # 2. ดึงข้อมูลคิวที่ต้องการแทรก
            queue_item = QueueItem.objects.get(id=item_id)
            
            # 3. อัปเดตสถานะเป็น ACTIVE
            queue_item.status = active_status
            queue_item.call_queue_date = timezone.now() # บันทึกเวลาที่เรียกคิว
            queue_item.is_adhoc = 1 # เป็นคิวที่ถูกแทรก
            
            queue_item.save()
            
            return JsonResponse({'success': True})
            
        except QueueItem.DoesNotExist:
            return JsonResponse({'success': False, 'error': 'Queue Item not found'})
        except QueueStatus.DoesNotExist:
             return JsonResponse({'success': False, 'error': 'Status ACTIVE not found'})
        except Exception as e:
            return JsonResponse({'success': False, 'error': str(e)})
            
    return JsonResponse({'success': False, 'error': 'Invalid request method'})

def finish_adhoc_queue(request):
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        done_status = QueueStatus.objects.get(code='DONE')
    except QueueStatus.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'System statuses not defined'})
    
    # ดึงรายการ Ad-hoc ที่กำลัง Active อยู่ตอนนี้
    current_items = QueueItem.objects.filter(status=active_status, is_adhoc=1)
    
    # 1. Validation Logic: ตรวจสอบสถานะ BMS ของรายการ Ad-hoc ก่อนปิด
    for item in current_items:
        if item.linked_job_no:
            try:
                bms_job = JobsBms.objects.get(jobno=item.linked_job_no)
                if bms_job.job_status not in ['2', '12']:
                     return JsonResponse({
                         'success': False, 
                         'error': f'ไม่สามารถปิดงานคิวแทรก (Ad-hoc) ได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: {bms_job.get_job_status_display()})'
                     })
            except JobsBms.DoesNotExist:
                pass

    # 2. ปิดงาน (ถ้าผ่าน Validation)
    updated_count = 0
    for item in current_items:
        item.status = done_status
        item.save()
        updated_count += 1
        
    if updated_count == 0 and not current_items.exists():
         # กรณีไม่มีรายการ (อาจจะถูกปิดไปแล้ว)
         return JsonResponse({'success': True})
        
    return JsonResponse({'success': True})
