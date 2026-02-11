@extends('installer.layout')

@section('step_title', 'Step 3: Database Configuration')

@section('content')
    <form action="{{ route('install.database.post') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label for="db_host" class="block text-sm font-medium text-gray-700">Database Host</label>
            <input type="text" name="db_host" id="db_host" value="{{ old('db_host', '127.0.0.1') }}" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <label for="db_port" class="block text-sm font-medium text-gray-700">Database Port</label>
            <input type="text" name="db_port" id="db_port" value="{{ old('db_port', '3306') }}" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <label for="db_name" class="block text-sm font-medium text-gray-700">Database Name</label>
            <input type="text" name="db_name" id="db_name" value="{{ old('db_name') }}" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Create this empty database in cPanel first.</p>
        </div>

        <div>
            <label for="db_username" class="block text-sm font-medium text-gray-700">Database Username</label>
            <input type="text" name="db_username" id="db_username" value="{{ old('db_username') }}" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div>
            <label for="db_password" class="block text-sm font-medium text-gray-700">Database Password</label>
            <input type="password" name="db_password" id="db_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div class="pt-4">
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Verify & Configure Database
            </button>
        </div>
    </form>
@endsection
