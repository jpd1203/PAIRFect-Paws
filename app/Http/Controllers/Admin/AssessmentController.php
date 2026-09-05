<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function create(Pet $pet)
    {
        $categories = [];

        if ($pet->species->value === 'Dog') {
            $categories = [
                'energy' => [
                    'label' => 'Energy Level',
                    'type' => 'flat',
                    'items' => [
                        'e1' => 'Hyperactive, restless, has trouble settling down',
                        'e2' => 'Playful, puppyish, boisterous',
                        'e3' => 'Active, energetic, always on the go',
                    ],
                ],
                'trainability' => [
                    'label' => 'Trainability',
                    'type' => 'flat',
                    'items' => [
                        't1' => 'When off the leash, returns immediately when called',
                        't2' => 'Obeys the "sit" command immediately',
                        't3' => 'Obeys the "stay" command immediately',
                        't4' => 'Seems to attend/listen closely to everything you say or do',
                        't5' => 'Slow to respond to punishment/correction or "thick-skinned" (Reverse Scored)',
                        't6' => 'Slow to learn new tricks or tasks (Reverse Scored)',
                        't7' => 'Easily distracted by interesting sights, sounds, or smells (Reverse Scored)',
                        't8' => 'Will "fetch" or attempt to fetch sticks, balls, or objects',
                    ],
                ],
                'independence' => [
                    'label' => 'Independence',
                    'type' => 'flat',
                    'items' => [
                        'i1' => 'Displays a strong attachment for one particular member of the household',
                        'i2' => 'Tends to follow you (or other members of the household) about the house, from room to room',
                        'i3' => 'Tends to sit close to, or in contact with, you (or others) when you are sitting down',
                        'i4' => 'Tends to nudge, nuzzle or paw you (or others) for attention when you are sitting down',
                        'i5' => 'Becomes agitated (whines, jumps up, tries to intervene) when you (or others) show affection for another person',
                        'i6' => 'Becomes agitated when you show affection for another dog or animal',
                    ],
                ],
                'temperament' => [
                    'label' => 'Temperament (Fearfulness)',
                    'type' => 'split',
                    'columns' => [
                        'Stranger-Directed Fear' => [
                            'sf1' => 'Fearful or cautious of strangers (unfamiliar people) who visit the home',
                            'sf2' => 'Fearful or cautious of strangers encountered in public spaces',
                            'sf3' => 'Tends to freeze, cower, or tremble when confronted by strangers',
                            'sf4' => 'Tucks tail or cringes in the presence of strangers',
                            'sf5' => 'Hides behind the owner or retreats from unfamiliar people',
                        ],
                        'Non-Social Fear' => [
                            'nf1' => 'Fearful or startled by sudden or loud noises (e.g., thunder, fireworks, gunshots)',
                            'nf2' => 'Fearful of traffic (cars, trucks, motorcycles)',
                            'nf3' => 'Fearful of unfamiliar or strange-looking objects (e.g., garbage bags, statues)',
                            'nf4' => 'Fearful of unfamiliar situations or new environments',
                            'nf5' => 'Fearful of fast-moving objects (bicycles, skateboards, joggers)',
                            'nf6' => 'Fearful of walking on slippery or unfamiliar surfaces',
                        ],
                    ],
                ],
            ];
        } else {
            // Cats (Fe-BARQ)
            $categories = [
                'energy' => [
                    'label' => 'Energy Level',
                    'type' => 'flat',
                    'items' => [
                        'ce1' => 'Actively seeks play with toys (e.g., chases, bats, or carries objects)',
                        'ce2' => 'Actively seeks play with people (e.g., chases hands, plays fetch)',
                        'ce3' => 'Runs, jumps, or climbs energetically',
                        'ce4' => 'Engages in predatory play (e.g., stalks, pounces on objects or people)',
                        'ce5' => 'Appears restless or unable to settle for extended periods',
                    ],
                ],
                'trainability' => [
                    'label' => 'Trainability',
                    'type' => 'flat',
                    'items' => [
                        'ct1' => 'Responds to its own name when called',
                        'ct2' => 'Comes when called by name or a familiar sound',
                        'ct3' => 'Follows basic cues or commands',
                        'ct4' => 'Appears to learn new behaviors or routines quickly',
                    ],
                ],
                'independence' => [
                    'label' => 'Independence',
                    'type' => 'split',
                    'columns' => [
                        'Sociability' => [
                            'cs1' => 'Approaches familiar people (owner, household members) voluntarily',
                            'cs2' => 'Approaches familiar people voluntarily (Listed twice in manuscript)',
                            'cs3' => 'Remains in close proximity to the owner during daily activities',
                        ],
                        'Attention-Seeking' => [
                            'ca1' => 'Vocalizes (meows, chirps) to attract the owner\'s attention',
                            'ca2' => 'Solicits petting or grooming from the owner',
                            'ca3' => 'Follows the owner around the home throughout the day',
                        ],
                    ],
                ],
                'temperament' => [
                    'label' => 'Temperament (Fearfulness)',
                    'type' => 'flat',
                    'items' => [
                        'tem1' => 'Hisses, growls, or spits at unfamiliar people',
                        'tem2' => 'Swipes or scratches at unfamiliar people without apparent provocation',
                        'tem3' => 'Hides or runs away when unfamiliar people visit the home',
                        'tem4' => 'Shows signs of fear when exposed to unfamiliar objects placed in the home',
                        'tem5' => 'Startles easily at sudden sounds or unexpected stimuli',
                        'tem6' => 'Startles easily at sudden sounds or unexpected stimuli (Listed twice in manuscript)',
                    ],
                ],
            ];
        }

        return view('admin.assessment.form', [
            'animal' => $pet,
            'assessmentNumber' => $pet->assessmentRecords()->count() + 1,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, Pet $pet)
    {
        if ($pet->assessmentRecords()->count() >= 3) {
            return redirect()->route('admin.assessments.record')
                ->withErrors(['assessment' => 'This pet already has three completed assessment records.']);
        }

        $isDog = $pet->species->value === 'Dog';
        $questionKeys = $isDog
            ? [
                'e1', 'e2', 'e3',
                't1', 't2', 't3', 't4', 't5', 't6', 't7', 't8',
                'i1', 'i2', 'i3', 'i4', 'i5', 'i6',
                'sf1', 'sf2', 'sf3', 'sf4', 'sf5',
                'nf1', 'nf2', 'nf3', 'nf4', 'nf5', 'nf6',
            ]
            : [
                'ce1', 'ce2', 'ce3', 'ce4', 'ce5',
                'ct1', 'ct2', 'ct3', 'ct4',
                'cs1', 'cs2', 'cs3', 'ca1', 'ca2', 'ca3',
                'tem1', 'tem2', 'tem3', 'tem4', 'tem5', 'tem6',
            ];

        $rules = array_fill_keys($questionKeys, ['required', 'integer', 'between:1,5']);
        $rules['medical_needs'] = ['required', 'integer', 'between:1,5'];
        $rules['is_reactive_to_pets'] = ['nullable', 'boolean'];
        $rules['has_aggression_history'] = ['nullable', 'boolean'];
        $input = $request->validate($rules);

        $energy = 0.0;
        $trainability = 0.0;
        $independence = 0.0;
        $temperament = 0.0;

        if ($isDog) {
            // E = (E1 + E2 + E3) / 3
            $energy = ($this->val($input, 'e1') + $this->val($input, 'e2') + $this->val($input, 'e3')) / 3;

            // T = Sum(T1..T8) / 8, where T5, T6, T7 are reverse-scored on 1-5 (6 - raw).
            $tSum = $this->val($input, 't1') + $this->val($input, 't2') + $this->val($input, 't3') + $this->val($input, 't4')
                  + (6 - $this->val($input, 't5')) + (6 - $this->val($input, 't6')) + (6 - $this->val($input, 't7'))
                  + $this->val($input, 't8');
            $trainability = $tSum / 8;

            // I = 6 - attachment_mean, so 5 means highly independent.
            $attachment_mean = ($this->val($input, 'i1') + $this->val($input, 'i2') + $this->val($input, 'i3')
                              + $this->val($input, 'i4') + $this->val($input, 'i5') + $this->val($input, 'i6')) / 6;
            $independence = 6 - $attachment_mean;

            // Questionnaire items measure fear. Reverse the mean so 5 means calm/safe.
            $sfMean = ($this->val($input, 'sf1') + $this->val($input, 'sf2') + $this->val($input, 'sf3') + $this->val($input, 'sf4') + $this->val($input, 'sf5')) / 5;
            $nfMean = ($this->val($input, 'nf1') + $this->val($input, 'nf2') + $this->val($input, 'nf3') + $this->val($input, 'nf4') + $this->val($input, 'nf5') + $this->val($input, 'nf6')) / 6;
            $temperament = 6 - (($sfMean + $nfMean) / 2);
        } else {
            // CE = Sum(CE1..CE5) / 5
            $energy = ($this->val($input, 'ce1') + $this->val($input, 'ce2') + $this->val($input, 'ce3') + $this->val($input, 'ce4') + $this->val($input, 'ce5')) / 5;

            // CT = Sum(CT1..CT4) / 4
            $trainability = ($this->val($input, 'ct1') + $this->val($input, 'ct2') + $this->val($input, 'ct3') + $this->val($input, 'ct4')) / 4;

            // CI = 6 - ((CS Mean + CA Mean) / 2), so 5 means highly independent.
            $csMean = ($this->val($input, 'cs1') + $this->val($input, 'cs2') + $this->val($input, 'cs3')) / 3;
            $caMean = ($this->val($input, 'ca1') + $this->val($input, 'ca2') + $this->val($input, 'ca3')) / 3;
            $independence = 6 - (($csMean + $caMean) / 2);

            // Fe-BARQ items measure fear/aggression. Reverse so 5 means calm/safe.
            $fearMean = ($this->val($input, 'tem1') + $this->val($input, 'tem2') + $this->val($input, 'tem3') + $this->val($input, 'tem4') + $this->val($input, 'tem5') + $this->val($input, 'tem6')) / 6;
            $temperament = 6 - $fearMean;
        }

        // Save individual assessment to AssessmentRecords table
        AssessmentRecord::create([
            'pet_id' => $pet->id,
            'assessor_id' => auth()->id(),
            'energy_level' => $energy,
            'trainability' => $trainability,
            'independence' => $independence,
            'temperament' => $temperament,
        ]);

        $assessment_count = $pet->assessmentRecords()->count();

        // Increment pet assessment count tracking
        $petUpdateData = [
            'assessment_count' => $assessment_count,
            'last_assessed_at' => now(),
            'last_assessed_by' => auth()->user()->name ?? 'Admin',
            // KNN flags — always saved from the form, regardless of assessment count
            'medical_needs' => (float) ($input['medical_needs'] ?? 1),
            'is_reactive_to_pets' => isset($input['is_reactive_to_pets']) && $input['is_reactive_to_pets'] == '1',
            'has_aggression_history' => isset($input['has_aggression_history']) && $input['has_aggression_history'] == '1',
        ];

        // Trigger Phase 2: Multi-Assessment Averaging if we reached exactly 3 (or more)
        if ($assessment_count >= 3) {
            $records = $pet->assessmentRecords()->get();

            $final_energy = $records->avg('energy_level');
            $final_trainability = $records->avg('trainability');
            $final_independence = $records->avg('independence');
            $final_temperament = $records->avg('temperament');

            $petUpdateData['energy_level'] = round($final_energy, 1);
            $petUpdateData['trainability'] = round($final_trainability, 1);
            $petUpdateData['independence'] = round($final_independence, 1);
            $petUpdateData['temperament'] = round($final_temperament, 1);
        }

        $pet->update($petUpdateData);

        AuditLogService::log(
            auth()->id(),
            'Pet Assessed',
            'Pet',
            $pet->id,
            "Completed behavioral assessment #$assessment_count for {$pet->name}.".($assessment_count >= 3 ? ' Final scores calculated.' : '')
        );

        return redirect()->route('admin.assessments.record')
            ->with('success', "Pet assessment #$assessment_count saved successfully.".($assessment_count >= 3 ? ' Final behavioral profile finalized.' : ''));
    }

    private function val(array $input, string $key): float
    {
        return (float) $input[$key];
    }

    public function summary(Pet $pet)
    {
        $summary = [
            'energy_level' => (float) ($pet->energy_level ?? 0),
            'trainability' => (float) ($pet->trainability ?? 0),
            'independence' => (float) ($pet->independence ?? 0),
            'temperament' => (float) ($pet->temperament ?? 0),
        ];

        return view('admin.assessment._summary-modal-content', [
            'animal' => $pet,
            'summary' => $summary,
        ]);
    }
}
