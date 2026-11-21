<?php

namespace Database\Seeders;

/** Fixed fictional profiles. Ages are months; weights are current kg. */
final class ResearchPets
{
    private const DATA = <<<'PETS'
Dog|Amihan|Aspin|5|5.2|Trails a soft rope toy around the pen
Dog|Bayani|Aspin|7|8.4|Pauses to sniff each new corner of the yard
Dog|Cora|Shih Tzu|8|4.7|Carries a fabric ball to the bedding area
Dog|Dalisay|Mixed-breed dog|9|12.1|Looks to a familiar handler before crossing a threshold
Dog|Ember|Beagle|11|8.8|Works through a scent mat at a steady pace
Dog|Fenn|Miniature Poodle|13|6.2|Returns a toy to begin another short training round
Dog|Ginto|Golden Retriever|14|25.2|Collects toys into a pile beside the water bowl
Dog|Habi|Aspin|16|17.4|Watches the yard gate from a shaded spot
Dog|Isla|Mixed-breed dog|18|22.1|Takes a few minutes to settle in an unfamiliar run
Dog|Juno|Labrador Retriever|20|28.4|Prefers gentle games with a squeaky toy
Dog|Kiko|Aspin|22|10.5|Learns feeding cues while staying close to the mat
Dog|Lira|Aspin|25|14.3|Checks in with a handler during sniff walks
Dog|Mako|Labrador Retriever|28|31.8|Enjoys slowly retrieving toys in an enclosed yard
Dog|Nilo|Dachshund|30|7.1|Searches for kibble in a low puzzle tray
Dog|Ora|Mixed-breed dog|33|19.2|Brings a tug ring over to invite play
Dog|Pippa|Shih Tzu|36|5.4|Naps near a familiar person's shoes
Dog|Quinn|Aspin|39|11.3|Waits by the door when a leash appears
Dog|Rosa|Beagle|42|10.1|Follows winding scent trails on grass
Dog|Sago|Miniature Poodle|45|5.9|Offers learned cues during short sessions
Dog|Tala|Aspin|48|15.7|Chooses a quiet corner after a walk
Dog|Ube|Mixed-breed dog|51|23.3|Lifts a paw for attention when approached
Dog|Vera|Cocker Spaniel|54|12.8|Settles with a chew after grooming practice
Dog|Wren|Aspin|58|9.4|Uses a snuffle towel before breakfast
Dog|Xavi|German Shepherd|61|34.5|Tracks movement outside the yard before settling
Dog|Yumi|Japanese Spitz|66|7.6|Alerts briefly when a familiar visitor arrives
Dog|Zora|Aspin|71|13.6|Lies at the edge of the play area to observe
Dog|Banjo|Mixed-breed dog|77|20.2|Moves between shaded resting spots during the day
Dog|Chico|Yorkshire Terrier|89|3.4|Searches a small room for soft toys
Dog|Datu|Aspin|98|16.3|Chooses slow sniff walks over fetch
Dog|Eli|Pug|104|8.1|Rests on a cool mat after gentle play
Dog|Fable|Shih Tzu|112|5.7|Prefers a floor-level bed beside the doorway
Dog|Gabi|Aspin|120|12.4|Follows a short familiar route around the yard
Dog|Hiro|Mixed-breed dog|125|26.1|Watches activity from a padded bed
Dog|Iggy|Beagle|135|11.5|Investigates a quiet scent trail without rushing
Dog|Jade|Miniature Poodle|149|6.4|Enjoys brief food puzzles on a nonslip mat
Cat|Aster|Puspin|4|1.6|Pounces on a crinkle ball between naps
Cat|Bituin|Puspin|5|2.1|Watches dangling toys from the edge of a tunnel
Cat|Clover|Domestic Shorthair|6|2.5|Bats paper balls under a low shelf
Cat|Dune|Siamese|7|2.8|Calls softly when a familiar person enters
Cat|Echo|Domestic Shorthair|8|3.1|Turns puzzle pieces over to find treats
Cat|Flora|Puspin|8|2.7|Chases a fabric mouse beneath a chair
Cat|Gale|Domestic Longhair|9|3.4|Sits on a low perch to watch other cats play
Cat|Hazel|Puspin|9|3.3|Explores a carrier when the door is left open
Cat|Indigo|Siamese|10|3.5|Approaches for gentle cheek rubs after meals
Cat|Jasper|Domestic Shorthair|10|3.7|Prefers short sessions with a wand toy
Cat|Kiwi|Puspin|11|3.2|Uses a cardboard scratcher near the door
Cat|Lumen|Puspin|13|3.6|Curls into a blanket when the room is quiet
Cat|Mango|Domestic Shorthair|16|4.5|Checks each ledge before taking a nap
Cat|Nova|British Shorthair|19|4.6|Follows a rolling treat across the floor
Cat|Olive|Puspin|22|2.9|Greets familiar people at floor level
Cat|Pearl|Persian|25|3.9|Sits on a familiar cushion during grooming
Cat|Quill|Puspin|29|4.3|Carries a cloth mouse to the window
Cat|River|Domestic Shorthair|33|5.2|Uses a short climbing route between shelves
Cat|Sage|Ragdoll|37|5.8|Follows a handler from bed to food bowl
Cat|Tofu|Puspin|42|3.7|Explores boxes left open on the floor
Cat|Uma|Domestic Longhair|48|4.7|Moves to the window after morning feeding
Cat|Vito|Maine Coon|55|7.0|Pats floating toys in a shallow water tray
Cat|Willow|Puspin|62|3.4|Brings small toys to the side of a bed
Cat|Zuri|Siamese|71|4.0|Calls to familiar people at the doorway
Cat|Acacia|Domestic Shorthair|80|4.8|Sits near a shelf before approaching new visitors
Cat|Bamboo|Puspin|96|3.6|Settles in a quiet fabric tunnel
Cat|Calamansi|Maine Coon|108|6.9|Chooses broad low shelves instead of narrow perches
Cat|Dew|Domestic Shorthair|123|5.0|Likes quiet observation from a window hammock
Cat|Fern|Puspin|132|3.6|Rests on a warm cushion near the feeding bowls
Cat|Guava|Persian|138|4.1|Accepts short brushing sessions with breaks
Cat|Honey|Puspin|146|3.5|Uses a low step to reach a familiar bed
Cat|Iris|Domestic Shorthair|152|4.4|Prefers gentle floor play in a quiet room
Cat|Juniper|Puspin|161|3.8|Takes a familiar route between litter tray and bed
Cat|Kalamay|Domestic Longhair|168|4.8|Waits on a padded mat for meals
Cat|Lotus|Puspin|177|3.1|Sleeps in a sheltered corner with easy access to water
PETS;

