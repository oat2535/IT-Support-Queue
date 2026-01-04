import pyodbc
from .models import JobsBms
from datetime import datetime

def sync_jobs_from_mssql():
    """
    Connects to MSSQL and syncs jobs matching the criteria.
    Returns the number of jobs synced.
    """
    # MSSQL Connection details
    server = '192.168.99.224' 
    database = 'BMSDB' 
    username = 'kanchana_a' 
    password = 'Bms@2025' 
    
    drivers = [driver for driver in pyodbc.drivers() if 'SQL Server' in driver]
    if not drivers:
        print("No SQL Server ODBC drivers found!")
        return 0
    driver = drivers[0]
    
    conn_str = f'DRIVER={{{driver}}};SERVER={server};DATABASE={database};UID={username};PWD={password}'
    
    count = 0
    try:
        conn = pyodbc.connect(conn_str)
        cursor = conn.cursor()
        
        sql = """
        SELECT
            jobs.jobno,
            jobs.catagory,
            jobs.description,
            jobs.dept_tech,
            e.name,
            jobs.jobdate,
            jobs.assign_date,
            jobs.arrive_date,
            jobs.req_date,
            jobs.caller,
            jobs.sap_code,
            jobs.aname,
            jobs.note,
            jobs.act_dstart,
            jobs.act_dfin,
            jobs.job_status,
            jobs.return_date,
            jobs.enterdate,
            jobs.enterby,
            -- คอลัมน์ใหม่สำหรับระดับความยาก (1 = ง่ายที่สุด, 5 = ยากที่สุด)
            CASE
                -- ระดับ 5: ยากที่สุด (ระบบวิกฤต/ความปลอดภัย/โครงสร้างหลัก)
                WHEN description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%' OR description LIKE N'%security%' OR 
                        description LIKE N'%server%' OR description LIKE N'%firewall%' OR description LIKE N'%database%' OR
                        description LIKE N'%ความปลอดภัย%'
                THEN 5        
                -- ระดับ 4: ค่อนข้างยาก (การติดตั้งใหญ่/ซ่อมซับซ้อน/กู้ข้อมูล)
                WHEN description LIKE N'%ติดตั้งระบบใหม่%' OR description LIKE N'%เปลี่ยนอะไหล่%' OR description LIKE N'%ซ่อมบอร์ด%' OR 
                        description LIKE N'%ไวรัส%' OR description LIKE N'%กู้ข้อมูล%' OR description LIKE N'%คอมพิวเตอร์(ชำรุด)%'
                THEN 4        
                -- ระดับ 2: ค่อนข้างง่าย (การเชื่อมต่อ/ย้ายจุด/เน็ตพื้นฐาน)
                WHEN description LIKE N'%เพิ่มสายแลน%' OR description LIKE N'%ย้ายโทรศัพท์%' OR description LIKE N'%อินเทอร์เน็ต หลุด%' OR 
                        description LIKE N'%อินเทอร์เน็ต ช้า%' OR description LIKE N'%ไฟดับ%'
                THEN 2        
                -- ระดับ 1: ง่ายที่สุด (อุปกรณ์ต่อพ่วง/การตั้งค่าเล็กน้อย)
                WHEN description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์ ไม่ได้%' OR description LIKE N'%ตั้งค่า%' OR 
                        description LIKE N'%เปลี่ยนรหัส%' OR description LIKE N'%เมาส์%' OR description LIKE N'%คีย์บอร์ด%'
                THEN 1
                        -- ระดับ 3: ปานกลาง (ค่าเริ่มต้น/ปัญหาทั่วไปที่ไม่เข้าข่ายระดับอื่น)
                ELSE 3
            END AS difficulty,
            CASE
                -- SERVER Category
                WHEN description LIKE N'%server%' OR description LIKE N'%database%' OR description LIKE N'%firewall%' OR
                        description LIKE N'%network core%' OR description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%'
                THEN 'SERVER'        
                -- APP Category
                WHEN description LIKE N'%excel%' OR description LIKE N'%word%' OR description LIKE N'%windows%' OR
                        description LIKE N'%ลงโปรแกรม%' OR description LIKE N'%ตั้งค่า%' OR description LIKE N'%รหัส%'
                THEN 'APP'        
                -- ENDPOINT Category
                WHEN description LIKE N'%คอม%' OR description LIKE N'%computer%' OR description LIKE N'%จอ%' OR
                        description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์%' OR description LIKE N'%เมาส์%' OR
                        description LIKE N'%คีย์บอร์ด%' OR description LIKE N'%โทรศัพท์%' OR description LIKE N'%สายแลน%'
                THEN 'ENDPOINT'        
                -- Default Category
                ELSE 'OTHER'
                END AS job_category_type,
                md.abb_desc,
                md.descriptions
        FROM
            jobs
            left join employee e on e.emp_id = jobs.emp_id
            left join m_dept md on jobs.dept = md.dept
            WHERE jobs.req_date >= '2025-12-01' 
                    AND jobs.job_status IN ('11', '1') 
                    AND jobs.dept_control = '2'
        ORDER BY
            jobs.req_date
        """
        
        cursor.execute(sql)
        rows = cursor.fetchall()
        
        columns = [column[0] for column in cursor.description]
        
        for row in rows:
            data = dict(zip(columns, row))
            
            JobsBms.objects.update_or_create(
                jobno=data['jobno'],
                defaults={
                    'catagory': data['catagory'],
                    'description': data['description'],
                    'dept_tech': data['dept_tech'],
                    'name': data['name'],
                    'jobdate': data['jobdate'].replace(microsecond=0) if data['jobdate'] else None,
                    'assign_date': data['assign_date'].replace(microsecond=0) if data['assign_date'] else None,
                    'arrive_date': data['arrive_date'].replace(microsecond=0) if data['arrive_date'] else None,
                    'req_date': data['req_date'].replace(microsecond=0) if data['req_date'] else None,
                    'caller': data['caller'],
                    'sap_code': data['sap_code'],
                    'aname': data['aname'],
                    'note': data['note'],
                    'act_dstart': data['act_dstart'].replace(microsecond=0) if data['act_dstart'] else None,
                    'act_dfin': data['act_dfin'].replace(microsecond=0) if data['act_dfin'] else None,
                    'job_status': data['job_status'],
                    'return_date': data['return_date'].replace(microsecond=0) if data['return_date'] else None,
                    'enterdate': data['enterdate'].replace(microsecond=0) if data['enterdate'] else None,
                    'enterby': data['enterby'],
                    'difficulty': data['difficulty'],
                    'job_category_type': data['job_category_type'],
                    'abb_desc': data['abb_desc'],
                    'descriptions': data['descriptions'],
                }
            )
            count += 1
            
        print(f"[{datetime.now().strftime('%d/%b/%Y %H:%M:%S')}] Synced {count} jobs from MSSQL.")
        
        # Now sync to QueueItem
        sync_to_queue_items()
            
    except Exception as e:
        print(f"Error syncing from MSSQL: {e}")
    finally:
        if 'conn' in locals():
            conn.close()
            
    return count

