from django.urls import path
from . import views

urlpatterns = [
    path('', views.dashboard, name='dashboard'),
    path('add-queue/', views.add_queue_item, name='add_queue'),
    path('call-next/', views.call_next_queue, name='call_next'),
    path('finish-queue/', views.finish_current_queue, name='finish_queue'),
    path('update-job-desc/', views.update_job_description, name='update_job_desc'),
    path('toggle-urgent/', views.toggle_urgent_status, name='toggle_urgent'),
]
