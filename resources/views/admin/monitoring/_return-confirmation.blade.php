<dialog id="welfareReturnConfirmation" class="max-w-lg rounded-xl border border-amber-300 bg-white p-6 shadow-2xl backdrop:bg-black/60">
    <h2 class="text-lg font-bold text-amber-900">Confirm physical return</h2>
    <p class="mt-3 text-sm text-gray-700">Confirm that this pet has physically been returned to the shelter. This action updates the adoption history and pet status. A recommendation alone does not count as a return.</p>
    <div class="mt-5 flex justify-end gap-3">
        <button type="button" class="btn btn-secondary" data-return-cancel>Cancel</button>
        <button type="button" class="btn btn-danger" data-return-confirm>Confirm Pet Returned</button>
    </div>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('welfareReturnConfirmation');
        let pendingForm = null;
        document.querySelectorAll('[data-resolution-form]').forEach(form => {
            const outcome = form.querySelector('[data-resolution-outcome]');
            const fields = form.querySelector('[data-return-fields]');
            const toggle = () => {
                const returning = outcome.value === 'pet_returned';
                fields.classList.toggle('hidden', !returning);
                fields.classList.toggle('grid', returning);
                fields.querySelectorAll('[data-return-required]').forEach(field => {
                    field.disabled = !returning;
                    field.required = returning;
                });
                form.querySelector('[data-return-confirmation]').value = '';
                form.dataset.returnConfirmed = '';
            };
            outcome.addEventListener('change', toggle);
            toggle();
            form.addEventListener('submit', event => {
                if (outcome.value === 'pet_returned' && form.dataset.returnConfirmed !== 'yes') {
                    event.preventDefault();
                    pendingForm = form;
                    dialog.showModal();
                }
            });
        });
        dialog.querySelector('[data-return-cancel]').addEventListener('click', () => dialog.close());
        dialog.querySelector('[data-return-confirm]').addEventListener('click', () => {
            if (!pendingForm) return;
            pendingForm.dataset.returnConfirmed = 'yes';
            pendingForm.querySelector('[data-return-confirmation]').value = '1';
            dialog.close();
            pendingForm.requestSubmit();
        });
    })();
</script>
