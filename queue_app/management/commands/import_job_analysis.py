from django.core.management.base import BaseCommand
from queue_app.utils import sync_jobs_from_mssql

class Command(BaseCommand):
    help = 'Sync jobs from BMS (MSSQL) to local database'

    def handle(self, *args, **kwargs):
        self.stdout.write("Starting sync job...")
        try:
            count = sync_jobs_from_mssql()
            self.stdout.write(self.style.SUCCESS(f'Successfully synced {count} jobs.'))
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'Error syncing jobs: {e}'))
