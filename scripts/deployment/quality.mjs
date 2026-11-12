import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const baseline = 'bfb9e1d97d0e98c077ca912218ab013d31402130';
const git = args => execFileSync('git', args).toString().trim();
const files = git(['ls-files']).split('\n').filter(f => f.endsWith('.php'));
for (const file of files) execFileSync('php', ['-l', file], { stdio: 'pipe' });
console.log('PHP syntax passed: ' + files.length + ' tracked files.');
let base = process.env.PINT_BASE || baseline;
if (/^0+$/.test(base)) base = baseline;
assert.match(base, /^[a-f0-9]{40}$/);
// Missing base is a blocking failure, not a silent quality-check skip.
git(['cat-file', '-e', base + '^{commit}']);
const inherited = new Set(['app/Http/Controllers/MonitoringController.php',
    'app/Services/ReservationQueueService.php',
    'app/Http/Controllers/Admin/HandoverController.php',
    'app/Http/Controllers/HandoverConfirmationController.php',
    'app/Models/Handover.php', 'config/matching.php', 'routes/web.php',
    'tests/Feature/AdoptionPipelineStateMachineTest.php',
    'tests/Feature/HandoverTrackingTest.php', 'tests/Feature/HandoverWorkflowTest.php',
    'tests/Feature/LandingPageFeaturedPetsTest.php', 'tests/Feature/RecommendationEligibilityTest.php',
    'tests/Feature/ReservationQueueTest.php', 'tests/Feature/VolunteerRbacTest.php']);
const changed = git(['diff', '--name-only', '--diff-filter=ACMR', base, 'HEAD']).split('\n')
    .filter(f => f.endsWith('.php') && existsSync(f));
const checked = changed.filter(file => {
    if (!inherited.has(file)) return true;
    const original = execFileSync('git', ['show', baseline + ':' + file]);
    const current = execFileSync('git', ['show', 'HEAD:' + file]);
    if (!original.equals(current)) return true;
    console.log('Unmodified inherited Pint exception: ' + file + ' at ' + baseline);
    return false;
});
if (checked.length) execFileSync('php', ['vendor/bin/pint', '--test', ...checked], { stdio: 'inherit' });
console.log('Pint scoped PHP paths: ' + checked.length + '. See docs/DEPLOYMENT_SAFETY.md for inherited baseline.');
