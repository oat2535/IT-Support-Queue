from apscheduler.schedulers.background import BackgroundScheduler
from queue_app.utils import sync_jobs_from_mssql
import logging

logger = logging.getLogger(__name__)

def start():
    scheduler = BackgroundScheduler()
    # Run sync every 1 minute for testing, can be adjusted
    scheduler.add_job(sync_jobs_from_mssql, 'interval', minutes=1, id='sync_mssql_job', replace_existing=True)
    scheduler.start()
    logger.info("APScheduler started: Syncing MSSQL jobs every 1 minute.")
