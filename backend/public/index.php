<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../src/db.php';

function respond($data, int $code=200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function appointmentExists(PDO $pdo, int $id): bool {
    $q = $pdo->prepare('SELECT id FROM appointments WHERE id = ?');
    $q->execute([$id]);
    return (bool)$q->fetchColumn();
}
function validStatus($status): bool {
    return in_array($status, ['Scheduled', 'Completed', 'Cancelled', 'No-show'], true);
}
function appointmentData(array $d, bool $partial=false): array {
    $fields = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'reason'];
    $result = [];
    foreach ($fields as $field) {
        if ($partial && !array_key_exists($field, $d)) continue;
        $value = $d[$field] ?? null;
        if (in_array($field, ['patient_id', 'doctor_id'], true)) {
            if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1)
                respond(['error' => ucfirst(str_replace('_', ' ', $field)) . ' must be a valid ID'], 422);
            $value = (int)$value;
        } elseif ($field === 'appointment_date') {
            $dt = DateTime::createFromFormat('!Y-m-d', (string)$value);
            if (!$dt || $dt->format('Y-m-d') !== (string)$value)
                respond(['error' => 'A valid appointment date is required (YYYY-MM-DD)'], 422);
        } elseif ($field === 'appointment_time') {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string)$value))
                respond(['error' => 'A valid appointment time is required'], 422);
            if (strlen((string)$value) === 5) $value .= ':00';
        } elseif ($field === 'reason') {
            $value = ($value === '' || $value === null) ? null : trim((string)$value);
        }
        $result[$field] = $value;
    }
    if (!$partial) {
        foreach (['patient_id','doctor_id','appointment_date','appointment_time'] as $required) {
            if (!isset($result[$required])) respond(['error' => "Missing required field: {$required}"], 422);
        }
    }
    return $result;
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = array_values(array_filter(explode('/', $path), 'strlen'));

    // Routes are /resource or /resource/{id}. Nginx removes the /api prefix.
    $resource = $parts[0] ?? '';
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : null;

    if ($method === 'GET' && $resource === 'health') respond(['status'=>'ok']);
    if ($method === 'GET' && $resource === 'stats') {
        respond([
            'patients' => (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
            'doctors' => (int)$pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn(),
            'appointments' => (int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
            'today' => (int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()')->fetchColumn()
        ]);
    }

    if ($resource === 'patients' && $method === 'GET' && $id === null)
        respond($pdo->query('SELECT * FROM patients ORDER BY created_at DESC')->fetchAll());
    if ($resource === 'patients' && $method === 'POST' && $id === null) {
        $d = body();
        if (trim($d['full_name'] ?? '') === '') respond(['error'=>'Patient name is required'],422);
        $q=$pdo->prepare('INSERT INTO patients(full_name,phone,email,date_of_birth,gender) VALUES(?,?,?,?,?)');
        $q->execute([trim($d['full_name']),$d['phone']??null,$d['email']??null,($d['date_of_birth']??'')?:null,$d['gender']??null]);
        respond(['id'=>(int)$pdo->lastInsertId(),'message'=>'Patient registered'],201);
    }

    if ($resource === 'doctors' && $method === 'GET' && $id === null)
        respond($pdo->query('SELECT * FROM doctors ORDER BY full_name')->fetchAll());
    if ($resource === 'doctors' && $method === 'POST' && $id === null) {
        $d=body();
        if (trim($d['full_name'] ?? '') === '') respond(['error'=>'Doctor name is required'],422);
        $q=$pdo->prepare('INSERT INTO doctors(full_name,specialty,phone) VALUES(?,?,?)');
        $q->execute([trim($d['full_name']),$d['specialty']??null,$d['phone']??null]);
        respond(['id'=>(int)$pdo->lastInsertId(),'message'=>'Doctor added'],201);
    }

    if ($resource === 'appointments' && $method === 'GET' && $id === null) {
        $sql = "SELECT a.*, p.full_name AS patient_name, d.full_name AS doctor_name, d.specialty
                FROM appointments a
                JOIN patients p ON p.id=a.patient_id
                JOIN doctors d ON d.id=a.doctor_id
                ORDER BY a.appointment_date DESC,a.appointment_time DESC";
        respond($pdo->query($sql)->fetchAll());
    }

    if ($resource === 'appointments' && $method === 'POST' && $id === null) {
        $d = appointmentData(body());
        $q=$pdo->prepare('INSERT INTO appointments(patient_id,doctor_id,appointment_date,appointment_time,reason) VALUES(?,?,?,?,?)');
        try {
            $q->execute(array_values($d));
        } catch (PDOException $e) {
            if ($e->getCode()==='23000') respond(['error'=>'This doctor already has an appointment at that date and time, or the selected patient/doctor is invalid.'],409);
            throw $e;
        }
        respond(['id'=>(int)$pdo->lastInsertId(),'message'=>'Appointment booked'],201);
    }

    if ($resource === 'appointments' && $id !== null && in_array($method, ['PUT','PATCH'], true)) {
        if (!appointmentExists($pdo, $id)) respond(['error'=>'Appointment not found'],404);
        $d = body();

        if (array_key_exists('status', $d) && !validStatus($d['status']))
            respond(['error'=>'Invalid appointment status'],422);

        if ($method === 'PUT') {
            $values = appointmentData($d, false);
            if (array_key_exists('status', $d)) $values['status'] = $d['status'];
        } else {
            // PATCH changes only the fields supplied by the client.
            $values = appointmentData($d, true);
            if (array_key_exists('status', $d)) $values['status'] = $d['status'];
            if (!$values) respond(['error'=>'No valid fields supplied for update'],422);
        }

        $sets = [];
        $params = [];
        foreach ($values as $field => $value) {
            $sets[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $id;
        try {
            $q = $pdo->prepare('UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $q->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode()==='23000') respond(['error'=>'This doctor already has an appointment at that date and time, or the selected patient/doctor is invalid.'],409);
            throw $e;
        }
        respond(['message'=>$method === 'PUT' ? 'Appointment updated' : 'Appointment updated']);
    }

    if ($resource === 'appointments' && $id !== null && $method === 'DELETE') {
        if (!appointmentExists($pdo, $id)) respond(['error'=>'Appointment not found'],404);
        $q=$pdo->prepare('DELETE FROM appointments WHERE id=?');
        $q->execute([$id]);
        respond(['message'=>'Appointment permanently deleted']);
    }

    respond(['error'=>'Endpoint not found'],404);
} catch (Throwable $e) {
    error_log((string)$e);
    respond(['error'=>'An unexpected server error occurred'],500);
}
