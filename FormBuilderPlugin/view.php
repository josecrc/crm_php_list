<?php
/**
 * view.php - Public standalone form renderer for FormBuilderPlugin.
 * Displays the customized form and handles AJAX submissions with elegant animations.
 */

require_once dirname(__FILE__) . '/../../accesscheck.php';

// Fetch FormBuilderPlugin instance
$plugin = $GLOBALS['plugins']['FormBuilderPlugin'] ?? $GLOBALS['allplugins']['FormBuilderPlugin'];
if (!$plugin) {
    echo "Error: El plugin Form Builder no está cargado.";
    return;
}

$tableName = $plugin->tables['forms'];

// Retrieve Form ID
$id = sprintf('%d', isset($_GET['id']) ? $_GET['id'] : 0);
$fres = Sql_Query(sprintf("SELECT * FROM %s WHERE id = %d", $tableName, $id));
$form = Sql_Fetch_Assoc($fres);

// Clear output buffers completely to render a pristine custom standalone HTML page
while (ob_get_level()) {
    ob_end_clean();
}

if (!$form) {
    header("HTTP/1.0 404 Not Found");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Formulario no encontrado</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; text-align: center; padding: 50px 20px; background: #f8fafc; color: #64748b; }
            h1 { color: #0f172a; font-size: 24px; }
        </style>
    </head>
    <body>
        <h1>Formulario no encontrado</h1>
        <p>Lo sentimos, el formulario solicitado no existe o ha sido eliminado.</p>
    </body>
    </html>
    <?php
    exit;
}

$fields = json_decode($form['fields'], true);
$styles = json_decode($form['styles'], true);

// Extract style tokens with premium fallbacks
$theme = isset($styles['theme']) ? $styles['theme'] : 'glassmorphism';
$primaryColor = isset($styles['primaryColor']) ? $styles['primaryColor'] : '#10b981';
$borderRadius = isset($styles['borderRadius']) ? $styles['borderRadius'] : '16';
$submitText = isset($styles['submitText']) ? $styles['submitText'] : 'Suscribirse';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    :root {
        --primary-color: <?php echo $primaryColor; ?>;
        --border-radius: <?php echo $borderRadius; ?>px;
        --font-family: 'Outfit', sans-serif;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 20px;
        font-family: var(--font-family);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        overflow: hidden;
        transition: background 0.3s ease;
        
        /* Moving premium background gradient for Glassmorphic visibility */
        background: linear-gradient(-45deg, #0f172a, #1e1b4b, #0f2a24, #1c1917);
        background-size: 400% 400%;
        animation: gradientBG 15s ease infinite;
    }

    @keyframes gradientBG {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Standalone clean styles if embedded in normal pages */
    body.embedded-mode {
        background: transparent !important;
        min-height: auto;
        padding: 0;
        overflow: visible;
    }

    .form-card {
        width: 100%;
        max-width: 480px;
        padding: 36px;
        border-radius: var(--border-radius);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        position: relative;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }

    /* Theme: Glassmorphism */
    .theme-glassmorphism {
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #f8fafc;
    }
    .theme-glassmorphism label {
        color: #cbd5e1;
    }
    .theme-glassmorphism input, .theme-glassmorphism textarea, .theme-glassmorphism select {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #ffffff;
    }
    .theme-glassmorphism input:focus, .theme-glassmorphism textarea:focus, .theme-glassmorphism select:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
    }
    .theme-glassmorphism input::placeholder, .theme-glassmorphism textarea::placeholder {
        color: #64748b;
    }
    .theme-glassmorphism select option {
        background: #1e293b;
        color: #ffffff;
    }

    /* Theme: Light */
    .theme-light {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
    }
    .theme-light label {
        color: #475569;
    }
    .theme-light input, .theme-light textarea, .theme-light select {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #0f172a;
    }
    .theme-light input:focus, .theme-light textarea:focus, .theme-light select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }
    .theme-light input::placeholder, .theme-light textarea::placeholder {
        color: #94a3b8;
    }
    .theme-light select option {
        background: #ffffff;
        color: #0f172a;
    }

    /* Theme: Dark */
    .theme-dark {
        background: #0f172a;
        border: 1px solid #1e293b;
        color: #f8fafc;
    }
    .theme-dark label {
        color: #94a3b8;
    }
    .theme-dark input, .theme-dark textarea, .theme-dark select {
        background: #1e293b;
        border: 1px solid #334155;
        color: #ffffff;
    }
    .theme-dark input:focus, .theme-dark textarea:focus, .theme-dark select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.05);
    }
    .theme-dark input::placeholder, .theme-dark textarea::placeholder {
        color: #475569;
    }
    .theme-dark select option {
        background: #1e293b;
        color: #ffffff;
    }

    .form-title {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 8px 0;
        text-align: center;
    }

    .form-desc {
        font-size: 14px;
        line-height: 1.6;
        text-align: center;
        opacity: 0.85;
        margin: 0 0 28px 0;
    }

    .form-group {
        margin-bottom: 20px;
        position: relative;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .form-group input, .form-group textarea, .form-group select {
        width: 100%;
        padding: 12px 16px;
        font-size: 14px;
        border-radius: 8px;
        outline: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: var(--font-family);
    }
    .form-group textarea {
        resize: none;
    }

    .form-group-checkbox {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 16px 0;
    }
    .form-group-checkbox input {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary-color);
    }
    .form-group-checkbox label {
        margin: 0;
        font-size: 13px;
        cursor: pointer;
    }

    .btn-submit {
        width: 100%;
        padding: 14px;
        border-radius: 8px;
        border: none;
        color: white;
        background: var(--primary-color);
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
        transition: all 0.2s ease;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        filter: brightness(1.1);
    }

    .btn-submit:active {
        transform: translateY(1px);
    }

    .error-text {
        font-size: 12px;
        color: #ef4444;
        margin-top: 4px;
        display: none;
    }

    /* Premium Success View State */
    .success-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: inherit;
        border-radius: inherit;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 30px;
        box-sizing: border-box;
        opacity: 0;
        pointer-events: none;
        transform: scale(0.9);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: 10;
        text-align: center;
    }

    .success-overlay.active {
        opacity: 1;
        pointer-events: auto;
        transform: scale(1);
    }

    /* Checkmark micro-animation */
    .checkmark-circle {
        width: 72px;
        height: 72px;
        position: relative;
        display: inline-block;
        vertical-align: top;
        margin-bottom: 20px;
    }

    .checkmark-circle .background {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #10b981;
        position: absolute;
    }

    .checkmark-circle .checkmark {
        border-radius: 5px;
    }

    .checkmark-circle .checkmark.draw:after {
        animation-duration: 800ms;
        animation-timing-function: ease;
        animation-name: checkmark;
        transform: scaleX(-1) rotate(135deg);
    }

    .checkmark-circle .checkmark:after {
        opacity: 1;
        height: 36px;
        width: 18px;
        transform-origin: left top;
        border-right: 5px solid white;
        border-top: 5px solid white;
        content: '';
        left: 18px;
        top: 36px;
        position: absolute;
    }

    @keyframes checkmark {
        0% { height: 0; width: 0; opacity: 1; }
        20% { height: 0; width: 18px; opacity: 1; }
        40% { height: 36px; width: 18px; opacity: 1; }
        100% { height: 36px; width: 18px; opacity: 1; }
    }

    .success-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .success-desc {
        font-size: 14px;
        opacity: 0.85;
        line-height: 1.6;
    }

    /* Loading Spinner */
    .spinner {
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top: 3px solid #ffffff;
        width: 18px;
        height: 18px;
        animation: spin 0.8s linear infinite;
        display: inline-block;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</head>
<body class="<?php echo isset($_GET['embed']) ? 'embedded-mode' : ''; ?>">

    <div class="form-card theme-<?php echo $theme; ?>">
        <!-- Success Overlay Panel -->
        <div id="success-panel" class="success-overlay">
            <div class="checkmark-circle">
                <div class="background"></div>
                <div id="checkmark-el" class="checkmark"></div>
            </div>
            <h3 class="success-title">Suscripción Exitosa</h3>
            <p class="success-desc" id="success-desc-text">¡Gracias por suscribirte!</p>
        </div>

        <!-- Title & Header -->
        <?php if (isset($styles['showTitle']) ? $styles['showTitle'] : true): ?>
            <h2 class="form-title"><?php echo htmlspecialchars($form['title']); ?></h2>
        <?php endif; ?>
        
        <?php if (isset($styles['showDescription']) ? $styles['showDescription'] : true): ?>
            <p class="form-desc"><?php echo htmlspecialchars($form['description']); ?></p>
        <?php endif; ?>

        <!-- Signup Form -->
        <form id="signup-form" novalidate>
            <!-- Form ID -->
            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                      <?php foreach ($fields as $field): 
                $fieldName = $field['id']; // Use unique field ID as name attribute for robust AJAX parsing
                $requiredAttr = $field['required'] ? 'required' : '';
                $labelText = htmlspecialchars($field['label']) . ($field['required'] ? ' *' : '');
            ?>
                <?php if ($field['type'] === 'textarea'): ?>
                    <div class="form-group">
                        <label for="<?php echo $field['id']; ?>"><?php echo $labelText; ?></label>
                        <textarea id="<?php echo $field['id']; ?>" name="<?php echo $fieldName; ?>" rows="3" placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>" <?php echo $requiredAttr; ?>></textarea>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>">Este campo es obligatorio.</div>
                    </div>
                <?php elseif ($field['type'] === 'checkbox'): ?>
                    <div class="form-group-checkbox">
                        <input type="checkbox" id="<?php echo $field['id']; ?>" name="<?php echo $fieldName; ?>" value="on" <?php echo $requiredAttr; ?>>
                        <label for="<?php echo $field['id']; ?>"><?php echo $labelText; ?></label>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>" style="position: absolute; bottom:-16px; left:0;">Debes marcar esta casilla.</div>
                    </div>
                <?php elseif ($field['type'] === 'checkbox_group'): ?>
                    <div class="form-group">
                        <label><?php echo $labelText; ?></label>
                        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
                            <?php 
                            $opts = array_filter(array_map('trim', explode(',', $field['options'] ?? '')));
                            $optIdx = 0;
                            foreach ($opts as $opt):
                                $optId = $field['id'] . '_' . $optIdx++;
                            ?>
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; font-size:13px;">
                                    <input type="checkbox" id="<?php echo $optId; ?>" name="<?php echo $fieldName; ?>[]" value="<?php echo htmlspecialchars($opt); ?>" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary-color); margin:0;">
                                    <span><?php echo htmlspecialchars($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>">Por favor, selecciona al menos una opción.</div>
                    </div>
                <?php elseif ($field['type'] === 'select'): ?>
                    <div class="form-group">
                        <label for="<?php echo $field['id']; ?>"><?php echo $labelText; ?></label>
                        <select id="<?php echo $field['id']; ?>" name="<?php echo $fieldName; ?>" <?php echo $requiredAttr; ?>>
                            <option value=""><?php echo htmlspecialchars($field['placeholder'] ?: '-- Seleccione --'); ?></option>
                            <?php 
                            $opts = array_filter(array_map('trim', explode(',', $field['options'] ?? '')));
                            foreach ($opts as $opt):
                            ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>">Este campo es obligatorio.</div>
                    </div>
                <?php elseif ($field['type'] === 'radio'): ?>
                    <div class="form-group">
                        <label><?php echo $labelText; ?></label>
                        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
                            <?php 
                            $opts = array_filter(array_map('trim', explode(',', $field['options'] ?? '')));
                            $optIdx = 0;
                            foreach ($opts as $opt):
                                $optId = $field['id'] . '_' . $optIdx++;
                            ?>
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; font-size:13px;">
                                    <input type="radio" id="<?php echo $optId; ?>" name="<?php echo $fieldName; ?>" value="<?php echo htmlspecialchars($opt); ?>" <?php echo $requiredAttr; ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary-color); margin:0;">
                                    <span><?php echo htmlspecialchars($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>">Por favor, selecciona una opción.</div>
                    </div>
                <?php elseif ($field['type'] === 'hidden'): ?>
                    <!-- Hidden Field: rendered without form-group wrapper, label, or error texts -->
                    <input type="hidden" id="<?php echo $field['id']; ?>" name="<?php echo $fieldName; ?>" value="<?php echo htmlspecialchars((isset($field['value']) && $field['value'] !== '') ? $field['value'] : ($field['placeholder'] ?? '')); ?>">
                <?php else: 
                    // Map input type (text, number, email)
                    $inputType = 'text';
                    if ($field['type'] === 'number') {
                        $inputType = 'number';
                    } elseif ($field['type'] === 'email') {
                        $inputType = 'email';
                    }
                ?>
                    <div class="form-group">
                        <label for="<?php echo $field['id']; ?>"><?php echo $labelText; ?></label>
                        <input type="<?php echo $inputType; ?>" id="<?php echo $field['id']; ?>" name="<?php echo $fieldName; ?>" placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>" <?php echo $requiredAttr; ?>>
                        <div class="error-text" id="error-<?php echo $field['id']; ?>">El formato o valor del campo no es válido.</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <button type="submit" id="btn-submit" class="btn-submit">
                <?php echo htmlspecialchars($submitText); ?>
            </button>
        </form>
    </div>

    <script>
    // Adjust layout mode depending on if loaded inside an iframe
    if (window.self !== window.top) {
        document.body.classList.add('embedded-mode');
    }

    const formConfig = <?php echo json_encode($form); ?>;
    const fieldsConfig = <?php echo json_encode($fields); ?>;
    const submitEndpoint = './?pi=FormBuilderPlugin&p=submit';

    document.getElementById('signup-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        
        // Reset previous errors
        document.querySelectorAll('.error-text').forEach(el => el.style.display = 'none');
        
        // Basic validation
        fieldsConfig.forEach(field => {
            const el = document.getElementById(field.id);
            const errorEl = document.getElementById('error-' + field.id);
            
            if (field.required) {
                if (field.type === 'checkbox') {
                    if (!el || !el.checked) {
                        errorEl.innerText = "Debes marcar esta casilla.";
                        errorEl.style.display = 'block';
                        isValid = false;
                    }
                } else if (field.type === 'checkbox_group') {
                    const checkedCheckbox = document.querySelector('input[name="' + field.id + '[]"]:checked');
                    if (!checkedCheckbox) {
                        errorEl.innerText = "Por favor, selecciona al menos una opción.";
                        errorEl.style.display = 'block';
                        isValid = false;
                    }
                } else if (field.type === 'radio') {
                    const checkedRadio = document.querySelector('input[name="' + field.id + '"]:checked');
                    if (!checkedRadio) {
                        errorEl.innerText = "Por favor, selecciona una opción.";
                        errorEl.style.display = 'block';
                        isValid = false;
                    }
                } else {
                    if (!el || !el.value.trim()) {
                        errorEl.innerText = "Este campo es obligatorio.";
                        errorEl.style.display = 'block';
                        isValid = false;
                    }
                }
            }
            
            // Validate Email format
            if (field.id === 'email' && el && el.value.trim()) {
                const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                if (!re.test(el.value.trim().toLowerCase())) {
                    errorEl.innerText = "Por favor, introduce un correo electrónico válido.";
                    errorEl.style.display = 'block';
                    isValid = false;
                }
            }
        });

        if (!isValid) return;

        // Perform AJAX Submission
        const btn = document.getElementById('btn-submit');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Suscribiendo...';

        const formData = new FormData(this);

        fetch(submitEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                throw new Error("Respuesta de error HTTP: " + res.status);
            }
            return res.text();
        })
        .then(text => {
            try {
                // Find start of JSON object in case there are PHP notices or warnings
                let cleanJson = text.trim();
                const jsonStart = cleanJson.indexOf('{');
                if (jsonStart !== -1) {
                    cleanJson = cleanJson.substring(jsonStart);
                }
                const data = JSON.parse(cleanJson);
                
                if (data.success) {
                    // Show gorgeous success state
                    document.getElementById('success-desc-text').innerText = formConfig.success_message || "¡Gracias por suscribirte!";
                    document.getElementById('success-panel').classList.add('active');
                    document.getElementById('checkmark-el').classList.add('draw');
                    
                    // If redirect URL is defined in settings
                    const redirectUrl = (formConfig.redirect_url || '').trim();
                    if (redirectUrl) {
                        setTimeout(() => {
                            if (window.self !== window.top) {
                                window.top.location.href = redirectUrl;
                            } else {
                                window.location.href = redirectUrl;
                            }
                        }, 2000);
                    }
                } else {
                    // Handle validation errors from server
                    if (data.errors && typeof data.errors === 'object' && data.errors !== null) {
                        Object.keys(data.errors).forEach(key => {
                            // Find matching field
                            const matchedField = fieldsConfig.find(f => f.id === key || ('attribute_' + f.attribute_id) === key);
                            if (matchedField) {
                                const errorEl = document.getElementById('error-' + matchedField.id);
                                errorEl.innerText = data.errors[key];
                                errorEl.style.display = 'block';
                            }
                        });
                    } else {
                        alert(data.message || 'Ocurrió un error al procesar la suscripción.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (e) {
                console.error("Server raw response:", text);
                console.error("JSON parse error:", e);
                alert("Error de respuesta del servidor (No es un JSON válido).\n\nConsola del navegador para ver el detalle.");
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            alert("Error de conexión o de red: " + err.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
    </script>

</body>
</html>
