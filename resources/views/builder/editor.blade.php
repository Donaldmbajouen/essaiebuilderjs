<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur BuilderJS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chargement de BuilderJS -->
    <script type="text/javascript" src="{{ asset('vendor/builderjs/builder.js') }}"></script>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light">
                <h2>Éditeur BuilderJS</h2>
                <div>
                    <button class="btn btn-outline-secondary" onclick="saveTemplate()">
                        <i class="fas fa-save"></i> Sauvegarder
                    </button>
                    <a href="/upload" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Retour aux templates
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div id="builderjs-container" style="height: 80vh; border: 1px solid #ddd;">
                <!-- BuilderJS sera initialisé ici -->
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement de l'éditeur...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration BuilderJS
const templateName = '{{ $templateName ?? '' }}';
let builderInstance = null;

// Fonction utilitaire pour afficher les erreurs
function showError(message) {
    const container = document.getElementById('builderjs-container');
    container.innerHTML = `
        <div class="alert alert-danger">
            <h4>Erreur</h4>
            <p>${message}</p>
            <a href="/upload" class="btn btn-primary">Retour aux templates</a>
        </div>
    `;
}

// Fonction pour charger un template depuis le serveur
async function loadTemplate(templateName) {
    try {
        const response = await fetch(`/api/template/${templateName}`);
        if (!response.ok) {
            throw new Error('Erreur lors du chargement du template');
        }
        return await response.text();
    } catch (error) {
        console.error('Erreur:', error);
        throw error;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si un template est spécifié
    if (!templateName) {
        showError('Aucun template spécifié');
        return;
    }

    // Démarrer l'initialisation après un court délai pour s'assurer que tout est chargé
    setTimeout(initializeBuilder, 500);
});

async function initializeBuilder() {
    const container = document.getElementById('builderjs-container');

    try {
        // Vérifier si BuilderJS est disponible
        if (typeof BuilderJS === 'undefined') {
            throw new Error('BuilderJS n\'est pas chargé. Vérifiez votre connexion Internet.');
        }

        // Afficher un indicateur de chargement
        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement du template...</p>
                </div>
            </div>
        `;

        // Charger le contenu du template
        const templateContent = await loadTemplate(templateName);

        // Configuration de base pour BuilderJS
        const config = {
            container: '#builderjs-container',
            toolbar: {
                enabled: true,
                position: 'top'
            },
            elements: {
                TextElement: { enabled: true },
                ImageElement: { enabled: true },
                ButtonElement: { enabled: true },
                BlockElement: { enabled: true },
                ListElement: { enabled: true },
                LinkElement: { enabled: true },
                PageElement: { enabled: true }
            },
            content: templateContent,
            onSave: function(html) {
                saveTemplate(html);
            }
        };

        // Initialiser BuilderJS
        builderInstance = new BuilderJS(config);

    } catch (error) {
        console.error('Erreur lors de l\'initialisation de BuilderJS:', error);
        showError(`Erreur lors du chargement de l'éditeur: ${error.message}`);
    }
}

async function saveTemplate(html = null) {
    if (!builderInstance && !html) {
        showError('Éditeur non initialisé');
        return;
    }

    try {
        const content = html || builderInstance.getHtml();

        // Afficher un indicateur de chargement
        const saveButton = document.querySelector('button[onclick="saveTemplate()"]');
        const originalButtonText = saveButton.innerHTML;
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde en cours...';

        // Envoyer le contenu au serveur
        const response = await fetch(`/api/template/${templateName}/save`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ content: content })
        });

        const result = await response.json();

        if (result.success) {
            // Afficher un message de succès
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                Template sauvegardé avec succès !
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            const container = document.querySelector('.container-fluid');
            container.insertBefore(alertDiv, container.firstChild);

            // Masquer l'alerte après 5 secondes
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                alert.close();
            }, 5000);
        } else {
            throw new Error(result.message || 'Erreur lors de la sauvegarde');
        }

    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showError(`Erreur lors de la sauvegarde: ${error.message}`);
    } finally {
        // Restaurer le bouton
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.innerHTML = originalButtonText;
        }
    }
}

// Fonction utilitaire pour afficher une notification
function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Masquer l'alerte après 5 secondes
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            alert.close();
        }, 5000);
    }
}

// Alias pour la rétrocompatibilité
function showSuccess(message) {
    showNotification(message, 'success');
}

function showError(message) {
    showNotification(message, 'danger');
}
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

@if(file_exists(public_path('vendor/builderjs/builderjs.min.js')))
    <script src="{{ asset('vendor/builderjs/builderjs.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/builderjs/builderjs.min.css') }}">
@else
    <script>
        console.warn('BuilderJS files not found in public/vendor/builderjs/');
    </script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
