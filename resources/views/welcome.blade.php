<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Builder</title>

    <!-- BuilderJS CSS -->
    <link rel="stylesheet" href="{{ asset('vendor/builderjs/builder.css') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />


</head>

<body>
    <!-- Injection de configuration pour BuilderJS -->
    <script>
        var BUILDERJS_CONFIG = {
            uploadImageUrl: '{{ route("builderjs.upload-image") }}',
            csrfToken: '{{ csrf_token() }}'
        };
    </script>

    <!-- BuilderJS JavaScript -->
    <script type="text/javascript" src="{{ asset('vendor/builderjs/builder.js') }}"></script>

    <script type="text/javascript">
        // Get template parameter from URL
        const urlParams = new URLSearchParams(window.location.search);
        const templateId = urlParams.get('template');
        const isNewTemplate = urlParams.get('new') === '1';

        // Variables globales pour la gestion des templates
        let currentTemplateId = templateId;
        let isEditingNewTemplate = isNewTemplate;

        // Initialize BuilderJS with dynamic template
        var builder = new Editor({
            root: '{{ asset('vendor/builderjs') }}',
            url: (!isNewTemplate && templateId) ? '/api/template/' + templateId : null,
            container: 'builderjs-editor',
            upload: {
                uploadUrl: BUILDERJS_CONFIG.uploadImageUrl,
                uploadAsync: true,
                uploadMethod: 'POST',
                uploadParamName: 'file',
                uploadTimeout: 30000,
                uploadHeaders: {
                    'X-CSRF-TOKEN': BUILDERJS_CONFIG.csrfToken
                }
            },
            uploadTemplateUrl: '{{ route("builderjs.upload-template") }}',
            url: (!isNewTemplate && templateId) ? '/api/template/' + templateId : null,
            allowExternalResources: true,
            corsEnabled: true,
            // Configuration de la sauvegarde intégrée - seulement si on a un template ID
            ...(currentTemplateId ? {
                saveUrl: '/api/templatesupd/' + currentTemplateId,
                saveMethod: 'PUT',
                saveHeaders: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': BUILDERJS_CONFIG.csrfToken || ''
                }
            } : {}),

            // Optional: Back URL
            backUrl: '/',
            // Custom tags for dynamic content
            // tags: [
            //     {name: 'Site Name', type: 'display'},
            //     {name: 'User Name', type: 'display'},
            //     {name: 'Current Date', type: 'display'},
            //     {name: 'Current Year', type: 'display'}
            // ]
        });

        // Initialize the builder with template
        builder.init();
    </script>

<!-- Upload image (Option B) - Bouton Remplacer image + sélection dans iframe -->
<script>
    const csrf = BUILDERJS_CONFIG.csrfToken || '';

    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = '🖼️ Remplacer l\'image';
    replaceBtn.className = 'save-button';
    replaceBtn.style.right = '200px';
    document.body.appendChild(replaceBtn);

    let lastClickedImg = null;

    function attachIframeListeners() {
        const iframe = document.querySelector('iframe');
        if (!iframe) return;
        try {
            const idoc = iframe.contentWindow?.document;
            if (!idoc) return;
            idoc.addEventListener('click', (e) => {
                const el = e.target;
                if (el && el.tagName && el.tagName.toUpperCase() === 'IMG') {
                    lastClickedImg = el;
                }
            }, true);
        } catch (_) {}
    }

    const iframeInterval = setInterval(() => {
        if (document.querySelector('iframe')) {
            clearInterval(iframeInterval);
            attachIframeListeners();
        }
    }, 300);

    replaceBtn.addEventListener('click', async () => {
        try {
            if (!currentTemplateId) {
                alert('Aucun template chargé');
                return;
            }
            if (!lastClickedImg) {
                alert('Cliquez d\'abord sur une image dans l\'éditeur');
                return;
            }

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = async (e) => {
                const file = e.target.files?.[0];
                if (!file) return;

                const fd = new FormData();
                fd.append('file', file);

                const resp = await fetch('/api/template/' + currentTemplateId + '/assets', {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                });

                const data = await resp.json();
                if (!resp.ok || !data?.success || !data?.url) {
                    throw new Error(data?.message || 'Upload échoué');
                }
                lastClickedImg.setAttribute('src', data.url);
            };
            input.click();
        } catch (err) {
            console.error(err);
            alert('Erreur upload: ' + err.message);
        }
    });
</script>
</body>
</html>
