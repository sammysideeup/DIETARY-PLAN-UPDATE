<?php 
// Membership.php (Finalized Code with Dietary Plan Logic)
session_start();
include 'connection.php';

// Check for and display session messages before anything else
$message = ''; 
if (isset($_SESSION['success_message'])) {
    $message = '<div class="bg-green-100 text-green-700 p-3 rounded-lg">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $message = '<div class="bg-red-100 text-red-700 p-3 rounded-lg">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}


if (!isset($_SESSION['email'])) {
    header("Location: Loginpage.php");
    exit();
}

$emailToFetch = $_SESSION['email'];

// --- START: Calorie Calculation Functions (Based on Harris-Benedict) ---

function calculateBMR($weight_kg, $height_cm, $age, $gender) {
    if (empty($weight_kg) || empty($height_cm) || empty($age)) return 0;

    if ($gender === 'Male') {
        return 88.362 + (13.397 * $weight_kg) + (4.799 * $height_cm) - (5.677 * $age);
    } else {
        return 447.593 + (9.247 * $weight_kg) + (3.098 * $height_cm) - (4.330 * $age);
    }
}

function calculateTDEE($bmr, $activity_level) {
    if ($bmr === 0) return 0;
    
    $multiplier = 1.2; 
    if ($activity_level === 'Moderate') {
        $multiplier = 1.55; 
    } elseif ($activity_level === 'High') {
        $multiplier = 1.9; 
    }
    return $bmr * $multiplier;
}

function calculateDailyCalorieGoal($tdee, $goal) {
    if ($tdee === 0) return 0;
    
    $adjustment = 0;
    
    // Adjust goal based on user selection
    if ($goal === 'Gain Muscle') {
        $adjustment = 300; 
    } elseif ($goal === 'Lose Weight') {
        $adjustment = -500; 
    }
    
    // Set a minimum calorie intake for safety
    return max(1500, round($tdee + $adjustment));
}

// --- NEW FUNCTION: MACRONUTRIENT CALCULATION ---
function getMacroSplit($goal) {
    $splits = [
        'Gain Muscle' => ['Protein' => 30, 'Carbs' => 50, 'Fat' => 20],
        'Lose Weight' => ['Protein' => 40, 'Carbs' => 35, 'Fat' => 25],
        'Maintain' => ['Protein' => 25, 'Carbs' => 45, 'Fat' => 30],
        // Default for any other goal
        'Default' => ['Protein' => 25, 'Carbs' => 50, 'Fat' => 25],
    ];

    return $splits[$goal] ?? $splits['Default'];
}

function calculateMacroGrams($calorie_goal, $macro_split) {
    $protein_percent = $macro_split['Protein'] / 100;
    $carbs_percent = $macro_split['Carbs'] / 100;
    $fat_percent = $macro_split['Fat'] / 100;

    // Grams = (Calories * Percentage) / Calorie density (4 for Protein/Carbs, 9 for Fat)
    return [
        'ProteinGrams' => round(($calorie_goal * $protein_percent) / 4),
        'CarbGrams' => round(($calorie_goal * $carbs_percent) / 4),
        'FatGrams' => round(($calorie_goal * $fat_percent) / 9),
    ];
}
// --- END: Calorie & Macro Calculation Functions ---


// --- 1. HANDLE FORM SUBMISSION (UPDATE DETAILS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $focus = filter_input(INPUT_POST, 'focus', FILTER_SANITIZE_STRING);
    $goal = filter_input(INPUT_POST, 'goal', FILTER_SANITIZE_STRING);
    $activity = filter_input(INPUT_POST, 'activity', FILTER_SANITIZE_STRING);
    $training_days = filter_input(INPUT_POST, 'training_days', FILTER_VALIDATE_INT);
    $weight_kg = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
    $height_cm = filter_input(INPUT_POST, 'height_cm', FILTER_VALIDATE_FLOAT);

    if ($age === false || $age < 1 || $training_days === false || $training_days < 0 || empty($gender) || empty($focus) || empty($goal) || empty($activity) || $weight_kg === false || $weight_kg <= 0 || $height_cm === false || $height_cm <= 0) {
        $_SESSION['error_message'] = 'Error: Please provide valid input for all fields.';
        header("Location: Membership.php");
        exit();
    } else {
        $height_m = $height_cm / 100;
        $bmi = $weight_kg / ($height_m * $height_m);

        $update_sql = "UPDATE users SET age=?, gender=?, focus=?, goal=?, activity=?, training_days=?, weight_kg=?, height_cm=?, bmi=? WHERE email=?";
        include 'connection.php'; 
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            $update_stmt->bind_param("issssdddds", $age, $gender, $focus, $goal, $activity, $training_days, $weight_kg, $height_cm, $bmi, $emailToFetch);
            
            if ($update_stmt->execute()) {
                $update_stmt->close(); 
                $_SESSION['success_message'] = 'Success! Your details have been updated.';
                header("Location: Membership.php");
                exit(); 
            } else {
                $_SESSION['error_message'] = 'Database Error: Could not update details.';
                $update_stmt->close(); 
                header("Location: Membership.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = 'Error preparing update query.';
            header("Location: Membership.php");
            exit();
        }
    }
}


