<?php
session_start();
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $password = $_POST['password'];

    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $password)) {
        $_SESSION['error_message'] = 'Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, and one number.';
        header("Location: Register.php");
        exit();
    }

    // Collect additional form data
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $focus = $_POST['focus'];
    $goal = $_POST['goal'];
    $activity = $_POST['activity'];
    $training_days = isset($_POST['training_days']) ? implode(", ", $_POST['training_days']) : '';
    $weight = floatval($_POST['weight']);
    $height = floatval($_POST['height']);

    // Compute BMI (height in meters)
    $bmi = ($height > 0) ? round($weight / (($height / 100) ** 2), 2) : 0;

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check for existing email
    $checkStmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        $_SESSION['error_message'] = 'This email is already registered.';
        header("Location: Register.php");
        exit();
    }

    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (
            fullname, email, password,
            age, gender, focus, goal,
            activity, training_days, bmi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssisssssd",
        $fullname,
        $email,
        $hashedPassword,
        $age,
        $gender,
        $focus,
        $goal,
        $activity,
        $training_days,
        $bmi
    );

    if ($stmt->execute()) {
        // ✅ Set session for logged-in client
        $client_id = $stmt->insert_id;
        $_SESSION['client_id'] = $client_id;
        $_SESSION['client_name'] = $fullname; // optional

        $stmt->close();
        $checkStmt->close();
        $conn->close();

        $_SESSION['success_message'] = 'Registration successful!';
        header("Location: MembershipPayment.php");
        exit();
    } else {
        $errorMsg = 'Error during registration: ' . $stmt->error;
        $stmt->close();
        $checkStmt->close();
        $conn->close();
        $_SESSION['error_message'] = $errorMsg;
        header("Location: Register.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Member Registration</title>
  <link rel="stylesheet" href="Registerstyle.css" />
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>

<div class="wrapper">
  <form method="POST" id="registerForm">
    <a href="Nonmember.php" class="back-icon"><i class='bx bx-arrow-back'></i></a>
    <h1>Membership Form</h1>
    <p> Want to become a member? Please fill out the form to complete the registration.</p>

    <div id="step1">
      <div class="input-box">
        <input type="text" name="fullname" id="fullname" placeholder="Full Name" required />
        <i class='bx bxs-user'></i>
      </div>

      <div class="input-box">
        <input type="email" name="email" id="email" placeholder="Email" required />
        <i class='bx bxs-envelope'></i>
      </div>

      <div class="input-box">
        <input type="password" name="password" id="password" placeholder="Password" required />
        <i class='bx bx-show' id="togglePassword" style="cursor: pointer;"></i>
      </div>

      <div class="input-box">
        <input type="password" id="repassword" placeholder="Confirm Password" required />
        <i class='bx bx-show' id="tgPassword" style="cursor: pointer;"></i>
      </div>

      <div class="input-box">
        <input type="text" id="verification_code" placeholder="Enter verification code" required />
        <i class='bx bxs-check-shield'></i>
      </div>

      <button type="button" class="btn" onclick="sendVerification()">Send Verification Code</button>
      <button type="button" class="btn" onclick="showStep2()">Next</button>
    </div>

  <div id="step2" class="hidden">
  <div class="form-card">
    <div class="group-label">Gender:</div>
    <div class="options">
      <span><input type="radio" name="gender" value="Male" required> Male</span>
      <span><input type="radio" name="gender" value="Female" required> Female</span>
    </div>
  </div>

  <div class="form-card">
    <div class="group-label">Select Focus Area:</div>
    <div class="options">
      <span><input type="radio" name="focus" value="Arms" required> Arms</span>
      <span><input type="radio" name="focus" value="Chest" required> Chest</span>
      <span><input type="radio" name="focus" value="Legs" required> Legs</span>
      <span><input type="radio" name="focus" value="Full Body" required> Full Body</span>
    </div>
  </div>

  <div class="form-card">
    <div class="group-label">Main Goal:</div>
    <div class="options">
      <span><input type="radio" name="goal" value="Lose Weight" required> Lose Weight</span>
      <span><input type="radio" name="goal" value="Gain Muscle" required> Gain Muscle</span>
      <span><input type="radio" name="goal" value="Stay Fit" required> Stay Fit</span>
    </div>
  </div>

  <div class="form-card">
    <div class="group-label">Activity Level:</div>
    <div class="options">
      <span><input type="radio" name="activity" value="Low" required> Low</span>
      <span><input type="radio" name="activity" value="Moderate" required> Moderate</span>
      <span><input type="radio" name="activity" value="High" required> High</span>
    </div>
  </div>

  <div class="form-card">
    <div class="group-label">Training Days per Week:</div>
    <div class="options">
      <span><input type="checkbox" name="training_days[]" value="Mon"> Mon</span>
      <span><input type="checkbox" name="training_days[]" value="Tue"> Tue</span>
      <span><input type="checkbox" name="training_days[]" value="Wed"> Wed</span>
      <span><input type="checkbox" name="training_days[]" value="Thu"> Thu</span>
      <span><input type="checkbox" name="training_days[]" value="Fri"> Fri</span>
      <span><input type="checkbox" name="training_days[]" value="Sat"> Sat</span>
      <span><input type="checkbox" name="training_days[]" value="Sun"> Sun</span>
    </div>
  </div>

  <div class="form-card">
    <div class="group-label">BMI Info:</div>
    <div class="options">
      <span><input type="number" name="age" placeholder="Age" required></span>
      <span><input type="number" name="weight" placeholder="Weight (kg)" step="0.1" required></span>
      <span><input type="number" name="height" placeholder="Height (cm)" step="0.1" required></span>
    </div>
  </div>

  <button type="submit" class="btn" name="register">Select Membership Plan</button>
</div>

<div class="register-link">
  <p>Already a member? <a href="Loginpage.php">Login here</a></p>
</div>
</form>
</div>

<!-- ✅ Toast Container -->
<div id="toast-alert" class="toast-alert" tabindex="0" style="display:none;">
  <div id="toast-message"></div>
  <button id="toast-close">&times;</button>
</div>

<script>
function sendVerification() {
  const email = document.getElementById("email").value.trim();
  if (!email) {
    showToast("Please enter your email first.");
    return;
  }

  fetch("send_verification.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "email=" + encodeURIComponent(email)
  })
  .then(response => response.text())
  .then(result => {
    if (result === "sent") {
      showToast("Verification code sent.", "success");
    } else {
      showToast("Failed to send verification code. Try again.");
    }
  })
  .catch(() => showToast("Failed to send verification code. Try again."));
}


function showStep2() {
  const fullname = document.getElementById("fullname").value.trim();
  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;
  const repassword = document.getElementById("repassword").value;
  const enteredCode = document.getElementById("verification_code").value.trim();

  if (!fullname || !email || !password || !repassword || !enteredCode) {
    showToast("Please complete all fields.");
    return;
  }

  if (password !== repassword) {
    showToast("Passwords do not match.");
    return;
  }

  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
  if (!passwordPattern.test(password)) {
    showToast("Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, and one number.");
    return;
  }

  fetch("verify_code.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "code=" + encodeURIComponent(enteredCode)
  })
  .then(res => res.text())
  .then(data => {
    if (data === "verified") {
      document.getElementById("step1").classList.add("hidden");
      document.getElementById("step2").classList.remove("hidden");
    } else {
      showToast("Invalid verification code.");
    }
  })
  .catch(() => showToast("Verification failed. Try again."));
}

