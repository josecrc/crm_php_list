<?php
/**
 * main.php - Administration listing page for FormBuilderPlugin.
 * Elegant dashboard to list, create, and manage signup forms.
 */

require_once dirname(__FILE__) . '/../../accesscheck.php';

// Fetch the FormBuilderPlugin instance
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

// Handle request actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'create') {
        $defaultFields = json_encode(array(
            array(
                'id' => 'email',
                'type' => 'email',
                'label' => 'Correo Electrónico',
                'placeholder' => 'ejemplo@correo.com',
                'required' => true,
                'attribute_id' => 0
            ),
            array(
                'id' => 'attribute_name',
                'type' => 'textline',
                'label' => 'Nombre',
                'placeholder' => 'Tu nombre completo',
                'required' => false,
                'attribute_id' => 0
            )
        ), JSON_UNESCAPED_UNICODE);
        
        $defaultStyles = json_encode(array(
            'theme' => 'glassmorphism',
            'primaryColor' => '#10b981', // Emerald/Teal
            'textColor' => '#f8fafc', // Slate 50
            'backgroundColor' => '#0f172a', // Slate 900
            'borderRadius' => '16',
            'submitText' => 'Suscribirse Ahora',
            'showTitle' => true,
            'showDescription' => true,
            'shadow' => 'rgba(16, 185, 129, 0.2) 0px 8px 24px'
        ));
        
        $sql = sprintf(
            "INSERT INTO %s (name, title, description, lists, fields, styles, success_message, created_at) 
             VALUES ('Formulario de Suscripción', '¡Únete a nuestra lista!', 'Recibe novedades y promociones exclusivas en tu correo electrónico.', '', '%s', '%s', '¡Gracias por suscribirte!', NOW())",
            $tableName,
            sql_escape($defaultFields),
            sql_escape($defaultStyles)
        );
        Sql_Query($sql);
        $newId = Sql_Insert_Id();
        
        Redirect("main&pi=FormBuilderPlugin&action=edit&id=" . $newId);
        exit;
    }
    
    if ($action == 'delete' && isset($_GET['id'])) {
        $id = sprintf('%d', $_GET['id']);
        Sql_Query(sprintf("DELETE FROM %s WHERE id = %d", $tableName, $id));
        $_SESSION['action_result'] = "Formulario eliminado con éxito.";
        Redirect("main&pi=FormBuilderPlugin");
        exit;
    }
    
    if ($action == 'listform' && isset($_GET['listid'])) {
        $listid = sprintf('%d', $_GET['listid']);
        
        // Buscar si ya existe un formulario que apunte a esta lista
        $sql = sprintf("SELECT id FROM %s WHERE FIND_IN_SET('%d', lists) > 0 LIMIT 1", $tableName, $listid);
        $res = Sql_Query($sql);
        $found = Sql_Fetch_Assoc($res);
        
        if ($found) {
            Redirect("main&pi=FormBuilderPlugin&action=edit&id=" . $found['id']);
            exit;
        } else {
            // Obtener el nombre de la lista para usarlo en el formulario
            $listRes = Sql_Query(sprintf("SELECT name FROM %s WHERE id = %d", $GLOBALS['tables']['list'], $listid));
            $listRow = Sql_Fetch_Assoc($listRes);
            $listName = $listRow ? $listRow['name'] : 'Lista ' . $listid;
            
            $formName = 'Formulario - ' . $listName;
            $formTitle = '¡Únete a ' . $listName . '!';
            
            $defaultFields = json_encode(array(
                array(
                    'id' => 'email',
                    'type' => 'email',
                    'label' => 'Correo Electrónico',
                    'placeholder' => 'ejemplo@correo.com',
                    'required' => true,
                    'attribute_id' => 0
                ),
                array(
                    'id' => 'attribute_name',
                    'type' => 'textline',
                    'label' => 'Nombre',
                    'placeholder' => 'Tu nombre completo',
                    'required' => false,
                    'attribute_id' => 0
                )
            ), JSON_UNESCAPED_UNICODE);
            
            $defaultStyles = json_encode(array(
                'theme' => 'glassmorphism',
                'primaryColor' => '#10b981',
                'textColor' => '#f8fafc',
                'backgroundColor' => '#0f172a',
                'borderRadius' => '16',
                'submitText' => 'Suscribirse Ahora',
                'showTitle' => true,
                'showDescription' => true,
                'shadow' => 'rgba(16, 185, 129, 0.2) 0px 8px 24px'
            ));
            
            $sql = sprintf(
                "INSERT INTO %s (name, title, description, lists, fields, styles, success_message, created_at) 
                 VALUES ('%s', '%s', 'Recibe novedades y promociones exclusivas en tu correo electrónico.', '%d', '%s', '%s', '¡Gracias por suscribirte!', NOW())",
                $tableName,
                sql_escape($formName),
                sql_escape($formTitle),
                $listid,
                sql_escape($defaultFields),
                sql_escape($defaultStyles)
            );
            Sql_Query($sql);
            $newId = Sql_Insert_Id();
            
            Redirect("main&pi=FormBuilderPlugin&action=edit&id=" . $newId);
            exit;
        }
    }
    
    // If visual editor is requested
    if ($action == 'edit' && isset($_GET['id'])) {
        include dirname(__FILE__) . '/edit.php';
        return;
    }
}

