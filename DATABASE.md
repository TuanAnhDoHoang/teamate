# Database schema

Tài liệu này mô tả schema cuối cùng được suy ra từ các migration trong
`database/migrations/`. Kiểu dữ liệu dưới đây dùng cách gọi của Laravel
(ví dụ `foreignId` là `BIGINT UNSIGNED`); kiểu vật lý cụ thể có thể khác theo
database driver.

## Quy ước

- `id`: khóa chính tự tăng, `BIGINT UNSIGNED`.
- `foreignId`: `BIGINT UNSIGNED`; khóa ngoại được ghi cụ thể ở từng bảng.
- `timestamps`: `created_at` và `updated_at`, đều nullable.
- `softDeletes`: `deleted_at`, nullable timestamp.
- String không ghi độ dài: `VARCHAR(255)`.
- Trừ khi ghi chú khác, cột không nullable và không có giá trị mặc định.

## Danh sách bảng

| Bảng | Mục đích |
|---|---|
| `users` | Tài khoản người dùng và thông tin xác thực |
| `password_reset_tokens` | Token đặt lại mật khẩu |
| `sessions` | Phiên đăng nhập |
| `cache`, `cache_locks` | Cache và khóa cache |
| `jobs`, `job_batches`, `failed_jobs` | Hàng đợi tác vụ |
| `passkeys` | Passkey gắn với người dùng |
| `teams`, `team_members` | Nhóm và thành viên nhóm |
| `projects`, `project_members` | Dự án và thành viên dự án |
| `project_items`, `project_item_versions` | Tệp/thư mục dự án và phiên bản |
| `roles`, `memberships`, `resource_types`, `resource_grants` | Vai trò và quyền truy cập |
| `backup_jobs` | Tác vụ sao lưu dự án |
| `audit_logs` | Nhật ký hoạt động |

## Schema chi tiết

### `users`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `name` | VARCHAR(255) | |
| `email` | VARCHAR(255) | UNIQUE |
| `email_verified_at` | TIMESTAMP | NULL |
| `password` | VARCHAR(255) | |
| `two_factor_secret` | TEXT | NULL |
| `two_factor_recovery_codes` | TEXT | NULL |
| `two_factor_confirmed_at` | TIMESTAMP | NULL |
| `remember_token` | VARCHAR(100) | NULL |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

### `password_reset_tokens`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `email` | VARCHAR(255) | PK |
| `token` | VARCHAR(255) | |
| `created_at` | TIMESTAMP | NULL |

### `sessions`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | VARCHAR(255) | PK |
| `user_id` | BIGINT UNSIGNED | NULL, INDEX; không khai báo FK |
| `ip_address` | VARCHAR(45) | NULL |
| `user_agent` | TEXT | NULL |
| `payload` | LONGTEXT | |
| `last_activity` | INT | INDEX |

### `cache`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `key` | VARCHAR(255) | PK |
| `value` | MEDIUMTEXT | |
| `expiration` | BIGINT | INDEX |

### `cache_locks`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `key` | VARCHAR(255) | PK |
| `owner` | VARCHAR(255) | |
| `expiration` | BIGINT | INDEX |

### `jobs`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `queue` | VARCHAR(255) | INDEX |
| `payload` | LONGTEXT | |
| `attempts` | SMALLINT UNSIGNED | |
| `reserved_at` | INT UNSIGNED | NULL |
| `available_at` | INT UNSIGNED | |
| `created_at` | INT UNSIGNED | |

### `job_batches`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | VARCHAR(255) | PK |
| `name` | VARCHAR(255) | |
| `total_jobs` | INT | |
| `pending_jobs` | INT | |
| `failed_jobs` | INT | |
| `failed_job_ids` | LONGTEXT | |
| `options` | MEDIUMTEXT | NULL |
| `cancelled_at` | INT | NULL |
| `created_at` | INT | |
| `finished_at` | INT | NULL |

### `failed_jobs`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `uuid` | VARCHAR(255) | UNIQUE |
| `connection` | VARCHAR(255) | |
| `queue` | VARCHAR(255) | |
| `payload` | LONGTEXT | |
| `exception` | LONGTEXT | |
| `failed_at` | TIMESTAMP | Mặc định thời gian hiện tại |

Index ghép: (`connection`, `queue`, `failed_at`).

### `passkeys`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `user_id` | BIGINT UNSIGNED | FK → `users.id`, ON DELETE CASCADE; INDEX |
| `name` | VARCHAR(255) | |
| `credential_id` | VARCHAR(255) | UNIQUE |
| `credential` | JSON | |
| `last_used_at` | TIMESTAMP | NULL |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

### `teams`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `name` | VARCHAR(255) | |
| `slug` | VARCHAR(255) | UNIQUE |
| `owner_id` | BIGINT UNSIGNED | FK → `users.id`, ON DELETE CASCADE |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

Migration tạo bảng `team` được migration kế tiếp đổi tên thành `teams`.

### `team_members`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `team_id` | BIGINT UNSIGNED | FK → `teams.id`, ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | FK → `users.id`, ON DELETE CASCADE |
| `role` | VARCHAR(255) | Mặc định `admin`; chú thích trong migration nêu `admin`, `dev`, `review`, `client` |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

UNIQUE: (`team_id`, `user_id`).

### `projects`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `team_id` | BIGINT UNSIGNED | FK → `teams.id`, ON DELETE CASCADE |
| `owner_id` | BIGINT UNSIGNED | FK → `users.id` |
| `name` | VARCHAR(255) | |
| `slug` | VARCHAR(255) | |
| `description` | TEXT | NULL |
| `status` | ENUM(`active`, `archived`) | Mặc định `active` |
| `created_at`, `updated_at` | TIMESTAMP | NULL |
| `deleted_at` | TIMESTAMP | NULL (soft delete) |

