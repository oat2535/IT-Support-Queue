from django.shortcuts import render, redirect
from django.utils import timezone
from django.core.paginator import Paginator, EmptyPage, PageNotAnInteger
from django.db.models import Q
from .models import QueueItem, QueueStatus, JobsBms, ShiftClosure
from .utils import sync_jobs_from_mssql, get_hostname_from_ip, get_client_ip 
# การ Sync ข้อมูลถูกจัดการโดย management command แล้ว: python manage.py import_job_analysis
from django.views.decorators.csrf import csrf_exempt
from django.http import JsonResponse
import json
import socket

from .scheduler import auto_close_shift_logic

def dashboard(request):
    """
    View Function: dashboard
    หน้าที่: แสดงหน้าจอหลักของระบบคิว (Dashboard)
    - แสดงจำนวนคิวแต่ละสถานะ
    - แสดงรายการคิวที่รอ (Waiting) และกำลังดำเนินการ (Active)
    - ตรวจสอบสิทธิ์ Admin (Based on Hostname/IP)
    - ตรวจสอบเวลาปิดกะอัตโนมัติ (Auto-close Logic)
    """
    # query ข้อมูลจาก QueueItem
    items = QueueItem.objects.all()
    
    # นับจำนวนตามสถานะต่างๆ เพื่อแสดงบนการ์ดด้านบน
    now = timezone.now()
    waiting_count = items.filter(status__code='WAITING').count()
    active_count = items.filter(status__code='ACTIVE').count()
    done_count = items.filter(status__code='DONE', created_at__month=now.month, created_at__year=now.year).count()
    coordinating_count = items.filter(status__code='COORDINATING').count()
    waiting_parts_count = items.filter(status__code='WAITING_PARTS').count()
    
    # Fetch specific statuses for the dropdown (Waiting, Coordinating, Waiting Parts)
    target_codes = ['WAITING', 'COORDINATING', 'WAITING_PARTS']
    all_statuses = QueueStatus.objects.filter(code__in=target_codes).order_by('id')
    
    # คิวที่กำลังเรียกอยู่ปัจจุบัน (Normal Queue)
    current_queue = items.filter(status__code='ACTIVE', is_adhoc=0).order_by('created_at').first()
    
    # คิวแทรก (Ad-hoc) ที่กำลัง Active (ถ้ามี จะแสดงแทรกขึ้นมา)
    current_adhoc = items.filter(status__code='ACTIVE', is_adhoc=1).order_by('created_at').first()

    # Helper to attach operator name
    def attach_operator_name(queue_item):
        if queue_item and queue_item.linked_job_no:
             try:
                 job = JobsBms.objects.get(jobno=queue_item.linked_job_no)
                 queue_item.operator_name = job.name
             except JobsBms.DoesNotExist:
                 queue_item.operator_name = None
    
    attach_operator_name(current_queue)
    attach_operator_name(current_adhoc)
    
    # --- Logic การกรองข้อมูล (Filter) ---
    status_filter = request.GET.get('status', 'waiting') # รับค่าจาก URL parameter
    search_query = request.GET.get('q', '') # รับค่าค้นหา
    
    if status_filter == 'active':
        queue_list = items.filter(status__code='ACTIVE').order_by('created_at')
        list_title = "รายการที่กำลังดำเนินการ (Active)"
    elif status_filter == 'done':
        queue_list = items.filter(status__code='DONE', created_at__month=now.month, created_at__year=now.year).order_by('created_at')
        list_title = "รายการที่เสร็จสิ้น (Done)"
    elif status_filter == 'pending':
        queue_list = items.filter(status__code__in=['COORDINATING', 'WAITING_PARTS']).order_by('created_at')
        list_title = "รายการที่อยู่ระหว่างประสานงานและรออะไหล่"
    elif status_filter == 'waiting':
        # เรียงตามความเร่งด่วน (urgent) ก่อน แล้วค่อยตามเลขคิว
        queue_list = items.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number')
        list_title = "รายการที่รอคิว (Waiting)"
    else:
        queue_list = items.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number')
        list_title = "รายการที่รอคิว (Waiting)"

    # --- Logic การค้นหา (Search) ---
    if search_query:
        queue_list = queue_list.filter(
            Q(issue_description__icontains=search_query) | 
            Q(user_name__icontains=search_query) |
            Q(queue_number__icontains=search_query)
        )

    # --- Logic การแบ่งหน้า (Pagination) ---
    paginator = Paginator(queue_list, 5) # แสดง 5 รายการต่อหน้า
    page = request.GET.get('page')
    try:
        queue_list = paginator.page(page)
    except PageNotAnInteger:
        queue_list = paginator.page(1)
    except EmptyPage:
        queue_list = paginator.page(paginator.num_pages)

    # --- Logic การจัดลำดับคิว (Ranking) ---
    # คำนวณลำดับคิวจริงๆ (ไม่นับ Pagination) เพื่อแสดงผลในตาราง
    if status_filter == 'waiting':
        try:
             all_waiting_ids = list(QueueItem.objects.filter(status__code='WAITING').order_by('-is_urgent', 'queue_number').values_list('id', flat=True))
             rank_map = {pk: i+1 for i, pk in enumerate(all_waiting_ids)}
             
             for item in queue_list:
                 item.waiting_rank = rank_map.get(item.id, '-')
        except Exception as e:
            print(f"Error calculating ranks: {e}")

    # --- ตรวจสอบสิทธิ์ Admin (Based on Hostname/IP) ---
    is_admin_computer = False
    client_ip = get_client_ip(request)
    
    # ใช้ Caching Helper เพื่อลดความหน่วง
    hostname = get_hostname_from_ip(client_ip)
    print(f"Client IP: {client_ip}, Hostname: {hostname}")
    
    if hostname:
        current_hostname = hostname.upper()
        # รายชื่อเครื่องที่อนุญาตให้เป็น Admin
        admin_hosts = ['DESKTOP-TIC1FOD', 'B-IT-24']
        
        if any(admin_host in current_hostname for admin_host in admin_hosts):
             is_admin_computer = True
             
    if client_ip == '127.0.0.1':
        is_admin_computer = True

    # --- Logic ตรวจสอบและปิดกะอัตโนมัติ (Auto-Close Shift) ---
    # เรียกใช้ Logic เดียวกับ Background Task เพื่อให้ปิดทันทีถ้ามีการ Refresh หน้าจอ
    try:
        auto_close_shift_logic()
    except Exception as e:
        print(f"Error in auto_close_shift_logic from view: {e}")

    # --- สรุปสถานะการปิดกะเพื่อส่งไปที่ Template ---
    is_shift_closed = ShiftClosure.objects.filter(opened_at__isnull=True).exists()
    
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
        'current_adhoc': current_adhoc,
        'is_shift_closed': is_shift_closed,
    }
    
    return render(request, 'queue_app/dashboard.html', context)

