<?php

namespace App\Support;

/**
 * Question banks for the Pet Assessment forms, ported from
 * PetAssessment_dogAssessmentForm.html and PetAssessment_catAssessmentForm.html.
 *
 * Both species share the same 4 behavioral categories (Energy Level,
 * Trainability, Independence, Temperament) and the same 0-4 rating scale,
 * but the exact questions differ, and two categories are split into two
 * side-by-side sub-columns for one species but not the other — so each
 * category is either:
 *   ['type' => 'flat',   'items' => [...]]
 *   ['type' => 'paired', 'columns' => ['Column A' => [...], 'Column B' => [...]]]
 *
 * Every question's rating rolls up into its top-level category average
 * (both paired columns count toward the same category), which is what
 * PetAssessment stores as *_avg and what the Assessment Record summary
 * (Image 1) displays.
 */
class AssessmentQuestions
{
    public static function forSpecies(string $species): array
    {
        return $species === 'Dog' ? self::dog() : self::cat();
    }

    public static function dog(): array
    {
        return [
            'energy_level' => ['label' => 'Energy Level', 'type' => 'flat', 'items' => [
                'Hyperactive, restless, has trouble settling down.',
                'Playful, puppyish, boisterous.',
                'Active, energetic, always on the go.',
            ]],
            'trainability' => ['label' => 'Trainability', 'type' => 'flat', 'items' => [
                'When off the leash, returns immediately when called.',
                'Obeys the "sit" command immediately',
                'Obeys the "stay" command immediately',
                'Seems to attend/listen closely to everything you say or do',
                'Slow to respond to correction or punishment; "thick-skinned"',
                'Slow to learn new tricks or tasks',
                'Easily distracted by interesting sights, sounds, or smells',
                'Will "fetch" or attempt to fetch sticks, balls, or objects',
            ]],
            'independence' => ['label' => 'Independence (Attachment / Attention-Seeking)', 'type' => 'flat', 'items' => [
                'Displays a strong attachment for one particular member of the household',
                'Tends to follow you (or other members of the household) about the house, from room to room.',
                'Tends to sit close to, or in contact with, you (or others) when you are sitting down.',
                'Tends to nudge, nuzzle or paw you (or others) for attention when you are sitting down.',
                'Becomes agitated (whines, jumps up, tries to intervene) when you (or others) show affection for a person.',
                'Becomes agitated (whines, jumps up, tries to intervene) when you (or others) show affection for another dog or animal.',
            ]],
            'temperament' => ['label' => 'Temperament', 'type' => 'paired', 'columns' => [
                'Stranger-Directed Fear Items' => [
                    'Fearful or cautious of strangers (unfamiliar people) who visit the home',
                    'Fearful or cautious of strangers (unfamiliar people) encountered in public spaces',
                    'Tends to freeze, cower, or tremble when confronted by strangers',
                    'Tucks tail or cringes in the presence of strangers',
                    'Hides behind the owner or retreats from unfamiliar people',
                ],
                'Non-Social Fear Items' => [
                    'Fearful or startled by sudden or loud noises (e.g., thunder, fireworks, gunshots)',
                    'Fearful of traffic (cars, trucks, motorcycles)',
                    'Fearful of unfamiliar or strange-looking objects (e.g., garbage bags, statues)',
                    'Fearful of unfamiliar situations or new environments',
                    'Fearful of fast-moving objects (bicycles, skateboards, joggers)',
                    'Fearful of walking on slippery or unfamiliar surfaces',
                ],
            ]],
        ];
    }

    public static function cat(): array
    {
        return [
            'energy_level' => ['label' => 'Energy Level', 'type' => 'flat', 'items' => [
                'Actively seeks play with toys (e.g., chases, bats, or carries objects).',
                'Actively seeks play with people (e.g., chases hands, plays fetch).',
                'Runs, jumps, or climbs energetically.',
                'Engages in predatory play (e.g., stalks, pounces on objects or people)',
                'Appears restless or unable to settle for extended periods',
            ]],
            'trainability' => ['label' => 'Trainability', 'type' => 'flat', 'items' => [
                'Responds to its own name when called',
                "Comes when called by name or a familiar sound (e.g., food package rustling)",
                "Follows basic cues or commands (e.g., 'sit,' 'come,' 'up')",
                'Appears to learn new behaviors or routines quickly',
            ]],
            'independence' => ['label' => 'Independence (Sociability + Attention-Seeking)', 'type' => 'paired', 'columns' => [
                'Sociability with People' => [
                    'Approaches familiar people (owner, household members) voluntarily',
                    'Approaches familiar people (owner, household members) voluntarily',
                    'Remains in close proximity to the owner during daily activities',
                ],
                'Attention-Seeking' => [
                    "Vocalizes (meows, chirps) to attract the owner's attention",
                    'Solicits petting or grooming from the owner',
                    'Follows the owner around the home throughout the day',
                ],
            ]],
            'temperament' => ['label' => 'Temperament', 'type' => 'flat', 'items' => [
                'Hisses, growls, or spits at unfamiliar people',
                'Swipes or scratches at unfamiliar people without apparent provocation',
                'Hides or runs away when unfamiliar people visit the home',
                'Shows signs of fear when exposed to unfamiliar objects placed in the home (e.g., new furniture, bags)',
                'Startles easily at sudden sounds or unexpected stimuli',
                'Startles easily at sudden sounds or unexpected stimuli',
            ]],
        ];
    }
}
