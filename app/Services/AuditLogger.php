<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Keys never written to the audit trail. */
    private const SENSITIVE = ['password', 'remember_token', 'account_number_encrypted', 'current_password', 'password_confirmation', 'db_password'];

    public static function log(string $action, Model|string|null $entity = null, ?array $old = null, ?array $new = null, ?int $userId = null): AuditLog
    {
        $entityType = null;
        $entityId = null;
        if ($entity instanceof Model) {
            $entityType = class_basename($entity);
            $entityId = $entity->getKey();
        } elseif (is_string($entity)) {
            $entityType = $entity;
        }

        $req = app()->runningInConsole() && ! app()->runningUnitTests() ? null : Request::instance();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values_json' => self::clean($old),
            'new_values_json' => self::clean($new),
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    public static function changes(Model $model): array
    {
        $dirty = array_keys($model->getChanges());
        $dirty = array_diff($dirty, ['updated_at']);
        $old = [];
        $new = [];
        foreach ($dirty as $k) {
            $old[$k] = $model->getOriginal($k);
            $new[$k] = $model->getAttribute($k);
        }

        return [$old, $new];
    }

    private static function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach ($values as $k => $v) {
            if (in_array($k, self::SENSITIVE, true)) {
                $values[$k] = '***';
            } elseif ($v instanceof \DateTimeInterface) {
                $values[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_object($v)) {
                $values[$k] = (string) $v;
            }
        }

        return $values;
    }
}
