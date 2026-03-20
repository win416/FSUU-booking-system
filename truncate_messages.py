file_path = r'c:\xampp\htdocs\FSUU-booking-system-1\admin\messages.php'

# Read the file with UTF-8 encoding
with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Get original line count
original_count = len(lines)

# Keep only first 481 lines
truncated_lines = lines[:481]

# Get final line count
final_count = len(truncated_lines)

# Write back to the file with UTF-8 encoding
with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(truncated_lines)

# Report results
print(f"Original line count: {original_count}")
print(f"Final line count: {final_count}")
print(f"Lines removed: {original_count - final_count}")
