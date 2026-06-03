<?php
/**
 * submit.php - Public AJAX form submission handler for FormBuilderPlugin.
 * Registers/updates subscribers, saves attributes, and signs them up to mailing lists.
 */

require_once dirname(__FILE__) . '/../../accesscheck.php';

// Ensure the necessary user library functions are included
if (!function_exists('addNewUser')) {
    require_once dirname(__FILE__) . '/../../inc/userlib.php';
}

// Clear output buffers completely to ensure a clean JSON output
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Fetch FormBuilderPlugin instance
$plugin = $GLOBALS['plugins']['FormBuilderPlugin'] ?? $GLOBALS['allplugins']['FormBuilderPlugin'];
if (!$plugin) {
    echo json_encode(array('success' => false, 'message' => 'Error: El plugin Form Builder no está cargado.'));
    exit;
}

$tableName = $plugin->tables['forms'];

// Retrieve Form ID from POST
$formId = isset($_POST['form_id']) ? sprintf('%d', $_POST['form_id']) : 0;
if (!$formId) {
    echo json_encode(array('success' => false, 'message' => 'ID de formulario no especificado.'));
    exit;
}

// Fetch Form details
$fres = Sql_Query(sprintf("SELECT * FROM %s WHERE id = %d", $tableName, $formId));
$form = Sql_Fetch_Assoc($fres);

if (!$form) {
    echo json_encode(array('success' => false, 'message' => 'El formulario solicitado no existe.'));
    exit;
}

$fields = json_decode($form['fields'], true);
$targetLists = array_filter(explode(',', $form['lists']));

if (empty($fields)) {
    echo json_encode(array('success' => false, 'message' => 'El formulario no tiene campos configurados.'));
    exit;
}

$errors = array();
$submittedData = array();
$email = '';

// 1. Validation Phase
foreach ($fields as $field) {
    $fieldName = $field['id'] ?? '';
    if (empty($fieldName)) continue;
    
    $fieldType = $field['type'] ?? 'textline';
    $fieldRequired = $field['required'] ?? false;
    $fieldLabel = $field['label'] ?? 'Campo';
    
    if (isset($_POST[$fieldName])) {
        if (is_array($_POST[$fieldName])) {
            $val = implode(', ', array_filter(array_map('trim', $_POST[$fieldName])));
        } else {
            $val = trim($_POST[$fieldName]);
        }
    } else {
        $val = '';
    }
    
    // Check if required
    if ($fieldRequired) {
        if ($fieldType === 'checkbox') {
            if (empty($_POST[$fieldName])) {
                $errors[$fieldName] = 'Debes marcar esta casilla.';
            }
        } elseif ($fieldType === 'checkbox_group') {
            if ($val === '') {
                $errors[$fieldName] = 'Por favor, selecciona al menos una opción.';
            }
        } elseif ($fieldType === 'radio') {
            if ($val === '') {
                $errors[$fieldName] = 'Por favor, selecciona una opción.';
            }
        } else {
            if ($val === '') {
                $errors[$fieldName] = 'Este campo es obligatorio.';
            }
        }
    }
    
    // Validate email format
    if ($fieldName === 'email') {
        $email = $val;
        if (empty($email)) {
            $errors[$fieldName] = 'El correo electrónico es obligatorio.';
        } elseif (!is_email($email)) {
            $errors[$fieldName] = 'Por favor, introduce un correo electrónico válido.';
        }
    }
    
    // Validate number type
    if ($fieldType === 'number' && $val !== '' && !is_numeric($val)) {
        $errors[$fieldName] = 'Por favor, introduce un número válido.';
    }
    
    $submittedData[$fieldName] = array(
        'value' => $val,
        'label' => $fieldLabel,
        'type' => $fieldType,
        'options' => $field['options'] ?? ''
    );
}

// If validation errors exist, return them
if (!empty($errors)) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Por favor, corrige los errores en el formulario.',
        'errors' => $errors
    ));
    exit;
}

// 2. Subscriber Creation / Retrieval
$userid = addNewUser($email);
if (!$userid) {
    echo json_encode(array('success' => false, 'message' => 'No se pudo registrar al suscriptor.'));
    exit;
}