// --- 1.5. HANDLE FORM SUBMISSION (LOG MEAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_calories'])) {
    $user_id_log = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $meal_type = filter_input(INPUT_POST, 'meal_type', FILTER_SANITIZE_STRING);
    $calories_intake = filter_input(INPUT_POST, 'calories_intake', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $log_date = date("Y-m-d"); 

    $image_path = NULL; // Default to NULL

    if ($user_id_log === false || $calories_intake === false || $calories_intake <= 0 || empty($meal_type) || empty($description)) {
        $_SESSION['error_message'] = 'Error: Please provide valid input for Meal Type, Description, and Calories.';
    } else {
        // Handle File Upload
        if (isset($_FILES['food_picture']) && $_FILES['food_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/food_logs/'; 
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); 
            }
            
            $file_extension = pathinfo($_FILES['food_picture']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('log_', true) . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['food_picture']['type'], $allowed_types) && $_FILES['food_picture']['size'] < 5000000) { 
                if (move_uploaded_file($_FILES['food_picture']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $_SESSION['error_message'] = 'Warning: File upload failed (move error/permissions issue). Log saved without picture.';
                }
            } else {
                $_SESSION['error_message'] = 'Warning: File type or size invalid. Log saved without picture.';
            }
        }
        
        // Prepare INSERT query
        include 'connection.php'; 
        $insert_sql = "INSERT INTO dietary_logs (user_id, log_date, meal_type, description, calories, image_path) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        
        if ($insert_stmt) {
            $insert_stmt->bind_param("isssds", $user_id_log, $log_date, $meal_type, $description, $calories_intake, $image_path);
            
            if ($insert_stmt->execute()) {
                if (!isset($_SESSION['error_message'])) {
                    $_SESSION['success_message'] = 'Meal successfully logged!';
                }
                $insert_stmt->close();
                header("Location: Membership.php");
                exit(); 
            } else {
                $_SESSION['error_message'] = 'Database Error: Could not log meal. ' . $insert_stmt->error;
                $insert_stmt->close();
            }
        } else {
            $_SESSION['error_message'] = 'Error preparing log query.';
        }
    }
    header("Location: Membership.php");
    exit();
}

// --- 1.6. HANDLE FORM SUBMISSION (RESET TODAY'S LOGS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_logs'])) {
    $user_id_reset = filter_input(INPUT_POST, 'user_id_reset', FILTER_VALIDATE_INT);
    $today = date("Y-m-d");

    if ($user_id_reset !== false) {
        include 'connection.php'; 

        $delete_sql = "DELETE FROM dietary_logs WHERE user_id = ? AND log_date = ?";
        $delete_stmt = $conn->prepare($delete_sql);

        if ($delete_stmt) {
            $delete_stmt->bind_param("is", $user_id_reset, $today);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "All logs for today ({$today}) have been reset.";
                $delete_stmt->close();
                header("Location: Membership.php");
                exit();
            } else {
                $_SESSION['error_message'] = 'Database Error: Could not reset logs.';
                $delete_stmt->close();
            }
        } else {
            $_SESSION['error_message'] = 'Error preparing reset query.';
        }
    } else {
        $_SESSION['error_message'] = 'Error: Invalid user ID for reset.';
    }
    header("Location: Membership.php");
    exit();
}


