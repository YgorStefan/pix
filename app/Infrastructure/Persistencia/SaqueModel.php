<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $id
 * @property string $account_id
 * @property string $method
 * @property string $amount
 * @property bool   $scheduled
 * @property string|null $scheduled_for
 * @property bool   $done
 * @property bool   $error
 * @property string|null $error_reason
 * @property string|null $processing_since
 * @property string $created_at
 */
class SaqueModel extends Model
{
    public bool $timestamps = false;
    protected ?string $table = 'account_withdraw';
    protected string $primaryKey = 'id';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    protected array $fillable = [
        'id', 'account_id', 'method', 'amount',
        'scheduled', 'scheduled_for', 'done', 'error',
        'error_reason', 'processing_since', 'created_at',
    ];
    protected array $casts = [
        'scheduled' => 'boolean',
        'done'      => 'boolean',
        'error'     => 'boolean',
    ];
}
