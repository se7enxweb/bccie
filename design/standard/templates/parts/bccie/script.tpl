<script>
(function () {ldelim}
    // A form with data-cie-confirm asks before it is sent; a button with data-cie-confirm-button asks for itself.
    document.addEventListener('submit', function (event) {ldelim}
        var form = event.target;
        var submitter = event.submitter;
        var text = submitter && submitter.getAttribute('data-cie-confirm-button');
        if (!text && form.getAttribute) {ldelim} text = form.getAttribute('data-cie-confirm'); {rdelim}
        if (text && !window.confirm(text)) {ldelim} event.preventDefault(); {rdelim}
    {rdelim});
    // Select all checkboxes of a table.
    document.querySelectorAll('input[data-cie-check-all]').forEach(function (box) {ldelim}
        box.addEventListener('change', function () {ldelim}
            var table = box.closest('table');
            table.querySelectorAll('input[type=checkbox][name]').forEach(function (c) {ldelim} c.checked = box.checked; {rdelim});
        {rdelim});
    {rdelim});
{rdelim})();
</script>
