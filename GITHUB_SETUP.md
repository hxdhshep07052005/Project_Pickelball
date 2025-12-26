# Hướng dẫn Push Project lên GitHub

## Bước 1: Cài đặt Git (nếu chưa có)

1. Tải Git từ: https://git-scm.com/download/win
2. Cài đặt với các tùy chọn mặc định
3. Mở lại terminal sau khi cài đặt

## Bước 2: Tạo .gitignore file

Tạo file `.gitignore` trong thư mục gốc của project để loại trừ các file không cần thiết:

```
# XAMPP
xampp/
htdocs/

# Python
__pycache__/
*.py[cod]
*$py.class
*.so
.Python
env/
venv/
.venv/
*.egg-info/
dist/
build/

# Jupyter Notebook
.ipynb_checkpoints
*.ipynb_checkpoints/

# Environment variables
.env
.env.local

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db
desktop.ini

# Logs
*.log
logs/

# Temporary files
*.tmp
*.temp
*.bak
*.cache

# Database
*.sql
*.db
*.sqlite

# Media files (nếu quá lớn)
# *.mp4
# *.avi
# *.mov

# Node modules (nếu có)
node_modules/
package-lock.json
```

## Bước 3: Khởi tạo Git Repository

Mở terminal/PowerShell trong thư mục project và chạy:

```bash
cd c:\xampp\htdocs\pickelball
git init
```

## Bước 4: Thêm các file vào Git

```bash
# Thêm tất cả các file
git add .

# Hoặc thêm từng file cụ thể
git add *.php
git add *.js
git add *.css
```

## Bước 5: Commit các thay đổi

```bash
git commit -m "Initial commit: Pickleball training application"
```

## Bước 6: Tạo Repository trên GitHub

1. Đăng nhập vào GitHub: https://github.com
2. Click nút "+" ở góc trên bên phải → "New repository"
3. Đặt tên repository (ví dụ: `pickleball-training`)
4. Chọn Public hoặc Private
5. KHÔNG tích "Initialize with README" (vì đã có code local)
6. Click "Create repository"

## Bước 7: Kết nối Local Repository với GitHub

GitHub sẽ hiển thị các lệnh. Chạy các lệnh sau (thay `YOUR_USERNAME` và `YOUR_REPO_NAME`):

```bash
# Thêm remote repository
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git

# Hoặc nếu dùng SSH:
# git remote add origin git@github.com:YOUR_USERNAME/YOUR_REPO_NAME.git

# Đổi tên branch chính thành main (nếu cần)
git branch -M main

# Push code lên GitHub
git push -u origin main
```

## Bước 8: Xác thực (nếu cần)

- Nếu dùng HTTPS, GitHub sẽ yêu cầu username và password (hoặc Personal Access Token)
- Nếu dùng SSH, cần setup SSH key trước

## Các lệnh Git hữu ích

```bash
# Xem trạng thái
git status

# Xem các thay đổi
git diff

# Xem lịch sử commit
git log

# Thêm thay đổi mới
git add .
git commit -m "Mô tả thay đổi"
git push

# Tạo branch mới
git checkout -b feature-branch-name

# Chuyển về branch chính
git checkout main

# Merge branch
git merge feature-branch-name
```

## Lưu ý quan trọng

1. **Không commit file nhạy cảm**: passwords, API keys, database credentials
2. **Sử dụng .gitignore**: để loại trừ file không cần thiết
3. **Commit message rõ ràng**: mô tả ngắn gọn những gì đã thay đổi
4. **Push thường xuyên**: để backup code và cộng tác dễ dàng hơn

## Troubleshooting

### Lỗi: "fatal: not a git repository"
→ Chạy `git init` trong thư mục project

### Lỗi: "Permission denied"
→ Kiểm tra SSH key hoặc Personal Access Token

### Lỗi: "remote origin already exists"
→ Xóa và thêm lại: `git remote remove origin` rồi `git remote add origin ...`

### Muốn thay đổi remote URL
→ `git remote set-url origin NEW_URL`

