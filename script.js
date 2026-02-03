
const $ = selector => document.querySelector(selector);

// DOM Elements references to HTML elements in variables 
const container = $('#main-container'); 
const authView = $('#auth-view');       
const dashboardView = $('#dashboard-view'); 
const recipeFormView = $('#recipe-form-view'); 
const authForm = $('#auth-form');
const recipesList = $('#recipes-list');
const recipeForm = $('#recipe-form');
const authStatus = $('#auth-status'); 
const authButton = $('#auth-button');

// Pop up detail box
const recipeModal = $('#recipe-modal');
const modalTitle = $('#modal-title');
const modalIngredients = $('#modal-ingredients');
const modalInstructions = $('#modal-instructions');
const modalDate = $('#modal-date');
const modalEditBtn = $('#modal-edit-btn');
const modalDeleteBtn = $('#modal-delete-btn');


let isLoginMode = true; // Tracks if the user is trying to Login (true) or Signup (false)
let currentRecipes = []; 


// function handles switching between screens (Login vs Dashboard vs Form)
function showView(viewName) {
    [authView, dashboardView, recipeFormView].forEach(view => view.classList.add('hidden'));
    
    // Show only the requested view 
    if (viewName === 'auth') {
        authView.classList.remove('hidden');
        container.classList.remove('wide');
    } else if (viewName === 'dashboard') {
        dashboardView.classList.remove('hidden');
        container.classList.add('wide');
    } else if (viewName === 'form') {
        recipeFormView.classList.remove('hidden');
        container.classList.add('wide');
    }
}

// Updates the Header based on if the user is logged in
function updateAuthStatus(username) {
    if (username) {
        authStatus.innerHTML = `
            <span style="margin-right: 10px; color: #666;">Hi, <b>${username}</b></span>
            <button id="logout-btn" class="secondary" style="padding: 5px 10px; font-size: 0.9em;">Logout</button>
        `;
        // click listener for Logout button
        $('#logout-btn').addEventListener('click', handleLogout);
        
        // Show the dashboard and load data
        showView('dashboard');
        fetchRecipes(); 
    } else {
       
        authStatus.innerHTML = '';
        showView('auth');
    }
}

// ---  AUTH HANDLERS (Login/Signup) ---

function handleAuthSubmit(e) {
    e.preventDefault(); // 
    
    // Get values from inputs
    const username = $('#auth-username').value;
    const password = $('#auth-password').value;
    const action = isLoginMode ? 'login' : 'signup';

    // Package data to send to PHP
    const formData = new FormData();
    formData.append('action', action);
    formData.append('username', username);
    formData.append('password', password);

    $('#auth-message').textContent = ''; 

    // Send POST request to auth.php
    fetch('auth.php', { method: 'POST', body: formData })
        .then(res => res.json()) 
        .then(data => {
            if (data.success) {
                $('#auth-form').reset(); // Clear inputs
                checkLoginStatus()
            } else {
                $('#auth-message').textContent = data.message; 
            }
        })
        .catch(() => {
            $('#auth-message').textContent = 'Connection error.';
        });
}

function handleLogout() {
    // Tell PHP to destroy the session
    fetch('auth.php?action=logout')
        .then(res => res.json())
        .then(() => {
            updateAuthStatus(null); 
            $('#auth-message').textContent = ''; 
            isLoginMode = true; 
            $('#toggle-auth').textContent = 'Switch to Signup';
            authButton.textContent = 'Login';
        });
}

// ---  MODAL LOGIC ---

function openModal(id) {
    // Search the 'currentRecipes' to find the recipe with this ID
    const recipe = currentRecipes.find(r => r.id == id);
    if (!recipe) return;

    modalTitle.textContent = recipe.title;
    modalIngredients.textContent = recipe.ingredients;
    modalInstructions.textContent = recipe.instructions;
    modalDate.textContent = new Date(recipe.created_at).toLocaleDateString();

    // Assign actions to the Edit/Delete buttons inside the modal
    modalEditBtn.onclick = () => { closeModal(); editRecipe(id); };
    modalDeleteBtn.onclick = () => { closeModal(); deleteRecipe(id); };

    recipeModal.classList.add('active');
}

function closeModal() {
    recipeModal.classList.remove('active');
}

