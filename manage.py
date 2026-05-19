#!/usr/bin/env python
"""Management Command ของ Django สำหรับจัดการงานดูแลระบบ (Administrative tasks)"""
import os
import sys


def main():
    """รันคำสั่ง administrative tasks"""
    os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
    try:
        from django.core.management import execute_from_command_line
    except ImportError as exc:
        raise ImportError(
            "ไม่สามารถ import Django ได้ กรุณาตรวจสอบว่าติดตั้งเรียบร้อยแล้ว "
            "และค่าตัวแปร PYTHONPATH ถูกต้อง หรือลืม activate virtual environment หรือเปล่า?"
        ) from exc
    execute_from_command_line(sys.argv)


if __name__ == '__main__':
    main()
