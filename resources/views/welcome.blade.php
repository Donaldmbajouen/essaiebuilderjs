<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel Builder</title>

    <!-- BuilderJS CSS -->
    <link rel="stylesheet" href="{{ asset('vendor/builderjs/builder.css') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
</head>

<body>
    <div id="builder-container"></div>

    <!-- BuilderJS JavaScript -->
    <script type="text/javascript" src="{{ asset('vendor/builderjs/builder.js') }}"></script>

    <script type="text/javascript">
        // Get template parameter from URL
        const urlParams = new URLSearchParams(window.location.search);
        const templateName = urlParams.get('template') || 'accueil';
        
        // Initialize BuilderJS with dynamic template
        var builder = new Editor({
            root: "{{ asset('vendor/builderjs') }}/",
            // Load custom template
            url: '/api/template/' + templateName,
            // Optional: Save URL endpoint
            // saveUrl: '/api/template/save',
            // saveMethod: 'POST',
            // Optional: Back URL
            // backUrl: '/',
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
