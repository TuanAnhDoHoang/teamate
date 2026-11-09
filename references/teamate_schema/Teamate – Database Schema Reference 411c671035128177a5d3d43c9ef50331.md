# Teamate – Database Schema Reference

Tài liệu mô tả schema MySQL hiện tại của Teamate theo bản SQL đã tạo để kiểm thử. Schema dùng InnoDB, utf8mb4 và tương thích MySQL 8.0+. Các khóa chính Laravel `$table->id()` và khóa ngoại tương ứng dùng `BIGINT UNSIGNED`.

> Trạng thái thiết kế: các bảng Laravel/auth dựa trên migration đã trao đổi; các bảng Team/Project/RBAC/Versioning/Backup/Audit là thiết kế nghiệp vụ đề xuất để thử nghiệm, chưa phải toàn bộ migration Laravel đã được xác nhận.
> 

## 1. Quy ước dữ liệu

- ID chính: `BIGINT UNSIGNED AUTO_INCREMENT`.
- FK đến `users.id`: `BIGINT UNSIGNED`.
- Thời gian: `TIMESTAMP NULL` cho created_at/updated_at theo cách khai báo Laravel thông dụng; một số cột như audit_logs.created_at dùng `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `scope_id` và `resource_specific` là polymorphic IDs: không có FK SQL trực tiếp; Laravel phải kiểm tra loại đối tượng, sự tồn tại và phạm vi của đối tượng.
- Permission mask: bit 1 = Own(O), bit 2 = Write (W), bit 4 = Read (R). Ví dụ 7 = R+W+T. Kiểm tra một quyền bằng phép AND bitwise, ví dụ Read: `(permission_mask & 4) = 4`.

## 2. Danh sách bảng

| Nhóm | Bảng |
| --- | --- |
| Authentication | users, password_reset_tokens, sessions, passkeys |
| Laravel infrastructure | cache, cache_locks, jobs, job_batches, failed_jobs |
| Team/Project | teams, projects, project_items |
| RBAC | roles, resource_types, role_grants, memberships, resource_grants |
| Mở rộng | project_item_versions, backup_jobs, audit_logs |

Tổng cộng: **20 bảng**.

## 3. Authentication

### users

Tài khoản người dùng và thông tin xác thực.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| name | VARCHAR(255) | NOT NULL |
| email | VARCHAR(255) | NOT NULL, UNIQUE |
| email_verified_at | TIMESTAMP | NULL |
| password | VARCHAR(255) | NOT NULL, password hash |
| two_factor_secret | TEXT | NULL, secret MFA |
| two_factor_recovery_codes | TEXT | NULL, mã khôi phục MFA |
| two_factor_confirmed_at | TIMESTAMP | NULL |
| remember_token | VARCHAR(100) | NULL |
| created_at, updated_at | TIMESTAMP | NULL |

Lưu ý: mã hóa secret MFA ở tầng ứng dụng/HashiCorp cần được xác định rõ. Các cột Laravel 2FA trong bản SQL hiện tại chưa tự động chứng minh secret đã được mã hóa bằng cơ chế Vault.

### password_reset_tokens

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| email | VARCHAR(255) | PK |
| token | VARCHAR(255) | NOT NULL |
| created_at | TIMESTAMP | NULL |

### sessions

Lưu session khi dùng database session driver.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | VARCHAR(255) | PK |
| user_id | BIGINT UNSIGNED | NULL, index; bản migration đã trao đổi chưa khai báo FK |
| ip_address | VARCHAR(45) | NULL |
| user_agent | TEXT | NULL |
| payload | LONGTEXT | NOT NULL |
| last_activity | INT | NOT NULL, index |

### passkeys

Lưu thông tin credential WebAuthn/passkey của user.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FK → [users.id](http://users.id), ON DELETE CASCADE |
| name | VARCHAR(255) | NOT NULL |
| credential_id | VARCHAR(255) | UNIQUE, NOT NULL |
| credential | JSON | NOT NULL |
| last_used_at | TIMESTAMP | NULL |
| created_at, updated_at | TIMESTAMP | NULL |

## 4. Laravel infrastructure

Các bảng này phục vụ queue, cache và xử lý job; không phải bảng nghiệp vụ chính của Teamate.

### cache

| Cột | MySQL type | Ràng buộc |
| --- | --- | --- |
| key | VARCHAR(255) | PK |
| value | MEDIUMTEXT | NOT NULL |
| expiration | BIGINT | NOT NULL, index |

### cache_locks

| Cột | MySQL type | Ràng buộc |
| --- | --- | --- |
| key | VARCHAR(255) | PK |
| owner | VARCHAR(255) | NOT NULL |
| expiration | BIGINT | NOT NULL, index |

### jobs

| Cột | MySQL type | Ràng buộc |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| queue | VARCHAR(255) | NOT NULL, index |
| payload | LONGTEXT | NOT NULL |
| attempts | TINYINT UNSIGNED | NOT NULL |
| reserved_at | INT UNSIGNED | NULL |
| available_at | INT UNSIGNED | NOT NULL |
| created_at | INT UNSIGNED | NOT NULL |

### job_batches

| Cột | MySQL type | Ràng buộc |
| --- | --- | --- |
| id | VARCHAR(255) | PK |
| name | VARCHAR(255) | NOT NULL |
| total_jobs, pending_jobs, failed_jobs | INT | NOT NULL |
| failed_job_ids | LONGTEXT | NOT NULL |
| options | MEDIUMTEXT | NULL |
| cancelled_at | INT | NULL |
| created_at | INT | NOT NULL |
| finished_at | INT | NULL |

### failed_jobs

| Cột | MySQL type | Ràng buộc |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| uuid | VARCHAR(255) | UNIQUE, NOT NULL |
| connection, queue | TEXT | NOT NULL |
| payload, exception | LONGTEXT | NOT NULL |
| failed_at | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP |
| Index phụ | connection, queue, failed_at | Index ghép (prefix 100 ký tự cho connection/queue) |

## 5. Team và Project

### teams

Đại diện một nhóm người dùng sở hữu và quản lý nhiều project.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| name | VARCHAR(255) | NOT NULL |
| slug | VARCHAR(255) | NOT NULL, UNIQUE |
| owner_id | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| created_at, updated_at | TIMESTAMP | NULL |

### projects

Một project thuộc một team; có một owner được lưu trực tiếp.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| team_id | BIGINT UNSIGNED | FK → [teams.id](http://teams.id) |
| owner_id | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| name | VARCHAR(255) | NOT NULL |
| slug | VARCHAR(255) | NOT NULL |
| description | TEXT | NULL |
| status | ENUM('active','archived') | NOT NULL, DEFAULT 'active' |
| created_at, updated_at | TIMESTAMP | NULL |
| deleted_at | TIMESTAMP | NULL, soft delete |

Unique index: `(team_id, slug)`. Một slug chỉ duy nhất trong phạm vi một team.

### project_items

Một bảng biểu diễn cả file và folder. `parent_id` tự tham chiếu để tạo cây thư mục.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| project_id | BIGINT UNSIGNED | FK → [projects.id](http://projects.id), ON DELETE CASCADE |
| parent_id | BIGINT UNSIGNED | NULL, FK → project_[items.id](http://items.id), ON DELETE SET NULL |
| kind | ENUM('file','folder') | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| mime_type | VARCHAR(255) | NULL, MIME type của file |
| size_bytes | BIGINT UNSIGNED | NULL |
| checksum_sha256 | CHAR(64) | NULL |
| storage_backend | VARCHAR(100) | NULL, nơi lưu bytes |
| storage_ref | TEXT | NULL, định danh/path bên storage |
| encryption_key_ref | VARCHAR(255) | NULL, tham chiếu khóa |
| encryption_key_version | VARCHAR(100) | NULL |
| scan_status | ENUM('pending','clean','quarantined','failed') | NULL |
| scanned_at | TIMESTAMP | NULL |
| created_by | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| updated_by | BIGINT UNSIGNED | NULL, FK → [users.id](http://users.id), ON DELETE SET NULL |
| created_at, updated_at | TIMESTAMP | NULL |
| deleted_at | TIMESTAMP | NULL, soft delete |

Quy tắc cần kiểm tra ở tầng ứng dụng:

- Folder không cần metadata lưu trữ nội dung file.
- File cần storage metadata khi đã upload hoàn tất.
- Parent phải là folder thuộc cùng project.
- Không cho phép tạo chu trình trong cây thư mục.
- Xóa folder cần có chính sách rõ ràng với các phần tử con; FK hiện tại chỉ SET NULL parent_id nếu parent bị xóa cứng.

## 6. RBAC

### roles

Định nghĩa vai trò và scope áp dụng.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| code | VARCHAR(100) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| scope_type | ENUM('team','project') | NOT NULL |
| is_system | BOOLEAN | NOT NULL, DEFAULT FALSE |

Unique index: `(code, scope_type)`.

Các role dự kiến: Client, Dev, Devops, Tester, Pentester, Administrator, Contributor. Role nào thuộc scope Team hay Project cần được xác định trong seed/policy.

### resource_types

Danh mục loại tài nguyên mà hệ thống phân quyền kiểm soát.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| code | VARCHAR(100) | NOT NULL, UNIQUE |

Giá trị mẫu: project, team, folder, file, member, role. Folder/file có thể cùng trỏ tới loại đối tượng vật lý `project_items`, nhưng vẫn phân biệt bằng `project_items.kind`.

### role_grants

Quyền mặc định của một role trên một loại resource. Không gắn với một object ID cụ thể.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| role_id | BIGINT UNSIGNED | FK → [roles.id](http://roles.id) |
| resource_type_id | BIGINT UNSIGNED | FK → resource_[types.id](http://types.id) |
| permission_mask | TINYINT UNSIGNED | NOT NULL, CHECK 1..7 |

PK ghép: `(role_id, resource_type_id)`. Khi xóa role/resource type, grant bị cascade delete.

### memberships

Gán role cho user trong một phạm vi Team hoặc Project.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FK → [users.id](http://users.id), ON DELETE CASCADE |
| role_id | BIGINT UNSIGNED | FK → [roles.id](http://roles.id) |
| scope_type | ENUM('team','project') | NOT NULL |
| scope_id | BIGINT UNSIGNED | NOT NULL, polymorphic ID |
| created_at, updated_at | TIMESTAMP | NULL |

Unique index: `(user_id, scope_type, scope_id)`. Điều này có nghĩa một user chỉ có **một role trên mỗi scope**. Nếu muốn nhiều role trong cùng project/team, cần đổi unique index, ví dụ thêm role_id.

Không có FK trực tiếp cho scope_id vì cột này có thể tham chiếu [teams.id](http://teams.id) hoặc [projects.id](http://projects.id). Ứng dụng phải xác thực scope_type và scope_id tương ứng.

### resource_grants

Quyền được cấp trực tiếp cho một user trên một object cụ thể, tách biệt với quyền mặc định của role.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FK → [users.id](http://users.id), ON DELETE CASCADE |
| resource_type_id | BIGINT UNSIGNED | FK → resource_[types.id](http://types.id) |
| resource_specific | BIGINT UNSIGNED | NOT NULL, ID object cụ thể, polymorphic |
| permission_mask | TINYINT UNSIGNED | NOT NULL, CHECK 1..7 |
| granted_by | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | NULL |

Unique index: `(user_id, resource_type_id, resource_specific)`.

Ví dụ: resource_type = file, resource_specific = 42, permission_mask = 4 nghĩa là user được cấp Read trực tiếp trên file có ID 42.

Lưu ý:

- `resource_specific` không có FK SQL vì có thể trỏ đến [projects.id](http://projects.id), project_[items.id](http://items.id) hoặc bảng resource khác tùy resource_type.
- Đây là grant trực tiếp cho user; không phải role-level grant.
- Khi cấp quyền, cần xác minh người cấp có quyền quản trị resource đó; xác minh object tồn tại; xác minh object thuộc team/project mà người cấp có quyền quản lý.
- Cần quyết định chính sách kết hợp quyền: role grants và direct grants được cộng dồn (union), hay có deny/override. Schema hiện tại chỉ biểu diễn allow grants, chưa có explicit deny.

## 7. Versioning, Backup, Audit

### project_item_versions

Lưu lịch sử phiên bản nội dung file. Folder thường không cần version record.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| project_item_id | BIGINT UNSIGNED | FK → project_[items.id](http://items.id), ON DELETE CASCADE |
| version_number | INT UNSIGNED | NOT NULL |
| storage_backend | VARCHAR(100) | NOT NULL |
| storage_ref | TEXT | NOT NULL |
| size_bytes | BIGINT UNSIGNED | NULL |
| checksum_sha256 | CHAR(64) | NULL |
| encryption_key_ref | VARCHAR(255) | NULL |
| encryption_key_version | VARCHAR(100) | NULL |
| created_by | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| created_at | TIMESTAMP | NULL |

Unique index: `(project_item_id, version_number)`.

### backup_jobs

Theo dõi vòng đời tác vụ backup project.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| project_id | BIGINT UNSIGNED | FK → [projects.id](http://projects.id), ON DELETE CASCADE |
| backend | ENUM('walrus','other') | NOT NULL |
| blob_ref | TEXT | NULL, tham chiếu bản backup |
| status | ENUM('pending','processing','completed','failed','expired') | NOT NULL, DEFAULT 'pending' |
| created_by | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| expires_at | TIMESTAMP | NULL |
| completed_at | TIMESTAMP | NULL |
| created_at | TIMESTAMP | NULL |

Index: `(project_id, status)`.

### audit_logs

Ghi lại actor, hành động và tài nguyên bị tác động.

| Cột | MySQL type | Ràng buộc / ý nghĩa |
| --- | --- | --- |
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| actor_id | BIGINT UNSIGNED | FK → [users.id](http://users.id) |
| action | VARCHAR(100) | NOT NULL |
| resource_type | VARCHAR(100) | NOT NULL |
| resource_id | VARCHAR(255) | NOT NULL, lưu dạng chuỗi để hỗ trợ nhiều kiểu ID |
| created_at | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP |

Index: `(actor_id, created_at)`, `(resource_type, resource_id)`, `(action, created_at)`.

Có thể mở rộng sau với metadata JSON, IP address, request ID, before/after snapshot. Chưa có trong SQL hiện tại.

## 8. Quan hệ chính

- users 1—N teams (qua teams.owner_id).
- users 1—N projects (qua projects.owner_id).
- teams 1—N projects.
- projects 1—N project_items.
- project_items 1—N project_items (cây folder/file qua parent_id).
- users 1—N memberships; roles 1—N memberships.
- roles N—N resource_types qua role_grants.
- users 1—N resource_grants; resource_types 1—N resource_grants.
- project_items 1—N project_item_versions.
- projects 1—N backup_jobs.
- users 1—N audit_logs.

## 9. Những điểm chưa được database tự bảo đảm

1. `memberships.scope_id` phải tham chiếu đúng bảng theo scope_type.
2. `resource_grants.resource_specific` phải tham chiếu đúng bảng theo resource_types.code.
3. Role được gán phải có scope_type phù hợp với memberships.scope_type.
4. Owner của project có thuộc team đó hay không cần được kiểm tra theo chính sách sản phẩm.
5. Parent của project_item phải là folder cùng project và cây không được có cycle.
6. Quy tắc cộng dồn role_grants với resource_grants phải được định nghĩa trong authorization service.
7. Nếu xóa user, cần quyết định chính sách với dữ liệu họ sở hữu/đã tạo; hiện nhiều FK cố ý không cascade để tránh xóa dây chuyền.

## 10. File SQL liên quan

- `schema.sql`: tạo schema và 20 bảng.
- `seed_rbac.sql`: dữ liệu mẫu resource_types, roles và role_grants.
- `test_samples.sql`: dữ liệu demo và các truy vấn kiểm tra.

Schema này là bản nền phục vụ thử nghiệm MySQL; cần đối chiếu lại với migrations thực tế trước khi dùng production.