// Ensure the subscriber is marked as confirmed and active (standard subscribe action behavior)
Sql_Query(sprintf(
    "UPDATE %s SET confirmed = 1, blacklisted = 0, disabled = 0 WHERE id = %d",
    $GLOBALS['tables']['user'],
    $userid
));

// Remove from blacklist if they were previously blacklisted
if (function_exists('unBlackList')) {
    unBlackList($userid);
}

// 3. Save Custom Attributes (Auto-mapped by Label name)
foreach ($submittedData as $fieldId => $info) {
    if ($fieldId === 'email') {
        continue; // Email is already updated in main user record
    }
    
    // Resolve or dynamically create the system attribute ID matching the Label exactly
    $attributeId = getOrCreateAttributeByLabel($info['label'], $info['type'], $info['options']);
    
    if ($attributeId > 0) {
        $value = $info['value'];
        
        // Handle checkbox mapping values ("on" or "")
        if ($info['type'] === 'checkbox') {
            $value = ($value === 'on') ? 'on' : '';
        } else {
            // Resolve choice option names to database IDs for standard phpList compliance
            $value = getOptionIdsForValue($info['label'], $value, $info['type']);
        }
        
        Sql_Query(sprintf(
            "REPLACE INTO %s (userid, attributeid, value) VALUES (%d, %d, '%s')",
            $GLOBALS['tables']['user_attribute'],
            $userid,
            $attributeId,
            sql_escape($value)
        ));
    }
}

// 4. Assign Target Mailing Lists
$listFeedback = array();
if (!empty($targetLists)) {
    foreach ($targetLists as $listId) {
        $listId = intval($listId);
        
        Sql_Query(sprintf(
            "INSERT IGNORE INTO %s (userid, listid, entered, modified) VALUES (%d, %d, NOW(), NOW())",
            $GLOBALS['tables']['listuser'],
            $userid,
            $listId
        ));
        
        if (function_exists('listName')) {
            $listFeedback[] = listName($listId);
        }
    }
}

// 5. Add History Entry
if (function_exists('addUserHistory')) {
    $listsText = !empty($listFeedback) ? implode(', ', $listFeedback) : 'Ninguna';
    $logMsg = sprintf(
        "Suscripción completada mediante el Formulario: %s (ID: %d).\nListas asignadas: %s",
        $form['name'],
        $form['id'],
        $listsText
    );
    addUserHistory($email, "Registro FormBuilder", $logMsg);
}

// 6. Return Success payload
echo json_encode(array(
    'success' => true,
    'message' => $form['success_message'] ?: '¡Gracias por suscribirte!'
));

/**
 * Resolves an existing system attribute ID by name, or automatically creates a new one if not found.
 * Also handles creating and populating the option values tables for choice types (select, radio, checkbox_group).
 */
