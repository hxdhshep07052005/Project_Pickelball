# Hướng dẫn Push Code lên GitHub

Repository của bạn: https://github.com/hxdhshep07052005/Project_Pickelball

## Các lệnh cần chạy (theo thứ tự):

### 1. Kiểm tra trạng thái hiện tại:
```bash
git status
```

### 2. Thêm tất cả các file vào staging:
```bash
git add .
```

### 3. Commit các thay đổi:
```bash
git commit -m "Initial commit: Pickleball training application with ghost trainer"
```

### 4. Thêm remote repository:
```bash
git remote add origin https://github.com/hxdhshep07052005/Project_Pickelball.git
```

### 5. Đổi tên branch thành main (nếu cần):
```bash
git branch -M main
```

### 6. Push code lên GitHub:
```bash
git push -u origin main
```

## Nếu gặp lỗi:

### Lỗi: "remote origin already exists"
```bash
git remote remove origin
git remote add origin https://github.com/hxdhshep07052005/Project_Pickelball.git
```

### Lỗi: "failed to push some refs"
Nếu repository trên GitHub đã có file (như README.md), bạn cần pull trước:
```bash
git pull origin main --allow-unrelated-histories
git push -u origin main
```

### Lỗi xác thực:
- Sử dụng Personal Access Token thay vì password
- Tạo token tại: https://github.com/settings/tokens
- Chọn quyền `repo` (full control)

## Sau khi push thành công:

Kiểm tra trên GitHub: https://github.com/hxdhshep07052005/Project_Pickelball

Bạn sẽ thấy tất cả các file của project đã được upload!

