<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // For now, log submission and send a simple email to the configured address.
        Log::info('Contact submission', $data);

        $to = config('mail.from.address') ?? env('MAIL_FROM_ADDRESS');
        if ($to) {
            try {
                Mail::raw("Name: {$data['name']}\nEmail: {$data['email']}\n\n{$data['message']}", function ($message) use ($data, $to) {
                    $message->to($to)->subject('New contact from Dawaoee');
                    $message->replyTo($data['email'], $data['name']);
                });
            } catch (\Throwable $e) {
                Log::error('Failed to send contact email: '.$e->getMessage());
            }
        }

        return response()->noContent();
    }
}
