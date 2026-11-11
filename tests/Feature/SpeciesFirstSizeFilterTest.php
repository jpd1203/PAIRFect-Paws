<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\Matching\MatchPresenter;
use App\Services\PetSizeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;
use Tests\Unit\PetSizeClassifierTest;

class SpeciesFirstSizeFilterTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function signIn(): void
    {
        $profile = $this->matchingAdopter();
        $this->actingAs($profile->user);
    }

    private function sizeSelect(string $html): string
    {
        preg_match('/<select id="recommendationSize".*?<\/select>/s', $html, $matches);
        $this->assertNotEmpty($matches);

        return $matches[0];
    }

    public function test_all_species_has_only_all_sizes_and_native_accessible_controls(): void
    {
        $this->signIn();
        $page = $this->get(route('recommendation.results'))->assertOk()
            ->assertSee('for="recommendationSpecies"', false)->assertSee('for="recommendationSize"', false)
            ->assertSee('aria-describedby="recommendationSizeHelp"', false)->assertSee('aria-live="polite"', false);
        $select = $this->sizeSelect($page->getContent());
        $this->assertStringContainsString('disabled', $select);
        $this->assertSame(1, substr_count($select, '<option'));
        $this->assertStringContainsString('All Sizes', $select);
    }

    public function test_labels_are_species_specific_and_submit_unchanged_category_names(): void
    {
        $this->signIn();
        $expected = [
            'Dog' => ['below 6.5 kg', '6.5 to under 9 kg', '9 to under 15 kg', '15 to under 30 kg', '30 kg and above'],
            'Cat' => ['below 3 kg', '3 to under 4 kg', '4 to under 5 kg', '5 to under 6.5 kg', '6.5 kg and above'],
        ];
        foreach ($expected as $species => $ranges) {
            $page = $this->get(route('recommendation.results', ['species' => $species, 'size' => 'Medium']))->assertOk();
            $select = $this->sizeSelect($page->getContent());
            $this->assertStringNotContainsString('disabled', $select);
            foreach (array_values(app(PetSizeClassifier::class)->filterOptions()[$species]) as $index => $option) {
                $this->assertStringContainsString($ranges[$index], $option['label']);
                $this->assertStringContainsString('value="'.$option['value'].'"', $select);
                $this->assertStringContainsString(e($option['label']), $select);
            }
            $this->assertMatchesRegularExpression('/value="Medium"\s+selected/', $select);
            $this->assertStringNotContainsString($expected[$species === 'Dog' ? 'Cat' : 'Dog'][0], $select);
        }
    }

    public static function invalidFilters(): array
    {
        return [
            [['size' => 'Medium'], 'species'], [['species' => '', 'size' => 'Small'], 'species'],
            [['species' => null, 'size' => 'Small'], 'species'], [['species' => 'All Species', 'size' => 'Small'], 'species'],
            [['species' => 'Bird'], 'species'], [['species' => ['Dog']], 'species'], [['species' => 1], 'species'],
            [['species' => 'Dog', 'size' => 'Small (6.5 to under 9 kg)'], 'size'],
            [['species' => 'Cat', 'size' => 'Giant'], 'size'], [['species' => 'Dog', 'size' => ['Medium']], 'size'],
            [['species' => 'Dog', 'size' => false], 'size'], [['score' => 99], 'score'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_get_and_recompute_reject_tampered_or_speciesless_sizes(array $filters, string $error): void
    {
        $this->signIn();
        $this->getJson(route('recommendation.results', $filters))->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->postJson(route('recommendation.recompute'), $filters)->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    public function test_service_cannot_bypass_species_first_validation(): void
    {
        $this->expectException(ValidationException::class);
        app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter(), preferences: ['size' => 'Medium']);
    }

    public static function boundaries(): array
    {
        return PetSizeClassifierTest::boundaries();
    }

    #[DataProvider('boundaries')]
    public function test_sql_filter_and_model_trust_agree_at_exact_weight_boundaries(string $species, mixed $weight, string $size): void
    {
        $pet = $this->matchingPet(['species' => $species, 'weight_kg' => $weight, 'age' => $species === 'Dog' ? 24 : 12]);
        $this->assertTrue($pet->hasClassifiedPhysicalSize());
        $this->assertSame($size, $pet->physical_size);
        foreach (array_keys(config('matching.size_levels')) as $category) {
            $this->assertSame($category === $size, Pet::withClassifiedSize($species, $category)->whereKey($pet->id)->exists());
        }
        $results = app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter(), preferences: ['species' => $species, 'size' => $size], includeIneligible: true);
        $this->assertSame([$pet->id], $results->pluck('pet.id')->all());
    }

    public static function inconsistentRecords(): array
    {
        return [
            [['size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null]],
            [['size_rule_version' => 'old-rule']], [['size_rule_version' => 'WEIGHT-V1']],
            [['size_classification_status' => 'Classified']], [['physical_size' => 'medium']],
            [['weight_kg' => '30.000']], [['weight_kg' => null]], [['weight_kg' => '0']], [['weight_kg' => '-1']],
            [['age' => 23]], [['age' => null]], [['species' => 'Cat']],
        ];
    }

    #[DataProvider('inconsistentRecords')]
    public function test_raw_historical_or_stale_records_are_excluded_even_when_ineligible_are_requested(array $changes): void
    {
        $pet = $this->matchingPet();
        DB::table('pets')->where('id', $pet->id)->update($changes);
        $pet = $pet->fresh();
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $this->assertFalse(Pet::withClassifiedSize('Dog', 'Medium')->whereKey($pet->id)->exists());
        $this->assertCount(0, app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter(), preferences: ['species' => 'Dog', 'size' => 'Medium'], includeIneligible: true));
    }

    public function test_all_sizes_preserves_juvenile_unknown_history_visibility_and_reasons(): void
    {
        $profile = $this->matchingAdopter();
        $pets = [$this->matchingPet(['name' => 'Young Test Pet', 'age' => 23]),
            $this->matchingPet(['name' => 'Unknown Age Test Pet', 'age' => null]),
            $this->matchingPet(['name' => 'Historical Test Pet'])];
        DB::table('pets')->where('id', $pets[2]->id)->update(['size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null]);
        $matcher = app(KnnRecommendationService::class);
        $all = $matcher->recommendPets($profile, preferences: ['species' => 'Dog', 'size' => null], includeIneligible: true);
        $this->assertSame(array_map(fn ($pet) => $pet->id, $pets), $all->pluck('pet.id')->all());
        $this->assertTrue($all->every(fn ($row) => ! $row['match']->eligible && $row['match']->exclusionReason === 'PET_SIZE_NOT_ASSESSED'));
        $this->actingAs($profile->user)->get(route('recommendation.results', ['species' => 'Dog', 'include_ineligible' => 1]))
            ->assertOk()->assertSee('Young Test Pet')->assertSee('Unknown Age Test Pet')->assertSee('Historical Test Pet')
            ->assertSee(MatchPresenter::reason('PET_SIZE_NOT_ASSESSED'))->assertSee('name="include_ineligible" value="1"', false);
        foreach ($pets as $pet) {
            app(ApplicationMatchService::class)->refreshPetSummary($pet->fresh());
        }
        $this->get(route('pets.index'))->assertOk()->assertSee('Young Test Pet')->assertSee('Unknown Age Test Pet')->assertSee('Historical Test Pet');
        $this->assertCount(0, $matcher->recommendPets($profile));
    }

    public function test_size_filter_does_not_change_safety_scores_sorting_or_pagination_queries(): void
    {
        $profile = $this->matchingAdopter(['has_children' => true]);
        for ($i = 0; $i < 7; $i++) {
            $this->matchingPet(['name' => 'Medium Dog '.$i, 'has_aggression_history' => $i === 6]);
        }
        $this->matchingPet(['species' => 'Cat']);
        $this->matchingPet(['physical_size' => 'Large']);
        $historical = $this->matchingPet();
        DB::table('pets')->where('id', $historical->id)->update(['size_rule_version' => null]);
        $matcher = app(KnnRecommendationService::class);
        $all = $matcher->recommendPets($profile, includeIneligible: true);
        $expected = $all->filter(fn ($row) => $row['pet']->species->value === 'Dog' && $row['pet']->physical_size === 'Medium' && $row['pet']->hasClassifiedPhysicalSize())->values();
        $filtered = $matcher->recommendPets($profile, preferences: ['species' => 'Dog', 'size' => 'Medium'], includeIneligible: true);
        $this->assertSame($expected->pluck('result')->all(), $filtered->pluck('result')->all());
        $this->assertSame('EXCLUDED_CHILD_SAFETY', $filtered->last()['match']->exclusionReason);
        $this->actingAs($profile->user);
        $query = ['species' => 'Dog', 'size' => 'Medium', 'include_ineligible' => 1];
        $this->get(route('recommendation.results', $query))->assertOk()->assertViewHas('matches', fn ($page) => $page->total() === 7 && $page->count() === 5
            && str_contains($page->url(2), 'species=Dog') && str_contains($page->url(2), 'size=Medium') && str_contains($page->url(2), 'include_ineligible=1'));
        $this->get(route('recommendation.results', $query + ['page' => 2]))->assertOk()
            ->assertSee('Showing 6 to 7 of 7 pets')->assertSee(MatchPresenter::reason('EXCLUDED_CHILD_SAFETY'));
        $this->postJson(route('recommendation.recompute'), $query)->assertOk()->assertJsonCount(7, 'matches');
    }
}