UNIQUE: (`team_id`, `slug`).

### `project_members`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `project_id` | BIGINT UNSIGNED | FK → `projects.id`, ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | FK → `users.id`, ON DELETE CASCADE |
| `role` | VARCHAR(255) | Mặc định `guest` |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

UNIQUE: (`project_id`, `user_id`).

### `project_items`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `project_id` | BIGINT UNSIGNED | FK → `projects.id`, ON DELETE CASCADE |
| `parent_id` | BIGINT UNSIGNED | NULL, FK → `project_items.id`, ON DELETE SET NULL |
| `kind` | ENUM(`file`, `folder`) | |
| `name` | VARCHAR(255) | |
| `mime_type` | VARCHAR(255) | NULL |
| `size_bytes` | BIGINT UNSIGNED | NULL |
| `checksum_sha256` | CHAR(64) | NULL |
| `storage_backend` | VARCHAR(100) | NULL |
| `storage_ref` | TEXT | NULL |
| `encryption_key_ref` | VARCHAR(255) | NULL |
| `encryption_key_version` | VARCHAR(100) | NULL |
| `scan_status` | ENUM(`pending`, `clean`, `quarantined`, `failed`, `malware`) | NULL |
| `scanned_at` | TIMESTAMP | NULL |
| `malware_signature` | TEXT | NULL |
| `malware_acknowledged_by` | BIGINT UNSIGNED | NULL, FK → `users.id`, ON DELETE SET NULL |
| `malware_acknowledged_at` | TIMESTAMP | NULL |
| `created_by` | BIGINT UNSIGNED | FK → `users.id` |
| `updated_by` | BIGINT UNSIGNED | NULL, FK → `users.id`, ON DELETE SET NULL |
| `created_at`, `updated_at` | TIMESTAMP | NULL |
| `deleted_at` | TIMESTAMP | NULL (soft delete) |

Index: (`project_id`, `parent_id`), (`project_id`, `kind`), `created_by`.

### `project_item_versions`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `project_item_id` | BIGINT UNSIGNED | FK → `project_items.id`, ON DELETE CASCADE |
| `version_number` | INT UNSIGNED | |
| `storage_backend` | VARCHAR(100) | |
| `storage_ref` | TEXT | |
| `size_bytes` | BIGINT UNSIGNED | NULL |
| `checksum_sha256` | CHAR(64) | NULL |
| `encryption_key_ref` | VARCHAR(255) | NULL |
| `encryption_key_version` | VARCHAR(100) | NULL |
| `created_by` | BIGINT UNSIGNED | NULL, FK → `users.id` |
| `created_at` | TIMESTAMP | NULL |

UNIQUE: (`project_item_id`, `version_number`).

### `roles`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `code` | VARCHAR(100) | |
| `name` | VARCHAR(255) | |
| `scope_type` | ENUM(`team`, `project`) | |
| `team_id` | BIGINT UNSIGNED | NULL, FK → `teams.id` |
| `is_system` | BOOLEAN | Mặc định `false` |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

UNIQUE: (`code`, `scope_type`).

### `resource_types`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `code` | VARCHAR(100) | UNIQUE |

### `memberships`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `user_id` | BIGINT UNSIGNED | FK → `users.id`, ON DELETE CASCADE |
| `role_id` | BIGINT UNSIGNED | FK → `roles.id` |
| `scope_id` | BIGINT UNSIGNED | |
| `scope_type` | VARCHAR(20) | |
| `created_at`, `updated_at` | TIMESTAMP | NULL |

UNIQUE: (`user_id`, `scope_type`, `scope_id`).

### `resource_grants`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `role_id` | BIGINT UNSIGNED | FK → `roles.id`, ON DELETE CASCADE |
| `resource_type_id` | BIGINT UNSIGNED | FK → `resource_types.id` |
| `resource_specific` | BIGINT UNSIGNED | |
| `permission_mask` | TINYINT UNSIGNED | CHECK từ 1 đến 7; bitmask: 1=own, 2=write, 4=read |
| `granted_by` | BIGINT UNSIGNED | FK → `users.id` |
| `created_at` | TIMESTAMP | NULL |

UNIQUE (`resource_grants_scope_unique`): (`role_id`, `resource_type_id`, `resource_specific`).

### `backup_jobs`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `project_id` | BIGINT UNSIGNED | FK → `projects.id`, ON DELETE CASCADE |
| `backend` | ENUM(`walrus`, `other`) | |
| `blob_ref` | TEXT | NULL |
| `status` | ENUM(`pending`, `processing`, `completed`, `failed`, `expired`) | Mặc định `pending` |
| `created_by` | BIGINT UNSIGNED | NULL, FK → `users.id` |
| `expires_at` | TIMESTAMP | NULL |
| `completed_at` | TIMESTAMP | NULL |
| `created_at` | TIMESTAMP | NULL |

Index: (`project_id`, `status`).

### `audit_logs`

| Cột | Kiểu | Ràng buộc / ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, tự tăng |
| `actor_id` | BIGINT UNSIGNED | NULL, FK → `users.id` |
| `action` | VARCHAR(100) | |
| `resource_type` | VARCHAR(100) | |
| `resource_id` | VARCHAR(255) | |
| `created_at` | TIMESTAMP | Mặc định thời gian hiện tại |

Index: (`actor_id`, `created_at`), (`resource_type`, `resource_id`), (`action`, `created_at`).

**Lưu ý:** migration `2026_11_07_172355_create_project_audit_logs_table.php`
tạo bảng `audit_logs`, không phải `project_audit_logs`. Phần `down()` của
migration này lại cố xóa `project_audit_logs`; tên bảng khi rollback không khớp
với tên bảng được tạo.