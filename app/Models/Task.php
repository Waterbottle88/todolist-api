<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Task Model
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property string $title
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Task|null $parent
 * @property-read Collection<int, Task> $children
 * @property-read Collection<int, Task> $descendants
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'parent_id',
        'status',
        'priority',
        'title',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (Task $task) {
            if ($task->isDirty('status')) {
                if ($task->status === TaskStatus::DONE && $task->getOriginal('status') !== TaskStatus::DONE->value) {
                    $task->completed_at = now();
                } elseif ($task->status === TaskStatus::TODO && $task->getOriginal('status') === TaskStatus::DONE->value) {
                    $task->completed_at = null;
                }
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * @return HasMany
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param Builder $query
     * @param TaskStatus $status
     * @return Builder
     */
    public function scopeWithStatus(Builder $query, TaskStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param Builder $query
     * @param TaskPriority $priority
     * @return Builder
     */
    public function scopeWithPriority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeRootTasks(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeSubtasks(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    /**
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::DONE;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === TaskStatus::TODO;
    }

    /**
     * @return bool
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * @return bool
     */
    public function isRootTask(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * @return bool
     */
    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * @return bool
     */
    public function allChildrenCompleted(): bool
    {
        if (!$this->hasChildren()) {
            return true;
        }

        return $this->children()
            ->where('status', TaskStatus::TODO)
            ->doesntExist();
    }

    /**
     * @return int
     */
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    /**
     * @return Collection
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * @return $this
     */
    public function getRootTask(): Task
    {
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
        }

        return $current;
    }

    /**
     * @return bool
     */
    public function markAsCompleted(): bool
    {
        if (!$this->allChildrenCompleted()) {
            return false;
        }

        $this->status = TaskStatus::DONE;
        $this->completed_at = now();

        return $this->save();
    }

    /**
     * @return bool
     */
    public function markAsPending(): bool
    {
        $this->status = TaskStatus::TODO;
        $this->completed_at = null;

        return $this->save();
    }
}
