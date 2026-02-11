@extends('installer.layout')

@section('step_title', 'Step 1: Check Requirements')

@section('content')
    <div class="space-y-4">
        @foreach($requirements as $type => $requirement)
            <div class="flex items-center justify-between p-3 border rounded-md {{ $requirement['check'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                <span class="font-medium {{ $requirement['check'] ? 'text-green-900' : 'text-red-900' }}">
                    {{ $requirement['name'] }}
                </span>
                @if($requirement['check'])
                    <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                @else
                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        @php
            $allMet = collect($requirements)->every(fn($req) => $req['check']);
        @endphp

        @if($allMet)
            <a href="{{ route('install.permissions') }}" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Continue to Permissions
            </a>
        @else
            <button disabled class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gray-400 cursor-not-allowed">
                Please fix requirements to continue
            </button>
            <div class="mt-2 text-center text-sm text-gray-500">
                Contact your hosting provider to enable missing extensions.
            </div>
        @endif
    </div>
@endsection
