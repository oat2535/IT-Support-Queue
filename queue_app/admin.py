from django.contrib import admin
from .models import QueueItem, QueueStatus

@admin.register(QueueStatus)
class QueueStatusAdmin(admin.ModelAdmin):
    list_display = ('name', 'code', 'color')
    search_fields = ('name', 'code')

@admin.register(QueueItem)
class QueueItemAdmin(admin.ModelAdmin):
    list_display = (
        'queue_number', 
        'user_name', 
        'user_department', 
        'issue_description', 
        'status', 
        'created_at'
    )
    list_filter = ('status', 'user_department')
    search_fields = ('queue_number', 'user_name', 'issue_description')