// --- 2. FETCH CURRENT USER DETAILS & CALCULATE GOAL & MACROS ---
$sql = "SELECT id, fullname, email, age, gender, focus, goal, activity, training_days, bmi, weight_kg, height_cm FROM users WHERE email = ?";
if (!isset($conn) || !$conn->ping()) {
    include 'connection.php';
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $emailToFetch);
$stmt->execute();
$result = $stmt->get_result();
$user = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

$daily_calorie_goal = 0;
$total_today_calories = 0;
$calorie_percent = 0;
$recent_logs = []; 
$user_id = $user['id'] ?? null; 
$macro_grams = ['ProteinGrams' => 0, 'CarbGrams' => 0, 'FatGrams' => 0];
$macro_split = ['Protein' => 0, 'Carbs' => 0, 'Fat' => 0];

if ($user && $user_id) {
    // 2.1 Calculate Calorie Goal
    $weight_kg = $user['weight_kg'];
    $height_cm = $user['height_cm'];
    $age = $user['age'];
    $gender = $user['gender'];
    $activity = $user['activity'];
    $goal = $user['goal']; // Ensure this is available

    
    if ($weight_kg > 0 && $height_cm > 0 && $age > 0) {
        $bmr = calculateBMR($weight_kg, $height_cm, $age, $gender);
        $tdee = calculateTDEE($bmr, $activity);
        $daily_calorie_goal = calculateDailyCalorieGoal($tdee, $goal);

        // 2.2 Calculate Macros based on Goal
        $macro_split = getMacroSplit($goal);
        $macro_grams = calculateMacroGrams($daily_calorie_goal, $macro_split);
        
    } else {
        if ($daily_calorie_goal == 0 && empty($message)) {
            $message = '<div class="bg-yellow-100 text-yellow-700 p-3 rounded-lg">Warning: Please update your Height and Weight to calculate your Calorie Goal and Dietary Plan.</div>';
        }
    }


    // 2.3 Fetch Today's Logged Calories
    $today = date("Y-m-d");
    $log_sql = "SELECT SUM(calories) AS total_calories FROM dietary_logs WHERE user_id = ? AND log_date = ?";
    
    if (!isset($conn) || !$conn->ping()) {
        include 'connection.php'; 
    }

    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user_id, $today);
        $log_stmt->execute();
        $log_result = $log_stmt->get_result()->fetch_assoc();
        $total_today_calories = $log_result['total_calories'] ?? 0;
        $log_stmt->close();
    }
    
    // 2.4 Calculate Progress Percentage
    if ($daily_calorie_goal > 0) {
        $calorie_percent = min(100, round(($total_today_calories / $daily_calorie_goal) * 100));
    }
    
    // --- 2.5 Fetch Recent Dietary Logs ---
    $logs_sql = "SELECT log_date, meal_type, description, calories, image_path FROM dietary_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 10";
    
    if (!isset($conn) || !$conn->ping()) {
        include 'connection.php'; 
    }

    $logs_stmt = $conn->prepare($logs_sql);
    if ($logs_stmt) {
        $logs_stmt->bind_param("i", $user_id);
        $logs_stmt->execute();
        $logs_result = $logs_stmt->get_result();
        while ($row = $logs_result->fetch_assoc()) {
            $recent_logs[] = $row;
        }
        $logs_stmt->close();
    }
}

// Close the connection
if (isset($conn)) {
    $conn->close();
}

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

