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
    <style>
    .accordion-button::after {
        filter: brightness(0) invert(1); /* Makes the arrow light (white) */
    }

    /* If you want a secondary color (gray) instead of light, use this: */
    /*
    .accordion-button::after {
        filter: brightness(0) invert(0.5);
    }
    */
</style>
</head>
<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'sidebar.php'; ?>
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
                                                    <div class="text-start d-flex justify-content-end">
                                                        <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#confirmSetAllocationModal">Set Allocations</button>
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
                                        <h3 class="mb-0">Manage Evaluation Question</h3>
                                        <hr>
                                    </div>
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-around">
                                            <div class="col-xl-6 rounded">
                                                <div class="mb-4">
                                                    <h4>Add New Question</h4>
                                                    <form method="POST" action="../admin/manageQuestions.php" class="needs-validation" novalidate>
                                                        <div class="form-group mb-3 position-relative mt-3">
                                                            <label for="position" class="fw-bold position-absolute text-light"
                                                                style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Position</label>
                                                            <select name="position" class="form-control bg-dark form-select border border-2 border-secondary text-light"
                                                                    style="height: 55px; padding-top: 15px; padding-bottom: 15px;" required>
                                                                <option value="" disabled selected>Select Position</option>
                                                                <option value="Admin">Admin</option>
                                                                <option value="Supervisor">Supervisor</option>
                                                                <option value="Staff">Staff</option>
                                                                <option value="Field Worker">Field Worker</option>
                                                                <option value="Contractual">Contractual</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-3 position-relative mt-3">
                                                        <label for="category" class="fw-bold position-absolute text-light"
                                                            style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Category</label>
                                                            <select name="category" class="form-control bg-dark form-select border border-2 border-secondary text-light"
                                                                style="height: 55px; padding-top: 15px; padding-bottom: 15px;" required>
                                                                <option value="" disabled selected>Select Category</option>
                                                                <option value="Communication Skills">Communication Skills</option>
                                                                <option value="Initiative">Initiative</option>
                                                                <option value="Punctuality">Punctuality</option>
                                                                <option value="Quality of Work">Quality of Work</option>
                                                                <option value="Teamwork">Teamwork</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mt-4 mb-3 position-relative mt-3">
                                                        <label for="category" class="fw-bold position-absolute text-light"
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Question</label>
                                                            <textarea name="question" class="form-control bg-dark border border-2 border-secondary text-light"
                                                            style="height: 100px; padding-top: 15px; padding-bottom: 15px;" rows="3" required></textarea>
                                                        </div>
                                                        <div class="d-flex justify-content-end">
                                                            <button type="submit" name="add_question" class="btn btn-primary mt-1">Add Question</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 rounded">
                                                <h4 class="text-light">Current Questions</h4>
                                                <div id="questions">
                                                    <div class="accordion mt-3" id="questionAccordion">
                                                        <?php if (!empty($questions)): ?>
                                                            <?php
                                                            // Group questions by category and then by position
                                                            $categories = [];
                                                            foreach ($questions as $question) {
                                                                $categories[$question['category']][$question['position']][] = $question;
                                                            }

                                                            // Define the desired order of positions
                                                            $positionOrder = ['Admin', 'Supervisor', 'Staff', 'Field Worker', 'Contractual'];
                                                            ?>

                                                            <?php foreach ($categories as $category => $positions): ?>
                                                                <?php
                                                                // Sanitize category names for use in id and data-target
                                                                $categoryId = str_replace(' ', '_', $category);
                                                                ?>
                                                                <div class="accordion-item bg-dark" id="questions">
                                                                    <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($categoryId); ?>">
                                                                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse"
                                                                                data-bs-target="#collapse-<?php echo htmlspecialchars($categoryId); ?>"
                                                                                aria-expanded="false" aria-controls="collapse-<?php echo htmlspecialchars($categoryId); ?>">
                                                                            <?php echo htmlspecialchars($category); ?>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="collapse-<?php echo htmlspecialchars($categoryId); ?>"
                                                                        class="accordion-collapse collapse bg-dark"
                                                                        aria-labelledby="heading-<?php echo htmlspecialchars($categoryId); ?>"
                                                                        data-bs-parent="#questionAccordion">
                                                                        <div class="accordion-body bg-black">
                                                                            <div class="accordion bg-dark" id="positionAccordion-<?php echo htmlspecialchars($categoryId); ?>">
                                                                                <?php
                                                                                // Sort positions based on the defined order
                                                                                uksort($positions, function($a, $b) use ($positionOrder) {
                                                                                    return array_search($a, $positionOrder) <=> array_search($b, $positionOrder);
                                                                                });
                                                                                ?>

                                                                                <?php foreach ($positions as $position => $questionsList): ?>
                                                                                    <?php
                                                                                    // Sanitize position names for use in id and data-target
                                                                                    $positionId = str_replace(' ', '_', $position);
                                                                                    ?>
                                                                                    <div class="accordion-item bg-dark">
                                                                                        <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($categoryId . '_' . $positionId); ?>">
                                                                                            <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse"
                                                                                                    data-bs-target="#collapse-<?php echo htmlspecialchars($categoryId . '_' . $positionId); ?>"
                                                                                                    aria-expanded="false" aria-controls="collapse-<?php echo htmlspecialchars($categoryId . '_' . $positionId); ?>">
                                                                                                <?php echo htmlspecialchars($position); ?>
                                                                                            </button>
                                                                                        </h2>
                                                                                        <div id="collapse-<?php echo htmlspecialchars($categoryId . '_' . $positionId); ?>"
                                                                                            class="accordion-collapse collapse bg-dark"
                                                                                            aria-labelledby="heading-<?php echo htmlspecialchars($categoryId . '_' . $positionId); ?>"
                                                                                            data-bs-parent="#positionAccordion-<?php echo htmlspecialchars($categoryId); ?>">
                                                                                            <div class="accordion-body bg-black">
                                                                                                <ul class="list-group">
                                                                                                    <?php foreach ($questionsList as $question): ?>
                                                                                                        <li class="list-group-item bg-dark border-light text-light">
                                                                                                            <span><?php echo htmlspecialchars($question['question']); ?></span>
                                                                                                            <div class="mt-2 d-flex justify-content-end gap-2">
                                                                                                                <div class="d-inline">
                                                                                                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editQuestionModal"
                                                                                                                            data-qid="<?php echo $question['id']; ?>"
                                                                                                                            data-question="<?php echo htmlspecialchars($question['question']); ?>"
                                                                                                                            data-position="<?php echo htmlspecialchars($question['position']); ?>">
                                                                                                                        Edit
                                                                                                                    </button>
                                                                                                                </div>
                                                                                                                <form method="POST" action="../admin/manageQuestions.php" class="d-inline">
                                                                                                                    <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
                                                                                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirmDeleteQuestionModal" data-qid="<?php echo $question['id']; ?>">
                                                                                                                        Delete
                                                                                                                    </button>
                                                                                                                </form>
                                                                                                            </div>
                                                                                                        </li>
                                                                                                    <?php endforeach; ?>
                                                                                                </ul>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="d-flex justify-content-center align-items-center vh-60">
                                                                <div class="text-center text-light fs-1">No questions found.</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border border-secondary">
                        <h1 class="card-title text-center text-light">Calendar</h1>
                        <hr class="border border-secondary">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card bg-dark text-light">
                                    <div class="card-header">
                                        <h3>Set Non-Working Days</h3>
                                        <hr>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-around">
                                            <div class="col-xl-12 rounded">
                                                <div class="mb-4">
                                                    <form id="nonWorkingDayForm">
                                                        <div class="form-group  mb-3 position-relative">
                                                            <label for="date" class="fw-bold position-absolute text-light"
                                                                style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Date</label>
                                                            <input type="date" id="date" class="form-control bg-dark border border-2 border-secondary text-light"
                                                                style="height: 55px; padding-top: 15px; padding-bottom: 15px;" placeholder="Select date" required>
                                                        </div>
                                                        <div class="form-group mt-4 mb-3 position-relative">
                                                            <label for="description" class="fw-bold position-absolute text-light"
                                                                style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Description:</label>
                                                            <input type="text" id="description" class="form-control bg-dark border border-2 border-secondary text-light"
                                                                style="height: 55px; padding-top: 15px; padding-bottom: 15px;" placeholder="Add description" required>
                                                        </div>
                                                        <div class="d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-primary">Add Non-Working Day</button>
                                                        </div>
                                                    </form>
                                                    <hr>
                                                    <h3>Existing Non-Working Days</h3>
                                                    <table class="table text-light" id="nonWorkingDaysTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Description</th>
                                                                <th>Type</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                                <!-- Existing non-working days will be populated here -->
                                                        </tbody>
                                                    </table>
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
                                        <div class="form-group mt-4 mb-3 position-relative">
                                            <label for="new_question" class="fw-bold position-absolute text-light"
                                                style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">New Question</label>
                                            <textarea name="new_question" id="editNewQuestion" class="form-control bg-dark border border-2 border-secondary text-light"
                                                style="padding-top: 15px; padding-bottom: 15px;" rows="3" required></textarea>
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
                    <!-- Success Modal for Adding Non-Working Day -->
                    <div class="modal fade" id="successAddModal" tabindex="-1" aria-labelledby="successAddModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title" id="successAddModalLabel">Success</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="successAddMessage">
                                    Non-working day added successfully!
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bootstrap Modal for Confirming Deletion -->
                    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title" id="confirmDeleteLabel">Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete this non-working day?
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Success Modal for Deleting Non-Working Day -->
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="successMessage">
                                    Non-working day deleted successfully!
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Modal for Setting Allocations -->
                    <div class="modal fade" id="confirmSetAllocationModal" tabindex="-1" aria-labelledby="confirmSetAllocationLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title" id="confirmSetAllocationLabel">Confirm Allocation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to set these leave allocations?
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="confirmSetAllocationBtn">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal for confirming question deletion -->
                    <div class="modal fade" id="confirmDeleteQuestionModal" tabindex="-1" aria-labelledby="confirmDeleteQuestionLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title" id="confirmDeleteQuestionLabel">Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete this question?
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <form method="POST" action="../admin/manageQuestions.php" class="d-inline">
                                        <input type="hidden" name="id" id="deleteQuestionId">
                                        <button type="submit" name="delete_question" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <footer class="py-4 bg-dark text-light mt-auto border-top border-secondary">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; Your Website 2024</div>
                            <div>
                                <a href="#">Privacy Policy</a>
                                &middot;
                                <a href="#">Terms & Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
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

                //EVALUATION QUESTIONS
                // Populate the edit modal with question data
                $('#editQuestionModal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget); // Button that triggered the modal
                    var qid = button.data('qid'); // Extract info from data-* attributes
                    var question = button.data('question'); // Extract the question
                    var position = button.data('position'); // Extract the position

                    var modal = $(this);
                    modal.find('#editQId').val(qid); // Insert question ID into the modal's input
                    modal.find('#editNewQuestion').val(question); // Insert question into the modal's textarea
                    modal.find('#editPosition').val(position);
                });

                // Populate the delete modal with question ID
                $('#confirmDeleteQuestionModal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget); // Button that triggered the modal
                    var qid = button.data('qid'); // Extract info from data-* attributes

                    var modal = $(this);
                    modal.find('#deleteQuestionId').val(qid); // Insert question ID into the modal's input
                });
                //EVALUATION QUESTIONS END

                //FETCH EMPLOYEE
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
                            // Add "All Employees" option
                            const allEmployeesOption = document.createElement('option');
                            allEmployeesOption.value = 'all';
                            allEmployeesOption.textContent = 'All Employees';
                            employeeSelect.appendChild(allEmployeesOption);

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

            document.addEventListener('DOMContentLoaded', function() {
                fetchNonWorkingDays();

                document.getElementById('nonWorkingDayForm').addEventListener('submit', function(event) {
                    event.preventDefault();
                    const date = document.getElementById('date').value;
                    const description = document.getElementById('description').value;

                    fetch('../db/nowork_days.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ date, description }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Show the success modal
                            var successModal = new bootstrap.Modal(document.getElementById('successAddModal'));
                            successModal.show();

                            document.getElementById('nonWorkingDayForm').reset();
                            fetchNonWorkingDays();  // Refresh the table
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred.');
                    });
                });
            });

            function fetchNonWorkingDays() {
                fetch('../db/nowork_days.php')
                    .then(response => response.json())
                    .then(data => {
                        const tbody = document.getElementById('nonWorkingDaysTable').querySelector('tbody');
                        tbody.innerHTML = ''; // Clear existing rows

                        if (data.length === 0) {
                            // If no data, display "No non-working days found"
                            tbody.innerHTML = '<tr><td colspan="4" class="text-center">No non-working days found</td></tr>';
                        } else {
                            // Populate the table with data
                            data.forEach(day => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${day.date}</td>
                                    <td>${day.description}</td>
                                    <td>${day.type}</td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="deleteNonWorkingDay('${day.date}')">Delete</button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching non-working days:', error);
                        const tbody = document.getElementById('nonWorkingDaysTable').querySelector('tbody');
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>';
                    });
            }

            // Call the function to fetch and display non-working days
            fetchNonWorkingDays();

            let selectedDate = null;

            function deleteNonWorkingDay(date) {
                selectedDate = date;
                var deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
                deleteModal.show();
            }

            document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                if (selectedDate) {
                    fetch('../db/del_nowork.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ date: selectedDate }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                            fetchNonWorkingDays();  // Refresh the table
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred.');
                    });

                    var deleteModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    deleteModal.hide();
                }
            });

            document.getElementById('date').addEventListener('focus', function() {
                this.showPicker(); // Opens the native date picker
            });

            // Confirmation for setting leave allocations
            document.getElementById('confirmSetAllocationBtn').addEventListener('click', function () {
                document.querySelector('form[action="../db/setLeave.php"]').submit();
                var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSetAllocationModal'));
                confirmModal.hide();
            });
        </script>
    </body>
</html>