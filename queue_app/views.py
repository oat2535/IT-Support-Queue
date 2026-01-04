from django.shortcuts import render, redirect
from django.utils import timezone
from django.core.paginator import Paginator, EmptyPage, PageNotAnInteger
from django.db.models import Q
from .models import QueueItem, QueueStatus, JobsBms
from .utils import sync_jobs_from_mssql 
# Sync is now handled by management command: python manage.py import_job_analysis
from django.views.decorators.csrf import csrf_exempt
from django.http import JsonResponse
import json
import random

def dashboard(request):
    # We rely on the management command to sync data now.
    # sync_jobs_from_mssql() is removed from here to improve performance
    
    # query QueueItem instead of JobsBms
    items = QueueItem.objects.all()
    
    total_today = items.count()
    
    # Status Counts
    waiting_count = items.filter(status__code='WAITING').count()
    active_count = items.filter(status__code='ACTIVE').count()
    done_count = items.filter(status__code='DONE').count()
    
    # Current Active Queue
    current_queue = items.filter(status__code='ACTIVE').order_by('created_at').first()
    
    # Filter Logic
    status_filter = request.GET.get('status', 'waiting') # Keep URL param lowercase for aesthetics if desired, but map to upper
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

    # Search Logic
    if search_query:
        queue_list = queue_list.filter(
            Q(issue_description__icontains=search_query) | 
            Q(user_name__icontains=search_query) |
            Q(queue_number__icontains=search_query)
        )

    # Pagination Logic
    paginator = Paginator(queue_list, 5) # 5 items per page
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
            comment = data.get('comment') # Changed from description to comment
            
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
    # Check if there is currently an active queue
    try:
        active_status = QueueStatus.objects.get(code='ACTIVE')
        waiting_status = QueueStatus.objects.get(code='WAITING')
    except QueueStatus.DoesNotExist:
        # Avoid crashing if status doesn't exist, maybe redirect or log
        return redirect('dashboard')
    
    current_active = QueueItem.objects.filter(status=active_status).exists()
    
    if current_active:
        # Cannot call next if one is already active (handled by UI but good for safety)
        pass 
    else:
        # Get next waiting item
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
    
    # Get current active item
    current_items = QueueItem.objects.filter(status=active_status)
    
    for item in current_items:
        item.status = done_status
        item.save()
        
    return redirect('dashboard')
