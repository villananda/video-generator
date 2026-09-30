<?php

namespace App\Models;

use App\Enums\AspectRatio;
use App\Enums\Resolution;
use App\Enums\VideoErrorCode;
use App\Enums\VideoMode;
use App\Enums\VideoStatus;
use App\Services\VideoStorageService;
use Carbon\CarbonImmutable;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property VideoMode $mode
 * @property string $prompt
 * @property string $final_prompt
 * @property AspectRatio $aspect_ratio
 * @property Resolution $resolution
 * @property VideoStatus $status
 * @property string|null $interaction_id
 * @property string|null $input_image_path
 * @property string|null $output_video_path
 * @property VideoErrorCode|null $error_code
 * @property string|null $error_message
 * @property int $attempts
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $expires_at
 */
#[Fillable([
    'user_id', 'session_id', 'mode', 'prompt', 'final_prompt', 'aspect_ratio', 'resolution',
    'status', 'interaction_id', 'input_image_path', 'output_video_path', 'error_code',
    'error_message', 'attempts', 'started_at', 'finished_at', 'expires_at',
])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory, HasVersion4Uuids, Prunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => VideoMode::class,
            'aspect_ratio' => AspectRatio::class,
            'resolution' => Resolution::class,
            'status' => VideoStatus::class,
            'error_code' => VideoErrorCode::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Expired videos are removed by `model:prune` (see routes/console.php).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now());
    }

    protected function pruning(): void
    {
        app(VideoStorageService::class)->deleteFiles($this);
    }
}
