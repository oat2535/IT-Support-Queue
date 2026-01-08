from queue_app.models import QueueStatus

def add_statuses():
    statuses = [
        {'code': 'COORDINATING', 'name': 'อยู่ระหว่างประสานงาน', 'color': 'primary'}, # Blue
        {'code': 'WAITING_PARTS', 'name': 'รออะไหล่', 'color': 'danger'}, # Red
        # WAITING already exists, but ensure color is warning
        {'code': 'WAITING', 'name': 'Waiting', 'color': 'warning'},
        # Ensure Active is Info
        {'code': 'ACTIVE', 'name': 'Active', 'color': 'info'},
        # Ensure Done is Success
        {'code': 'DONE', 'name': 'Done', 'color': 'success'},
    ]

    for s in statuses:
        obj, created = QueueStatus.objects.update_or_create(
            code=s['code'],
            defaults={'name': s['name'], 'color': s['color']}
        )
        if created:
            print(f"Created status: {s['name']}")
        else:
            print(f"Updated status: {s['name']}")

if __name__ == '__main__':
    add_statuses()
