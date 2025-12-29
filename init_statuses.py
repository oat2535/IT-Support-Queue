import os
import django

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from queue_app.models import QueueStatus

def init_statuses():
    statuses = [
        {'code': 'WAITING', 'name': 'Waiting', 'color': 'warning'},
        {'code': 'ACTIVE', 'name': 'Active', 'color': 'info'},
        {'code': 'DONE', 'name': 'Done', 'color': 'success'},
    ]

    for s in statuses:
        obj, created = QueueStatus.objects.get_or_create(
            code=s['code'],
            defaults={'name': s['name'], 'color': s['color']}
        )
        if created:
            print(f"Created status: {s['name']}")
        else:
            print(f"Status already exists: {s['name']}")

if __name__ == '__main__':
    init_statuses()
