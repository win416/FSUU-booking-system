file = r'C:\xampp\htdocs\FSUU-booking-system-1\assets\css\admin-messages.css'
with open(file, 'r', encoding='utf-8') as f:
    content = f.read()

replacements = [
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
    ('rgba(0, 174, 239,', 'rgba(0, 0, 0,'),
    ('rgba(0,174,239,', 'rgba(0,0,0,'),
]

for old, new in replacements:
    count = content.count(old)
    content = content.replace(old, new)
    if count:
        print(f'Replaced {count}x: {old} -> {new}')

with open(file, 'w', encoding='utf-8') as f:
    f.write(content)
print('Done')
