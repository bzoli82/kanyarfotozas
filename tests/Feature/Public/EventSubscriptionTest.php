<?php

namespace Tests\Feature\Public;

use App\Jobs\NotifyEventSubscribersJob;
use App\Mail\EventLiveNotificationMail;
use App\Models\Event;
use App\Models\EventSubscription;
use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_visitor_can_subscribe_to_a_location(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'email' => 'Fan@example.com',
            'location' => 'Mátraháza',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('event_subscriptions', ['email' => 'fan@example.com', 'location' => 'Mátraháza']);
        $this->assertNotNull(EventSubscription::query()->first()->unsubscribe_token);
    }

    public function test_subscribing_twice_is_idempotent(): void
    {
        $this->postJson('/api/subscriptions', ['email' => 'fan@example.com', 'location' => 'Eger']);
        $this->postJson('/api/subscriptions', ['email' => 'fan@example.com', 'location' => 'Eger'])->assertOk();

        $this->assertSame(1, EventSubscription::query()->count());
    }

    public function test_event_going_live_dispatches_the_notification_job(): void
    {
        Queue::fake();

        $event = Event::factory()->create([
            'name' => 'Mátra Kanyar',
            'location' => 'Mátraháza',
            'status' => Event::STATUS_ANNOUNCED,
        ]);

        $event->update(['status' => Event::STATUS_LIVE]);

        Queue::assertPushed(NotifyEventSubscribersJob::class);
    }

    public function test_notification_job_emails_matching_subscribers_with_unsubscribe_link(): void
    {
        Mail::fake();

        $event = Event::factory()->create([
            'name' => 'Bükk Túra',
            'location' => 'Lillafüred',
            'status' => Event::STATUS_LIVE,
        ]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        EventSubscription::query()->create(['email' => 'a@example.com', 'location' => 'Lillafüred']);
        EventSubscription::query()->create(['email' => 'b@example.com', 'location' => 'Máshol']);

        (new NotifyEventSubscribersJob($event->id))->handle();

        Mail::assertQueued(EventLiveNotificationMail::class, fn ($mail) => $mail->hasTo('a@example.com'));
        Mail::assertNotQueued(EventLiveNotificationMail::class, fn ($mail) => $mail->hasTo('b@example.com'));

        $this->assertNotNull(EventSubscription::query()->where('email', 'a@example.com')->first()->last_notified_at);
    }

    public function test_unsubscribe_link_removes_the_subscription(): void
    {
        $sub = EventSubscription::query()->create(['email' => 'a@example.com', 'location' => 'Eger']);

        $this->get("/unsubscribe/{$sub->unsubscribe_token}")->assertOk();

        $this->assertModelMissing($sub);
    }
}
