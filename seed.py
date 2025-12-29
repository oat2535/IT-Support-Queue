import os
import django
from django.utils import timezone
from datetime import timedelta

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from queue_app.models import QueueItem, QueueStatus

QueueItem.objects.all().delete()
now = timezone.now()

# Done items
print("Creating Done items...")
done_status = QueueStatus.objects.get(code='DONE')
for i in range(1, 5):
    QueueItem.objects.create(
        queue_number=f'IT-{i:03}',
        user_name=f'User Done {i}',
        user_department='Account',
        issue_description='Printer issue',
        created_at=now - timedelta(hours=2, minutes=i*10),
        status=done_status
    )

# Active item
print("Creating Active item...")
active_status = QueueStatus.objects.get(code='ACTIVE')
QueueItem.objects.create(
    queue_number='IT-005',
    user_name='Khun Kong',
    user_department='Admin',
    issue_description='Internet slow',
    created_at=now - timedelta(minutes=30),
    status=active_status
)

# Waiting items
print("Creating Waiting items...")
waiting_status = QueueStatus.objects.get(code='WAITING')
QueueItem.objects.create(
    queue_number='IT-006',
    user_name='User Demo',
    user_department='General',
    issue_description='General usage issue',
    created_at=now - timedelta(minutes=10),
    status=waiting_status
)
QueueItem.objects.create(
    queue_number='IT-007',
    user_name='User Demo 2',
    user_department='General',
    issue_description='General usage issue',
    created_at=now - timedelta(minutes=5),
    status=waiting_status
)
print("Data seeded successfully.")
