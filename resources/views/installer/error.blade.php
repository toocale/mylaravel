@extends('installer.layout')

@section('step_title', 'Installation Error')

@section('content')
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
        <div class="flex">
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Something went wrong</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="pt-4">
        <a href="{{ route('install.database') }}" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Retry Database Setup
        </a>
    </div>
@endsection
