import pyodbc
from .models import JobsBms
from datetime import datetime
import socket
from functools import lru_cache

def sync_jobs_from_mssql():
    """
    เชื่อมต่อฐานข้อมูล MSSQL และดึงข้อมูลงานซ่อม (Sync Jobs) ตามเงื่อนไข
    คืนค่าจำนวนรายการที่ sync ไปได้
    """
    count = 0
    try:
        conn = get_mssql_connection()
        if not conn:
            return 0
            
        cursor = conn.cursor()
        
        # 1. Sync รายการใหม่ที่เป็น Active หรือ Waiting (ตาม Logic เดิม)
        # ---------------------------------------------------------
        sql_new = """
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
            jobs.outsource_date,
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
                    WHERE jobs.job_status IN ('11', '1') 
                    AND jobs.dept_control = '2'
        ORDER BY
            jobs.req_date
        """
        
        cursor.execute(sql_new)
        rows = cursor.fetchall()
        
        columns = [column[0] for column in cursor.description]
        
        for row in rows:
            data = dict(zip(columns, row))
            update_or_create_job(data)
            count += 1
            
        print(f"[{datetime.now().strftime('%d/%b/%Y %H:%M:%S')}] Synced {count} new/active jobs from MSSQL.")
        
        # 2. Sync Update สำหรับรายการที่มีอยู่แล้วในระบบทั้งหมด (Round 2 Sync)
        # ---------------------------------------------------------
        updated_count = sync_existing_jobs_updates(cursor, columns) # ส่ง cursor และ columns definition ไปใช้ต่อ
        print(f"[{datetime.now().strftime('%d/%b/%Y %H:%M:%S')}] Updated {updated_count} existing jobs from MSSQL.")
        
        # หลังจาก Sync Job เสร็จ ให้เอา Job ไปสร้างเป็น QueueItem ต่อทันที
        # หลังจาก Sync Job เสร็จ ให้เอา Job ไปสร้างเป็น QueueItem ต่อทันที
        sync_to_queue_items()
        
        # เพิ่มเติม: Logic Update Status ตามเงื่อนไข Outsource Date
        update_queue_status_from_logic()
            
    except Exception as e:
        print(f"Error syncing from MSSQL: {e}")
    finally:
        if 'conn' in locals() and conn:
            conn.close()
            
    return count

def get_mssql_connection():
    """
    Function: เชื่อมต่อฐานข้อมูล MSSQL
    หน้าที่: สร้าง Connection String เพื่อเชื่อมต่อไปยัง Server BMS (ระบบแจ้งซ่อมเก่า)
    """
    # รายละเอียดการเชื่อมต่อ MSSQL
    server = '192.168.99.224' 
    database = 'BMSDB' 
    username = 'kanchana_a' 
    password = 'Bms@2025' 
    
    # ตรวจสอบ Driver ODBC ในเครื่อง
    drivers = [driver for driver in pyodbc.drivers() if 'SQL Server' in driver]
    if not drivers:
        print("No SQL Server ODBC drivers found!")
        return None
    driver = drivers[0]
    
    # สร้าง Connection String (รองรับ TrustServerCertificate สำหรับ Self-signed SSL)
    conn_str = f'DRIVER={{{driver}}};SERVER={server};DATABASE={database};UID={username};PWD={password};TrustServerCertificate=yes'
    try:
        return pyodbc.connect(conn_str)
    except Exception as e:
        print(f"Error connecting to MSSQL: {e}")
        return None

def update_or_create_job(data):
    """ 
    Helper function to update or create a single Job record 
    หน้าที่: อัปเดตข้อมูล JobsBms หรือสร้างใหม่ถ้ายังไม่มี (Upsert)
    - data['jobno'] เป็น Key ในการค้นหา
    - ตัด microseconds ออกจากวันที่เพื่อความสะอาดของข้อมูล
    """
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
            'outsource_date': data['outsource_date'].replace(microsecond=0) if data['outsource_date'] else None,
            'difficulty': data['difficulty'],
            'job_category_type': data['job_category_type'],
            'abb_desc': data['abb_desc'],
            'descriptions': data['descriptions'],
        }
    )