def sync_to_queue_items():
    """
    Syncs data from JobsBms to QueueItem based on linked_job_no.
    """
    from .models import QueueItem, QueueStatus, JobsBms
    
    
    # Ensure default status 'Waiting' exists
    # User requested default status_id = 1
    try:
        waiting_status = QueueStatus.objects.get(id=1)
    except QueueStatus.DoesNotExist:
        # Fallback if ID 1 doesn't exist (unlikely if seeded, but safe)
        waiting_status, _ = QueueStatus.objects.get_or_create(
            id=1,
            defaults={'code': 'waiting', 'name': 'Waiting', 'color': 'warning'}
        )
    
    # Get un-synced jobs ordered by req_date
    # We use linked_job_no to check existence
    existing_linked_ids = QueueItem.objects.filter(linked_job_no__isnull=False).values_list('linked_job_no', flat=True)
    
    jobs_to_sync = JobsBms.objects.exclude(jobno__in=existing_linked_ids).order_by('req_date')
    
    for job in jobs_to_sync:
        # Generate Queue Number: IT-{running_number}
        # We need to find the last queue number to increment. 
        # But wait, requirement says "running number by req_date asc".
        # If we just append, it works for new items.
        
        last_item = QueueItem.objects.all().order_by('id').last()
        if last_item and last_item.queue_number.startswith('IT-'):
            try:
                last_num = int(last_item.queue_number.split('-')[1])
                new_num = last_num + 1
            except ValueError:
                new_num = 1
        else:
            new_num = 1
            
        queue_number = f"IT-{new_num:04d}"
        
        # Check collision just in case (though we check last_item, concurrency might be an issue but low traffic assumed)
        while QueueItem.objects.filter(queue_number=queue_number).exists():
            new_num += 1
            queue_number = f"IT-{new_num:04d}"
        
        QueueItem.objects.create(
            queue_number=queue_number,
            user_name=job.caller or 'Unknown',
            user_department=job.descriptions or 'Unknown',
            issue_description=job.description or '',
            created_at=job.req_date,
            status=waiting_status,
            linked_job_no=job.jobno
        )
        print(f"Created QueueItem {queue_number} for Job {job.jobno}")
