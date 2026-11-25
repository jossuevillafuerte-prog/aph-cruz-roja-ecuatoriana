<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Borradores cifrados de fichas, por cuenta. Se borran al guardar la ficha y caducan a los 7 días.
 */
final class AttentionDrafts
{
    public const LIFETIME_DAYS = 7;

    public const MAX_BYTES = 65536;

    public static function newKey(string $province): string
    {
        return 'nueva:'.$province;
    }

    public static function recordKey(int $id): string
    {
        return 'atencion:'.$id;
    }

    /**
     * Comprueba que la cuenta pueda trabajar sobre la ficha o la provincia del borrador.
     */
    public static function allowed(Authenticatable $user, string $key): bool
    {
        $provinces = json_decode(file_get_contents(base_path('legacy/provincias.json')), true, 512, JSON_THROW_ON_ERROR);
        if (preg_match('/\Anueva:([a-z-]{2,60})\z/', $key, $match)) {
            return isset($provinces[$match[1]]) && ($user->role === 'superadmin' || $user->province === $match[1]);
        }
        if (preg_match('/\Aatencion:([1-9][0-9]{0,18})\z/', $key, $match)) {
            $province = DB::table('atenciones')->where('id', (int) $match[1])->whereNull('eliminado')->value('provincia');

            return $province !== null && ($user->role === 'superadmin' || $user->province === $province);
        }

        return false;
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function store(int $accountId, string $key, int $version, array $fields): void
    {
        DB::table('drafts')->where('updated_at', '<', now()->subDays(self::LIFETIME_DAYS))->delete();
        DB::table('drafts')->updateOrInsert(
            ['account_id' => $accountId, 'draft_key' => $key],
            ['record_version' => $version, 'payload' => Crypt::encryptString(json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 'updated_at' => now()],
        );
    }

    /**
     * @return array{fields: array<string, string>, version: int, saved_at: string}|null
     */
    public static function find(int $accountId, string $key): ?array
    {
        $draft = DB::table('drafts')->where('account_id', $accountId)->where('draft_key', $key)
            ->where('updated_at', '>=', now()->subDays(self::LIFETIME_DAYS))->first();
        if (! $draft) {
            return null;
        }
        try {
            $fields = json_decode(Crypt::decryptString($draft->payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            self::forget($accountId, $key);

            return null;
        }

        return ['fields' => $fields, 'version' => (int) $draft->record_version, 'saved_at' => (string) $draft->updated_at];
    }

    public static function forget(int $accountId, string $key): void
    {
        DB::table('drafts')->where('account_id', $accountId)->where('draft_key', $key)->delete();
    }
}
