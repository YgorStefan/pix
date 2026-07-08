<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $balance
 * @property string $email
 * @property string $password_hash
 */
class ContaModel extends Model
{
    public bool $timestamps = false;
    protected ?string $table = 'account';
    protected string $primaryKey = 'id';
    public bool $incrementing = false;
    protected string $keyType = 'string';
    protected array $fillable = ['id', 'name', 'balance', 'email', 'password_hash'];
}