// Password Toggles
document.getElementById("togglePassword").addEventListener("click", function() {
  const passwordInput = document.getElementById("password");
  const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
  passwordInput.setAttribute("type", type);
  this.classList.toggle("bx-show");
  this.classList.toggle("bx-hide");
});

document.getElementById("tgPassword").addEventListener("click", function() {
  const passwordInput = document.getElementById("repassword");
  const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
  passwordInput.setAttribute("type", type);
  this.classList.toggle("bx-show");
  this.classList.toggle("bx-hide");
});
</script>

<!-- ✅ Toast Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const toast = document.getElementById('toast-alert');
  const messageEl = document.getElementById('toast-message');
  const closeBtn = document.getElementById('toast-close');

  window.showToast = function(message, type = 'error') {
    if (!toast || !messageEl || !message) return;
    messageEl.textContent = message;
    toast.className = `toast-alert ${type}`;
    toast.style.display = 'flex';
    setTimeout(() => toast.classList.add('show'), 10);

    const autoHideTimer = setTimeout(() => hideToast(), 3000);
    function hideToast() {
      toast.classList.remove('show');
      setTimeout(() => {
        toast.style.display = 'none';
        clearTimeout(autoHideTimer);
      }, 300);
    }

    closeBtn.onclick = hideToast;
    toast.onclick = (e) => { if (e.target === toast) hideToast(); };
  };

  <?php if (isset($_SESSION['error_message'])): ?>
    showToast('<?php echo $_SESSION['error_message']; ?>');
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['success_message'])): ?>
    showToast('<?php echo $_SESSION['success_message']; ?>', 'success');
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>
});
</script>

</body>
</html>