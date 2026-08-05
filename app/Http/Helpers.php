<?php

// use AizPackages\ColorCodeConverter\Services\ColorCodeConverter;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

if (!function_exists('api_asset')) {
    function api_asset($id)
    {
        if (($asset = \App\Models\Uploads::find($id)) != null) {
            return my_asset($asset->file_name);
        }
        return "";
    }
}

if (!function_exists('uploaded_asset')) {
    function uploaded_asset($id)
    {
        if (($asset = \App\Models\Uploads::find($id)) != null) {
            return my_asset($asset->file_name);
        }
        return null;
    }
}

if (!function_exists('my_asset')) {
    function my_asset($path, $secure = null)
    {
        if (empty($path)) return "";

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $driver = config('filesystems.default');

        try {
            if (in_array($driver, ['cloudinary', 's3'])) {
                return \Illuminate\Support\Facades\Storage::disk($driver)->url($path);
            }
        } catch (\Exception $e) {
        }

        return asset($path, $secure);
    }
}

if (!function_exists('static_asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function static_asset($path, $secure = null)
    {
        // return app('url')->asset('public/' . $path, $secure);
        return app('url')->asset($path, $secure);
    }
}

if (!function_exists('getBaseURL')) {
    function getBaseURL()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $base = str_replace('/public', '', $scriptDir);
        $base = rtrim($base, '/') . '/';

        return $scheme . '://' . $host . $base;
    }
}

if (!function_exists('getFileBaseURL')) {
    function getFileBaseURL()
    {
        if (env('FILESYSTEM_DRIVER') == 's3') {
            return env('AWS_URL') . '/';
        } else {
            return getBaseURL() . '/public/';
        }
    }
}


if (!function_exists('get_setting')) {
    function get_setting($key, $default = null)
    {
        $settings = Cache::remember('settings', 86400, function () {
            return Setting::all();
        });

        $setting = $settings->where('type', $key)->first();

        return $setting == null ? $default : $setting->value;
    }
}
