<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'project_items';

    protected $fillable = [
        'project_id',
        'parent_id',
        'kind',
        'name',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'storage_backend',
        'storage_ref',
        'encryption_key_ref',
        'encryption_key_version',
        'scan_status',
        'scanned_at',
        'malware_signature',
        'malware_acknowledged_by',
        'malware_acknowledged_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'malware_acknowledged_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProjectItem::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function malwareAcknowledgingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'malware_acknowledged_by');
    }

    public function isFolder(): bool
    {
        return $this->kind === 'folder';
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }

    public function formattedSize(): string
    {
        if ($this->isFolder()) {
            return '—';
        }

        $bytes = (float) $this->size_bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }

    /**
     * Build breadcrumb trail from root to this item.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function breadcrumbTrail(): array
    {
        $trail = [];
        $current = $this;

        while ($current) {
            array_unshift($trail, [
                'id' => $current->id,
                'name' => $current->name,
            ]);
            $current = $current->parent;
        }

        return $trail;
    }
}