function getOrCreateAttributeByLabel($label, $fieldType, $optionsStr = '') {
    if (empty($label)) {
        return 0;
    }
    
    // Clean tablename to be alphanumeric, safe for SQL
    $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($label));
    if (empty($cleanName)) {
        $cleanName = 'attr_' . rand(100, 999);
    }
    
    // Map form field types to standard phpList attribute types:
    // 'textline', 'number', 'email', 'textarea', 'checkbox', 'hidden', 'select', 'radio', 'checkboxgroup'
    $attribType = 'textline';
    if ($fieldType === 'textarea') {
        $attribType = 'textarea';
    } elseif ($fieldType === 'checkbox') {
        $attribType = 'checkbox';
    } elseif ($fieldType === 'checkbox_group') {
        $attribType = 'checkboxgroup';
    } elseif ($fieldType === 'hidden') {
        $attribType = 'hidden';
    } elseif ($fieldType === 'select') {
        $attribType = 'select';
    } elseif ($fieldType === 'radio') {
        $attribType = 'radio';
    }
    
    // 1. Check if an attribute with this exact name already exists
    // We query the database directly instead of using core getAttributeIDbyName,
    // which has a prefix-doubling bug under certain phpList configurations.
    $stmt = Sql_Query(sprintf(
        "SELECT id, tablename FROM %s WHERE name = '%s'",
        $GLOBALS['tables']['attribute'],
        sql_escape($label)
    ));
    $row = Sql_Fetch_Assoc($stmt);
    
    $attributeId = 0;
    if ($row && isset($row['id'])) {
        $attributeId = intval($row['id']);
        $cleanName = $row['tablename'];
    } else {
        // Ensure uniqueness of tablename
        $check = Sql_Fetch_Row_Query(sprintf("SELECT id FROM %s WHERE tablename = '%s'", $GLOBALS['tables']['attribute'], $cleanName));
        if ($check) {
            $cleanName .= rand(1, 99);
        }
        
        Sql_Query(sprintf(
            "INSERT INTO %s (name, type, listorder, default_value, required, tablename) VALUES ('%s', '%s', 0, '', 0, '%s')",
            $GLOBALS['tables']['attribute'],
            sql_escape($label),
            sql_escape($attribType),
            sql_escape($cleanName)
        ));
        
        $attributeId = intval(Sql_Insert_Id());
    }
    
    // 2. If it's a choice field, ensure the options table exists and is populated
    if ($attribType === 'select' || $attribType === 'radio' || $attribType === 'checkboxgroup') {
        $tablePrefix = $GLOBALS['table_prefix'] ?? 'phplist_';
        $optionTable = $tablePrefix . 'listattr_' . $cleanName;
        
        // Create the option table if not exists
        Sql_Query(sprintf(
            "CREATE TABLE IF NOT EXISTS %s (
                id integer not null primary key auto_increment,
                name varchar(255),
                unique (name(150)),
                listorder integer default 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $optionTable
        ), 1);
        
        // Insert defined options
        if (!empty($optionsStr)) {
            $opts = array_filter(array_map('trim', explode(',', $optionsStr)));
            foreach ($opts as $opt) {
                Sql_Query(sprintf(
                    "INSERT IGNORE INTO %s (name) VALUES ('%s')",
                    $optionTable,
                    sql_escape($opt)
                ), 1);
            }
        }
    }
    
    return $attributeId;
}

/**
 * Resolves option value strings to their corresponding IDs in the listattr_{tablename} options table.
 * Crucial for phpList core database compatibility.
 */
function getOptionIdsForValue($label, $value, $fieldType) {
    if ($fieldType !== 'select' && $fieldType !== 'radio' && $fieldType !== 'checkbox_group') {
        return $value;
    }
    if ($value === '') {
        return '';
    }
    
    $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($label));
    if (empty($cleanName)) {
        return '';
    }
    
    // Lookup the correct tablename from attribute table to be extremely robust
    $stmt = Sql_Query(sprintf(
        "SELECT tablename FROM %s WHERE name = '%s'",
        $GLOBALS['tables']['attribute'],
        sql_escape($label)
    ));
    $row = Sql_Fetch_Assoc($stmt);
    if ($row && !empty($row['tablename'])) {
        $cleanName = $row['tablename'];
    }
    
    $tablePrefix = $GLOBALS['table_prefix'] ?? 'phplist_';
    $optionTable = $tablePrefix . 'listattr_' . $cleanName;
    
    // Check if table exists first by describing it
    $check = Sql_Query(sprintf("DESCRIBE %s", $optionTable), 1);
    if (!$check) {
        return $value; // Fallback to raw value if table doesn't exist
    }
    
    // Split input values (checkbox group might be comma-separated like "Música, Cine")
    $submittedValues = array_filter(array_map('trim', explode(',', $value)));
    $resolvedIds = array();
    
    foreach ($submittedValues as $sVal) {
        $q = Sql_Query(sprintf(
            "SELECT id FROM %s WHERE name = '%s'",
            $optionTable,
            sql_escape($sVal)
        ));
        $r = Sql_Fetch_Row($q);
        if ($r && isset($r[0])) {
            $resolvedIds[] = intval($r[0]);
        }
    }
    
    if (empty($resolvedIds)) {
        return $value; // Fallback to raw value if options are not found
    }
    
    // Return comma-separated list of IDs (or single ID for select/radio)
    return implode(',', $resolvedIds);
}

exit;