def sync_existing_jobs_updates(cursor, columns_template=None):
    """
    Sync รอบที่ 2: ดึงข้อมูลของ Job ที่มีอยู่แล้วใน Local DB ทั้งหมด
    กลับไปเช็คที่ MSSQL ว่ามีการอัปเดตหรือไม่ (เช่น เปลี่ยนสถานะเป็น Closed)
    Logic:
    1. ดึง ID (jobno) ทั้งหมดจาก Local DB
    2. แบ่ง ID เป็น Chunk (ชุดละ 50) เพื่อไม่ให้ Query ยาวเกินไป
    3. สร้าง SQL Query โดยใช้ WHERE IN (...) เพื่อดึงข้อมูลอัปเดตเฉพาะ ID เหล่านั้น
    """
    updated_count = 0
    
    # 1. ดึง ID ของ Job ทั้งหมดที่มีในระบบ
    all_job_ids = list(JobsBms.objects.values_list('jobno', flat=True))
    
    if not all_job_ids:
        return 0
        
    # 2. แบ่งเป็น Chunk (เช่น ทีละ 50 ID) เพื่อไม่ให้ Query ยาวเกินไป
    chunk_size = 50
    for i in range(0, len(all_job_ids), chunk_size):
        chunk_ids = all_job_ids[i:i + chunk_size]
        
        if not chunk_ids:
            continue
            
        # สร้างเงื่อนไข IN (...)
        ids_placeholder = ', '.join(map(str, chunk_ids))
        
        # Reuse SQL query logic but filter by specific IDs
        sql = f"""
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
            jobs.outsource_date,
             CASE
                WHEN description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%' OR description LIKE N'%security%' OR 
                        description LIKE N'%server%' OR description LIKE N'%firewall%' OR description LIKE N'%database%' OR
                        description LIKE N'%ความปลอดภัย%' THEN 5        
                WHEN description LIKE N'%ติดตั้งระบบใหม่%' OR description LIKE N'%เปลี่ยนอะไหล่%' OR description LIKE N'%ซ่อมบอร์ด%' OR 
                        description LIKE N'%ไวรัส%' OR description LIKE N'%กู้ข้อมูล%' OR description LIKE N'%คอมพิวเตอร์(ชำรุด)%' THEN 4        
                WHEN description LIKE N'%เพิ่มสายแลน%' OR description LIKE N'%ย้ายโทรศัพท์%' OR description LIKE N'%อินเทอร์เน็ต หลุด%' OR 
                        description LIKE N'%อินเทอร์เน็ต ช้า%' OR description LIKE N'%ไฟดับ%' THEN 2        
                WHEN description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์ ไม่ได้%' OR description LIKE N'%ตั้งค่า%' OR 
                        description LIKE N'%เปลี่ยนรหัส%' OR description LIKE N'%เมาส์%' OR description LIKE N'%คีย์บอร์ด%' THEN 1
                ELSE 3
            END AS difficulty,
            CASE
                WHEN description LIKE N'%server%' OR description LIKE N'%database%' OR description LIKE N'%firewall%' OR
                        description LIKE N'%network core%' OR description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%' THEN 'SERVER'        
                WHEN description LIKE N'%excel%' OR description LIKE N'%word%' OR description LIKE N'%windows%' OR
                        description LIKE N'%ลงโปรแกรม%' OR description LIKE N'%ตั้งค่า%' OR description LIKE N'%รหัส%' THEN 'APP'        
                WHEN description LIKE N'%คอม%' OR description LIKE N'%computer%' OR description LIKE N'%จอ%' OR
                        description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์%' OR description LIKE N'%เมาส์%' OR
                        description LIKE N'%คีย์บอร์ด%' OR description LIKE N'%โทรศัพท์%' OR description LIKE N'%สายแลน%' THEN 'ENDPOINT'        
                ELSE 'OTHER'
                END AS job_category_type,
                md.abb_desc,
                md.descriptions
        FROM
            jobs
            left join employee e on e.emp_id = jobs.emp_id
            left join m_dept md on jobs.dept = md.dept           
        WHERE 
            jobs.jobno IN ({ids_placeholder})
        """
        
        try:
            cursor.execute(sql)
            rows = cursor.fetchall()
            
            # ถ้าไม่มี columns_template ให้หาจาก cursor (เผื่อกรณีใช้แยก)
            columns = [column[0] for column in cursor.description]
            
            for row in rows:
                data = dict(zip(columns, row))
                
                # Check dept_tech condition: Must start with 'T' (เฉพาะแผนก Tech)
                dept_tech = data.get('dept_tech', '')
                if dept_tech and not dept_tech.startswith('T'):
                    from .models import QueueItem
                    QueueItem.objects.filter(linked_job_no=data['jobno']).delete()
                    JobsBms.objects.filter(jobno=data['jobno']).delete()
                    print(f"Deleted Job {data['jobno']} because dept_tech '{dept_tech}' does not start with 'T'")
                    continue

                update_or_create_job(data)
                updated_count += 1
                
        except Exception as e:
            print(f"Error syncing chunk {chunk_ids}: {e}")
            
    return updated_count

