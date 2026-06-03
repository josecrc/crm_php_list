<?php
/**
 * edit.php - Interactive visual form editor for FormBuilderPlugin.
 * Includes Live Real-time visual customizer and attributes mapper.
 */

require_once dirname(__FILE__) . '/../../accesscheck.php';

// Fetch FormBuilderPlugin instance
$plugin = $GLOBALS['plugins']['FormBuilderPlugin'] ?? $GLOBALS['allplugins']['FormBuilderPlugin'];
if (!$plugin) {
    echo "<div class='alert alert-danger'>Error: El plugin Form Builder no está cargado.</div>";
    return;
}

$tableName = $plugin->tables['forms'];

// Self-healing database check
$checkRes = Sql_Query(sprintf("DESCRIBE %s", $tableName), 1);
if ($checkRes) {
    $hasRedirectUrl = false;
    while ($row = Sql_Fetch_Assoc($checkRes)) {
        $fieldName = $row['Field'] ?? $row['field'] ?? '';
        if (strtolower($fieldName) === 'redirect_url') {
            $hasRedirectUrl = true;
            break;
        }
    }
    if (!$hasRedirectUrl) {
        Sql_Query(sprintf("ALTER TABLE %s ADD COLUMN redirect_url text", $tableName));
    }
} else {
    Sql_Create_table($tableName, $plugin->DBstruct['forms']);
}

// Retrieve Form ID
$id = sprintf('%d', isset($_GET['id']) ? $_GET['id'] : 0);
$fres = Sql_Query(sprintf("SELECT * FROM %s WHERE id = %d", $tableName, $id));
$form = Sql_Fetch_Assoc($fres);

if (!$form) {
    echo "<div class='alert alert-danger'>Error: Formulario no encontrado.</div>";
    return;
}

// Handle AJAX Save
if (isset($_POST['action']) && $_POST['action'] == 'save') {
    $name = isset($_POST['name']) ? stripslashes($_POST['name']) : 'Formulario';
    $title = isset($_POST['title']) ? stripslashes($_POST['title']) : '';
    $description = isset($_POST['description']) ? stripslashes($_POST['description']) : '';
    
    // Target lists
    $listsArray = isset($_POST['lists']) ? $_POST['lists'] : array();
    $listsStr = implode(',', array_map('intval', $listsArray));
    
    // Fields & Styles JSON
    $fields = isset($_POST['fields']) ? stripslashes($_POST['fields']) : '[]';
    $styles = isset($_POST['styles']) ? stripslashes($_POST['styles']) : '{}';
    $success_message = isset($_POST['success_message']) ? stripslashes($_POST['success_message']) : '¡Gracias!';
    $redirect_url = isset($_POST['redirect_url']) ? stripslashes($_POST['redirect_url']) : '';
    
    Sql_Query(sprintf(
        "UPDATE %s SET name='%s', title='%s', description='%s', lists='%s', fields='%s', styles='%s', success_message='%s', redirect_url='%s' WHERE id=%d",
        $tableName,
        sql_escape($name),
        sql_escape($title),
        sql_escape($description),
        sql_escape($listsStr),
        sql_escape($fields),
        sql_escape($styles),
        sql_escape($success_message),
        sql_escape($redirect_url),
        $id
    ));
    
    // Return AJAX response
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '¡Formulario guardado con éxito!'));
    exit;
}

// Fetch all active lists in the system
$listsRes = Sql_Query(sprintf("SELECT id, name, active FROM %s ORDER BY listorder, name", $GLOBALS['tables']['list']));
$systemLists = array();
while ($row = Sql_Fetch_Assoc($listsRes)) {
    $systemLists[] = $row;
}

// Fetch all custom subscriber attributes in the system
$attribRes = Sql_Query(sprintf("SELECT id, name, type FROM %s ORDER BY listorder, name", $GLOBALS['tables']['attribute']));
$systemAttributes = array();
while ($row = Sql_Fetch_Assoc($attribRes)) {
    $systemAttributes[] = $row;
}

// Target lists currently selected
$selectedLists = array_filter(explode(',', $form['lists']));

?>

