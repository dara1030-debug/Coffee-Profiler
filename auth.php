<?php


//  database connection ($conn) and session setup from config.php
require_once 'config.php';

//  Handle POST Requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // --- SIGNUP LOGIC ---
        case 'signup':
            global $conn; 
            
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            if (empty($username) || empty($password)) {
                echo json_encode(["success" => false, "message" => "Please fill all fields."]);
                exit; 
            }

            // Prepare the SQL command
            $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
               
                //  "Hash" of the password.
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt->bind_param("ss", $username, $hashed_password);
                
                if ($stmt->execute()) {
                    echo json_encode(["success" => true, "message" => "Signup successful! You can now log in."]);
                } else {
                
                    if ($conn->errno == 1062) {
                         echo json_encode(["success" => false, "message" => "Username already exists."]);
                    } else {
        
                        echo json_encode(["success" => false, "message" => "Error during signup: " . $stmt->error]); 
                    }
                }
                $stmt->close();
            }
            break;

        // --- LOGIN LOGIC ---
        case 'login':
            global $conn; // database connection
            
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            // SQL: Find the user who has this username
            $sql = "SELECT id, username, password FROM users WHERE username = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                
                // Get the result from the database
                $result = $stmt->get_result(); 

                // Did we find exactly one user?
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc(); 
                    
                    // Get the hashed password from the database array
                    $hashed_password = $user['password']; 

                    //  Verify the typed password matches the Hash in the DB
                    if (password_verify($password, $hashed_password)) {
                        
                        
                        // Start the "Session" 
                        $_SESSION['loggedin'] = true;
                        $_SESSION['id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        
                        echo json_encode(["success" => true, "message" => "Login successful!"]);
                    } else {
                        echo json_encode(["success" => false, "message" => "Invalid password."]);
                    }
                } else {
                    echo json_encode(["success" => false, "message" => "No account found with that username."]);
                }
                $stmt->close();
            }
            break;
    }
} 
// GET Requests for logout
else if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // --- LOGOUT LOGIC ---
    if ($action == 'logout') {
        // Clear all session variables
        $_SESSION = array();
        // Destroy the session on the server
        session_destroy();
        echo json_encode(["success" => true, "message" => "Logged out successfully."]);
        exit;
    } 
    // --- CHECK STATUS LOGIC ---
    // called when the page loads to see if we should show the dashboard or login screen
    else if ($action == 'check') {
        echo json_encode(["loggedin" => isLoggedIn(), "username" => $_SESSION['username'] ?? null]);
    }
}

$conn->close();
?>