window.onclick = function(event) {
    if (event.target == recipeModal) {
        closeModal();
    }
}

// --- CRUD Handler ---

// READ: Get all recipes from server
function fetchRecipes() {
    recipesList.innerHTML = '<p style="text-align:center; color:#888;">Loading...</p>';
    
    fetch('recipes.php', { method: 'GET' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentRecipes = data.recipes; 
                displayRecipes(currentRecipes);
            } else {
                
                if (data.message && data.message.includes('Unauthorized')) checkLoginStatus();
                recipesList.innerHTML = `<p>${data.message}</p>`;
            }
        })
        .catch(() => recipesList.innerHTML = '<p>Network error.</p>');
}

// Helper for the HTML list of recipes
function displayRecipes(recipes) {
    if (recipes.length === 0) {
        recipesList.innerHTML = '<p style="text-align:center;">No recipes yet. Add one!</p>';
        return;
    }

    recipesList.innerHTML = ''; 
    recipes.forEach(recipe => {
        // Create a new div for each recipe
        const div = document.createElement('div');
        div.className = 'recipe-list-item';
        
        
        div.innerHTML = `
            <h3>${recipe.title}</h3>
            <span class="arrow-icon">➔</span>
        `;
        
        // Make the bar clickable -> Opens Modal
        div.onclick = () => openModal(recipe.id);
        
        recipesList.appendChild(div);
    });
}

// CREATE & UPDATE: Submit the recipe form
function handleRecipeSubmit(e) {
    e.preventDefault();
    
    // Check if we have a hidden ID. If yes, it's an Update. If no, it's a Create.
    const isUpdate = !!$('#recipe-id').value;
    
    const formData = new FormData();
    formData.append('title', $('#recipe-title').value);
    formData.append('ingredients', $('#recipe-ingredients').value); 
    formData.append('instructions', $('#recipe-instructions').value);
    
    let action, method;
    if (isUpdate) {
        action = 'update';
        method = 'POST';
        formData.append('action', action);
        formData.append('id', $('#recipe-id').value);
    } else {
        action = 'create';
        method = 'POST';
        formData.append('action', action);
    }

    fetch('recipes.php', { method: method, body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                recipeForm.reset();
                showView('dashboard'); 
                fetchRecipes(); 
            } else {
                 alert("Error: " + data.message); 
            }
        });
}

// Prepares the form for editing
function editRecipe(id) {
    // Find the data for this recipe
    const recipe = currentRecipes.find(r => r.id == id);
    if (recipe) {
       
        $('#recipe-form-title').textContent = 'Edit Recipe';
        $('#recipe-submit-btn').textContent = 'Update Recipe';
        
        // Fill input fields with existing data
        $('#recipe-id').value = recipe.id;
        $('#recipe-title').value = recipe.title;
        $('#recipe-ingredients').value = recipe.ingredients;
        $('#recipe-instructions').value = recipe.instructions;
        
        
        showView('form');
    }
}

// DELETE: Deletes a recipe
function deleteRecipe(id) {
    fetch('recipes.php', { 
        method: 'DELETE', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}` 
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) fetchRecipes(); 
    });
}

// --- INITIALIZATION ---

function checkLoginStatus() {
    fetch('auth.php?action=check')
        .then(res => res.json())
        .then(data => updateAuthStatus(data.username))
        .catch(() => updateAuthStatus(null));
}

// Event Listeners for buttons
$('#toggle-auth').addEventListener('click', (e) => {
    e.preventDefault();
    isLoginMode = !isLoginMode; 
    $('#toggle-auth').textContent = isLoginMode ? 'Switch to Signup' : 'Switch to Login';
    authButton.textContent = isLoginMode ? 'Login' : 'Signup';
    $('#auth-message').textContent = '';
});

authForm.addEventListener('submit', handleAuthSubmit);
recipeForm.addEventListener('submit', handleRecipeSubmit);

// "Add Recipe" button clears the form and shows it
$('#show-add-form-btn').addEventListener('click', () => {
    recipeForm.reset();
    $('#recipe-id').value = ''; 
    $('#recipe-form-title').textContent = 'Add Recipe';
    $('#recipe-submit-btn').textContent = 'Save Recipe';
    showView('form');
});

$('#cancel-recipe-btn').addEventListener('click', () => {
    showView('dashboard');
});


checkLoginStatus();