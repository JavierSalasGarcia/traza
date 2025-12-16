<?php
/**
 * TrazaFI - Funciones de Validación
 * Funciones para validar datos de entrada
 */

class Validator {
    private $errors = [];
    private $data = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    public function validate($rules) {
        foreach ($rules as $field => $ruleSet) {
            $ruleArray = explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;

            foreach ($ruleArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    private function applyRule($field, $value, $rule) {
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }

        $method = 'validate' . ucfirst($rule);
        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    private function validateRequired($field, $value, $params) {
        if (empty($value) && $value !== '0') {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' es requerido');
        }
    }

    private function validateEmail($field, $value, $params) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' debe ser un email válido');
        }
    }

    private function validateInstitutional($field, $value, $params) {
        if (!empty($value) && !is_institutional_email($value)) {
            $this->addError($field, 'Debe usar un correo institucional @uaemex.mx');
        }
    }

    private function validateMin($field, $value, $params) {
        $min = $params[0] ?? 0;
        if (!empty($value) && strlen($value) < $min) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' debe tener al menos ' . $min . ' caracteres');
        }
    }

    private function validateMax($field, $value, $params) {
        $max = $params[0] ?? 255;
        if (!empty($value) && strlen($value) > $max) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' no debe exceder ' . $max . ' caracteres');
        }
    }

    private function validateNumeric($field, $value, $params) {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' debe ser numérico');
        }
    }

    private function validateMatches($field, $value, $params) {
        $matchField = $params[0] ?? null;
        if (!$matchField) return;

        $matchValue = $this->data[$matchField] ?? null;
        if ($value !== $matchValue) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' no coincide');
        }
    }

    private function validateUnique($field, $value, $params) {
        if (empty($value)) return;

        $table = $params[0] ?? null;
        $column = $params[1] ?? $field;
        $ignoreId = $params[2] ?? null;

        if (!$table) return;

        $db = Database::getInstance();
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";

        if ($ignoreId) {
            $query .= " AND id != :ignoreId";
        }

        $db->query($query)->bind(':value', $value);
        if ($ignoreId) {
            $db->bind(':ignoreId', $ignoreId);
        }

        $count = $db->fetchColumn();

        if ($count > 0) {
            $this->addError($field, 'El ' . $this->getFieldLabel($field) . ' ya está en uso');
        }
    }

    private function validateExists($field, $value, $params) {
        if (empty($value)) return;

        $table = $params[0] ?? null;
        $column = $params[1] ?? 'id';

        if (!$table) return;

        $db = Database::getInstance();
        $count = $db->query("SELECT COUNT(*) FROM {$table} WHERE {$column} = :value")
                   ->bind(':value', $value)
                   ->fetchColumn();

        if ($count == 0) {
            $this->addError($field, 'El ' . $this->getFieldLabel($field) . ' no existe');
        }
    }

    private function validateDate($field, $value, $params) {
        if (!empty($value)) {
            $d = DateTime::createFromFormat('Y-m-d', $value);
            if (!($d && $d->format('Y-m-d') === $value)) {
                $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' debe ser una fecha válida');
            }
        }
    }

    private function validateDatetime($field, $value, $params) {
        if (!empty($value)) {
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if (!($d && $d->format('Y-m-d H:i:s') === $value)) {
                $d = DateTime::createFromFormat('Y-m-d H:i', $value);
                if (!($d && $d->format('Y-m-d H:i') === $value)) {
                    $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' debe ser una fecha/hora válida');
                }
            }
        }
    }

    private function validateIn($field, $value, $params) {
        if (!empty($value) && !in_array($value, $params)) {
            $this->addError($field, 'El valor seleccionado para ' . $this->getFieldLabel($field) . ' no es válido');
        }
    }

    private function validateAlpha($field, $value, $params) {
        if (!empty($value) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $value)) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' solo debe contener letras');
        }
    }

    private function validateAlphanumeric($field, $value, $params) {
        if (!empty($value) && !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/', $value)) {
            $this->addError($field, 'El campo ' . $this->getFieldLabel($field) . ' solo debe contener letras y números');
        }
    }

    private function validateFile($field, $value, $params) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES[$field];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->addError($field, 'Error al subir el archivo');
                return;
            }

            if ($file['size'] > MAX_FILE_SIZE) {
                $this->addError($field, 'El archivo excede el tamaño máximo permitido de ' . format_filesize(MAX_FILE_SIZE));
                return;
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!is_allowed_file_type($extension)) {
                $this->addError($field, 'El tipo de archivo no está permitido');
            }
        }
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    private function getFieldLabel($field) {
        $labels = [
            'nombre' => 'nombre',
            'apellidos' => 'apellidos',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'password_confirm' => 'confirmación de contraseña',
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'contenido' => 'contenido',
            'categoria' => 'categoría',
            'fecha' => 'fecha',
        ];

        return $labels[$field] ?? $field;
    }

    public function errors() {
        return $this->errors;
    }

    public function firstError($field = null) {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }

        return null;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getError($field) {
        return $this->errors[$field] ?? [];
    }
}

/**
 * Helper para crear un validador
 */
function validator($data = []) {
    return new Validator($data);
}
