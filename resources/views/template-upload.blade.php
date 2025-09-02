<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Template - Laravel Builder</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .template-list {
            margin-top: 40px;
        }
        .template-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Upload Template HTML</h1>
        <p>Uploadez un fichier HTML normal et le système le convertira automatiquement pour être compatible avec BuilderJS.</p>

        <div id="alerts"></div>

        <form id="uploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Nom du template:</label>
                <input type="text" id="name" name="name" required placeholder="Ex: Mon Template Accueil">
            </div>

            <div class="form-group">
                <label for="template">Fichier HTML:</label>
                <input type="file" id="template" name="template" accept=".html,.htm" required>
            </div>

            <button type="submit" class="btn">Convertir et Uploader</button>
            <a href="/" class="btn" style="background: #6c757d; margin-left: 10px;">Retour à l'éditeur</a>
        </form>

        <div class="template-list">
            <h2>Templates Disponiblews</h2>
            <div id="templatesList">
                <p>Chargement...</p>
            </div>
        </div>
    </div>

    <script>
        // CSRF Token setup
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Upload form handler
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const alertsDiv = document.getElementById('alerts');

            try {
                const response = await fetch('/api/template/upload', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': token
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-success">
                            ${result.message}<br>
                            <strong>URL:</strong> <a href="${result.url}" target="_blank">${result.url}</a>
                        </div>
                    `;
                    this.reset();
                    loadTemplates();
                } else {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-error">
                            ${result.message || 'Erreur lors de l\'upload'}
                            ${result.errors ? '<br>' + result.errors.join('<br>') : ''}
                        </div>
                    `;
                }
            } catch (error) {
                alertsDiv.innerHTML = `
                    <div class="alert alert-error">
                        Erreur de connexion: ${error.message}
                    </div>
                `;
            }
        });

        // Load templates list
        async function loadTemplates() {
            try {
                const response = await fetch('/api/templates');
                const result = await response.json();

                const templatesList = document.getElementById('templatesList');

                if (result.templates.length === 0) {
                    templatesList.innerHTML = '<p>Aucun template disponible.</p>';
                } else {
                    templatesList.innerHTML = result.templates.map(template => `
                        <div class="template-item">
                            <div>
                                <strong>${template.name}</strong><br>
                                <small>Créé le: ${template.created_at}</small>
                            </div>
                            <div>
                                <a href="${template.url}" target="_blank" class="btn" style="padding: 8px 15px;">Voir</a>
                                <button onclick="useTemplate('${template.name}')" class="btn" style="padding: 8px 15px; margin-left: 5px;">Utiliser</button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (error) {
                document.getElementById('templatesList').innerHTML = '<p>Erreur lors du chargement des templates.</p>';
            }
        }

        // Use template function
        function useTemplate(templateName) {
            const url = `/?template=${encodeURIComponent(templateName)}`;
            window.location.href = url;
        }

        // Load templates on page load
        loadTemplates();
    </script>
</body>
</html>