@csrf_exempt
def update_job_description(request):
    """
    API: อัปเดตหมายเหตุ (Note) หรือสถานะของ Job จาก Modal
    """
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            comment = data.get('comment') # หมายเหตุที่ user พิมพ์มา
            status_id = data.get('status_id') # สถานะใหม่ที่ user เลือก
            
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
    """
    API: ใช้สลับสถานะความเร่งด่วน (Urgent) ของคิว
    """
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            item_id = data.get('id')
            is_urgent = data.get('is_urgent')
            
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
    """
    Function: ปุ่ม "เรียกคิวถัดไป" (Call Next)
    หน้าที่:
    1. ปิดงาน (Done) ให้กับงานที่ Active อยู่ปัจจุบัน
    2. หาคิวถัดไปที่มีสถานะ Waiting
    3. เปลี่ยนสถานะคิวถัดไปเป็น Active
    4. ตรวจสอบเงื่อนไขการปิด BMS ก่อน (Validation)
    """
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        waiting_status = QueueStatus.objects.get(code='WAITING')
        done_status = QueueStatus.objects.get(code='DONE')
    except QueueStatus.DoesNotExist:
        return JsonResponse({'success': False, 'error': 'System statuses not defined'})
    
    # 1. ตรวจสอบงานปัจจุบันก่อนปิด (Validation Logic) -> เฉพาะ Normal Queue
    current_active_items = QueueItem.objects.filter(status=active_status, is_adhoc=0)
    for item in current_active_items:
        # ถ้ามีการเชื่อมโยงกับ Job BMS -> ต้องปิดงานใน BMS ก่อน
        if item.linked_job_no:
            try:
                # ดึงข้อมูล Job BMS ล่าสุด
                bms_job = JobsBms.objects.get(jobno=item.linked_job_no)
                
                # ตรวจสอบสถานะ (Allow only '2' or '12')
                # 2=ซ่อมเสร็จ, 12=ตรวจรับงานแล้ว
                if bms_job.job_status not in ['2', '12']:
                     return JsonResponse({
                         'success': False, 
                         'error': f'ไม่สามารถกดเรียกคิวถัดไปได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: {bms_job.get_job_status_display()})'
                     })
                     
            except JobsBms.DoesNotExist:
                pass

    # 2. ปิดงานเก่า (ถ้าผ่าน Validation)
    for item in current_active_items:
        item.status = done_status
        item.save()
    
    # 3. เรียกคิวถัดไป (Priority: Urgent > Normal, แล้วเรียงตามเลขคิว)
    next_item = QueueItem.objects.filter(status=waiting_status).order_by('-is_urgent', 'queue_number').first()
    if next_item:
        next_item.status = active_status
        next_item.call_queue_date = timezone.now() # บันทึกเวลาที่เรียกคิว
        next_item.save()
            
    return JsonResponse({'success': True})