// --- Dietary Plan Suggestions based on Goal ---
$diet_plan_suggestions = [
    'Gain Muscle' => [
        'title' => 'Muscle Gain Plan',
        'icon' => 'bxs-up-arrow',
        'color' => 'green',
        'recommendation' => 'Focus on hitting your high protein target and consume carbohydrates pre and post-workout to fuel growth.',
        'meals' => [
            ['meal' => 'Breakfast', 'cal_percent' => '25%', 'suggestion' => 'Oats, Eggs/Whey Protein, Fruit (high carbs)'],
            ['meal' => 'Lunch', 'cal_percent' => '35%', 'suggestion' => 'Chicken Breast, Rice/Quinoa, Mixed Vegetables'],
            ['meal' => 'Dinner', 'cal_percent' => '30%', 'suggestion' => 'Lean Beef/Salmon, Sweet Potato, Salad'],
            ['meal' => 'Snack/Post-Workout', 'cal_percent' => '10%', 'suggestion' => 'Greek Yogurt or Protein Shake with Fruit'],
        ]
    ],
    'Lose Weight' => [
        'title' => 'Weight Loss Plan',
        'icon' => 'bxs-down-arrow',
        'color' => 'red',
        'recommendation' => 'Prioritize protein intake to maintain muscle and maximize satiety. Focus on low-energy-dense foods (vegetables).',
        'meals' => [
            ['meal' => 'Breakfast', 'cal_percent' => '20%', 'suggestion' => 'Egg Scramble with spinach, Small whole-grain toast'],
            ['meal' => 'Lunch', 'cal_percent' => '30%', 'suggestion' => 'Large Salad with grilled Fish/Tofu, light dressing'],
            ['meal' => 'Dinner', 'cal_percent' => '35%', 'suggestion' => 'Lean Ground Turkey, Steamed Broccoli, small portion of beans'],
            ['meal' => 'Snack', 'cal_percent' => '15%', 'suggestion' => 'Cottage Cheese, Berries, or Vegetable sticks'],
        ]
    ],
    'Default' => [
        'title' => 'Balanced Plan',
        'icon' => 'bxs-circle',
        'color' => 'indigo',
        'recommendation' => 'A balanced approach for health and maintenance. Focus on variety and whole foods.',
        'meals' => [
            ['meal' => 'Breakfast', 'cal_percent' => '25%', 'suggestion' => 'Yogurt, Granola, Fruit'],
            ['meal' => 'Lunch', 'cal_percent' => '35%', 'suggestion' => 'Sandwich on Whole Wheat Bread with Turkey/Ham, Side Salad'],
            ['meal' => 'Dinner', 'cal_percent' => '30%', 'suggestion' => 'Chicken or Tofu Stir-Fry with lots of vegetables and Noodles'],
            ['meal' => 'Snack', 'cal_percent' => '10%', 'suggestion' => 'Apple slices with Peanut Butter or a handful of nuts'],
        ]
    ]
];

$plan_key = $user['goal'] ?? 'Default';
if (!array_key_exists($plan_key, $diet_plan_suggestions)) {
    $plan_key = 'Default';
}
$current_plan = $diet_plan_suggestions[$plan_key];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details</title>
    <link rel="stylesheet" href="Memberstyle.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Hide the edit form by default */
        #edit-form-container {
            display: none;
        }
        /* Style for submenu transition */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .toggle-icon {
            transition: transform 0.3s;
        }
        /* Modal transitions */
        .transition-opacity { transition: opacity 0.3s ease-in-out; }
        .transition-transform { transition: transform 0.3s ease-in-out; }
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
                <li><a href="ScanEquipment.php"><i class='bx bx-qr-scan'></i> Scan Equipment</a></li>
            </ul>
        </li>
        <li><a href="Loginpage.php"><i class='bx bx-log-out'></i> Logout</a></li>
    </ul>
