<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get operators (for shift start dropdown)
     */
    public function index()
    {
        // Get users who are in the 'Operator' group
        $users = User::select('id', 'name', 'email', 'account_type')
            ->whereHas('groups', function($query) {
                $query->where('name', 'Operator');
            })
            ->orderBy('name')
            ->get();
            
        \Log::info('Users API called, found ' . $users->count() . ' operators');
            
        return response()->json([
            'users' => $users
        ]);
    }
}
