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
        <h1>Upload Template - Mode Hybride</h1>
        <p>Uploadez un fichier ZIP contenant votre template. Le système le stockera en base de données et l'extraira automatiquement à la première consultation.</p>

        <div id="alerts"></div>

        <form id="uploadForm" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="name">Nom du template:</label>
                <input type="text" id="name" name="name" required placeholder="Ex: Mon Template Accueil">
            </div>

            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3" placeholder="Décrivez votre template..." maxlength="1000"></textarea>
            </div>

            <div class="form-group">
                <label for="template">Fichier ZIP:</label>
                <input type="file" id="template" name="template" accept=".zip" required>
                <small style="color: #666; margin-top: 5px; display: block;">
                    Le fichier ZIP sera stocké en base de données et extrait automatiquement à la demande.
                </small>
            </div>

            <div class="form-group">
                <label for="preview_image">Image de prévisualisation (optionnel):</label>
                <input type="file" id="preview_image" name="preview_image" accept="image/*">
                <small style="color: #666; margin-top: 5px; display: block;">
                    Image PNG, JPG, GIF (max 2MB) pour représenter votre template.
                </small>
            </div>

            <button type="submit" class="btn">Uploader en Mode Hybride</button>
            <a href="/" class="btn" style="background: #6c757d; margin-left: 10px;">Retour à l'accueil</a>
        </form>

        <div class="template-list">
            <h2>Templates Disponibles - Mode Hybride</h2>
            <div id="templatesList">
                <p>Chargement des templates hybrides...</p>
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
                const response = await fetch('/api/templates', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const contentType = response.headers.get('content-type') || '';
                const isJson = contentType.includes('application/json');
                const result = isJson ? await response.json() : { success: false, message: await response.text() };

                if (response.ok && result.success) {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-success">
                            ${result.message}<br>
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
                const response = await fetch('/api/templates', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const contentType = response.headers.get('content-type') || '';
                const isJson = contentType.includes('application/json');
                const result = isJson ? await response.json() : { templates: [] };

                const templatesList = document.getElementById('templatesList');

                if (result.templates.length === 0) {
                    templatesList.innerHTML = '<p>Aucun template hybride disponible.</p>';
                } else {
                    templatesList.innerHTML = result.templates.map(template => `
                        <div class="template-item" style="display: flex; align-items: center; gap: 15px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; background: white;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: flex-start; gap: 15px;">
                                    ${template.preview_image ? `<img src="/api/template/${template.id}/images/${template.preview_image}" alt="Preview" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">` : '<div style="width: 80px; height: 60px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 24px;">📄</div>'}
                                    <div style="flex: 1;">
                                        <h3 style="margin: 0 0 8px 0; color: #333;">${template.name}</h3>
                                        ${template.description ? `<p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">${template.description}</p>` : ''}
                                        <div style="font-size: 12px; color: #888;">
                                            <span>ID: ${template.id}</span> •
                                            <span>Créé le: ${template.created_at}</span> •
                                            <span>Par: ${template.user}</span>
                                        </div>
                                        <div style="margin-top: 8px;">
                                            ${template.has_zip_content ? '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">ZIP stocké</span>' : ''}
                                            ${template.is_extracted ? '<span style="background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">Extrait</span>' : '<span style="background: #ffc107; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">Non extrait</span>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-shrink: 0;">
                                <a href="${template.url}" target="_blank" class="btn" style="padding: 8px 15px; font-size: 14px; text-decoration: none;">👁️ Voir</a>
                                <button onclick="editTemplate('${template.id}', '${template.name}')" class="btn" style="padding: 8px 15px; font-size: 14px; background: #ffc107; color: #000;">✏️ Modifier</button>
                                <button onclick="deleteTemplate('${template.id}', '${template.name}')" class="btn" style="padding: 8px 15px; font-size: 14px; background: #dc3545;">🗑️ Supprimer</button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (error) {
                document.getElementById('templatesList').innerHTML = '<p>Erreur lors du chargement des templates.</p>';
            }
        }

        // Use template function
        function useTemplate(templateId) {
            const url = `/templates/${templateId}`;
            window.location.href = url;
        }

        // Edit template function - redirect to welcome.blade.php with preloaded template
        function editTemplate(templateId, templateName) {
            const builderUrl = `/?template=${templateId}`;
            window.location.href = builderUrl;
        }

        // Delete template function
        async function deleteTemplate(templateId, templateName) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer le template "${templateName}" ?`)) {
                return;
            }

            try {
                const response = await fetch(`/templates/${templateId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token
                    }
                });

                const result = await response.json();
                const alertsDiv = document.getElementById('alerts');

                if (result.success) {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-success">
                            ${result.message}
                        </div>
                    `;
                    loadTemplates();
                } else {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-error">
                            ${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('alerts').innerHTML = `
                    <div class="alert alert-error">
                        Erreur lors de la suppression: ${error.message}
                    </div>
                `;
            }
        }

        // Load templates on page load
        loadTemplates();
    </script>
</body>
</html>
