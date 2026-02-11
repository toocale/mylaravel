@extends('installer.layout')

@section('step_title', 'Step 2: Check Permissions')

@section('content')
    <div class="space-y-4">
        @foreach($permissions as $folder => $isWritable)
            <div class="flex items-center justify-between p-3 border rounded-md {{ $isWritable ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                <span class="font-medium {{ $isWritable ? 'text-green-900' : 'text-red-900' }}">
                    {{ $folder }}
                </span>
                <span class="text-sm {{ $isWritable ? 'text-green-700' : 'text-red-700' }}">
                    {{ $isWritable ? 'Writable' : 'Not Writable' }}
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        @php
            $allWritable = collect($permissions)->every(fn($p) => $p);
        @endphp

        @if($allWritable)
            <a href="{{ route('install.database') }}" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Continue to Database Setup
            </a>
        @else
            <a href="{{ route('install.permissions') }}" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-500 hover:bg-indigo-600 mb-2">
                Refresh
            </a>
            <p class="text-center text-sm text-gray-500">
                Please set permissions to 775 or 777 for the listed folders.
            </p>
        @endif
    </div>
@endsection