def sync_to_queue_items():
    """
    Sync ข้อมูลจาก JobsBms ไปยัง QueueItem (ตารางคิว)
    - สร้าง QueueItem เฉพาะรายการใหม่ที่ยังไม่เคยมี (เช็คจาก linked_job_no)
    - กำหนดเลขคิวรันต่อเนื่อง (IT-XXXX)
    - ตั้งสถานะเริ่มต้นเป็น Waiting
    """
    from .models import QueueItem, QueueStatus, JobsBms
    
    # ตรวจสอบว่ามีสถานะเริ่มต้น 'Waiting' หรือยัง
    try:
        waiting_status = QueueStatus.objects.get(id=1)
    except QueueStatus.DoesNotExist:
        # กรณีที่ไม่มี ID 1 (Seed ไว้แล้ว แต่กันเหนียว)
        waiting_status, _ = QueueStatus.objects.get_or_create(
            id=1,
            defaults={'code': 'waiting', 'name': 'Waiting', 'color': 'warning'}
        )
    
    # ดึง Job ที่ยังไม่เคย Sync โดยเรียงตามวันที่แจ้งซ่อม (req_date)
    # เราใช้ linked_job_no เพื่อตรวจสอบว่าเคยมีแล้วหรือยังใน QueueItem
    existing_linked_ids = QueueItem.objects.filter(linked_job_no__isnull=False).values_list('linked_job_no', flat=True)
    
    jobs_to_sync = JobsBms.objects.exclude(jobno__in=existing_linked_ids).order_by('req_date')
    
    for job in jobs_to_sync:
        # Generate Queue Number: IT-{running_number}
        # คำนวณเลขล่าสุด + 1
        
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
        
        # ป้องกันเลขซ้ำ (Concurrency Safety Check)
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

def update_queue_status_from_logic():
    """
    อัปเดตสถานะของ QueueItem ตาม Business Logic เพิ่มเติม
    1. ถ้ามี outsource_date ให้เป็นสถานะ ID 5 (รอประสานงาน/รออะไหล่)
    2. ยกเลิกการใช้ ID 6 (รออะไหล่) โดยย้ายไป 5 ทั้งหมด
    """
    from .models import QueueItem, QueueStatus, JobsBms
    
    # 1. จัดการ Status ID 6 -> 5
    try:
        status_5 = QueueStatus.objects.get(id=5)
        
        # ย้ายรายการที่เคยเป็น 6 มาเป็น 5
        QueueItem.objects.filter(status__id=6).update(status=status_5)
        
        # ลบ Status 6 ทิ้ง (ถ้าต้องการทำความสะอาด DB)
        QueueStatus.objects.filter(id=6).delete()
        
    except QueueStatus.DoesNotExist:
        pass
        
    # 2. Update Status เป็น 5 ถ้ามี outsource_date
    # เราเลือกเฉพาะรายการที่ยังไม่เสร็จ (Done) หรือ Active หรือ Waiting
    # และต้องไม่เป็นสถานะ 5 อยู่แล้ว (เพื่อลด load update)
    
    try:
        status_5 = QueueStatus.objects.get(id=5)
        status_1 = QueueStatus.objects.get(id=1)
        done_status = QueueStatus.objects.get(code='DONE')
        
        # 2.1 Case: มี outsource_date -> Set Status 5
        jobs_with_outsource = JobsBms.objects.exclude(outsource_date__isnull=True).values_list('jobno', flat=True)
        
        target_items_to_5 = QueueItem.objects.filter(linked_job_no__in=jobs_with_outsource).exclude(status=done_status).exclude(status=status_5)
        updated_count_5 = target_items_to_5.update(status=status_5)
        
        if updated_count_5 > 0:
            print(f"Updated {updated_count_5} items to Status 5 (Coordinating) due to outsource_date present.")

        # 2.2 Case: outsource_date เป็นค่าว่าง (ถูกลบออก) -> Revert Status 5 กลับเป็น 1 (Waiting)
        # เฉพาะรายการที่เป็น Status 5 อยู่
        jobs_without_outsource = JobsBms.objects.filter(outsource_date__isnull=True).values_list('jobno', flat=True)
        
        target_items_to_1 = QueueItem.objects.filter(linked_job_no__in=jobs_without_outsource, status=status_5)
        updated_count_1 = target_items_to_1.update(status=status_1)
        
        if updated_count_1 > 0:
            print(f"Updated {updated_count_1} items back to Status 1 (Waiting) due to outsource_date cleared.")

    except QueueStatus.DoesNotExist:
        pass

@lru_cache(maxsize=128)
def get_hostname_from_ip(ip_address):
    """
    Function: Resolve Hostname from IP (with Caching)
    หน้าที่: แปลง IP (เช่น 192.168.1.33) เป็นชื่อเครื่อง (Hostname)
    - ใช้ @lru_cache เพื่อเก็บค่าที่เคยหาแล้วไว้ใน Memory ไม่ต้องหาใหม่ทุกครั้ง
    - ช่วยลดเวลาโหลดหน้าเว็บกรณี Network ช้า
    """
    try:
        hostname, _, _ = socket.gethostbyaddr(ip_address)
        return hostname
    except Exception:
        return None

def get_client_ip(request):
    """
    Function: ดึง IP Address ของ Client
    รองรับทั้งการเชื่อมต่อตรง (Direct) และผ่าน Proxy (Nginx/Load Balancer)
    โดยเช็คจาก HTTP_X_FORWARDED_FOR ก่อน
    """
    x_forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR')
    if x_forwarded_for:
        # กรณีผ่าน Proxy: Header จะเป็น list ของ IP เช่น "client_ip, proxy1_ip, proxy2_ip"
        # เราต้องการ IP แรกสุด (Client IP จริง)
        ip = x_forwarded_for.split(',')[0].strip()
    else:
        # กรณีเชื่อมต่อตรง
        ip = request.META.get('REMOTE_ADDR')
    return ip
