from django.apps import AppConfig


class QueueAppConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'queue_app'

    def ready(self):
        import os
        from . import scheduler
        
        # Prevent scheduler from starting twice when using runserver with autoreload
        # RUN_MAIN is set by the auto-reloader
        if os.environ.get('RUN_MAIN', None) == 'true':
            scheduler.start()

