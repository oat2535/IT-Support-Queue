from django.apps import AppConfig


class QueueAppConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'queue_app'

    def ready(self):
        import os
        from . import scheduler
        
        # ป้องกันไม่ให้ scheduler รันซ้ำ 2 รอบ เวลาใช้ runserver ที่มี autoreload
        # RUN_MAIN จะถูก set โดย auto-reloader ของ Django
        if os.environ.get('RUN_MAIN', None) == 'true':
            scheduler.start()