</div>

    <main class="bg-white rounded-2xl w-full drop-shadow-lg select-none p-6" role="main">
        <h1 class="text-2xl font-bold leading-tight mb-6 text-black">User Profile & Details</h1>

        <?php 
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
                
                <div id="view-details-container" class="grid">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-md">
                             <div class="p-3 rounded-full bg-red-100 text-red-600">
                                 <i class='bx bx-line-chart-down text-2xl'></i>
                             </div>
                             <div>
                                 <p class="text-sm font-medium text-gray-500">Weight</p>
                                 <p class="text-lg font-bold text-gray-800"><?= number_format($user['weight_kg'], 1) ?> kg</p>
                             </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-md">
                             <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                 <i class='bx bx-ruler text-2xl'></i>
                             </div>
                             <div>
                                 <p class="text-sm font-medium text-gray-500">Height</p>
                                 <p class="text-lg font-bold text-gray-800"><?= number_format($user['height_cm'], 0) ?> cm</p>
                             </div>
                        </div>
                        
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
                                <label for="weight_kg" class="text-sm font-medium text-gray-500 mb-1">Weight (kg)</label>
                                <input type="number" name="weight_kg" id="weight_kg" value="<?= htmlspecialchars($user['weight_kg']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" step="0.1" required>
                            </div>
                            <div class="flex flex-col">
                                <label for="height_cm" class="text-sm font-medium text-gray-500 mb-1">Height (cm)</label>
                                <input type="number" name="height_cm" id="height_cm" value="<?= htmlspecialchars($user['height_cm']) ?>" 
                                    class="p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-gray-800" required>
                            </div>
                            
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
                                <label class="text-sm font-medium text-gray-500 mb-1">BMI (Automatically Recalculated)</label>
                                <input type="text" value="<?= number_format($user['bmi'], 2) ?>" 
                                    class="p-2 border border-gray-200 bg-gray-100 rounded-lg text-gray-600 cursor-not-allowed" readonly>
                            </div>

                        </div> 
                        <div class="mt-8 text-center space-x-4">
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
            
            <?php if ($daily_calorie_goal > 0): ?>
                <div class="bg-gray-50 p-8 rounded-xl shadow-2xl border-t-4 border-<?= $current_plan['color'] ?>-600 max-w-4xl mx-auto relative mt-8">
                    <h2 class="text-2xl font-bold leading-tight mb-6 text-black flex items-center">
                        <i class='bx <?= $current_plan['icon'] ?> mr-2 text-<?= $current_plan['color'] ?>-600'></i> Suggested Dietary Plan
                    </h2>

                    <div class="mb-6 p-4 border rounded-lg bg-white shadow-inner">
                        <p class="text-xl font-semibold text-gray-700 mb-2">Daily Targets:</p>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="p-2 bg-blue-100 rounded-md">
                                <p class="text-sm text-blue-600 font-medium">Protein (<?= $macro_split['Protein'] ?>%)</p>
                                <p class="text-lg font-bold text-gray-800"><?= number_format($macro_grams['ProteinGrams']) ?> g</p>
                            </div>
                            <div class="p-2 bg-yellow-100 rounded-md">
                                <p class="text-sm text-yellow-600 font-medium">Carbs (<?= $macro_split['Carbs'] ?>%)</p>
                                <p class="text-lg font-bold text-gray-800"><?= number_format($macro_grams['CarbGrams']) ?> g</p>
                            </div>
                            <div class="p-2 bg-red-100 rounded-md">
                                <p class="text-sm text-red-600 font-medium">Fat (<?= $macro_split['Fat'] ?>%)</p>
                                <p class="text-lg font-bold text-gray-800"><?= number_format($macro_grams['FatGrams']) ?> g</p>
                            </div>
                        </div>
                        <p class="text-sm italic text-center mt-3 text-gray-500">Goal: <?= htmlspecialchars($user['goal']) ?> (<?= number_format($daily_calorie_goal) ?> kcal)</p>
                    </div>

                    <h3 class="text-xl font-semibold mb-3 border-b pb-2">Meal Distribution</h3>
                    <p class="text-sm text-gray-600 mb-4 italic"><?= $current_plan['recommendation'] ?></p>

                    <div class="space-y-3">
                        <?php foreach ($current_plan['meals'] as $meal): ?>
                            <div class="bg-white p-3 rounded-lg border-l-4 border-gray-300 flex items-center shadow-sm">
                                <div class="w-24 flex-shrink-0">
                                    <p class="font-bold text-gray-800"><?= $meal['meal'] ?></p>
                                    <p class="text-xs text-gray-500"><?= $meal['cal_percent'] ?> of Calories</p>
                                </div>
                                <div class="ml-4 flex-grow">
                                    <p class="text-sm text-gray-700"><?= $meal['suggestion'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="bg-gray-50 p-8 rounded-xl shadow-2xl border-t-4 border-emerald-600 max-w-4xl mx-auto relative mt-8">
                <h2 class="text-2xl font-bold leading-tight mb-6 text-black flex items-center">
                    <i class='bx bxs-food-menu mr-2 text-emerald-600'></i> Daily Calorie Tracker
                </h2>

                <div class="mb-6 p-4 border rounded-lg bg-white shadow-inner">
                    <div class="flex justify-between items-end mb-2">
                        <p class="text-xl font-semibold text-gray-700">Today's Intake: <span class="text-indigo-600"><?= number_format($total_today_calories) ?></span> kcal</p>
                        <p class="text-md font-medium text-gray-500">Goal: <?= number_format($daily_calorie_goal) ?> kcal</p>
                    </div>

                    <?php 
                        $progressBarColor = $calorie_percent >= 100 ? 'bg-green-500' : 'bg-indigo-500';
                        $messageClass = $calorie_percent >= 100 ? 'text-green-600 font-bold' : 'text-gray-500';
                        $messageText = $calorie_percent >= 100 ? 'Goal Reached! 🎉' : 'Keep going...';
                    ?>

                    <div class="w-full bg-gray-200 rounded-full h-4 relative overflow-hidden">
                        <div class="h-4 rounded-full transition-all duration-500 <?= $progressBarColor ?>" style="width: <?= $calorie_percent ?>%;">
                            <span class="text-xs font-medium text-white absolute inset-0 flex items-center justify-center">
                                <?= $calorie_percent ?>%
                            </span>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-center <?= $messageClass ?>"><?= $messageText ?></p>
                </div>

                <h3 class="text-xl font-semibold mb-3 border-b pb-2">Log Your Meal</h3>
                <form method="POST" action="Membership.php" enctype="multipart/form-data">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="flex flex-col">
                            <label for="meal_type" class="text-sm font-medium text-gray-500 mb-1">Meal Type</label>
                            <select name="meal_type" id="meal_type" class="p-2 border border-gray-300 rounded-lg" required>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch">Lunch</option>
                                <option value="Dinner">Dinner</option>
                                <option value="Snack">Snack</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label for="calories_intake" class="text-sm font-medium text-gray-500 mb-1">Calories (kcal)</label>
                            <input type="number" name="calories_intake" id="calories_intake" min="1" step="0.1"
                                class="p-2 border border-gray-300 rounded-lg" placeholder="e.g. 500.5" required>
                        </div>
                    </div>
                    
                    <div class="flex flex-col mb-4">
                        <label for="description" class="text-sm font-medium text-gray-500 mb-1">Description</label>
                        <input type="text" name="description" id="description" 
                            class="p-2 border border-gray-300 rounded-lg" placeholder="What did you eat?" required>
                    </div>
                    
                    <div class="flex flex-col mb-4">
                        <label for="food_picture" class="text-sm font-medium text-gray-500 mb-1">Upload Picture (Optional)</label>
                        <input type="file" name="food_picture" id="food_picture" accept="image/jpeg, image/png, image/gif"
                            class="p-2 border border-gray-300 rounded-lg bg-white file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                    
                    <button type="submit" name="log_calories" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-150 ease-in-out">
                        <i class='bx bx-check-circle mr-2'></i> Log Meal
                    </button>
                </form>

                <h3 class="text-xl font-semibold mb-3 border-b pb-2 mt-8 flex justify-between items-center">
                    Recent Logs
                    <form id="reset-logs-form" method="POST" action="Membership.php">
                        <input type="hidden" name="reset_logs" value="1">
                        <input type="hidden" name="user_id_reset" value="<?= htmlspecialchars($user['id']) ?>">
                        <button type="button" onclick="showResetModal()" class="text-sm text-red-600 hover:text-red-800 font-normal py-1 px-3 border border-red-300 rounded-lg transition duration-150">
                            Reset Today's Logs
                        </button>
                    </form>
                </h3>

                <div class="space-y-3 max-h-64 overflow-y-auto pr-2">
                    <?php if (!empty($recent_logs)): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="bg-white p-3 rounded-lg border-l-4 border-emerald-400 shadow-sm flex justify-between items-center group">
                                <div class="flex-grow min-w-0">
                                    <p class="font-medium text-gray-800 flex items-center">
                                        <?= htmlspecialchars($log['meal_type']) ?> 
                                        <span class="text-sm text-gray-500 italic ml-2">
                                            (<?= date('M d', strtotime($log['log_date'])) ?>)
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-600 truncate max-w-full">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </p>
                                    <?php if ($log['image_path']): ?>
                                        <div class="mt-2 flex items-center space-x-2">
                                            <a href="<?= htmlspecialchars($log['image_path']) ?>" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center">
                                                <i class='bx bx-image mr-1'></i> View Picture
                                            </a>
                                            <img src="<?= htmlspecialchars($log['image_path']) ?>" alt="Meal Picture" 
                                                class="w-10 h-10 object-cover rounded-md border border-gray-200"
                                                onerror="this.style.display='none';"> 
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-shrink-0 text-right ml-4">
                                    <span class="text-lg font-bold text-emerald-600"><?= number_format($log['calories'], 0) ?></span> 
                                    <span class="text-sm text-gray-500">kcal</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500 italic p-4">No meals logged yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-red-100 p-6 rounded-lg border border-red-400">
                <p class="text-red-700 font-semibold">Error: User details could not be loaded.</p>
            </div>
        <?php endif; ?>
    </main>

    <div id="reset-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="bg-white p-8 rounded-xl shadow-2xl max-w-sm w-full transform transition-transform duration-300 scale-95">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class='bx bx-trash text-3xl text-red-600'></i>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900">Confirm Reset</h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">Are you sure you want to **permanently delete ALL** of your dietary logs for **today**? This action cannot be undone.</p>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 space-y-3">
                <button id="confirm-reset-button" class="w-full justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition duration-150">
                    Yes, Reset Logs
                </button>
                <button id="cancel-reset-button" class="w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-150">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    <script>
        // --- 1. User Details Edit Mode Toggle (Global) ---
        function toggleEditMode() {
            const viewContainer = document.getElementById('view-details-container');
            const editContainer = document.getElementById('edit-form-container');
            const editButton = document.getElementById('edit-button');
            
            if (viewContainer.style.display === 'none') {
                viewContainer.style.display = 'grid'; 
                editContainer.style.display = 'none';
                editButton.innerHTML = "<i class='bx bx-edit-alt mr-1'></i> Edit Details";
                editButton.classList.remove('bg-red-600', 'hover:bg-red-700');
                editButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            } else {
                viewContainer.style.display = 'none';
                editContainer.style.display = 'block';
                editButton.innerHTML = "<i class='bx bx-x-circle mr-1'></i> Exit Edit";
                editButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                editButton.classList.add('bg-red-600', 'hover:bg-red-700');
            }
        }

        // --- 2. Reset Modal Functions (Global for HTML onclick) ---
        const resetModal = document.getElementById('reset-modal');
        
        function showResetModal() {
            if (!resetModal) return; 
            // Show the modal with transition effects
            resetModal.classList.remove('opacity-0', 'pointer-events-none');
            resetModal.querySelector('.bg-white').classList.remove('scale-95');
            resetModal.querySelector('.bg-white').classList.add('scale-100');
        }

        function hideResetModal() {
            if (!resetModal) return; 
            // Hide the modal with transition effects
            resetModal.classList.add('opacity-0', 'pointer-events-none');
            resetModal.querySelector('.bg-white').classList.remove('scale-100');
            resetModal.querySelector('.bg-white').classList.add('scale-95');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // --- Sidebar Toggle Logic ---
            const moreToggle = document.querySelector('.more-toggle');
            const submenu = document.querySelector('.more-menu .submenu');
            const toggleIcon = document.querySelector('.more-menu .toggle-icon');

            if (moreToggle && submenu && toggleIcon) {
                moreToggle.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    
                    if (submenu.style.maxHeight === '0px' || submenu.style.maxHeight === '') {
                        submenu.style.maxHeight = submenu.scrollHeight + 'px'; 
                        toggleIcon.style.transform = 'rotate(180deg)';
                    } else {
                        submenu.style.maxHeight = '0px';
                        toggleIcon.style.transform = 'rotate(0deg)';
                    }
                });
            }

            // --- Reset Modal Event Listeners (Setup on DOM load) ---
            const confirmButton = document.getElementById('confirm-reset-button');
            const cancelButton = document.getElementById('cancel-reset-button');
            const resetForm = document.getElementById('reset-logs-form');

            if (cancelButton && confirmButton) {
                cancelButton.addEventListener('click', hideResetModal);

                confirmButton.addEventListener('click', function() {
                    hideResetModal();
                    // Submit the form only after confirmation
                    resetForm.submit();
                });
            }

            // Close modal if background is clicked
            if (resetModal) {
                resetModal.addEventListener('click', function(e) {
                    if (e.target === resetModal) {
                        hideResetModal();
                    }
                });
            }
        });
    </script>
</body>
</html>