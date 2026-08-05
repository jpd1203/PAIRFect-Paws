<div class="custom-modal-backdrop" id="compatibilityResultModal">
    <div class="custom-modal">
        <div class="custom-modal-header flex items-start justify-between">
            <div>
                <h2>Compatibility Result</h2>
                <small id="compatSubheading"></small>
            </div>
            <div class="text-right">
                <div class="score-num text-primary" id="compatOverall"></div>
                <div class="score-tag" id="compatLabel"></div>
            </div>
        </div>
        <div class="custom-modal-body">
            <div id="compatRows" class="flex flex-col gap-3"></div>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('compatibilityResultModal')">Close</button>
        </div>
    </div>
</div>
