<?php

// connection to the Database
require_once 'config.php';

//  check if the user is logged in, If not, send a "401 Unauthorized" error.
if (!isLoggedIn()) {
    http_response_code(401); 
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please log in."]);
    $conn->close();
    exit; 
}

// Setup Variables
$user_id = $_SESSION['id']; 
$method = $_SERVER['REQUEST_METHOD']; // Is it POST, GET, or DELETE?
$action = $_REQUEST['action'] ?? ''; // Specific action (create vs update)

// Tell the browser we are sending back JSON data
header('Content-Type: application/json');

// Handle Different Request Methods
switch ($method) {
    
    // --- POST REQUESTS (Used for Creating and Updating) ---
    case 'POST': 
        if ($action === 'create') {
            // === CREATE (Insert New Recipe) ===
            global $conn;
            
            // Clean input data
            $title = trim($_POST['title']);
            $ingredients = trim($_POST['ingredients']); 
            $instructions = trim($_POST['instructions']);

            // SQL: Insert data
            $sql = "INSERT INTO recipes (user_id, title, ingredients, instructions) VALUES (?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                // Bind: i=integer, s=string
                $stmt->bind_param("isss", $user_id, $title, $ingredients, $instructions); 
                
                if ($stmt->execute()) {
                    echo json_encode(["success" => true, "message" => "Recipe added successfully!"]);
                } else {
                    echo json_encode(["success" => false, "message" => "Error adding recipe: " . $stmt->error]);
                }
                $stmt->close();
            }
        } elseif ($action === 'update') {
            // === UPDATE (Edit Existing Recipe) ===
            global $conn;
            $id = trim($_POST['id']);
            $title = trim($_POST['title']);
            $ingredients = trim($_POST['ingredients']);
            $instructions = trim($_POST['instructions']);

            // SQL: Update specific fields.
            //  "AND user_id=?" to ensure users can only edit their OWN recipes
            $sql = "UPDATE recipes SET title=?, ingredients=?, instructions=? WHERE id=? AND user_id=?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssii", $title, $ingredients, $instructions, $id, $user_id);
                
                if ($stmt->execute()) {
                    echo json_encode(["success" => true, "message" => "Recipe updated successfully."]);
                } else {
                    echo json_encode(["success" => false, "message" => "Error updating recipe: " . $stmt->error]);
                }
                $stmt->close();
            }
        }
        break;

    // --- GET REQUESTS Reading Data ---
    case 'GET': 
        global $conn;
        
        // Case A: Get ONE specific recipe example for editing
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            // SQL: Select one recipe and checking the user_id
            $sql = "SELECT id, title, ingredients, instructions FROM recipes WHERE id = ? AND user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($recipe = $result->fetch_assoc()) {
                    echo json_encode(["success" => true, "recipe" => $recipe]);
                } else {
                    echo json_encode(["success" => false, "message" => "Recipe not found or you don't own it."]);
                }
                $stmt->close();
            }
        } 
        // Case B: Get ALL recipes for the dashboard
        else {
            // SQL: Select all recipes belonging to this user
            $sql = "SELECT id, title, ingredients, instructions, created_at FROM recipes WHERE user_id = ? ORDER BY created_at DESC";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $recipes = [];
                // Loop through every row found and add to our list
                while ($row = $result->fetch_assoc()) {
                    $recipes[] = $row;
                }
                // Send the list back to JavaScript
                echo json_encode(["success" => true, "recipes" => $recipes]);
                $stmt->close();
            }
        }
        break;

    // --- DELETE REQUESTS ---
    case 'DELETE': 
        global $conn;
        
        //  $_POST doesn't work for DELETE requests so read the raw input  to get the data.
        parse_str(file_get_contents("php://input"), $delete_vars);
        $id = $delete_vars['id'] ?? null;

        if ($id) {
            // SQL: Delete. 
            $sql = "DELETE FROM recipes WHERE id = ? AND user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $id, $user_id);
                
                if ($stmt->execute()) {
                    // Check if a row was actually deleted 
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(["success" => true, "message" => "Recipe deleted successfully."]);
                    } else {
                        echo json_encode(["success" => false, "message" => "Recipe not found or you don't own it."]);
                    }
                } else {
                    echo json_encode(["success" => false, "message" => "Error deleting recipe: " . $stmt->error]);
                }
                $stmt->close();
            }
        } else {
            echo json_encode(["success" => false, "message" => "Missing recipe ID."]);
        }
        break;

    // --- DEFAULT (Error) ---
    default:
        http_response_code(405); // method not allowed
        echo json_encode(["success" => false, "message" => "Request method not supported."]);
        break;
}


$conn->close();
?>