<!-- Styled visual editor -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.fbe-container {
    font-family: 'Outfit', sans-serif;
    color: #1e293b;
    margin: 20px 0;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.fbe-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: #0f172a;
    border-radius: 16px;
    color: #fff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
}

.fbe-header h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(to right, #38bdf8, #10b981);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.fbe-header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.btn-fbe-back {
    color: #94a3b8;
    text-decoration: none !important;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-fbe-back:hover {
    color: #fff;
}

.btn-fbe-save {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white !important;
    font-weight: 600;
    font-size: 14px;
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px 0 rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-fbe-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px 0 rgba(16, 185, 129, 0.5);
    filter: brightness(1.1);
}
.btn-fbe-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.fbe-split {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

.fbe-panel-left {
    flex: 6;
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-height: 80vh;
    overflow-y: auto;
    padding-right: 6px;
}

.fbe-panel-right {
    flex: 4;
    position: sticky;
    top: 20px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 500px;
}

.section-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 16px 0;
    color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.form-control {
    width: 100%;
    padding: 10px 14px;
    font-size: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    color: #1e293b;
    background-color: #fff;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.form-control:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
}

.lists-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}
.list-checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}
.list-checkbox-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.list-checkbox-item.checked {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}

/* Fields management styling */
.fbe-fields-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}
.field-item-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    position: relative;
    transition: all 0.2s;
}
.field-item-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}
.field-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.field-card-title {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-delete-field {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    padding: 4px;
    transition: opacity 0.2s;
}
.btn-delete-field:hover {
    opacity: 0.8;
}
.field-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.btn-add-field {
    background: #f1f5f9;
    color: #475569;
    border: 2px dashed #cbd5e1;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}
.btn-add-field:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    color: #1e293b;
}

/* Styles customizer details */
.styles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 16px;
}

/* Preview Area */
.preview-title-bar {
    width: 100%;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px dashed #cbd5e1;
    padding-bottom: 10px;
}

/* Interactive Preview Form styles */
.preview-form-wrapper {
    width: 100%;
    max-width: 360px;
    padding: 28px;
    border-radius: var(--preview-radius, 16px);
    transition: all 0.3s ease;
    box-sizing: border-box;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* Glassmorphism theme */
.theme-glassmorphism {
    background: rgba(15, 23, 42, 0.7) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
}
.theme-glassmorphism label {
    color: #cbd5e1 !important;
}
.theme-glassmorphism input, .theme-glassmorphism select, .theme-glassmorphism textarea {
    background: rgba(255,255,255,0.07) !important;
    border: 1px solid rgba(255,255,255,0.15) !important;
    color: #fff !important;
}
.theme-glassmorphism input::placeholder, .theme-glassmorphism textarea::placeholder {
    color: #94a3b8 !important;
}
.theme-glassmorphism select option {
    background: #1e293b !important;
    color: #fff !important;
}

/* Light Theme */
.theme-light {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
}
.theme-light label {
    color: #475569 !important;
}
.theme-light input, .theme-light select, .theme-light textarea {
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}
.theme-light select option {
    background: #ffffff !important;
    color: #0f172a !important;
}

/* Dark Theme */
.theme-dark {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    color: #f8fafc !important;
}
.theme-dark label {
    color: #94a3b8 !important;
}
.theme-dark input, .theme-dark select, .theme-dark textarea {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
    color: #fff !important;
}
.theme-dark select option {
    background: #0f172a !important;
    color: #fff !important;
}

.preview-form-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px 0;
    text-align: center;
}
.preview-form-desc {
    font-size: 12px;
    text-align: center;
    line-height: 1.5;
    margin: 0 0 20px 0;
    opacity: 0.8;
}

.preview-form-group {
    margin-bottom: 12px;
}
.preview-form-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 4px;
}
.preview-form-group input {
    width: 100%;
    padding: 8px 12px;
    font-size: 13px;
    border-radius: 8px;
    outline: none;
    box-sizing: border-box;
}

