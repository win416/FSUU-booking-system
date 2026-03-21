#!/usr/bin/env python
import os
import sys

files_to_delete = [
    r'c:\xampp\htdocs\FSUU-booking-system-1\replace_styles.py',
    r'c:\xampp\htdocs\FSUU-booking-system-1\setup_debug_data.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\debug_appointments.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\debug_sql_error.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\check_medical_info.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\check_users_schema.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\tmp_db_check.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\tmp_inspect_db.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\test_api_approval.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\test_reports_api.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\debug_api.log',
    r'c:\xampp\htdocs\FSUU-booking-system-1\cleanup.bat',
    r'c:\xampp\htdocs\FSUU-booking-system-1\cleanup_files.bat',
    r'c:\xampp\htdocs\FSUU-booking-system-1\cleanup_files.ps1',
    r'c:\xampp\htdocs\FSUU-booking-system-1\admin\setup-test-data.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\admin\migrate-verification.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\tests\test-connection.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\tests\check-tables.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\auth\verify-email.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\auth\resend-code.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\auth\debug_email.log',
    r'c:\xampp\htdocs\FSUU-booking-system-1\includes\SimpleMailer.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\includes\email_helper.php',
    r'c:\xampp\htdocs\FSUU-booking-system-1\assets\css\auth-verify.css',
]

deleted = []
not_found = []

for file_path in files_to_delete:
    if os.path.exists(file_path):
        try:
            os.remove(file_path)
            deleted.append(file_path)
            print('[OK] Deleted: ' + file_path)
        except Exception as e:
            not_found.append(file_path)
            print('[ERROR] Failed to delete: ' + file_path + ' (' + str(e) + ')')
    else:
        not_found.append(file_path)
        print('[NOTFOUND] Not found: ' + file_path)

# Check if tests directory is empty and remove if so
tests_dir = r'c:\xampp\htdocs\FSUU-booking-system-1\tests'
if os.path.exists(tests_dir):
    items = os.listdir(tests_dir)
    if len(items) == 0:
        try:
            os.rmdir(tests_dir)
            print('[OK] Deleted empty tests directory')
        except Exception as e:
            print('[ERROR] Failed to delete tests directory: ' + str(e))
    else:
        print('[INFO] Tests directory not empty (' + str(len(items)) + ' items remain)')

print()
print('=== SUMMARY ===')
print('Deleted: ' + str(len(deleted)) + ' files')
print('Not found: ' + str(len(not_found)) + ' files')

if not_found:
    print()
    print('Files not found:')
    for f in not_found:
        print('  - ' + f)
