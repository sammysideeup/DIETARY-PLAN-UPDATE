<?php 
// Membership.php
session_start();
include 'connection.php';

if (!isset($_SESSION['email'])) {
    // Redirect if not logged in
    header("Location: Loginpage.php");
    exit();
}

$emailToFetch = $_SESSION['email'];
$message = ''; // For status messages

// --- 1. HANDLE FORM SUBMISSION (UPDATE DETAILS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    // Sanitize and validate inputs
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $focus = filter_input(INPUT_POST, 'focus', FILTER_SANITIZE_STRING);
    $goal = filter_input(INPUT_POST, 'goal', FILTER_SANITIZE_STRING);
    $activity = filter_input(INPUT_POST, 'activity', FILTER_SANITIZE_STRING);
    $training_days = filter_input(INPUT_POST, 'training_days', FILTER_VALIDATE_INT);

    // Basic validation check
    if ($age === false || $age < 1 || $training_days === false || $training_days < 0 || empty($gender) || empty($focus) || empty($goal) || empty($activity)) {
        $message = '<div class="bg-red-100 text-red-700 p-3 rounded-lg">Error: Please provide valid input for all fields.</div>';
    } else {
        // Prepare UPDATE query
        $update_sql = "UPDATE users SET age=?, gender=?, focus=?, goal=?, activity=?, training_days=? WHERE email=?";
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            $update_stmt->bind_param("issssds", $age, $gender, $focus, $goal, $activity, $training_days, $emailToFetch);
            
            if ($update_stmt->execute()) {
                // Set a flag to immediately switch back to view mode after success
                $message = '<div class="bg-green-100 text-green-700 p-3 rounded-lg">Success! Your details have been updated.</div>';
            } else {
                $message = '<div class="bg-red-100 text-red-700 p-3 rounded-lg">Database Error: Could not update details.</div>';
            }
            $update_stmt->close();
        } else {
            $message = '<div class="bg-red-100 text-red-700 p-3 rounded-lg">Error preparing update query.</div>';
        }
    }
}

// --- 2. FETCH CURRENT USER DETAILS (Refreshed after potential update) ---
$sql = "SELECT fullname, email, age, gender, focus, goal, activity, training_days, bmi FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $emailToFetch);
$stmt->execute();
$result = $stmt->get_result();
$user = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;

$stmt->close();
$conn->close();

// Prepare details for the view mode (card layout)
$view_details = [
    ['label' => 'Age', 'value' => $user['age'] . ' years', 'icon' => 'bx-calendar-alt', 'color' => 'blue'],
    ['label' => 'Gender', 'value' => $user['gender'], 'icon' => 'bx-male-female', 'color' => 'pink'],
    ['label' => 'Focus Area', 'value' => $user['focus'], 'icon' => 'bx-target-lock', 'color' => 'purple'],
    ['label' => 'Goal', 'value' => $user['goal'], 'icon' => 'bx-run', 'color' => 'green'],
    ['label' => 'Activity Level', 'value' => $user['activity'], 'icon' => 'bx-trending-up', 'color' => 'red'],
    ['label' => 'Training Days', 'value' => $user['training_days'] . ' / week', 'icon' => 'bx-dumbbell', 'color' => 'orange'],
    ['label' => 'BMI', 'value' => number_format($user['bmi'], 2), 'icon' => 'bx-body', 'color' => 'yellow'],
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details</title>
    <link rel="stylesheet" href="membershipstyle.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Hide the edit form by default */
        #edit-form-container {
            display: none;
        }
    </style>
</head>
<body>

    <div class="sidebar">
    <ul>
        <li><a href="#"><i class='bx bx-user'></i> User Details</a></li>
        <li><a href="WorkoutJournal.php"><i class='bx bx-notepad'></i> Workout Journal</a></li>
        <li><a href="Progress.php"><i class='bx bx-line-chart'></i>Progress</a></li>
        <li><a href="TrainerBooking.php"><i class='bx bxs-user-pin'></i> Trainers</a></li>
        
        <li class="more-menu">
            <a href="#" class="more-toggle">
                 More 
                <i class='bx bx-chevron-down toggle-icon'></i>
            </a>
            <ul class="submenu">
                <li><a href="CalorieScanner.php"><i class='bx bx-scan'></i> Calorie Scanner</a></li>
                <li><a href="gym_scanner_module.php"><i class='bx bx-qr-scan'></i> Scan Equipment</a></li>
            </ul>
        </li>
        <li><a href="Loginpage.php"><i class='bx bx-log-out'></i> Logout</a></li>
    </ul>
