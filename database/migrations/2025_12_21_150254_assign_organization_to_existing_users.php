<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first real organization (not "Default Org" which is typically empty)
        $defaultOrganization = Organization::where('name', '!=', 'Default Org')
            ->orderBy('created_at')
            ->first();
        
        // Fallback to any organization if no "real" one exists
        if (!$defaultOrganization) {
            $defaultOrganization = Organization::first();
        }
        
        if (!$defaultOrganization) {
            Log::warning('No organization found to assign users to. Skipping user organization assignment.');
            return;
        }
        
        // Find all users without an organization
        $usersWithoutOrg = User::whereNull('organization_id')->get();
        
        $count = 0;
        foreach ($usersWithoutOrg as $user) {
            $user->organization_id = $defaultOrganization->id;
            $user->save();
            $count++;
            
            Log::info('Assigned user to organization', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'organization_id' => $defaultOrganization->id,
                'organization_name' => $defaultOrganization->name
            ]);
        }
        
        Log::info('Migration complete: assigned organization to existing users', [
            'users_updated' => $count,
            'organization_id' => $defaultOrganization->id,
            'organization_name' => $defaultOrganization->name
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to remove organization assignments on rollback
        // as it might break access for users. Manual intervention would be better.
        Log::info('Rollback of organization assignment migration - no action taken (preserve user access)');
    }
};
