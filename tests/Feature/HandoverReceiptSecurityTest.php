<?php

namespace Tests\Feature;

use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class HandoverReceiptSecurityTest extends TestCase
{
    use BuildsMatchingFixtures;
    use RefreshDatabase;

    private const CONFIRM_HANDLER = "return confirm('Are you sure you want to report that your pet has not arrived? Staff will be alerted immediately.');";

    public static function petNames(): array
    {
        return [
            'ordinary' => ['Koko'],
            'apostrophe' => ["O'Reilly"],
            'quotation marks' => ['The "Best" Pet'],
            'HTML-like markup' => ['<img src=x onerror="window.__receiptMarker=1">'],
            'JavaScript-like payload' => ["Koko'); window.__receiptMarker=1; //"],
            'attribute-like payload' => ['" autofocus onfocus="window.__receiptMarker=1'],
        ];
    }

    #[DataProvider('petNames')]
    public function test_pet_names_are_escaped_text_and_never_part_of_executable_code(string $name): void
    {
        $handover = $this->releasedHandover($name);
        $originalHistory = $handover->history;

        $response = $this->actingAs($handover->user)
            ->get(route('adopter.confirm', $handover))
            ->assertOk()
            ->assertSee($name);

        $html = $response->getContent();
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $negative = $xpath->query('//button[@name="outcome" and @value="not_received"]');
        $positive = $xpath->query('//button[@name="outcome" and @value="received"]');

        $this->assertCount(1, $negative);
        $this->assertCount(1, $positive);
        $this->assertSame(self::CONFIRM_HANDLER, $negative->item(0)->getAttribute('onclick'));
        $this->assertSame('submit', $negative->item(0)->getAttribute('type'));
        $this->assertStringContainsString("Yes, I've received ".$name, $positive->item(0)->textContent);
        $this->assertSame('', $positive->item(0)->getAttribute('onclick'));

        $form = $negative->item(0)->parentNode;
        while ($form->nodeName !== 'form') {
            $form = $form->parentNode;
        }
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('adopter.confirm.submit', $handover), $form->getAttribute('action'));
        $this->assertCount(1, $xpath->query('.//input[@name="_token" and @type="hidden"]', $form));
        $this->assertCount(1, $xpath->query('.//input[@name="receipt_proof" and @type="file"]', $form));

        foreach ($xpath->query('//script | //@*[starts-with(translate(name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "on")]') as $executable) {
            $this->assertStringNotContainsString($name, $executable->textContent);
            $this->assertStringNotContainsString('__receiptMarker', $executable->textContent);
        }
        $this->assertCount(0, $xpath->query('//*[@onerror or @onfocus or @autofocus]'));
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertSame($originalHistory, $handover->fresh()->history);
    }

    public function test_negative_outcome_keeps_existing_authorization_release_and_duplicate_guards(): void
    {
        Mail::fake();
        $handover = $this->releasedHandover("Koko'); window.__receiptMarker=1; //");
        $url = route('adopter.confirm.submit', $handover);

        $this->actingAs($this->matchingUser())->post($url, ['outcome' => 'not_received'])->assertForbidden();
        $this->assertNull($handover->fresh()->adopter_outcome);

        $handover->update(['released_at' => null]);
        $this->actingAs($handover->user)->post($url, ['outcome' => 'not_received'])
            ->assertSessionHasErrors('outcome');
        $this->assertNull($handover->fresh()->adopter_outcome);

        $handover->update(['released_at' => now()]);
        $this->post($url, ['outcome' => 'other'])->assertSessionHasErrors('outcome');
        $this->post($url, ['outcome' => 'received'])->assertSessionHasErrors('receipt_proof');
        $this->assertNull($handover->fresh()->adopter_outcome);

        // A non-receipt report does not require a receipt photo or activate monitoring.
        $this->post($url, ['outcome' => 'not_received', 'note' => 'Synthetic test report.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $handover->refresh();
        $this->assertSame('not_received', $handover->adopter_outcome);
        $this->assertNotNull($handover->adopter_confirmed_at);
        $this->assertNull($handover->received_at);
        $this->assertNull($handover->receipt_proof_path);
        $this->assertSame('Synthetic test report.', $handover->adopter_note);
        $this->assertDatabaseMissing('post_adoption_logs', ['application_id' => $handover->application_id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'handover.not_received', 'entity_id' => $handover->id]);
        $this->assertDatabaseHas('handover_notifications', ['handover_id' => $handover->id, 'kind' => 'issue_logged']);

        $history = $handover->history;
        $notificationCount = $handover->notifications()->count();
        $this->post($url, ['outcome' => 'not_received'])->assertSessionHasErrors('outcome');
        $this->assertSame($history, $handover->fresh()->history);
        $this->assertSame($notificationCount, $handover->notifications()->count());
    }

    private function releasedHandover(string $name): Handover
    {
        $user = $this->matchingUser();
        $pet = Pet::create(['name' => $name, 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create([
            'user_id' => $user->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now(),
        ]);

        return Handover::create([
            'code' => 'SEC-'.Str::uuid(), 'application_id' => $application->id,
            'pet_id' => $pet->id, 'user_id' => $user->id, 'adopter_name' => $user->full_name,
            'approved_at' => now(), 'released_at' => now(), 'release_method' => 'delivery',
            'history' => [], 'reminders' => [],
        ]);
    }
}