// Fetch all forms from the database
$res = Sql_Query(sprintf("SELECT * FROM %s ORDER BY id DESC", $tableName));
$forms = array();
while ($row = Sql_Fetch_Assoc($res)) {
    $forms[] = $row;
}

// Generate the public subscription URLs
$website = hostName();
$publicBaseUrl = $GLOBALS['admin_scheme'] . '://' . $website . '/' . $GLOBALS['pageroot'] . '/';

?>

<!-- Styled Container for Premium Aesthetics -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.fb-container {
    font-family: 'Outfit', sans-serif;
    color: #1e293b;
    margin: 20px 0;
}

.fb-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 24px;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    border-radius: 20px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.3);
    color: #fff;
}

.fb-header h2 {
    font-size: 26px;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(to right, #38bdf8, #10b981);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.fb-header p {
    margin: 5px 0 0 0;
    color: #94a3b8;
    font-size: 14px;
}

.btn-premium-add {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white !important;
    font-weight: 600;
    font-size: 14px;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.4);
    transition: all 0.3s ease;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-premium-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.6);
    filter: brightness(1.1);
}

.fb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
    margin-top: 20px;
}

.fb-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.fb-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: #38bdf8;
}

.fb-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(to right, #38bdf8, #10b981);
}

.fb-card-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: #0f172a;
}

.fb-card-desc {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 16px 0;
    height: 38px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.fb-tag-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 20px;
}

.fb-tag {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.fb-tag.list-tag {
    background: #e0f2fe;
    color: #0369a1;
    border-color: #bae6fd;
}

.fb-embed-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 20px;
}

.fb-embed-title {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 6px 0;
    text-transform: uppercase;
}

.fb-embed-input-group {
    display: flex;
    gap: 8px;
}

.fb-embed-input {
    flex-grow: 1;
    font-family: monospace;
    font-size: 11px;
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    outline: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-copy {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    color: #0f172a;
    transition: all 0.2s ease;
}

.btn-copy:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.fb-card-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 16px;
    margin-top: 10px;
}

.fb-action-left {
    display: flex;
    gap: 12px;
}

.action-link {
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

.action-edit {
    color: #3b82f6 !important;
}
.action-edit:hover {
    color: #1d4ed8 !important;
}

.action-view {
    color: #10b981 !important;
}
.action-view:hover {
    color: #047857 !important;
}

.action-delete {
    color: #ef4444 !important;
}
.action-delete:hover {
    color: #b91c1c !important;
}

.fb-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 20px;
    margin-top: 20px;
}

.fb-empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    color: #334155;
    margin: 0 0 10px 0;
}

.fb-empty-state p {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 24px 0;
}

/* Toast Success Micro-animation */
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
</style>

