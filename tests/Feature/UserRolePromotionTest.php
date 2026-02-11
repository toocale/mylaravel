<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_promote_user_to_admin_role()
    {
        $user = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->followingRedirects()
            ->put("/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
                'groups' => [],
                'is_active' => true,
            ])
            ->assertStatus(200);

        $this->assertEquals('admin', $user->fresh()->role);
        $this->assertTrue($user->fresh()->isAdmin());
    }
}
