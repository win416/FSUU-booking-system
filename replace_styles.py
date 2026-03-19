#!/usr/bin/env python
# -*- coding: utf-8 -*-

file_path = r'c:\xampp\htdocs\FSUU-booking-system-1\admin\schedule.php'

# Read the file
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Make the replacements
content = content.replace('style="width:100%"', 'class="w-100"')
content = content.replace('style="background:#0d6efd"', 'class="legend-dot--appt"')
content = content.replace('style="background:#dc3545"', 'class="legend-dot--blocked"')

# Write the file back
with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print('Done')
