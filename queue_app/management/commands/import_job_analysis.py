from django.core.management.base import BaseCommand
from queue_app.utils import sync_jobs_from_mssql

class Command(BaseCommand):
    help = 'Syncs Job Analysis data from SQL Server'

    def handle(self, *args, **kwargs):
        self.stdout.write("Syncing jobs from MSSQL...")
        count = sync_jobs_from_mssql()
        self.stdout.write(self.style.SUCCESS(f"Successfully synced {count} jobs."))
