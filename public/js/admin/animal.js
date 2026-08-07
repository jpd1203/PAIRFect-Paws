/** Animal Records — populates the View/Edit modal from the inline JSON payload. */
const ANIMALS = JSON.parse(document.getElementById('animalData')?.textContent || '[]');

function openViewAnimalModal(id) {
    const a = ANIMALS.find((x) => x.id === id);
    if (!a) return;

    document.getElementById('viewAnimalTitle').textContent = a.name;
    document.getElementById('vName').value = a.name;
    document.getElementById('vSpecies').value = a.species;
    document.getElementById('vBreed').value = a.breed;
    document.getElementById('vAgeYears').value = a.age_years ?? '';
    document.getElementById('vAgeMonths').value = a.age_months ?? '';
    document.getElementById('vSex').value = a.sex;
    document.getElementById('vIntake').value = a.intake;
    document.getElementById('vHealth').value = a.health;
    document.getElementById('vStatus').value = a.status;
    document.getElementById('vSize').value = a.physical_size;
    document.getElementById('vVacc').value = a.vacc;
    document.getElementById('vNotes').value = a.notes ?? '';

    const badge = document.getElementById('vAssessBadge');
    badge.textContent = a.assessment_status === 'complete' ? 'Complete' : 'Pending';
    badge.className = `badge ${a.assessment_status === 'complete' ? 'badge-completed' : 'badge-pending'}`;
    document.getElementById('vAssessCount').textContent = `${a.assessment_count}/3 assessments completed`;
    document.getElementById('vAssessBtn').href = a.assess_url;

    document.getElementById('viewAnimalForm').action = a.update_url;

    openModal('viewAnimalModal');
}
