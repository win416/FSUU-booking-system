f = r'c:\xampp\htdocs\FSUU-booking-system-1\admin\messages.php'
with open(f, 'r', encoding='utf-8') as fh:
    lines = fh.readlines()
original = len(lines)
kept = lines[:481]
with open(f, 'w', encoding='utf-8') as fh:
    fh.writelines(kept)
with open(f, 'r', encoding='utf-8') as fh:
    final = len(fh.readlines())
print(f'Original line count: {original}, Final line count: {final}')
