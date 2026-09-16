<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $value
 * @psalm-type ModsecConfig = array{
 *   mode: string,
 *   enabled_rulesets: array<string>,
 * }
 */
class Setting extends Model
{
    protected $fillable = [
        'name',
        'value',
    ];

    /** @var ?Collection<int, static> */
    protected static $allSettings = null;

    /** @var array<string, string> */
    protected static array $runtimeSettings = [];

    /**
     * @return Collection<int, static>
     */
    public static function getAllSettings()
    {
        if (self::$allSettings === null) {
            self::$allSettings = self::all();
        }
        return self::$allSettings;
    }

    /**
     * @param array<string, string> $settings
     */
    public static function setRuntimeSettings(array $settings): void
    {
        self::$runtimeSettings = $settings;
        self::$allSettings = null;
    }

    public static function clearRuntimeSettings(): void
    {
        self::$runtimeSettings = [];
        self::$allSettings = null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function exists($name)
    {
        return (bool)(self::getAllSettings()->where('name', $name)->count());
    }

    /**
     * @param string $name
     * @return ?string
     */
    public static function get($name)
    {
        if (array_key_exists($name, self::$runtimeSettings)) {
            return self::$runtimeSettings[$name];
        }
        return self::getAllSettings()->where('name', $name)->first()?->value;
    }

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    public static function set($name, $value)
    {
        if (array_key_exists($name, self::$runtimeSettings)) {
            self::$runtimeSettings[$name] = $value;
            self::$allSettings = null;
            return;
        }

        self::updateOrCreate([
            'name' => $name,
        ], [
            'value' => $value,
        ]);

        // clear cached settings
        self::$allSettings = null;
    }

    /**
     *  @return array<string>
     */
    public static function getEximConfig(): array
    {
        $default = [
            'smarthost_provider' => '',
            'sendgrid_api_token' => '',
            'mailchannels_username' => '',
            'mailchannels_password' => '',
            'amazon_ses_smtp_endpoint' => '',
            'amazon_ses_starttls_port' => '',
            'amazon_ses_smtp_username' => '',
            'amazon_ses_smtp_password' => '',
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_implicit_tls' => '',
            'sender_domain' => '',
        ];

        $json = self::get('exim');
        if (empty($json)) {
            return $default;
        }
        /** @var mixed */
        $saved = json_decode($json, true);
        if (!is_array($saved)) {
            return $default;
        }

        $config = [];
        foreach ($default as $name => $value) {
            if (!empty($saved[$name])) {
                if (!is_string($saved[$name])) {
                    $saved[$name] = (string)$saved[$name];
                }
                $config[$name] = $saved[$name];
                continue;
            }
            $config[$name] = $value;
        }

        return $config;
    }

    public static function updateEximConfig(array $config): void
    {
        $json = json_encode($config);
        self::set('exim', $json);
    }

    /**
     * @psalm-return ModsecConfig
     */
    public static function getModsecConfig(): array
    {
        $default = [
            'mode' => 'off',
            'enabled_rulesets' => [],
        ];

        $json = self::get('modsec');
        if (empty($json)) {
            return $default;
        }
        /** @var mixed */
        $saved = json_decode($json, true);
        if (!is_array($saved)) {
            return $default;
        }

        $config = [];
        foreach ($default as $name => $value) {
            if (!empty($saved[$name]) && gettype($value) === gettype($saved[$name])) {
                /** @var mixed */
                $config[$name] = $saved[$name];
                continue;
            }
            $config[$name] = $value;
        }

        /** @psalm-var ModsecConfig */
        return $config;
    }

    public static function setModsecConfig(string $name, mixed $value): array
    {
        $config = self::getModsecConfig();
        /** @var mixed */
        $config[$name] = $value;
        $json = json_encode($config);
        self::set('modsec', $json);

        return self::getModsecConfig();
    }
}
