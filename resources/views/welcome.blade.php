<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Permissions-Policy" content="unload=()">
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
            uploadAssetUrl: BUILDERJS_CONFIG.uploadImageUrl,
            uploadAssetMethod: 'POST',
            upload: {
                url: BUILDERJS_CONFIG.uploadImageUrl,
                method: 'POST',
                paramName: 'file',
                timeout: 30000,
                headers: {
                    'X-CSRF-TOKEN': BUILDERJS_CONFIG.csrfToken
                },
                success: function(response) {
                    console.log('Upload success:', response);
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                        }
                    }
                    return response.url || response.path || response;
                },
                error: function(error) {
                    console.error('Upload error:', error);
                    alert('Erreur lors de l\'upload de l\'image');
                }
            },
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
</body>
</html>