def finish_current_queue(request):
    """
    Function: ปุ่ม "จบงานโดยไม่เรียกคิวต่อ" (Finish Job)
    หน้าที่: เปลี่ยนสถานะงาน Active ปัจจุบันเป็น Done โดยไม่ดึงคิวใหม่
    """
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
    API: แทรกคิว (Ad-hoc)
    หน้าที่:
    - เปลี่ยนสถานะคิวที่เลือก (จากใน List) เป็น ACTIVE (2) ทันที
    - Note: จะทำได้ก็ต่อเมื่อไม่มีคิว Ad-hoc อื่นที่กำลัง Active อยู่
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
            queue_item.is_adhoc = 1 # Mark ว่าเป็นคิวที่ถูกแทรก
            
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
    """
    Function: ปุ่ม "จบงานคิวแทรก" (Finish Ad-hoc)
    หน้าที่: ปิดงานเฉพาะคิวที่แทรกเข้ามา (Ad-hoc) โดยไม่กระทบคิว Normal ที่อาจจะ Active ค้างอยู่
    """
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

@csrf_exempt
def toggle_shift_status(request):
    """
    API: เปลี่ยนสถานะการให้บริการ (ปิดกะ/เปิดกะ)
    Logic:
    - ถ้าปิดกะ: สร้าง Record ShiftClosure ใหม่
    - ถ้าเปิดกะ: อัปเดต opened_at ของ Record ล่าสุด
    """
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            should_close = data.get('closed')
            
            # Resolve Hostname Check (ดึงชื่อเครื่องผู้กด)
            client_ip = get_client_ip(request)
            hostname = get_hostname_from_ip(client_ip)
            
            if not hostname:
                hostname = client_ip # ใช้ IP แทนถ้าหาชื่อไม่เจอ
 
            now = timezone.now().replace(microsecond=0)
            
            if should_close:
                # สร้าง Record ปิดกะใหม่
                ShiftClosure.objects.create(
                    closed_by=hostname
                    # closed_at จะถูกใส่ Auto ใน Model
                )
                is_closed = True
            else:
                # หาประวัติการปิดกะล่าสุดที่ยังไม่เปิด
                active_closures = ShiftClosure.objects.filter(opened_at__isnull=True)
                if active_closures.exists():
                    # อัปเดตว่าเปิดกะแล้ว
                    active_closures.update(
                        opened_at=now,
                        opened_by=hostname
                    )
                is_closed = False
            
            return JsonResponse({
                'success': True, 
                'is_closed': is_closed,
                'timestamp': now.strftime('%Y-%m-%d %H:%M:%S')
            })
            
        except Exception as e:
             return JsonResponse({'success': False, 'error': str(e)})
             
    return JsonResponse({'success': False, 'error': 'Invalid request method'})
