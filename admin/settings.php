<?php
session_start();

if (!isset($_SESSION['a_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../db/db_conn.php';

// Fetch admin's ID from the session
$adminId = $_SESSION['a_id']; 

// Fetch admin info
$sql = "SELECT a_id, firstname, middlename, lastname, birthdate, email, role, department, phone_number, address, pfp FROM admin_register WHERE a_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$adminInfo = $result->fetch_assoc();

// Fetch all leave requests that have been approved or denied by the supervisor
$sql = "SELECT lr.leave_id, e.e_id, e.firstname, e.lastname, e.department, lr.start_date, lr.end_date, lr.leave_type, lr.proof, lr.status, lr.created_at
        FROM leave_requests lr
        JOIN employee_register e ON lr.e_id = e.e_id
        WHERE lr.supervisor_approval = 'Supervisor Approved' AND lr.status = 'Supervisor Approved' ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Fetch employee data from the database
$employees_sql = "SELECT e_id, firstname, lastname, gender FROM employee_register";
$employees_result = $conn->query($employees_sql);

// Store the employee data in an array
$employees = [];
while ($employee = $employees_result->fetch_assoc()) {
    $employees[] = $employee;
}

// Pass the employee data to JavaScript
echo "<script>const employees = " . json_encode($employees) . ";</script>";

// Handle adding, editing, or deleting questions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_question'])) {
        $category = $_POST['category'];
        $question = $_POST['question'];

        $sql = "INSERT INTO evaluation_questions (category, question) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $category, $question);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['edit_question'])) {
        $id = $_POST['id'];
        $new_question = $_POST['new_question'];

        $sql = "UPDATE evaluation_questions SET question = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_question, $id);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['delete_question'])) {
        $id = $_POST['id'];

        $sql = "DELETE FROM evaluation_questions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

$sql = "SELECT * FROM evaluation_questions ORDER BY category, id";
$result = $conn->query($sql);

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="User Profile Dashboard" />
    <meta name="author" content="Your Name" />
    <title>Settings</title>
    <link href="../css/styles.css" rel="stylesheet" />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../css/calendar.css" rel="stylesheet"/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'  ?>
    <div id="layoutSidenav">
        <?php include 'sidebar.php' ?>
            <div id="layoutSidenav_content">
                <main class="bg-black">
                    <div class="container-fluid position-relative px-4 py-4">
                        <div class="container-fluid" id="calendarContainer" 
                            style="position: fixed; top: 9%; right: 0; z-index: 1050; 
                            max-width: 100%; display: none;">
                            <div class="row">
                                <div class="col-12 col-md-10 col-lg-8 mx-auto">
                                    <div id="calendar" class="p-2"></div>
                                </div>
                            </div>
                        </div>
                        <h1 class="big mb-2 text-light">Admin Settings</h1>
                        <hr class="border border-secondary">
                        <h1 class="card-title text-center text-light">Attendance Management</h1>
                        <hr class="border border-secondary">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-dark text-light">
                                        <h3 class="card-title text-start">Time and attendance</h3>
                                        <hr>
                                    </div>
                                    <div class="card-body bg-dark">
                                        <div class="row">
                                            <div class="col-xl-6">
                                                <form method="POST" action="../db/set_leave.php" class="needs-validation" novalidate>
                                                    <div class="form-group mb-3">
                                                        <label for="employee_leaves" class="form-label text-light">Leave Days for Employees:</label>
                                                        <input type="number" name="employee_leaves" id="employee_leaves" class="form-control" required>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label for="employee_id" class="form-label text-light">Select Employee:</label>
                                                        <select name="employee_id" id="employee_ids" class="form-control">
                                                            <option value="all">All Employees</option>
                                                            <?php
                                                            $employees_sql = "SELECT e_id, firstname, lastname FROM employee_register";
                                                            $employees_result = $conn->query($employees_sql);
                                                            while ($employee = $employees_result->fetch_assoc()) {
                                                                echo "<option value='" . $employee['e_id'] . "'>" . $employee['firstname'] . " " . $employee['lastname'] . "</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="text-start">
                                                        <button type="submit" class="btn btn-primary mt-3">Set Allocations</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border border-secondary">
                        <h1 class="card-title text-center text-light">Leave Management</h1>
                        <hr class="border border-secondary">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-dark text-light">
                                        <h3 class="card-title text-start">Leave Allocation</h3>
                                        <hr>
                                    </div>
                                    <div class="card-body bg-dark">
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <form method="POST" action="../db/setLeave.php" class="needs-validation" novalidate>
                                                    <div class="row">
                                                        <div class="col-sm-6 mb-3">
                                                            <div class="form-group mb-3 position-relative">
                                                                <label for="gender" class="fw-bold position-absolute text-light" 
                                                                    style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Select Gender</label>
                                                                <select name="gender" id="gender" class="form-control form-select bg-dark border border-2 border-secondary text-light" 
                                                                    style="height: 55px; padding-top: 15px; padding-bottom: 15px;" required onchange="updateEmployeeList()">
                                                                    <option value="" disabled selected>Select Gender</option>
                                                                    <option value="Male">Male</option>
                                                                    <option value="Female">Female</option>
                                                                </select>
                                                                <div class="invalid-feedback">Please select a gender.</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6 mb-3">
                                                            <div class="form-group mb-3 position-relative">
                                                                <label for="employee_id" class="fw-bold position-absolute text-light" 
                                                                    style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Select Employee</label>
                                                                <select name="employee_id" id="employee_id" class="form-control form-select bg-dark border border-2 border-secondary text-light" 
                                                                    style="height: 55px; padding-top: 15px; padding-bottom: 15px;" required>
                                                                    <option value="" disabled selected>Select Employee</option>
                                                                    <!-- Employees will be populated here dynamically -->
                                                                </select>
                                                                <div class="invalid-feedback">Please select an employee.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="male-leave" class="row mb-3" style="display: none;">
                                                        <div class="col-md-12 mb-3">
                                                            <div class="row justify-content-between">
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="bereavement_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Bereavement Leave</label>
                                                                        <input type="number" name="bereavement_leave_male" id="bereavement_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="emergency_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Emergency Leave</label>
                                                                        <input type="number" name="emergency_leave_male" id="emergency_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="parental_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Parental Leave</label>
                                                                        <input type="number" name="parental_leave_male" id="parental_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12 mb-3">
                                                            <div class="row justify-content-between">
                                                                <div class="col-md-3">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="paternity_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Paternity Leave</label>
                                                                        <input type="number" name="paternity_leave_male" id="paternity_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="service_incentive_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Service Incentive Leave</label>
                                                                        <input type="number" name="service_incentive_leave_male" id="service_incentive_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="sick_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Sick Leave</label>
                                                                        <input type="number" name="sick_leave_male" id="sick_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="vacation_leave_male" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Vacation Leave</label>
                                                                        <input type="number" name="vacation_leave_male" id="vacation_leave_male" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="female-leave" class="row mb-3" style="display: none;">
                                                        <div class="col-md-12 mb-3">
                                                            <div class="row justify-content-between">
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="bereavement_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Bereavement Leave</label>
                                                                        <input type="number" name="bereavement_leave" id="bereavement_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="emergency_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Emergency Leave</label>
                                                                        <input type="number" name="emergency_leave" id="emergency_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="maternity_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Maternity Leave</label>
                                                                        <input type="number" name="maternity_leave" id="maternity_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12 mb-3">
                                                            <div class="row justify-content-between">
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="mcw_special_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">MCW Special Leave</label>
                                                                        <input type="number" name="mcw_special_leave" id="mcw_special_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="parental_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Parental Leave</label>
                                                                        <input type="number" name="parental_leave" id="parental_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="service_incentive_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Service Incentive Leave</label>
                                                                        <input type="number" name="service_incentive_leave" id="service_incentive_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12 mb-3">
                                                            <div class="row justify-content-between">
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="sick_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Sick Leave</label>
                                                                        <input type="number" name="sick_leave" id="sick_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="vacation_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Vacation Leave</label>
                                                                        <input type="number" name="vacation_leave" id="vacation_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb-3 position-relative">
                                                                        <label for="vawc_leave" class="fw-bold position-absolute text-light" 
                                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">VAWC Leave</label>
                                                                        <input type="number" name="vawc_leave" id="vawc_leave" value="" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                                            style="height: 55px; padding-top: 15px; padding-bottom: 15px;" min="0" placeholder="Enter days">
                                                                        <div class="invalid-feedback">Please set a value.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="text-start">
                                                        <button type="submit" class="btn btn-primary mt-3">Set Allocations</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border border-secondary">
                        <h1 class="card-title text-center text-light">Performance Management</h1>
                        <hr class="border border-secondary">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card bg-dark text-light">
                                    <div class="card-header">
                                        <h3 class="mb-0">Performance Management</h3>
                                        <hr>
                                    </div>
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-around">
                                            <h2 class="text-center text-light mb-4">Manage Evaluation Questions</h2>
                                            <div class="col-xl-6 rounded">
                                                <div class="mb-4">
                                                    <h4>Add New Question</h4>
                                                    <form method="POST" action="../admin/manageQuestions.php" class="needs-validation" novalidate>
                                                        <div class="form-group mt-3 mb-3 position-relative">
                                                        <label for="category" class="fw-bold position-absolute text-light" 
                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Category:</label>
                                                            <select name="category" class="form-control bg-dark form-select border border-2 border-secondary text-light" 
                                                                style="height: 55px; padding-top: 15px; padding-bottom: 15px;" required>
                                                                <option value="Quality of Work">Quality of Work</option>
                                                                <option value="Communication Skills">Communication Skills</option>
                                                                <option value="Teamwork">Teamwork</option>
                                                                <option value="Punctuality">Punctuality</option>
                                                                <option value="Initiative">Initiative</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mt-4 mb-3 position-relative">
                                                        <label for="category" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Question:</label>
                                                            <textarea name="question" class="form-control bg-dark border border-2 border-secondary text-light" 
                                                            style="height: 100px; padding-top: 15px; padding-bottom: 15px;" rows="3" required></textarea>
                                                        </div>
                                                        <button type="submit" name="add_question" class="btn btn-primary mt-1">Add Question</button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 rounded">
                                                <h4>Current Questions</h4>
                                                <div class="accordion" id="questionAccordion">
                                                    <?php if (!empty($questions)): ?>
                                                        <?php 
                                                        // Group questions by category
                                                        $categories = [];
                                                        foreach ($questions as $question) {
                                                            $categories[$question['category']][] = $question;
                                                        }
                                                        ?>

                                                        <?php foreach ($categories as $category => $questionsList): ?>
                                                        <?php 
                                                            // Sanitize category names for use in id and data-target
                                                            $categoryId = str_replace(' ', '_', $category); 
                                                        ?>
                                                        <div class="accordion-item ">
                                                            <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($categoryId); ?>">
                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo htmlspecialchars($categoryId); ?>" aria-expanded="false" aria-controls="collapse-<?php echo htmlspecialchars($categoryId); ?>">
                                                                    <?php echo htmlspecialchars($category); ?>
                                                                </button>
                                                            </h2>
                                                            <div id="collapse-<?php echo htmlspecialchars($categoryId); ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo htmlspecialchars($categoryId); ?>" data-bs-parent="#questionAccordion">
                                                                <div class="accordion-body">
                                                                    <ul class="list-group">
                                                                        <?php foreach ($questionsList as $question): ?>
                                                                        <li class="list-group-item bg-dark text-light">
                                                                            <span><?php echo htmlspecialchars($question['question']); ?></span>
                                                                            <div class="mt-2">
                                                                                <!-- Edit Button -->
                                                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editQuestionModal" 
                                                                                    data-qid="<?php echo $question['id']; ?>"
                                                                                    data-question="<?php echo htmlspecialchars($question['question']); ?>">Edit</button>
                                                                                
                                                                                <!-- Delete Form -->
                                                                                <form method="POST" action="../admin/manageQuestions.php" class="d-inline">
                                                                                    <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
                                                                                    <button type="submit" name="delete_question" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this question?')">Delete</button>
                                                                                </form>
                                                                            </div>
                                                                        </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="text-center text-light">No questions found.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
                    <div class="modal fade" id="editQuestionModal" tabindex="-1" role="dialog" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content bg-dark text-light">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="manageQuestions.php">
                                        <input type="hidden" name="id" id="editQId">
                                        <div class="form-group">
                                            <label for="new_question">New Question:</label>
                                            <textarea name="new_question" id="editNewQuestion" class="form-control" rows="3" required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" name="edit_question" class="btn btn-primary mt-3">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content bg-dark text-light">
                                <div class="modal-header border-bottom border-secondary">
                                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to log out?
                                </div>
                                <div class="modal-footer border-top border-secondary">
                                    <button type="button" class="btn border-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                                    <form action="../admin/logout.php" method="POST">
                                        <button type="submit" class="btn btn-danger">Logout</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>  
                <?php include 'footer.php' ?>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'> </script>
        <script src="../js/admin.js"></script>
        <script>
        // Bootstrap form validation script
            (function () {
                'use strict';
                var forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms)
                    .forEach(function (form) {
                        form.addEventListener('submit', function (event) {
                            if (!form.checkValidity()) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                            form.classList.add('was-validated');
                        }, false);
                    });
            })();

            // Toggle display of leave fields
            document.getElementById('gender').addEventListener('change', function () {
                var gender = this.value;
                document.getElementById('male-leave').style.display = gender === 'Male' ? 'flex' : 'none';
                document.getElementById('female-leave').style.display = gender === 'Female' ? 'flex' : 'none';
            });

            //CALENDAR 
            let calendar;
                function toggleCalendar() {
                    const calendarContainer = document.getElementById('calendarContainer');
                        if (calendarContainer.style.display === 'none' || calendarContainer.style.display === '') {
                            calendarContainer.style.display = 'block';
                            if (!calendar) {
                                initializeCalendar();
                            }
                        } else {
                            calendarContainer.style.display = 'none';
                        }
                }

                function initializeCalendar() {
                    const calendarEl = document.getElementById('calendar');
                        calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: 'dayGridMonth',
                            headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                            },
                            height: 440,  
                            events: {
                            url: '../db/holiday.php',  
                            method: 'GET',
                            failure: function() {
                            alert('There was an error fetching events!');
                            }
                            }
                        });

                        calendar.render();
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const currentDateElement = document.getElementById('currentDate');
                    const currentDate = new Date().toLocaleDateString(); 
                    currentDateElement.textContent = currentDate; 
                });

                document.addEventListener('click', function(event) {
                    const calendarContainer = document.getElementById('calendarContainer');
                    const calendarButton = document.querySelector('button[onclick="toggleCalendar()"]');

                        if (!calendarContainer.contains(event.target) && !calendarButton.contains(event.target)) {
                            calendarContainer.style.display = 'none';
                            }
                });
                //CALENDAR END

                //TIME 
                function setCurrentTime() {
                const currentTimeElement = document.getElementById('currentTime');
                const currentDateElement = document.getElementById('currentDate');

                const currentDate = new Date();

                // Convert to 12-hour format with AM/PM
                let hours = currentDate.getHours();
                const minutes = currentDate.getMinutes();
                const seconds = currentDate.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12; // If hour is 0, set to 12

                const formattedHours = hours < 10 ? '0' + hours : hours;
                const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
                const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;

                currentTimeElement.textContent = `${formattedHours}:${formattedMinutes}:${formattedSeconds} ${ampm}`;

                // Format the date in text form (e.g., "January 12, 2025")
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                currentDateElement.textContent = currentDate.toLocaleDateString('en-US', options);
                }

                setCurrentTime();
                setInterval(setCurrentTime, 1000);
                //TIME END

                //EVALUATION QUESTIONS
                // Populate the edit modal with question data
                $('#editQuestionModal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget); // Button that triggered the modal
                    var qid = button.data('qid'); // Extract info from data-* attributes
                    var question = button.data('question'); // Extract the question

                    var modal = $(this);
                    modal.find('#editQId').val(qid); // Insert question ID into the modal's input
                    modal.find('#editNewQuestion').val(question); // Insert question into the modal's textarea
                });
                //EVALUATION QUESTIONS END


                //FETCH EMPLOYEE
                console.log("Employees data:", employees); // Debugging line

                function updateEmployeeList() {
                    console.log("updateEmployeeList function called"); // Debugging line
                    const gender = document.getElementById('gender').value;
                    console.log("Gender value:", gender); // Debugging line to check the gender value

                    const employeeSelect = document.getElementById('employee_id');
                    console.log("Employee select element:", employeeSelect); // Ensure element exists

                    // Clear existing options
                    employeeSelect.innerHTML = '<option value="" disabled selected>Select Employee</option>';

                    if (gender) {
                        console.log("Selected gender:", gender); // Debugging line
                        const filteredEmployees = employees.filter(emp => emp.gender.toLowerCase() === gender.toLowerCase());
                        console.log("Filtered employees:", filteredEmployees); // Debugging line

                        // Check if there are any employees to populate
                        if (filteredEmployees.length > 0) {
                            // Populate the employee dropdown
                            filteredEmployees.forEach(emp => {
                                const option = document.createElement('option');
                                option.value = emp.e_id;
                                option.textContent = `${emp.firstname} ${emp.lastname}`; // Use template literals
                                employeeSelect.appendChild(option);
                            });

                            // Enable the employee dropdown
                            employeeSelect.disabled = false;
                            console.log("Employee dropdown disabled status:", employeeSelect.disabled); // Debugging line
                        } else {
                            const noResultsOption = document.createElement('option');
                            noResultsOption.disabled = true;
                            noResultsOption.textContent = "No employees found for the selected gender";
                            employeeSelect.appendChild(noResultsOption);
                            employeeSelect.disabled = true;
                        }
                    } else {
                        // Disable the employee dropdown if no gender is selected
                        employeeSelect.disabled = true;
                    }
                }

                // Initialize the employee list based on the selected gender (if any)
                document.addEventListener('DOMContentLoaded', function() {
                    console.log("DOM fully loaded and parsed"); // Debugging line
                    updateEmployeeList();
                });

                // Add event listener for when gender changes dynamically (if applicable)
                document.getElementById('gender').addEventListener('change', function() {
                    updateEmployeeList();
                });
            //FETCH EMPLOYEE END
        </script>
    </body>
</html>