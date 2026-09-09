<?php

namespace Tests\Feature;

use App\Models\RoomCategory;
use App\Models\RoomName;
use App\Models\RoomNumber;
use App\Models\RoomTemp;
use App\Models\RoomTempDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoomTempAntiCollisionTest extends TestCase
{
    use DatabaseTransactions;

    protected $user1;
    protected $user2;
    protected $room;
    protected $room2;
    protected $category;
    protected $roomName;

    protected function setUp(): void
    {
        parent::setUp();

        // Find or create test users
        $this->user1 = User::first() ?? User::create([
            'name' => 'Test User 1',
            'user_name' => 'testuser1',
            'email' => 'test1@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 1
        ]);

        $this->user2 = User::where('id', '!=', $this->user1->id)->first() ?? User::create([
            'name' => 'Test User 2',
            'user_name' => 'testuser2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'status' => 1
        ]);

        // Get or create rooms for test
        $this->category = RoomCategory::first() ?? RoomCategory::create(['name' => 'Deluxe']);
        $this->roomName = RoomName::first() ?? RoomName::create([
            'name' => 'Deluxe Room',
            'room_category_id' => $this->category->id,
            'rate' => 2500
        ]);

        $rooms = RoomNumber::take(2)->get();
        if ($rooms->count() >= 2) {
            $this->room = $rooms[0];
            $this->room2 = $rooms[1];
        } else {
            $this->room = RoomNumber::first() ?? RoomNumber::create([
                'room_no' => '101',
                'room_name_id' => $this->roomName->id,
                'room_category_id' => $this->category->id,
                'room_floor_id' => 1,
            ]);
            $this->room2 = RoomNumber::create([
                'room_no' => '102',
                'room_name_id' => $this->roomName->id,
                'room_category_id' => $this->category->id,
                'room_floor_id' => 1,
            ]);
        }
    }

    public function test_session_creation_and_validation()
    {
        $response = $this->actingAs($this->user1)
            ->postJson('/api/session');

        $response->assertStatus(201)
            ->assertJsonStructure(['session_id', 'is_new', 'message'])
            ->assertJson(['is_new' => true]);

        $sessionId = $response->json('session_id');
        $this->assertDatabaseHas('room_temps', [
            'id' => $sessionId,
            'user_id' => $this->user1->id
        ]);

        // Reusing existing session
        $reuseResponse = $this->actingAs($this->user1)
            ->postJson('/api/session', ['session_id' => $sessionId]);

        $reuseResponse->assertStatus(200)
            ->assertJson(['session_id' => $sessionId, 'is_new' => false]);
    }

    public function test_expired_or_invalid_session_id_returns_new_session()
    {
        $invalidSessionId = '999999999999999';

        $response = $this->actingAs($this->user1)
            ->postJson('/api/session', ['session_id' => $invalidSessionId]);

        $response->assertStatus(201)
            ->assertJson(['is_new' => true]);

        $newSessionId = $response->json('session_id');
        $this->assertNotEquals($invalidSessionId, $newSessionId);
        $this->assertDatabaseHas('room_temps', [
            'id' => $newSessionId,
            'user_id' => $this->user1->id
        ]);
    }

    public function test_multi_tab_independent_session_creation()
    {
        // Tab 1 creates session
        $tab1Response = $this->actingAs($this->user1)
            ->postJson('/api/session');
        $tab1Response->assertStatus(201);
        $tab1SessionId = $tab1Response->json('session_id');

        // Tab 2 creates session
        $tab2Response = $this->actingAs($this->user1)
            ->postJson('/api/session');
        $tab2Response->assertStatus(201);
        $tab2SessionId = $tab2Response->json('session_id');

        // Verify Tab 1 and Tab 2 have different independent IDs
        $this->assertNotEquals($tab1SessionId, $tab2SessionId);
        $this->assertDatabaseHas('room_temps', ['id' => $tab1SessionId, 'user_id' => $this->user1->id]);
        $this->assertDatabaseHas('room_temps', ['id' => $tab2SessionId, 'user_id' => $this->user1->id]);
    }

    public function test_other_users_session_cannot_be_reused()
    {
        // User 2 creates a session
        $user2Session = RoomTemp::create(['user_id' => $this->user2->id]);

        // User 1 tries to use User 2's session ID
        $response = $this->actingAs($this->user1)
            ->postJson('/api/session', ['session_id' => $user2Session->id]);

        // User 1 should get a brand new session, NOT User 2's session
        $response->assertStatus(201)
            ->assertJson(['is_new' => true]);

        $user1NewSessionId = $response->json('session_id');
        $this->assertNotEquals((string) $user2Session->id, $user1NewSessionId);
        $this->assertDatabaseHas('room_temps', [
            'id' => $user1NewSessionId,
            'user_id' => $this->user1->id
        ]);
    }

    public function test_single_room_anti_collision_returns_409()
    {
        $targetDate = Carbon::today()->addDays(5)->toDateString();

        // User 1 creates session and holds room
        $session1 = RoomTemp::create(['user_id' => $this->user1->id]);
        RoomTempDetail::create([
            'room_temp_id' => $session1->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $targetDate
        ]);

        // User 2 creates session and tries to hold the same room
        $session2 = RoomTemp::create(['user_id' => $this->user2->id]);
        $response = $this->actingAs($this->user2)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session2->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $targetDate,
                    ]
                ]
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'conflict' => true,
            ]);

        $this->assertStringContainsString('already held', $response->json('message'));
    }

    public function test_yesterday_date_rule_validation()
    {
        $session = RoomTemp::create(['user_id' => $this->user1->id]);

        $twoDaysAgo = Carbon::yesterday()->subDay()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::today()->toDateString();

        // Attempting date earlier than yesterday (2 days ago) should fail with 409
        $failResponse = $this->actingAs($this->user1)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $twoDaysAgo,
                    ]
                ]
            ]);

        $failResponse->assertStatus(409);
        $this->assertStringContainsString('Cannot hold rooms for dates earlier than yesterday', $failResponse->json('message'));

        // Yesterday should succeed
        $yesterdayResponse = $this->actingAs($this->user1)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $yesterday,
                    ]
                ]
            ]);

        $yesterdayResponse->assertStatus(200)
            ->assertJson(['success' => true, 'held_count' => 1]);

        // Today should succeed
        $todayResponse = $this->actingAs($this->user1)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room2->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $today,
                    ]
                ]
            ]);

        $todayResponse->assertStatus(200)
            ->assertJson(['success' => true, 'held_count' => 1]);
    }

    public function test_batch_hold_skips_conflicted_rooms_and_holds_available()
    {
        $targetDate = Carbon::today()->addDays(7)->toDateString();

        // User 1 holds room 1
        $session1 = RoomTemp::create(['user_id' => $this->user1->id]);
        RoomTempDetail::create([
            'room_temp_id' => $session1->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $targetDate
        ]);

        // User 2 tries batch hold for room 1 and room 2 on same date
        $session2 = RoomTemp::create(['user_id' => $this->user2->id]);
        $response = $this->actingAs($this->user2)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session2->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $targetDate,
                    ],
                    [
                        'room_number_id' => $this->room2->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $targetDate,
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'held_count' => 1,
                'skipped_count' => 1
            ]);

        $this->assertCount(1, $response->json('skipped_rooms'));
        $this->assertEquals($this->room->id, $response->json('skipped_rooms.0.room_number_id'));

        // Verify room2 is held by session2 in database
        $this->assertDatabaseHas('room_temp_details', [
            'room_temp_id' => $session2->id,
            'room_number_id' => $this->room2->id,
            'date' => $targetDate
        ]);
    }

    public function test_room_temp_release_date_releases_held_rooms()
    {
        $date1 = Carbon::today()->addDays(10)->toDateString();
        $date2 = Carbon::today()->addDays(11)->toDateString();

        $session = RoomTemp::create(['user_id' => $this->user1->id]);
        RoomTempDetail::create([
            'room_temp_id' => $session->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $date1
        ]);
        RoomTempDetail::create([
            'room_temp_id' => $session->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $date2
        ]);

        $response = $this->actingAs($this->user1)
            ->postJson('/api/room-temp-release-date', [
                'session_id' => $session->id,
                'date' => $date1
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // date1 should be removed, date2 should remain
        $this->assertDatabaseMissing('room_temp_details', [
            'room_temp_id' => $session->id,
            'date' => $date1
        ]);
        $this->assertDatabaseHas('room_temp_details', [
            'room_temp_id' => $session->id,
            'date' => $date2
        ]);
    }

    public function test_get_current_users_temp_list_eager_loads_relations()
    {
        $session = RoomTemp::create(['user_id' => $this->user1->id]);
        $date = Carbon::today()->addDays(12)->toDateString();

        RoomTempDetail::create([
            'room_temp_id' => $session->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $date
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson('/api/current-users-temp-list');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $found = collect($data)->firstWhere('id', $session->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('details', $found);
        $this->assertNotEmpty($found['details']);
    }

    public function test_destroy_all_sessions_deletes_all_user_sessions()
    {
        $session1 = RoomTemp::create(['user_id' => $this->user1->id]);
        $session2 = RoomTemp::create(['user_id' => $this->user1->id]);

        $date = Carbon::today()->addDays(15)->toDateString();
        RoomTempDetail::create([
            'room_temp_id' => $session1->id,
            'room_number_id' => $this->room->id,
            'room_category_id' => $this->category->id,
            'room_name_id' => $this->roomName->id,
            'bed' => 'DBL',
            'rate' => 2500,
            'date' => $date
        ]);

        $response = $this->actingAs($this->user1)
            ->deleteJson('/api/destroy-all-sessions');

        $response->assertStatus(200)
            ->assertJson(['message' => 'All room hold sessions deleted successfully.']);

        $this->assertDatabaseMissing('room_temps', ['id' => $session1->id]);
        $this->assertDatabaseMissing('room_temps', ['id' => $session2->id]);
        $this->assertDatabaseMissing('room_temp_details', ['room_temp_id' => $session1->id]);
    }

    public function test_unauthorized_session_access_returns_403()
    {
        $session = RoomTemp::create(['user_id' => $this->user1->id]);
        $targetDate = Carbon::today()->addDays(20)->toDateString();

        // User 2 attempts to use User 1's session_id
        $response = $this->actingAs($this->user2)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $targetDate,
                    ]
                ]
            ]);

        $response->assertStatus(403);
    }

    public function test_maintenance_blocked_room_is_rejected_with_409()
    {
        $targetDate = Carbon::today()->addDays(22)->toDateString();

        // Create a maintenance block
        $block = \App\Models\RoomBlock::create([
            'user_id' => $this->user1->id,
            'note' => 'Air conditioning repair'
        ]);
        \App\Models\RoomBlockDetail::create([
            'room_block_id' => $block->id,
            'room_id' => $this->room->id,
            'date' => $targetDate
        ]);

        $session = RoomTemp::create(['user_id' => $this->user1->id]);
        $response = $this->actingAs($this->user1)
            ->postJson('/api/room-temp-block', [
                'session_id' => $session->id,
                'rooms' => [
                    [
                        'room_number_id' => $this->room->id,
                        'room_category_id' => $this->category->id,
                        'room_name_id' => $this->roomName->id,
                        'bed' => 'DBL',
                        'rate' => 2500,
                        'date' => $targetDate,
                    ]
                ]
            ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'conflict' => true]);
        $this->assertStringContainsString('maintenance', $response->json('message'));
    }
}