</div>

    <main class="bg-white rounded-2xl w-full drop-shadow-lg select-none p-6" role="main">
        <h1 class="text-2xl font-bold leading-tight mb-6 text-black">User Profile & Details</h1>

        <?php 
        // Display status message if any
        if ($message) {
            echo "<div class='mb-4'>{$message}</div>";
        }
        ?>

        <?php if ($user): ?>
            <div class="bg-gray-50 p-8 rounded-xl shadow-2xl border-t-4 border-indigo-600 max-w-4xl mx-auto relative">
                
                <div class="text-center pb-6 border-b border-gray-200 mb-6">
                    <p class="text-3xl font-extrabold text-black"><?= htmlspecialchars($user['fullname']) ?></p>
                    <p class="text-gray-500 italic mt-1"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                
                <button onclick="toggleEditMode()" id="edit-button" class="absolute top-4 right-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-150 ease-in-out z-10">
                    <i class='bx bx-edit-alt mr-1'></i> Edit Details
                </button>
                
                <div id="view-details-container">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($view_details as $detail):
                            $bgColor = "bg-{$detail['color']}-100";
                            $iconColor = "text-{$detail['color']}-600";
                        ?>
                            <div class="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-md">
                                <div class="p-3 rounded-full <?= $bgColor ?> <?= $iconColor ?>">
                                    <i class='bx <?= $detail['icon'] ?> text-2xl'></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500"><?= $detail['label'] ?></p>
                                    <p class="text-lg font-bold text-gray-800"><?= $detail['value'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="edit-form-container">
                    <form method="POST" action="Membership.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <div class="flex flex-col">
                                <label for="age" class="text-sm font-medium text-gray-500 mb-1">Age</label>
                                <input type="number" name="age" id="age" value="<?= htmlspecialchars($user['age']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                            </div>
                            
                            <div class="flex flex-col">
                                <label for="gender" class="text-sm font-medium text-gray-500 mb-1">Gender</label>
                                <select name="gender" id="gender" class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                                    <option value="Male" <?= ($user['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($user['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($user['gender'] == 'Other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>

                            <div class="flex flex-col">
                                <label for="focus" class="text-sm font-medium text-gray-500 mb-1">Focus Area</label>
                                <input type="text" name="focus" id="focus" value="<?= htmlspecialchars($user['focus']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                            </div>
                            
                            <div class="flex flex-col">
                                <label for="goal" class="text-sm font-medium text-gray-500 mb-1">Goal</label>
                                <input type="text" name="goal" id="goal" value="<?= htmlspecialchars($user['goal']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                            </div>

                            <div class="flex flex-col">
                                <label for="activity" class="text-sm font-medium text-gray-500 mb-1">Activity Level</label>
                                <select name="activity" id="activity" class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                                    <option value="Low" <?= ($user['activity'] == 'Low') ? 'selected' : '' ?>>Low</option>
                                    <option value="Moderate" <?= ($user['activity'] == 'Moderate') ? 'selected' : '' ?>>Moderate</option>
                                    <option value="High" <?= ($user['activity'] == 'High') ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>

                            <div class="flex flex-col">
                                <label for="training_days" class="text-sm font-medium text-gray-500 mb-1">Training Days (Per Week)</label>
                                <input type="number" name="training_days" id="training_days" value="<?= htmlspecialchars($user['training_days']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                            </div>
                            
                            <div class="flex flex-col md:col-span-2">
                                <label class="text-sm font-medium text-gray-500 mb-1">BMI (Read-Only)</label>
                                <input type="text" value="<?= number_format($user['bmi'], 2) ?>" 
                                    class="p-2 border border-gray-200 bg-gray-100 rounded-lg text-gray-600 cursor-not-allowed" readonly>
                            </div>

                        </div> <div class="mt-8 text-center space-x-4">
                            <button type="submit" name="update_details" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-150 ease-in-out">
                                <i class='bx bx-save mr-2'></i> Save Changes
                            </button>
                            <button type="button" onclick="toggleEditMode()" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-150 ease-in-out">
                                <i class='bx bx-x mr-2'></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                </div>
        <?php else: ?>
            <div class="bg-red-100 p-6 rounded-lg border border-red-400">
                <p class="text-red-700 font-semibold">Error: User details could not be loaded.</p>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function toggleEditMode() {
            const viewContainer = document.getElementById('view-details-container');
            const editContainer = document.getElementById('edit-form-container');
            const editButton = document.getElementById('edit-button');
            
            if (viewContainer.style.display === 'none') {
                // Switch to View Mode
                viewContainer.style.display = 'block';
                editContainer.style.display = 'none';
                editButton.innerHTML = "<i class='bx bx-edit-alt mr-1'></i> Edit Details";
                editButton.classList.remove('bg-red-600', 'hover:bg-red-700');
                editButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            } else {
                // Switch to Edit Mode
                viewContainer.style.display = 'none';
                editContainer.style.display = 'block';
                editButton.innerHTML = "<i class='bx bx-x-circle mr-1'></i> Exit Edit";
                editButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                editButton.classList.add('bg-red-600', 'hover:bg-red-700');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // ... (Your toggleEditMode function logic remains unchanged)
            
            // --- Sidebar Toggle Logic (No 'active' class) ---
            const moreToggle = document.querySelector('.more-toggle');
            const submenu = document.querySelector('.more-menu .submenu');
            const toggleIcon = document.querySelector('.more-menu .toggle-icon');

            if (moreToggle && submenu && toggleIcon) {
                moreToggle.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    
                    // Check the current computed max-height
                    if (submenu.style.maxHeight === '0px' || submenu.style.maxHeight === '') {
                        // Open the menu: set a height large enough for the content (e.g., 200px)
                        submenu.style.maxHeight = '200px'; 
                        toggleIcon.style.transform = 'rotate(180deg)';
                    } else {
                        // Close the menu: reset the height to 0
                        submenu.style.maxHeight = '0px';
                        toggleIcon.style.transform = 'rotate(0deg)';
                    }
                });
            }
        });
    </script>
</body>
</html>