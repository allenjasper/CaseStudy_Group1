<?php
session_start();
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "soap_system");
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

$response = [];

// User Authentication 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['name'] = $row['name'];
            $response["success"] = "Login successful";
        } else {
            $response["error"] = "Invalid email or password.";
        }
    } else {
        $response["error"] = "Invalid email or password.";
    }
    echo json_encode($response);
    exit();
}

// Get logged-in user info
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_user'])) {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(["name" => $_SESSION['name']]);
    } else {
        echo json_encode(["error" => "Not logged in"]);
    }
    exit();
}

// User Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    if ($stmt->execute()) {
        $response["success"] = "Registration successful.";
    } else {
        $response["error"] = "Failed to register user.";
    }
    echo json_encode($response);
    exit();
}

// Patient Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_patient'])) {
    $name = $_POST['name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $contact_info = $_POST['contact_info'];
    $created_by = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO patients (name, age, gender, contact_info, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $name, $age, $gender, $contact_info, $created_by);
    if ($stmt->execute()) {
        $response["success"] = "Patient registered successfully.";
    } else {
        $response["error"] = "Failed to register patient.";
    }
    echo json_encode($response);
    exit();
}

// Create SOAP Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_soap'])) {
    $patient_name = $_POST['patient_name'];
    $doctor_id = $_SESSION['user_id'];
    $subjective = $_POST['subjective'];
    $objective = $_POST['objective'];
    $assessment = $_POST['assessment'];
    $plan = $_POST['plan'];
    
    // Fetch patient_id based on the provided name
    $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE name = ?");
    $stmt->bind_param("s", $patient_name);
    $stmt->execute();
    $result = $stmt->get_result();
    

    
    if ($row = $result->fetch_assoc()) {
        $patient_id = $row['patient_id'];
        
        // Insert SOAP record
        $stmt = $conn->prepare("INSERT INTO soap_records (patient_id, doctor_id, subjective, objective, assessment, plan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $patient_id, $doctor_id, $subjective, $objective, $assessment, $plan);
        if ($stmt->execute()) {
            $response["success"] = "SOAP record created successfully.";
        } else {
            $response["error"] = "Failed to create SOAP record: " . $stmt->error;
        }        
    } else {
        $response["error"] = "Patient not found.";
    }
    echo json_encode($response);
    exit();
}

// Fetch Patients
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_patients'])) {
    $patients = $conn->query("SELECT * FROM patients")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($patients);
    exit();
}

// Fetch Latest 5 Patients
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_latest_patients'])) {
    $patients = $conn->query("SELECT * FROM patients ORDER BY patient_id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($patients);
    exit();
}

// Fetch SOAP Records
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_soap'])) {
    $soap_records = $conn->query("SELECT s.*, p.name AS patient_name FROM soap_records s JOIN patients p ON s.patient_id = p.patient_id")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($soap_records);
    exit();
}
?>
