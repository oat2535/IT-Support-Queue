import os
import django
from django.conf import settings

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from queue_app.models import QueueItem, QueueStatus

def check_db():
    print("Checking Database Content...")
    
    # Check Statuses
    statuses = list(QueueStatus.objects.values('id', 'code', 'name'))
    print(f"QueueStatus count: {len(statuses)}")
    for s in statuses:
        print(f" - {s['code']}: {s['name']}")
        
    # Check Items
    item_count = QueueItem.objects.count()
    print(f"QueueItem count: {item_count}")
    
    if item_count > 0:
        print("Sample items:")
        for item in QueueItem.objects.all()[:5]:
            print(f" - {item.queue_number} ({item.status.code})")

if __name__ == '__main__':
    check_db()
