<?php
/**
 * Standalone Installer for Dawaoee
 * 
 * This file handles fresh installations.
 * It creates the .env file, runs migrations, and creates an admin user.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes for migrations

// Configuration
$basePath = dirname(__DIR__);
$envPath = $basePath . '/.env';
$envExamplePath = $basePath . '/.env.example';

// Determine current step
$step = $_GET['step'] ?? 'database';

// Check if already fully installed
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/APP_KEY=base64:.+/', $envContent)) {
        // .env exists with key, check if DB is migrated
        try {
            // Try to connect and check for users table
            require_once $basePath . '/vendor/autoload.php';
            $dotenv = Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->load();
            
            $pdo = new PDO(
                "mysql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_DATABASE'],
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $result = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $result->fetchColumn();
            
            if ($userCount > 0) {
                // Already installed, redirect to app
                header('Location: /public/');
                exit;
            }
            // Has users table but no users - go to admin step
            if ($step === 'database') $step = 'admin';
        } catch (PDOException $e) {
            // Table doesn't exist - need migrations
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                if ($step === 'database') $step = 'migrations';
            }
        } catch (Exception $e) {
            // Other error, continue with current step
        }
    }
}

$errors = [];
$success = false;
$migrateOutput = '';

// ===========================================
// STEP: DATABASE CONFIGURATION
// ===========================================
if ($step === 'database' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? '127.0.0.1';
    $dbPort = $_POST['db_port'] ?? '3306';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_username'] ?? '';
    $dbPass = $_POST['db_password'] ?? '';
    
    if (empty($dbName)) $errors[] = 'Database name is required';
    if (empty($dbUser)) $errors[] = 'Database username is required';
    
    // Test database connection
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("USE `{$dbName}`");
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        // Generate APP_KEY
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        
        // Read .env.example
        $envContent = file_exists($envExamplePath) ? file_get_contents($envExamplePath) : '';
        
        // Update values
        $replacements = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_KEY' => $appKey,
            'APP_URL' => 'http://' . $_SERVER['HTTP_HOST'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
        ];
        
        foreach ($replacements as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }
        
        file_put_contents($envPath, $envContent);
        
        header('Location: install.php?step=migrations');
        exit;
    }
}

// ===========================================
// STEP: RUN MIGRATIONS (Using Laravel Artisan)
// ===========================================
if ($step === 'migrations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Bootstrap Laravel
        require_once $basePath . '/vendor/autoload.php';
        $app = require_once $basePath . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        // Clear any cached config first
        Illuminate\Support\Facades\Artisan::call('config:clear');
        $migrateOutput .= "Config cleared.\n";
        
        // Run migrations
        Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        $migrateOutput .= Illuminate\Support\Facades\Artisan::output();
        
        // Run seeders
        Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $migrateOutput .= "\n" . Illuminate\Support\Facades\Artisan::output();
        
        // Success - redirect to admin
        header('Location: install.php?step=admin');
        exit;
        
    } catch (Exception $e) {
        $errors[] = 'Migration failed: ' . $e->getMessage();
        $migrateOutput = $e->getTraceAsString();
    }
}

// ===========================================
// STEP: CREATE ADMIN
// ===========================================
if ($step === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirmation'] ?? '';
    
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if ($password !== $passwordConfirm) $errors[] = 'Passwords do not match';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    
    if (empty($errors)) {
        try {
            require_once $basePath . '/vendor/autoload.php';
            $app = require_once $basePath . '/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
            
            // Create admin user
            $user = \App\Models\User::create([
                'name' => $name,
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
            
            // Disable debug mode
            $envContent = file_get_contents($envPath);
            $envContent = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=false', $envContent);
            file_put_contents($envPath, $envContent);
            
            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Failed to create admin: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Dawaoee</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-lg border border-gray-100">
        <div class="text-center">
            <h2 class="text-3xl font-extrabold text-gray-900">Setup Wizard</h2>
            <p class="mt-2 text-sm text-gray-600">
                <?php if ($step === 'database'): ?>Step 1: Database Configuration
                <?php elseif ($step === 'migrations'): ?>Step 2: Run Migrations
                <?php elseif ($step === 'admin'): ?>Step 3: Create Admin Account
                <?php endif; ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <ul class="list-disc pl-5 text-sm text-red-700">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($migrateOutput)): ?>
                    <pre class="mt-2 p-2 bg-red-100 text-xs overflow-auto max-h-40"><?php echo htmlspecialchars($migrateOutput); ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 text-center">
                <p class="text-green-800 font-medium">Installation Complete!</p>
                <p class="text-green-700 text-sm mt-2">Your admin account has been created.</p>
                <a href="/public/" class="mt-4 inline-block py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    Go to Application
                </a>
            </div>
        <?php elseif ($step === 'database'): ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Database Host</label>
                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Database Port</label>
                    <input type="text" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Database Name</label>
                    <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Create this database in your hosting panel first.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Database Username</label>
                    <input type="text" name="db_username" value="<?php echo htmlspecialchars($_POST['db_username'] ?? ''); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Database Password</label>
                    <input type="password" name="db_password" 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="pt-4">
                    <button type="submit" class="w-full py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Configure Database
                    </button>
                </div>
            </form>

        <?php elseif ($step === 'migrations'): ?>
            <div class="text-center">
                <p class="text-gray-600 mb-4">Click below to create database tables. This may take a moment.</p>
                <form method="POST">
                    <button type="submit" class="w-full py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Run Migrations
                    </button>
                </form>
            </div>

        <?php elseif ($step === 'admin'): ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" name="password_confirmation" required 
                        class="mt-1 block w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="pt-4">
                    <button type="submit" class="w-full py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Create Admin & Complete
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center text-xs text-gray-500">
            <p>PHP <?php echo phpversion(); ?> | Step: <?php echo $step; ?></p>
        </div>
    </div>
</body>
</html>
