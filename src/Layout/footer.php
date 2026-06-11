<?php
declare(strict_types=1);

/*
    Shared website footer.

    This file closes the page and loads Bootstrap JavaScript.
    Footer text comes from the editable content table.

    It also starts small admin-page helper scripts:
    - Bootstrap tooltips
    - Theme color preview syncing
    - Simple custom rich text editor syncing
*/
?>

<footer class="bg-dark text-white py-4 mt-auto">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?>
                    <?php echo escapeHtml(SITE_NAME); ?>.
                    <?php echo escapeHtml(contentValue('footer_copyright_suffix')); ?>
                </p>
            </div>

            <div class="col-md-6 text-md-end">
                <p class="mb-0">
                    <?php echo escapeHtml(contentValue('footer_tagline')); ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

        tooltipTriggerList.forEach(function (tooltipTriggerElement) {
            new bootstrap.Tooltip(tooltipTriggerElement);
        });

        const colorInputs = document.querySelectorAll('[data-color-preview-target]');

        colorInputs.forEach(function (colorInput) {
            const targetId = colorInput.getAttribute('data-color-preview-target');
            const targetInput = document.getElementById(targetId);

            if (!targetInput) {
                return;
            }

            colorInput.addEventListener('input', function () {
                targetInput.value = colorInput.value.toUpperCase();
            });
        });

        const richEditors = document.querySelectorAll('[data-rich-editor]');

        function isAllowedRichLink(value) {
            return (
                value.startsWith('#') ||
                value.startsWith('/') ||
                value.startsWith('https://') ||
                value.startsWith('http://') ||
                value.startsWith('tel:') ||
                value.startsWith('mailto:')
            );
        }

        function syncRichEditor(editorWrapper) {
            const editorBox = editorWrapper.querySelector('[data-rich-editor-box]');

            if (!editorBox) {
                return;
            }

            const hiddenInputId = editorBox.getAttribute('data-rich-hidden-input');
            const hiddenInput = document.getElementById(hiddenInputId);

            if (!hiddenInput) {
                return;
            }

            hiddenInput.value = editorBox.innerHTML;
        }

        richEditors.forEach(function (editorWrapper) {
            const editorBox = editorWrapper.querySelector('[data-rich-editor-box]');
            const commandButtons = editorWrapper.querySelectorAll('[data-rich-command]');

            if (!editorBox) {
                return;
            }

            editorBox.addEventListener('input', function () {
                syncRichEditor(editorWrapper);
            });

            editorBox.addEventListener('blur', function () {
                syncRichEditor(editorWrapper);
            });

            commandButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const command = button.getAttribute('data-rich-command');

                    editorBox.focus();

                    if (command === 'createLink') {
                        const linkValue = window.prompt(
                            'Enter a link. Examples: #contact, /gallery.php, https://example.com, tel:6085551234, or mailto:name@example.com'
                        );

                        if (!linkValue) {
                            return;
                        }

                        const cleanLinkValue = linkValue.trim();

                        if (!isAllowedRichLink(cleanLinkValue)) {
                            window.alert('Link must start with #, /, https://, http://, tel:, or mailto:.');
                            return;
                        }

                        document.execCommand('createLink', false, cleanLinkValue);
                        syncRichEditor(editorWrapper);
                        return;
                    }

                    document.execCommand(command, false, null);
                    syncRichEditor(editorWrapper);
                });
            });

            syncRichEditor(editorWrapper);
        });

        const forms = document.querySelectorAll('form');

        forms.forEach(function (form) {
            form.addEventListener('submit', function () {
                richEditors.forEach(function (editorWrapper) {
                    syncRichEditor(editorWrapper);
                });
            });
        });
    });
</script>

</body>
</html>