    public static function profiles(): iterable
    {
        $care = [
            1 => ['Healthy; preventive care only', 'Healthy; routine wellness review due'],
            2 => ['Stable; minor paw injury healing with activity limits', 'Stable; routine skin-care follow-up', 'Stable; routine dental follow-up'],
            3 => ['Stable chronic condition; prescribed maintenance medication', 'Stable mobility limitation; regular low-impact exercise', 'Stable; scheduled rehabilitation follow-up'],
            4 => ['Recovering from surgery; frequent wound checks', 'In rehabilitation; frequent assisted mobility sessions', 'Chronic condition; frequent veterinary monitoring'],
            5 => ['On treatment hold; intensive postoperative care', 'On treatment hold; intensive rehabilitation', 'On treatment hold; ongoing intensive clinical support'],
        ];
        foreach (explode("\n", self::DATA) as $i => $row) {
            [$species, $name, $breed, $age, $weight, $description] = explode('|', $row);
            $idx = $i % 35;
            $needs = match (true) {
                $idx < 3 => 3,
                $idx < 5 => 1,
                $idx < 8 => 2,
                $idx < 11 => 3,
                $idx < 20 => 1,
                $idx < 25 => 2,
                $idx < 28 => 1,
                $idx < 32 => 4,
                $i === 32 => 3,
                default => 5,
            };
            $lowActivity = $needs >= 4 || $idx >= 28
                || ($needs === 2 && $idx % 3 === 0) || ($needs === 3 && $idx % 3 !== 0);
            $energy = $lowActivity ? $idx % 2 : 1 + $idx % 4;
            yield [
                'pet' => [
                    'name' => $name, 'species' => $species, 'breed' => $breed,
                    'age' => (int) $age, 'weight_kg' => $weight, 'sex' => $i % 2 ? 'Female' : 'Male',
                    'availability_status' => match (true) {
                        $needs === 5, $needs === 4 && $idx % 3 !== 2 => 'On Hold',
                        $idx === 10 => 'Assessing',
                        default => 'Available',
                    },
                    'description' => $description.'. Fictional care scenario: '.$care[$needs][$idx % count($care[$needs])].'.',
                    'health_status' => match ($needs) {
                        1 => 'Healthy', 2 => 'Needs Vet', default => 'Under Treatment'
                    },
                    'vaccination_record_status' => match ($idx % 7) {
                        0 => 'Unknown', 1 => 'Incomplete', default => 'Complete'
                    },
                    'medical_needs' => $needs,
                    'behavioral_notes' => ($energy <= 1 ? 'Prefers gentle or short activity.' : 'Engages readily in play and enrichment.').($idx % 7 === 3 ? ' Vocal when seeking attention.' : ''),
                    'high_vocalization' => $idx % 7 === 3 || in_array($name, ['Yumi', 'Zuri', 'Indigo'], true),
                    'is_reactive_to_pets' => $idx % 9 === 8,
                    'has_aggression_history' => false,
                ],
                'scores' => [
                    'energy' => $energy, 'trainability' => 1 + ($idx + 2) % 4,
                    'attachment' => 1 + ($idx % 4), 'sociability' => 1 + ($idx + 1) % 4,
                    'attention_seeking' => 1 + $idx % 4,
                    'stranger_fear' => $idx % 9 === 8 ? 4 : $idx % 3,
                    'non_social_fear' => $idx % 9 === 8 ? 3 : ($idx + 1) % 3,
                    'temperament' => $idx % 9 === 8 ? 4 : $idx % 3,
                ],
            ];
        }
    }
}
