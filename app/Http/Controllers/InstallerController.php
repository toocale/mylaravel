<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InstallerController extends Controller
{
    public function welcome() {
        // Check PHP requirements
        $requirements = [
            'php' => [
                'name' => 'PHP Version >= 8.2',
                'check' => version_compare(phpversion(), '8.2.0', '>='),
            ],
            'pdo' => [
                'name' => 'PDO Extension',
                'check' => extension_loaded('pdo'),
            ],
            'mbstring' => [
                'name' => 'Mbstring Extension',
                'check' => extension_loaded('mbstring'),
            ],
            'curl' => [
                'name' => 'Curl Extension',
                'check' => extension_loaded('curl'),
            ],
            'xml' => [
                'name' => 'XML Extension',
                'check' => extension_loaded('xml'),
            ],
            'zip' => [
                'name' => 'Zip Extension',
                'check' => extension_loaded('zip'),
            ],
        ];

        return view('installer.welcome', compact('requirements'));
    }

    public function permissions() {
        $permissions = [
            'storage/framework/' => is_writable(storage_path('framework')),
            'storage/logs/' => is_writable(storage_path('logs')),
            'bootstrap/cache/' => is_writable(base_path('bootstrap/cache')),
            '.env' => is_writable(base_path('.env')) || is_writable(base_path()),
        ];

        return view('installer.permissions', compact('permissions'));
    }

    public function database() {
        return view('installer.database');
    }

    public function processDatabase(Request $request) {
        $request->validate([
            'db_host' => 'required',
            'db_name' => 'required',
            'db_username' => 'required',
            // db_password can be empty
        ]);

        // Try to connect
        try {
            $pdo = new \PDO(
                "mysql:host={$request->db_host};port={$request->db_port}",
                $request->db_username,
                $request->db_password
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            return back()->withErrors(['connection' => 'Could not connect to database: ' . $e->getMessage()])->withInput();
        }

        // Update .env
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $this->updateEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => url('/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $request->db_host,
            'DB_PORT' => $request->db_port,
            'DB_DATABASE' => $request->db_name,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password,
        ]);

        return redirect()->route('install.migrations');
    }

    public function migrations() {
        // Run migrations
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (\Exception $e) {
            return view('installer.error', ['error' => $e->getMessage()]);
        }

        return redirect()->route('install.register');
    }

    public function register() {
        return view('installer.register');
    }

    public function processRegister(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        // Create Admin User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin', // Ensure you use your specific role/permission logic here
            // Add other mandatory fields if any
        ]);

        // Force Login
        auth()->login($user);

        // Mark as Installed (Update .env APP_KEY if not set, usually key:generate is run)
        // Or simply relying on DB existing and a file marker. 
        // For now, let's create a marker file "installed" in storage to be safe, 
        // although our middleware checked APP_KEY. 
        // Best approach: If APP_KEY is empty, generate it.
        
        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        return redirect()->route('home');
    }

    protected function updateEnv($data) {
        $path = base_path('.env');
        if (file_exists($path)) {
            $content = file_get_contents($path);
            foreach ($data as $key => $value) {
                // Determine pattern
                $pattern = "/^{$key}=.*/m";
                // If quote is needed
                if (preg_match('/\s/', $value)) {
                    $value = '"' . $value . '"';
                }
                
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, "{$key}={$value}", $content);
                } else {
                    $content .= "\n{$key}={$value}";
                }
            }
            file_put_contents($path, $content);
        }
    }
}
