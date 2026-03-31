import re
import os

# Files to process
css_dir = r'C:\xampp\htdocs\FSUU-booking-system-1\assets\css'
css_files = [
    'admin-dashboard.css', 'admin-messages.css', 'admin-reports.css', 'admin-patients.css',
    'admin-patient-profile.css', 'admin-schedule.css', 'patient-dashboard.css',
    'patient-book-appointment.css', 'patient-messages.css', 'patient-notifications.css',
    'patient-profile.css', 'style.css', 'auth-login.css', 'auth-register.css', 'index.css'
]
php_files = [
    r'C:\xampp\htdocs\FSUU-booking-system-1\auth\login.php',
    r'C:\xampp\htdocs\FSUU-booking-system-1\auth\register.php',
    r'C:\xampp\htdocs\FSUU-booking-system-1\index.php',
    r'C:\xampp\htdocs\FSUU-booking-system-1\auth\google_auth.php',
]

all_files = [os.path.join(css_dir, f) for f in css_files] + php_files

# Direct hex replacements (case-insensitive)
hex_replacements = [
    ('#00aeef', '#1A1A1A'),
    ('#0096ce', '#333333'),
    ('#0095cc', '#333333'),
    ('#008ecc', '#333333'),
    ('#1e293b', '#1A1A1A'),
    ('#0f172a', '#1A1A1A'),
    ('#2d3e50', '#333333'),
    ('#475569', '#4D4D4D'),
    ('#64748b', '#4D4D4D'),
    ('#94a3b8', '#4D4D4D'),
    ('#cbd5e1', '#E0E0E0'),
    ('#e2e8f0', '#E0E0E0'),
    ('#e1effe', '#E0E0E0'),
    ('#f8fbff', '#F8F8F8'),
    ('#f1f5f9', '#F8F8F8'),
    ('#f8fafc', '#F8F8F8'),
    ('#dff5ff', '#F8F8F8'),
    ('#f0f9ff', '#F8F8F8'),
    ('#e0f5fd', '#F8F8F8'),
]

def process_file(filepath):
    if not os.path.exists(filepath):
        print(f'SKIP (not found): {filepath}')
        return
    
    with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()
    
    original = content
    
    # 1. Replace linear-gradient containing #00aeef or #1e293b with #1A1A1A
    # Handle gradients that may span a single line (no nested parens assumed)
    content = re.sub(r'linear-gradient\([^)]*#00aeef[^)]*\)', '#1A1A1A', content, flags=re.IGNORECASE)
    content = re.sub(r'linear-gradient\([^)]*#1e293b[^)]*\)', '#1A1A1A', content, flags=re.IGNORECASE)
    
    # 2. Replace rgba(0, 174, 239, X) -> rgba(0, 0, 0, X)
    content = re.sub(r'rgba\(\s*0\s*,\s*174\s*,\s*239\s*,\s*([^)]+)\)', r'rgba(0,0,0,\1)', content, flags=re.IGNORECASE)
    
    # 3. Replace hex colors (case-insensitive)
    for old, new in hex_replacements:
        content = re.sub(re.escape(old), new, content, flags=re.IGNORECASE)
    
    if content != original:
        with open(filepath, 'w', encoding='utf-8', newline='') as f:
            f.write(content)
        print(f'CHANGED: {filepath}')
    else:
        print(f'NO CHANGE: {filepath}')

for fp in all_files:
    process_file(fp)

print('\nDone.')
