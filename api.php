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
    
    // Fetch user details including role
    $stmt = $conn->prepare("SELECT user_id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if the user is disabled
        if ($row['role'] === 'Disabled') {
            echo json_encode(["error" => "Your account is disabled. Please contact the admin."]);
            exit();
        }

        // Verify password
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = $row['role']; // Store role in session
            
            // Send user role instead of redirecting in PHP
            echo json_encode(["success" => "Login successful", "role" => $row['role']]);
        } else {
            echo json_encode(["error" => "Invalid email or password."]);
        }
    } else {
        echo json_encode(["error" => "Invalid email or password."]);
    }
    exit();
}





// Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    echo json_encode(["success" => "Logged out successfully."]);
    exit();
}

// Get logged-in user info
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_user'])) {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            "user_id" => $_SESSION['user_id'],
            "name" => $_SESSION['name']
        ]);
    } else {
        echo json_encode(["error" => "Please login first."]);
    }
    exit();
}

// User Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if the email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(["error" => "Email already exists. Please log in."]);
        exit();
    }

    // Insert the new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => "Registration successful. Please log in."]);
    } else {
        echo json_encode(["error" => "Registration failed."]);
    }
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

// Fetch a single SOAP record for updating
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_soap'])) {
    $soap_id = $_GET['soap_id'];
    $stmt = $conn->prepare("SELECT s.*, p.name AS patient_name FROM soap_records s JOIN patients p ON s.patient_id = p.patient_id WHERE s.soap_id = ?");
    $stmt->bind_param("i", $soap_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(["error" => "SOAP record not found."]);
    }
    exit();
}

// Update SOAP Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_soap'])) {
    $soap_id = $_POST['soap_id'];
    $subjective = $_POST['subjective'];
    $objective = $_POST['objective'];
    $assessment = $_POST['assessment'];
    $plan = $_POST['plan'];
    
    $stmt = $conn->prepare("UPDATE soap_records SET subjective = ?, objective = ?, assessment = ?, plan = ? WHERE soap_id = ?");
    $stmt->bind_param("ssssi", $subjective, $objective, $assessment, $plan, $soap_id);
    
    if ($stmt->execute()) {
        $response["success"] = "SOAP record updated successfully.";
    } else {
        $response["error"] = "Failed to update SOAP record.";
    }
    echo json_encode($response);
    exit();
}

// Delete SOAP Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_soap'])) {
    $soap_id = $_POST['soap_id'];
    $stmt = $conn->prepare("DELETE FROM soap_records WHERE soap_id = ?");
    $stmt->bind_param("i", $soap_id);
    if ($stmt->execute()) {
        $response["success"] = "SOAP record deleted successfully.";
    } else {
        $response["error"] = "Failed to delete SOAP record.";
    }
    echo json_encode($response);
    exit();
}

// Fetch all users (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_users'])) {
    $result = $conn->query("SELECT user_id, name, email, role FROM users");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit();
}


// Update User (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $name, $email, $role, $user_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => "User updated successfully."]);
    } else {
        echo json_encode(["error" => "Failed to update user."]);
    }
    exit();
}


?>