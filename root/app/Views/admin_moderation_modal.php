<?php
/**
 * The dialog of the moderation buttons (App\Controllers\ClientBusinesses::moderationButtons()): it asks for the reason
 * and the message to the business where the action needs them (flag, block, remove), posts to
 * admin/clients/businesses/moderate (or moderate/remove), and reloads the page once it is done. Include it once on a
 * page that has the buttons; nothing is drawn for staff outside MODERATION_DEPARTMENTS.
 * @var string $businessSlug
 */

use App\Controllers\ClientBusinesses as CB;

helper('auth');
if (! inDepartment(MODERATION_DEPARTMENTS)) {
    return;
}
?>
<div class="modal fade" id="moderationModal" tabindex="-1" aria-labelledby="moderationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="moderationForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="moderationModalTitle">Moderate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="moderationIntro"></p>
                <div class="alert alert-danger d-none" id="moderationError"></div>
                <input type="hidden" name="action">
                <input type="hidden" name="subject">
                <input type="hidden" name="public_id">
                <input type="hidden" name="business_slug" value="<?= esc($businessSlug, 'attr') ?>">
                <div class="mb-3" data-explained>
                    <label class="form-label" for="moderationReason">Reason <span class="text-danger">*</span></label>
                    <select class="form-select" id="moderationReason" name="reason_code">
                        <option value="">Choose a reason</option>
                        <?php foreach (CB::MODERATION_REASONS as $code => $label): ?>
                            <option value="<?= esc($code, 'attr') ?>"><?= esc($label) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="invalid-feedback" data-error="reason_code"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="moderationMessage">Message to the business <span class="text-danger" data-explained>*</span></label>
                    <textarea class="form-control" id="moderationMessage" name="client_message" rows="3" maxlength="2000"
                              placeholder="What they need to fix, in plain words"></textarea>
                    <div class="invalid-feedback" data-error="client_message"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="moderationNote">Internal note</label>
                    <textarea class="form-control" id="moderationNote" name="internal_note" rows="2" maxlength="2000"
                              placeholder="Only Otternova staff see this"></textarea>
                    <div class="invalid-feedback" data-error="internal_note"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="moderationSubmit">Confirm</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () { // jQuery and Bootstrap load after the content
        const urls  = {moderate: <?= json_encode(base_url('admin/clients/businesses/moderate')) ?>, remove: <?= json_encode(base_url('admin/clients/businesses/moderate/remove')) ?>};
        const texts = {
            approve: ['Approve', 'Approve %s: it meets the marketplace rules.', 'btn-success'],
            flag:    ['Flag', 'Flag %s: it stays on the marketplace, and the business is asked to fix it. Once they change it, it comes back for review.', 'btn-warning'],
            block:   ['Block', 'Block %s: it is taken off the marketplace until it is unblocked. Orders placed already are honoured. Once the business changes it, it comes back for review.', 'btn-danger'],
            unblock: ['Unblock', 'Unblock %s: it is approved and goes back to how it was before the block.', 'btn-primary'],
            remove:  ['Remove for good', 'Remove %s for good: it is taken off the marketplace and can never be changed or brought back. Orders placed already are honoured.', 'btn-dark'],
        };
        const explained = ['flag', 'block', 'remove'];
        const modalEl   = document.getElementById('moderationModal');
        const modal     = bootstrap.Modal.getOrCreateInstance(modalEl);
        const form      = document.getElementById('moderationForm');
        const submit    = document.getElementById('moderationSubmit');
        const errorBox  = document.getElementById('moderationError');

        function clearErrors() {
            errorBox.classList.add('d-none');
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-moderate]');
            if (!button) return;
            const action = button.dataset.moderate;
            const [title, intro, style] = texts[action];
            form.reset();
            clearErrors();
            form.elements.action.value    = action;
            form.elements.subject.value   = button.dataset.subject;
            form.elements.public_id.value = button.dataset.publicId || '';
            document.getElementById('moderationModalTitle').textContent = title;
            document.getElementById('moderationIntro').textContent      = intro.replace('%s', button.dataset.label);
            submit.textContent = title;
            submit.className   = 'btn ' + style;
            form.querySelectorAll('[data-explained]').forEach(function (el) { el.classList.toggle('d-none', !explained.includes(action)); });
            modal.show();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();
            const action = form.elements.action.value;
            submit.disabled = true;
            $.ajax({url: 'remove' === action ? urls.remove : urls.moderate, method: 'POST', data: $(form).serialize(), dataType: 'json'})
                .done(function (body) {
                    modal.hide();
                    if (window.toastr) toastr.success(body.message);
                    setTimeout(function () { location.reload(); }, 600);
                })
                .fail(function (xhr) {
                    const body = xhr.responseJSON || {};
                    errorBox.textContent = body.message || 'Could not save. Please try again.';
                    errorBox.classList.remove('d-none');
                    Object.entries(body.errors || {}).forEach(function ([field, message]) {
                        const input = form.elements[field];
                        const help  = form.querySelector('[data-error="' + field + '"]');
                        if (input && help) {
                            input.classList.add('is-invalid');
                            help.textContent = message;
                        }
                    });
                })
                .always(function () { submit.disabled = false; });
        });
    });
</script>
