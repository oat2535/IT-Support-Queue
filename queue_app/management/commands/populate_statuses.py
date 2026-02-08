from django.core.management.base import BaseCommand
from queue_app.models import QueueStatus

class Command(BaseCommand):
    help = 'Populate initial QueueStatus data'

    def handle(self, *args, **kwargs):
        statuses = [
            {'code': 'WAITING', 'name': 'รอรับบริการ', 'color': 'warning', 'id': 1},
            {'code': 'ACTIVE', 'name': 'กำลังดำเนินการ', 'color': 'info', 'id': 2},
            {'code': 'DONE', 'name': 'เสร็จสิ้น', 'color': 'success', 'id': 3},
            # Using 5 for Coordinating/Spare Parts as inferred from utils.py logic
            {'code': 'COORDINATING', 'name': 'รอประสานงาน', 'color': 'primary', 'id': 5}, 
             # WAITING_PARTS might be deprecated as per utils.py logic (id 6 -> 5), but dashboard still references it in filter.
             # We will create it if needed or map it. Dashboard uses code 'WAITING_PARTS'.
             # Let's add it for safety if the code expects it, but maybe as id 6.
            {'code': 'WAITING_PARTS', 'name': 'รออะไหล่', 'color': 'primary', 'id': 6},
        ]

        for s in statuses:
            try:
                obj, created = QueueStatus.objects.get_or_create(
                    id=s['id'],
                    defaults={
                        'code': s['code'],
                        'name': s['name'],
                        'color': s['color']
                    }
                )
                
                # Also ensure code matches if ID existed but code was different?
                if not created:
                    obj.code = s['code']
                    obj.name = s['name']
                    obj.color = s['color']
                    obj.save()
                    self.stdout.write(f"Updated status: {s['name']} (ID: {s['id']})")
                else:
                    self.stdout.write(self.style.SUCCESS(f"Created status: {s['name']} (ID: {s['id']})"))
                    
            except Exception as e:
                self.stdout.write(self.style.ERROR(f"Error processing status {s['name']}: {e}"))
