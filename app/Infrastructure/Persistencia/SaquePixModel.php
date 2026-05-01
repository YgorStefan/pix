<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $account_withdraw_id
 * @property string $type
 * @property string $key
 */
class SaquePixModel extends Model
{
    public bool $timestamps = false;
    protected ?string $table = 'account_withdraw_pix';
    protected string $primaryKey = 'account_withdraw_id';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    protected array $fillable = ['account_withdraw_id', 'type', 'key'];
}