<div class="fb-container">
    <!-- Header -->
    <div class="fb-header">
        <div>
            <h2>Constructor de Formularios</h2>
            <p>Diseña formularios elegantes para capturar suscriptores y guardarlos en tus listas.</p>
        </div>
        <a href="<?php echo PageURL2('main&pi=FormBuilderPlugin&action=create'); ?>" class="btn-premium-add">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Crear Formulario
        </a>
    </div>

    <!-- Forms Listing -->
    <?php if (empty($forms)): ?>
        <div class="fb-empty-state">
            <h3>No tienes formularios creados</h3>
            <p>Comienza a capturar contactos en minutos creando un formulario totalmente personalizado.</p>
            <a href="<?php echo PageURL2('main&pi=FormBuilderPlugin&action=create'); ?>" class="btn-premium-add">
                Crear mi primer formulario
            </a>
        </div>
    <?php else: ?>
        <div class="fb-grid">
            <?php foreach ($forms as $form): 
                $fields = json_decode($form['fields'], true);
                $fieldsCount = is_array($fields) ? count($fields) : 0;
                
                // Get target list names
                $listIds = array_filter(explode(',', $form['lists']));
                $listNames = array();
                if (!empty($listIds)) {
                    $lres = Sql_Query(sprintf("SELECT name FROM %s WHERE id IN (%s)", $GLOBALS['tables']['list'], implode(',', $listIds)));
                    while ($lrow = Sql_Fetch_Assoc($lres)) {
                        $listNames[] = stripslashes($lrow['name']);
                    }
                }
                
                // Standalone Form URL
                $formUrl = $publicBaseUrl . '?pi=FormBuilderPlugin&p=view&id=' . $form['id'];
                
                // Iframe Embed Code
                $embedCode = sprintf(
                    '<iframe src="%s" style="width:100%%; max-width:600px; height:480px; border:none; background:transparent;" allowtransparency="true"></iframe>',
                    htmlspecialchars($formUrl)
                );
            ?>
                <div class="fb-card">
                    <h3 class="fb-card-title"><?php echo htmlspecialchars($form['name']); ?></h3>
                    <p class="fb-card-desc"><?php echo htmlspecialchars($form['description'] ?: 'Sin descripción.'); ?></p>
                    
                    <div class="fb-tag-container">
                        <span class="fb-tag"><?php echo $fieldsCount; ?> campos</span>
                        
                        <?php if (empty($listNames)): ?>
                            <span class="fb-tag" style="background:#fef2f2; color:#ef4444; border-color:#fecaca;">Sin listas asignadas</span>
                        <?php else: ?>
                            <?php foreach ($listNames as $lname): ?>
                                <span class="fb-tag list-tag"><?php echo htmlspecialchars($lname); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Copy Code Embed Section -->
                    <div class="fb-embed-section">
                        <h4 class="fb-embed-title">Código para incrustar (Embed)</h4>
                        <div class="fb-embed-input-group">
                            <input type="text" class="fb-embed-input" value="<?php echo htmlspecialchars($embedCode); ?>" readonly id="embed-<?php echo $form['id']; ?>" onclick="this.select();">
                            <button class="btn-copy" onclick="copyEmbedCode(<?php echo $form['id']; ?>)">Copiar</button>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="fb-card-actions">
                        <div class="fb-action-left">
                            <a href="<?php echo PageURL2('main&pi=FormBuilderPlugin&action=edit&id=' . $form['id']); ?>" class="action-link action-edit">Editar</a>
                            <a href="<?php echo $formUrl; ?>" target="_blank" class="action-link action-view">Ver En Vivo</a>
                        </div>
                        <a href="javascript:confirmDelete('<?php echo PageURL2('main&pi=FormBuilderPlugin&action=delete&id=' . $form['id']); ?>');" class="action-link action-delete">Eliminar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Success Toast Alert -->
<div id="copy-toast" class="toast-success">¡Código copiado al portapapeles!</div>

<script>
function copyEmbedCode(id) {
    const input = document.getElementById('embed-' + id);
    input.select();
    input.setSelectionRange(0, 99999); // For mobile
    
    // Copy the text
    navigator.clipboard.writeText(input.value).then(() => {
        const toast = document.getElementById('copy-toast');
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    });
}

function confirmDelete(url) {
    if (confirm('¿Estás seguro de que deseas eliminar este formulario? Esta acción no se puede deshacer.')) {
        window.location.href = url;
    }
}
</script>
