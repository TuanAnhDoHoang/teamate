# Proposal: Teamate

1. Mô tả
- Là một ứng dụng web quản lý dự án số dành cho  nhóm, an toàn mã nguồn lưu trữ và quản lý truy cập phân quyền hiệu quả.

1. Công nghệ sử dụng

Sử dụng laravel là framework chính cùng với :

- Mysql: database lưu trữ chính
- Harshicorp: quản lý vòng đời khóa cho mã hóa
- Walrus: Lưu trữ phi tập trung (giải pháp đăng ký backup dự án)

1. RBAC

Ứng dụng có 3 quyền chính đối với tài nguyên

- 4: Đọc (read)
- 2: Chỉnh sửa  (write)
- 1: Sở hữu (own) - được gán quyền cho các vai trò về resource mình sở hữu

→ Từ giờ tôi gọi quyền R là read, W là write và O là own cho ngắn gọn 

Tài nguyên (resource)

- Team
- Project
- Folder
- File
- Team Member
- Project Member
- TeamRole
- ProjectRole

Các phạm vi (scope)

- Team: Thường quản lý truy cập cấu hình member và project
- Project: Thường quản lý truy cập cấu hình file và folder

Các vai trò (custom):

- Nhóm quản trị: Administrator, TeamAdministrator, ProjectAdministrator
- Nhóm phát triển: Developer, Devop
- Nhóm reviewer: Tester, Pentester
- Nhóm khách hàng: Guest (mặc định cho thành viên mới)

1. Tính năng

Team upload project lên ứng dụng web được lưu trữ trong mysql database

Project được mã hóa khi truyền tải và lưu trữ

Mọi người sử dụng quyền của mình trong việc tương tác với dự án

File được scanning trước khi upload

2. Các tính năng phụ

Đăng nhập cần xác thực MFA

Backup đến môi trường phi tập trung - Walrus (chưa triển khai)

3. Phân quyền trong Teamate

Công thức chung: <u>quyền</u> + <u>tài nguyên</u> trong <u>phạm vi</u>

Ví dụ cho câu nói: Quản trị viên của nhóm được mời người khác vào nhóm của mình

- Người tạo nhóm được gán cho vai trò TeamAdministrator - Quản trị viên nhóm
- TeamAdministrator sẽ có quyền tối thiểu để thực hiện:
    - Đọc cấu hình nhóm (<u>Read</u> <u>Team</u> in <u>Team</u>)
    - Chỉnh sửa cấu hình nhóm (<u>Write</u> <u>Team</u> in <u>Team</u>)
    - Đọc cấu hình danh sách thành viên (<u>Read</u> <u>Team Member</u> in <u>Team</u>)
    - Thêm thành viên (<u>Write</u> <u>Team Member</u> in <u>Team</u>)

Ví dụ cho câu nói: Quản trị viên của nhóm muốn dời chức cho một thành viên khác trong nhóm

- Người tạo nhóm được gán cho vai trò TeamAdministrator - Quản trị viên nhóm
- TeamAdministrator sẽ có quyền tối thiểu để thực hiện gồm:
    - Sở hữu nhóm (<u>Own</u> <u>Team</u> in <u>Team</u>)
- Điều kiện: Người được ủy quyền cần là thành  viên trong nhóm

Ví dụ cho câu nói: Quản trị viên dự án muốn cấp một thành viên trong nhóm làm developer, quyền hạn là <u>Đọc</u> và <u>Chỉnh sửa</u> Thư mục .github, thư mục frontend và file docker-compose.yml

Tương tự như các phần trên thì ta có luồng hoạt động:

- Quản trị viên nhóm sẽ tạo ra project hoặc bất kỳ ai có quyền chính sửa project (<u>Write</u> <u>Project</u> in <u>Team</u>)
- Người tạo ra project được gán cho vai trò ProjectAdministrator - Quản trị viên dự án
- ProjectAdministrator thêm Peter vào project (<u>Write</u> <u>Project Member</u> in <u>Project</u>)
- Quản trị viên project có quyền sở hữu dự án và cấp quyền cho role để chỉnh sửa dự án:
    - Ví dụ người cần cấp ProjectRole Developer là Peter
    - Quản trị viên project tạo một ProjectRole là Project_A_Developer có vai trò chỉnh sửa file docker-compose.yml trong dự án anh ta sở hữu
    - Quản trị viên gán role cho Peter (điều kiện: Peter là thành viên của Project)
    - Sau đó quản trị viên có thể cấp thêm cho role Project_A_Developer quyền đọc và chỉnh sửa thư mục .github, thư mục frontend và file docker-compose.yml

→ Ta đã có Peter với role Project_A_Developer được quyền đọc và chỉnh sửa Thư mục .github, thư mục frontend và file docker-compose.yml trong dự án của nhóm mà anh ta tham gia


Kịch bản:  Pentester thuê ngoài làm việc 7 ngày

- TeamAdministrator add vào nhóm
- ProjectAdministrator add vào project
- ProjectAdministrator tạo ProjectRole là Project_A_Pentester
- ProjectAdministrator với quyền sở hữu project sẽ cấp quyền đọc và chỉnh sửa cho các file/folder mà pentester cần và đưa vào Project_A_Pentester
- Sau 7 ngày, thu hồi (hoặc xóa) Role Project_A_Pentester từ người pentest
- Như vậy, người được thuê sẽ ở trong project nhưng không đọc/chỉnh sửa được bất cứ thứ gì
- ProjectAdministrator xóa khỏi project hoặc TeamAdministrator xóa khỏi nhóm


## Chạy ứng dụng bằng Docker Compose

Khởi chạy ứng dụng và MySQL:

```bash
docker compose up -d --build
```

Compose cũng khởi chạy Vault Transit và tự tạo khóa `teamate-files` để mã hóa nội dung file mới tải lên. File được mã hóa trước khi lưu; Vault giữ khóa mã hóa và ứng dụng cần Vault để tải xuống hoặc xem trước. Tên file/folder và metadata vẫn được lưu rõ trong database. Vault dev mode cùng root token mẫu chỉ dùng cho phát triển: dữ liệu và khóa Vault không bền vững qua lần tạo lại container, vì vậy không dùng cấu hình này cho production.

Compose cũng chạy ClamAV trong mạng nội bộ, không mở cổng scanner ra máy host. File được quét trước khi mã hóa; nếu phát hiện malware, người tải lên có thể hủy hoặc xác nhận tiếp tục. File được chấp nhận sẽ vẫn được mã hóa và gắn cảnh báo kèm tên signature để thành viên khác nhìn thấy. Nếu ClamAV không sẵn sàng hoặc không thể hoàn tất việc quét, upload bị từ chối. Database signature được cập nhật tự động và lưu trong volume `clamav-data`.

Các biến cấu hình tùy chọn là `CLAMAV_HOST`, `CLAMAV_PORT` và `CLAMAV_TIMEOUT` (mặc định lần lượt là `clamav`, `3310` và `60` giây). Giới hạn upload là 50 MB; ClamAV được cấu hình giới hạn INSTREAM cao hơn để nhận đủ file ở ngưỡng tối đa.

Chạy database migrations và seed dữ liệu ban đầu:

```bash
docker compose exec app php artisan migrate --seed
```

Auto-Approved Email trong môi trường docker
```bash
docker compose exec -e XDG_CONFIG_HOME=/tmp app php artisan tinker --execute 'User::latest()->first()->markEmailAsVerified();'
```

Sau đó truy cập ứng dụng tại [http://localhost:8000](http://localhost:8000).