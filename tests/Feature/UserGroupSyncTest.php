<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGroupSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_user_groups()
    {
        // Create groups
        $groupA = Group::create(['name' => 'Basic']);
        $groupB = Group::create(['name' => 'Advanced']);

        // Create target user and assign to group A
        $user = User::factory()->create(['role' => 'member']);
        $user->groups()->attach($groupA->id);

        // Create admin user and authenticate
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->followingRedirects()
            ->put("/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'member',
                'groups' => [$groupB->id],
                'is_active' => true,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $groupB->id,
        ]);

        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $groupA->id,
        ]);
    }
}
