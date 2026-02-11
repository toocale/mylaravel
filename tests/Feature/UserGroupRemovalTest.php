<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGroupRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_all_groups_from_user()
    {
        // Create a group and assign to user
        $group = Group::create(['name' => 'Temp']);

        $user = User::factory()->create(['role' => 'member']);
        $user->groups()->attach($group->id);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->followingRedirects()
            ->put("/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'member',
                'groups' => [],
                'is_active' => true,
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);
    }
}
