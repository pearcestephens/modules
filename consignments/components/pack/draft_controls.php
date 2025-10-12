<section class="vt-block vt-block--draft">
  <form id="draftForm" class="d-flex gap-2">
    <input type="hidden" name="transfer_id" value="<?= (int)$transferId ?>">
    <?= \Transfers\Lib\Security::csrfTokenInput(); ?>
    <button type="button" class="btn btn-light btn-sm js-draft-save">Save draft</button>
    <button type="button" class="btn btn-light btn-sm js-draft-restore">Restore</button>
  </form>
</section>
