<dialog id="delete-confirmation" class="confirmation-dialog" aria-labelledby="delete-title">
    <h2 id="delete-title">Eliminar atención</h2>
    <p>¿Deseas eliminar la hoja <strong id="delete-sheet"></strong>?</p>
    <p>Dejará de aparecer en los listados. Se conservarán los datos y el registro de quién la eliminó.</p>
    <form method="post" id="delete-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
        <input type="hidden" name="_province"><input type="hidden" name="version">
        <div class="actions"><button type="button" id="delete-cancel" class="button emphasis"><?= icon('close') ?> Cancelar</button><button type="submit" class="danger emphasis"><?= icon('delete') ?> Confirmar eliminación</button></div>
    </form>
</dialog>
<script src="<?= htmlspecialchars(\App\Support\Asset::url('/delete-dialog.js'), ENT_QUOTES) ?>" defer></script>
