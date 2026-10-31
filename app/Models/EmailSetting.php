<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    use HasFactory;

    protected $fillable = ['enabled', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'application_url'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'port' => 'integer', 'password' => 'encrypted'];
    }

    public static function configureMailer(): bool
    {
        $settings = self::find(1);
        if (! $settings) {
            return ! in_array(config('mail.default'), ['log', 'array']);
        }
        if (! $settings->enabled) {
            return false;
        }
        config(['mail.default' => 'company-smtp', 'mail.mailers.company-smtp' => [
            'transport' => 'smtp', 'scheme' => $settings->encryption === 'smtps' ? 'smtps' : 'smtp', 'host' => $settings->host, 'port' => $settings->port, 'username' => $settings->username, 'password' => $settings->password, 'timeout' => 15, 'auto_tls' => $settings->encryption !== 'none', 'require_tls' => $settings->encryption === 'starttls',
        ], 'mail.from.address' => $settings->from_address, 'mail.from.name' => $settings->from_name]);
        app('mail.manager')->purge('company-smtp');

        return true;
    }
}
