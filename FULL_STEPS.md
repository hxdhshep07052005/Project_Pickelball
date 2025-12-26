# Hướng dẫn đầy đủ - Push Code lên GitHub

## Bước 1: Kiểm tra trạng thái hiện tại
```bash
git status
```

## Bước 2: Thêm tất cả các file vào staging area
```bash
git add .
```

## Bước 3: Commit các thay đổi
```bash
git commit -m "Initial commit: Pickleball training application with ghost trainer"
```

## Bước 4: Kiểm tra remote đã được thêm chưa
```bash
git remote -v
```

Nếu chưa có, thêm remote:
```bash
git remote add origin https://github.com/hxdhshep07052005/Project_Pickelball.git
```

Nếu đã có nhưng sai URL, xóa và thêm lại:
```bash
git remote remove origin
git remote add origin https://github.com/hxdhshep07052005/Project_Pickelball.git
```

## Bước 5: Đảm bảo branch là main
```bash
git branch -M main
```

## Bước 6: Fetch code từ GitHub (nếu có)
```bash
git fetch origin
```

## Bước 7: Pull và merge với code trên GitHub
```bash
git pull origin main --allow-unrelated-histories
```

**Lưu ý:** Nếu Git mở editor (vim/nano):
- Trong vim: Nhấn `Esc`, gõ `:wq`, nhấn `Enter`
- Hoặc đơn giản: Nhấn `Esc`, gõ `:x`, nhấn `Enter`
- Trong nano: Nhấn `Ctrl+X`, sau đó `Y`, rồi `Enter`

## Bước 8: Push code lên GitHub
```bash
git push -u origin main
```

## Bước 9: Xác thực (nếu được yêu cầu)
- **Username:** hxdhshep07052005
- **Password:** Sử dụng Personal Access Token (KHÔNG dùng password thường)

### Tạo Personal Access Token:
1. Vào: https://github.com/settings/tokens
2. Click "Generate new token" → "Generate new token (classic)"
3. Đặt tên token (ví dụ: "Pickleball Project")
4. Chọn quyền: ✅ **repo** (full control)
5. Click "Generate token"
6. **SAO CHÉP TOKEN NGAY** (chỉ hiện 1 lần)
7. Dán token vào khi được hỏi password

## Bước 10: Kiểm tra kết quả
Mở trình duyệt và vào: https://github.com/hxdhshep07052005/Project_Pickelball

Bạn sẽ thấy tất cả các file đã được upload!

---

## Nếu gặp lỗi:

### Lỗi: "merge conflict"
```bash
# Xem các file conflict
git status

# Giải quyết conflict trong các file, sau đó:
git add .
git commit -m "Resolve merge conflicts"
git push -u origin main
```

### Lỗi: "authentication failed"
- Kiểm tra lại Personal Access Token
- Đảm bảo token có quyền `repo`
- Tạo token mới nếu cần

### Lỗi: "remote origin already exists"
```bash
git remote remove origin
git remote add origin https://github.com/hxdhshep07052005/Project_Pickelball.git
```

### Muốn xem log commit
```bash
git log --oneline
```

### Muốn xem các file đã thay đổi
```bash
git status
```