.preview-form-submit {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: none;
    color: white;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: transform 0.2s ease, filter 0.2s;
    margin-top: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
.preview-form-submit:hover {
    transform: scale(1.02);
    filter: brightness(1.1);
}

/* Toast micro-animation */
.toast-success {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #10b981;
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1000;
}
.toast-success.show {
    transform: translateY(0);
    opacity: 1;
}

/* Spinner */
.spinner {
    border: 3px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top: 3px solid #fff;
    width: 16px;
    height: 16px;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="fbe-container">
    <!-- Header -->
    <div class="fbe-header">
        <div>
            <a href="<?php echo PageURL2('main&pi=FormBuilderPlugin'); ?>" class="btn-fbe-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Volver al listado
            </a>
            <h2 style="margin-top: 8px;">Editar Formulario: <?php echo htmlspecialchars($form['name']); ?></h2>
        </div>
        <div class="fbe-header-actions">
            <button id="btn-save-form" class="btn-fbe-save" onclick="saveFormSettings()">
                Guardar Cambios
            </button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="fbe-split">
        <!-- Settings Panel -->
        <div class="fbe-panel-left">
            
            <!-- Section: General -->
            <div class="section-card">
                <h3 class="section-title">Ajustes Generales</h3>
                
                <div class="form-group">
                    <label for="form-name">Nombre Interno del Formulario</label>
                    <input type="text" id="form-name" class="form-control" value="<?php echo htmlspecialchars($form['name']); ?>" oninput="updateLivePreview()">
                </div>
                
                <div class="form-group">
                    <label for="form-title">Título del Formulario (Público)</label>
                    <input type="text" id="form-title" class="form-control" value="<?php echo htmlspecialchars($form['title']); ?>" oninput="updateLivePreview()">
                </div>
                
                <div class="form-group">
                    <label for="form-desc">Descripción (Pública)</label>
                    <textarea id="form-desc" class="form-control" rows="3" oninput="updateLivePreview()"><?php echo htmlspecialchars($form['description']); ?></textarea>
                </div>
            </div>

            <!-- Section: Lists -->
            <div class="section-card">
                <h3 class="section-title">Listas de Destino</h3>
                <p style="font-size: 13px; color:#64748b; margin:-8px 0 16px 0;">Selecciona las listas del sistema donde se suscribirá automáticamente a los contactos.</p>
                
                <div class="lists-checkbox-grid">
                    <?php foreach ($systemLists as $slist): 
                        $isChecked = in_array($slist['id'], $selectedLists);
                    ?>
                        <label class="list-checkbox-item <?php echo $isChecked ? 'checked' : ''; ?>" id="label-list-<?php echo $slist['id']; ?>">
                            <input type="checkbox" name="target-lists[]" value="<?php echo $slist['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?> onchange="toggleListClass(<?php echo $slist['id']; ?>)" style="margin:0;">
                            <?php echo htmlspecialchars(stripslashes($slist['name'])); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section: Fields -->
            <div class="section-card">
                <h3 class="section-title">Campos del Formulario</h3>
                <p style="font-size: 13px; color:#64748b; margin:-8px 0 16px 0;">Añade y personaliza los campos. Mapea cada campo a los atributos de tu base de datos de phpList.</p>
                
                <div id="fields-container" class="fbe-fields-list">
                    <!-- Fields injected dynamically via JS -->
                </div>
                
                <div class="btn-add-field" onclick="addNewField()">
                    + Añadir Nuevo Campo
                </div>
            </div>

            <!-- Section: Style -->
            <div class="section-card">
                <h3 class="section-title">Ajustes Estéticos y Éxito</h3>
                
                <div class="styles-grid">
                    <div class="form-group">
                        <label for="style-theme">Tema de Diseño</label>
                        <select id="style-theme" class="form-control" onchange="updateLivePreview()">
                            <option value="glassmorphism">Glassmorphism (Elegante)</option>
                            <option value="light">Claro Moderno</option>
                            <option value="dark">Oscuro Premium</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="style-color">Color de Acento</label>
                        <div style="display: flex; gap:8px;">
                            <input type="color" id="style-color" class="form-control" style="width: 44px; height: 38px; padding: 2px; cursor: pointer;" oninput="updateLivePreview()">
                            <input type="text" id="style-color-hex" class="form-control" style="flex-grow:1;" placeholder="#10b981" oninput="syncColorInput(this.value)">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="style-radius">Borde Redondeado (px)</label>
                        <input type="number" id="style-radius" class="form-control" min="0" max="30" value="16" oninput="updateLivePreview()">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="style-submit-text">Texto del Botón de Envío</label>
                    <input type="text" id="style-submit-text" class="form-control" value="Suscribirse Ahora" oninput="updateLivePreview()">
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="success-message">Mensaje de Éxito (Público)</label>
                    <input type="text" id="success-message" class="form-control" value="<?php echo htmlspecialchars($form['success_message']); ?>">
                    <span style="font-size: 11px; color:#64748b;">El texto de agradecimiento que se mostrará en el formulario tras el envío.</span>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="redirect-url">URL de Página de Gracias / Redirección (Opcional)</label>
                    <input type="text" id="redirect-url" class="form-control" value="<?php echo htmlspecialchars($form['redirect_url'] ?? ''); ?>" placeholder="Ej: https://tusitio.com/gracias">
                    <span style="font-size: 11px; color:#64748b;">Si se define, se redirigirá automáticamente al suscriptor a este enlace tras el envío.</span>
                </div>
            </div>

        </div>

        <!-- Live Preview Panel -->
        <div class="fbe-panel-right">
            <div class="preview-title-bar">Vista Previa En Vivo</div>
            
            <div id="live-preview-wrapper" class="preview-form-wrapper theme-glassmorphism">
                <h3 id="preview-form-title-text" class="preview-form-title">¡Únete a nuestra lista!</h3>
                <p id="preview-form-desc-text" class="preview-form-desc">Recibe novedades y promociones exclusivas en tu correo electrónico.</p>
                
                <div id="preview-fields-container">
                    <!-- Fields preview rendered here -->
                </div>
                
                <button id="preview-submit-btn" class="preview-form-submit">Suscribirse Ahora</button>
            </div>
        </div>
    </div>
</div>

<div id="save-toast" class="toast-success">¡Formulario guardado con éxito!</div>

<!-- Pass PHP structures to JS -->
<script>
const systemAttributes = <?php echo json_encode($systemAttributes); ?>;
let formFields = <?php echo $form['fields'] ? $form['fields'] : '[]'; ?>;
let formStyles = <?php echo $form['styles'] ? $form['styles'] : '{}'; ?>;

// Initialize layout options
document.addEventListener('DOMContentLoaded', () => {
    // Populate Styles
    if (formStyles.theme) document.getElementById('style-theme').value = formStyles.theme;
    if (formStyles.primaryColor) {
        document.getElementById('style-color').value = formStyles.primaryColor;
        document.getElementById('style-color-hex').value = formStyles.primaryColor;
    }
    if (formStyles.borderRadius) document.getElementById('style-radius').value = formStyles.borderRadius;
    if (formStyles.submitText) document.getElementById('style-submit-text').value = formStyles.submitText;
    
    // Render Fields
    renderFieldsManager();
    updateLivePreview();
});

function toggleListClass(id) {
    const checkbox = document.querySelector('input[value="' + id + '"]');
    const label = document.getElementById('label-list-' + id);
    if (checkbox.checked) {
        label.classList.add('checked');
    } else {
        label.classList.remove('checked');
    }
}

function syncColorInput(hex) {
    if (hex.match(/^#[0-9A-F]{6}$/i)) {
        document.getElementById('style-color').value = hex;
        updateLivePreview();
    }
}

function renderFieldsManager() {
    const container = document.getElementById('fields-container');
    container.innerHTML = '';
    
    formFields.forEach((field, index) => {
        const card = document.createElement('div');
        card.className = 'field-item-card';
        card.dataset.index = index;
        
        let selectOptions = '<option value="0">-- Ninguno (No mapear) --</option>';
        systemAttributes.forEach(attr => {
            selectOptions += `<option value="${attr.id}" ${parseInt(field.attribute_id) === parseInt(attr.id) ? 'selected' : ''}>${attr.name} (${attr.type})</option>`;
        });

        // Email can't be deleted, is always required
        const isEmail = field.id === 'email';
        const deleteButton = isEmail ? '' : `<button type="button" class="btn-delete-field" onclick="deleteField(${index})">Eliminar</button>`;
        const attributeDropdown = isEmail ? 
            `<div class="form-group"><label>Mapeo de Base de Datos</label><input type="text" class="form-control" value="Correo Electrónico (Sistema)" disabled style="background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; font-weight:600;"></div>` :
            `<div class="form-group"><label>Mapeo de Base de Datos</label><input type="text" class="form-control" value="Auto-mapeado por Etiqueta" disabled style="background:#f0fdf4; border-color:#bbf7d0; color:#166534; font-weight:600;" title="Este campo se vinculará automáticamente con el atributo de suscriptor que tenga exactamente el mismo nombre de Etiqueta. Si no existe, se creará de forma dinámica."></div>`;

        const requiredCheckbox = isEmail ?
            `<div class="form-group" style="display:flex; align-items:center; gap:6px; margin-top:28px;"><input type="checkbox" checked disabled style="margin:0;"><label style="margin:0;">Requerido</label></div>` :
            `<div class="form-group" style="display:flex; align-items:center; gap:6px; margin-top:28px;"><input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateFieldProperty(${index}, 'required', this.checked)" style="margin:0;"><label style="margin:0;">Requerido</label></div>`;

        const fieldTypeDropdown = isEmail ? 
            `<input type="hidden" value="email">` :
            `<div class="form-group"><label>Tipo de Campo</label>
             <select class="form-control" onchange="updateFieldProperty(${index}, 'type', this.value)">
                <option value="textline" ${field.type === 'textline' ? 'selected' : ''}>Texto</option>
                <option value="number" ${field.type === 'number' ? 'selected' : ''}>Número</option>
                <option value="email" ${field.type === 'email' ? 'selected' : ''}>Email</option>
                <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Área de texto</option>
                <option value="checkbox" ${field.type === 'checkbox' ? 'selected' : ''}>Casilla de verificación única (Check)</option>
                <option value="checkbox_group" ${field.type === 'checkbox_group' ? 'selected' : ''}>Grupo de casillas (Checkbox Group)</option>
                <option value="select" ${field.type === 'select' ? 'selected' : ''}>Lista desplegable (Select)</option>
                <option value="radio" ${field.type === 'radio' ? 'selected' : ''}>Botones de opción (Radio)</option>
                <option value="hidden" ${field.type === 'hidden' ? 'selected' : ''}>Campo oculto (Hidden)</option>
             </select></div>`;

        const showOptionsInput = (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox_group');
        const optionsInputBlock = showOptionsInput ? 
            `<div class="form-group" style="grid-column: 1 / -1; margin-top: 8px;">
                <label>Opciones del Campo (separadas por comas)</label>
                <input type="text" class="form-control" value="${field.options || ''}" placeholder="Ej: Opción 1, Opción 2, Opción 3" oninput="updateFieldProperty(${index}, 'options', this.value)">
                <span style="font-size: 11px; color:#64748b;">Define las opciones que estarán disponibles para este campo.</span>
             </div>` : '';

        const isHidden = field.type === 'hidden';
        const placeholderOrValueInput = isHidden ?
            `<div class="form-group">
                <label>Valor del Campo (Value)</label>
                <input type="text" class="form-control" value="${field.value || ''}" placeholder="Ej: CAMP_2026" oninput="updateFieldProperty(${index}, 'value', this.value)">
             </div>` :
            `<div class="form-group">
                <label>Texto Marcador (Placeholder)</label>
                <input type="text" class="form-control" value="${field.placeholder || ''}" placeholder="Ej: Escribe tu respuesta..." oninput="updateFieldProperty(${index}, 'placeholder', this.value)" ${field.type === 'radio' || field.type === 'checkbox_group' ? 'disabled' : ''}>
             </div>`;

        card.innerHTML = `
            <div class="field-card-header">
                <span class="field-card-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
                    Campo: ${isEmail ? 'Email (Principal)' : field.label || 'Campo nuevo'}
                </span>
                ${deleteButton}
            </div>
            <div class="field-card-grid">
                <div class="form-group">
                    <label>Etiqueta (Label)</label>
                    <input type="text" class="form-control" value="${field.label}" oninput="updateFieldProperty(${index}, 'label', this.value)">
                </div>
                
                ${placeholderOrValueInput}
                
                ${fieldTypeDropdown}
                ${attributeDropdown}
                ${requiredCheckbox}
                ${optionsInputBlock}
            </div>
        `;
        
        container.appendChild(card);
    });
}

function updateFieldProperty(index, property, value) {
    formFields[index][property] = value;
    if (property === 'type') {
        renderFieldsManager();
    }
    updateLivePreview();
}

function addNewField() {
    const nextIndex = formFields.length;
    formFields.push({
        id: 'field_' + Date.now(),
        type: 'textline',
        label: 'Campo ' + (nextIndex + 1),
        placeholder: 'Escribe tu respuesta...',
        value: '',
        required: false,
        attribute_id: 0
    });
    renderFieldsManager();
    updateLivePreview();
}

function deleteField(index) {
    if (confirm('¿Eliminar este campo?')) {
        formFields.splice(index, 1);
        renderFieldsManager();
        updateLivePreview();
    }
}

function updateLivePreview() {
    // 1. Text changes
    const title = document.getElementById('form-title').value;
    const desc = document.getElementById('form-desc').value;
    
    document.getElementById('preview-form-title-text').innerText = title || '¡Únete a nuestra lista!';
    document.getElementById('preview-form-desc-text').innerText = desc || 'Recibe novedades y promociones.';
    
    // 2. Styling classes & variables
    const theme = document.getElementById('style-theme').value;
    const primaryColor = document.getElementById('style-color').value;
    const borderRadius = document.getElementById('style-radius').value;
    const submitText = document.getElementById('style-submit-text').value;
    
    document.getElementById('style-color-hex').value = primaryColor;
    
    const wrapper = document.getElementById('live-preview-wrapper');
    wrapper.className = 'preview-form-wrapper theme-' + theme;
    wrapper.style.setProperty('--preview-radius', borderRadius + 'px');
    
    const submitBtn = document.getElementById('preview-submit-btn');
    submitBtn.innerText = submitText || 'Suscribirse';
    submitBtn.style.background = primaryColor;
    
    // 3. Render fields in preview
    const previewContainer = document.getElementById('preview-fields-container');
    previewContainer.innerHTML = '';
    
    formFields.forEach(field => {
        const group = document.createElement('div');
        group.className = 'preview-form-group';
        
        const labelText = field.label + (field.required ? ' *' : '');
        
        if (field.type === 'textarea') {
            group.innerHTML = `
                <label>${labelText}</label>
                <textarea class="form-control" style="font-size:12px; border-radius:6px; resize:none;" rows="2" placeholder="${field.placeholder || ''}" disabled></textarea>
            `;
        } else if (field.type === 'checkbox') {
            group.style.display = 'flex';
            group.style.alignItems = 'center';
            group.style.gap = '8px';
            group.style.margin = '10px 0';
            group.innerHTML = `
                <input type="checkbox" style="width:auto; margin:0;" disabled>
                <label style="margin:0; font-size:12px; cursor:default;">${labelText}</label>
            `;
        } else if (field.type === 'checkbox_group') {
            const options = (field.options || '').split(',').map(o => o.trim()).filter(o => o !== '');
            let checkboxesHtml = '';
            if (options.length === 0) {
                checkboxesHtml = '<span style="font-size:11px; font-style:italic; opacity:0.6;">(Sin opciones definidas)</span>';
            } else {
                options.forEach((opt, oIdx) => {
                    checkboxesHtml += `
                        <label style="display:flex; align-items:center; gap:6px; font-weight:normal; font-size:12px; cursor:default; margin-bottom:4px;">
                            <input type="checkbox" disabled style="width:auto; margin:0;">
                            <span>${opt}</span>
                        </label>
                    `;
                });
            }
            group.innerHTML = `
                <label style="display:block; margin-bottom:4px;">${labelText}</label>
                <div style="display:flex; flex-direction:column; gap:4px; margin-top:4px;">
                    ${checkboxesHtml}
                </div>
            `;
        } else if (field.type === 'hidden') {
            group.style.background = 'rgba(255,255,255,0.05)';
            group.style.border = '1px dashed rgba(255,255,255,0.2)';
            group.style.borderRadius = '8px';
            group.style.padding = '8px 12px';
            group.style.margin = '8px 0';
            group.innerHTML = `
                <span style="font-size:11px; font-style:italic; opacity:0.6;">[Campo Oculto: ${field.label} = "${field.value || ''}"]</span>
            `;
        } else if (field.type === 'select') {
            const placeholder = field.placeholder || '-- Seleccione --';
            let selectOptions = `<option value="">${placeholder}</option>`;
            const options = (field.options || '').split(',').map(o => o.trim()).filter(o => o !== '');
            options.forEach(opt => {
                selectOptions += `<option value="${opt}">${opt}</option>`;
            });
            group.innerHTML = `
                <label>${labelText}</label>
                <select class="form-control" style="font-size:12px; border-radius:6px;" disabled>${selectOptions}</select>
            `;
        } else if (field.type === 'radio') {
            const options = (field.options || '').split(',').map(o => o.trim()).filter(o => o !== '');
            let radioButtonsHtml = '';
            if (options.length === 0) {
                radioButtonsHtml = '<span style="font-size:11px; font-style:italic; opacity:0.6;">(Sin opciones definidas)</span>';
            } else {
                options.forEach((opt, oIdx) => {
                    radioButtonsHtml += `
                        <label style="display:flex; align-items:center; gap:6px; font-weight:normal; font-size:12px; cursor:default; margin-bottom:4px;">
                            <input type="radio" name="preview_radio_${field.id}" disabled style="width:auto; margin:0;">
                            <span>${opt}</span>
                        </label>
                    `;
                });
            }
            group.innerHTML = `
                <label style="display:block; margin-bottom:4px;">${labelText}</label>
                <div style="display:flex; flex-direction:column; gap:4px; margin-top:4px;">
                    ${radioButtonsHtml}
                </div>
            `;
        } else {
            const inputType = (field.type === 'number') ? 'number' : ((field.type === 'email') ? 'email' : 'text');
            group.innerHTML = `
                <label>${labelText}</label>
                <input type="${inputType}" placeholder="${field.placeholder || ''}" disabled>
            `;
        }
        
        previewContainer.appendChild(group);
    });
}

function saveFormSettings() {
    const btn = document.getElementById('btn-save-form');
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Guardando...';
    
    // Gather lists
    const selectedLists = [];
    document.querySelectorAll('input[name="target-lists[]"]:checked').forEach(cb => {
        selectedLists.push(parseInt(cb.value));
    });
    
    const name = document.getElementById('form-name').value;
    const title = document.getElementById('form-title').value;
    const description = document.getElementById('form-desc').value;
    const success_message = document.getElementById('success-message').value;
    const redirect_url = document.getElementById('redirect-url').value;
    
    const styles = {
        theme: document.getElementById('style-theme').value,
        primaryColor: document.getElementById('style-color').value,
        borderRadius: document.getElementById('style-radius').value,
        submitText: document.getElementById('style-submit-text').value
    };

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('name', name);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('success_message', success_message);
    formData.append('redirect_url', redirect_url);
    formData.append('fields', JSON.stringify(formFields));
    formData.append('styles', JSON.stringify(styles));
    
    selectedLists.forEach(listId => {
        formData.append('lists[]', listId);
    });

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => {
        if (!res.ok) {
            throw new Error("HTTP error " + res.status);
        }
        return res.text();
    })
    .then(text => {
        try {
            let cleanJson = text.trim();
            const jsonStart = cleanJson.indexOf('{');
            if (jsonStart !== -1) {
                cleanJson = cleanJson.substring(jsonStart);
            }
            const data = JSON.parse(cleanJson);
            
            if (data.success) {
                const toast = document.getElementById('save-toast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2500);
            } else {
                alert(data.message || 'Error al guardar el formulario.');
            }
        } catch (e) {
            console.error("Server raw response:", text);
            console.error("JSON parse error:", e);
            alert("Error al procesar la respuesta del servidor (No es un JSON válido).\n\nDetalles en la consola de desarrollo (F12).");
        }
    })
    .catch(err => {
        console.error("Save error details:", err);
        alert("Ocurrió un error al enviar los datos: " + err.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>
