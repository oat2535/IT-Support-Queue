from django.core.management.base import BaseCommand
from queue_app.models import JobsBms, QueueItem

class Command(BaseCommand):
    help = 'Strips microseconds from datetime fields in JobsBms and QueueItem'

    def handle(self, *args, **options):
        self.stdout.write("cleaning JobsBms data...")
        jobs = JobsBms.objects.all()
        job_count = jobs.count()
        for i, job in enumerate(jobs):
            job.save() # This triggers the overridden save() which strips microseconds
            if (i+1) % 100 == 0:
                self.stdout.write(f"Processed {i+1}/{job_count} JobsBms records")
        self.stdout.write(self.style.SUCCESS(f"Finished cleaning {job_count} JobsBms records"))

        self.stdout.write("cleaning QueueItem data...")
        items = QueueItem.objects.all()
        item_count = items.count()
        for i, item in enumerate(items):
            item.save()
            if (i+1) % 100 == 0:
                self.stdout.write(f"Processed {i+1}/{item_count} QueueItem records")
        self.stdout.write(self.style.SUCCESS(f"Finished cleaning {item_count} QueueItem records"))
